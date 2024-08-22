<?php

/*
 * RoutineManager.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\Nordigen\Conversion;

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Services\Nordigen\Conversion\Routine\FilterTransactions;
use App\Services\Nordigen\Conversion\Routine\GenerateTransactions;
use App\Services\Nordigen\Conversion\Routine\TransactionProcessor;
use App\Services\Nordigen\Request\Request;
use App\Services\Shared\Authentication\IsRunningCli;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\CombinedProgressInformation;
use App\Services\Shared\Conversion\GeneratesIdentifier;
use App\Services\Shared\Conversion\ProgressInformation;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    use CombinedProgressInformation;
    use GeneratesIdentifier;
    use IsRunningCli;
    use ProgressInformation;

    private Configuration        $configuration;
    private FilterTransactions   $transactionFilter;
    private GenerateTransactions $transactionGenerator;
    private TransactionProcessor $transactionProcessor;

    public function __construct(?string $identifier)
    {
        $this->allErrors   = [];
        $this->allWarnings = [];
        $this->allMessages = [];

        if (null === $identifier) {
            $this->generateIdentifier();
        }
        if (null !== $identifier) {
            $this->identifier = $identifier;
        }
        $this->transactionProcessor = new TransactionProcessor();
        $this->transactionGenerator = new GenerateTransactions();
        $this->transactionFilter    = new FilterTransactions();
    }

    #[\Override]
    public function getServiceAccounts(): array
    {
        return $this->transactionProcessor->getAccounts();
    }

    public function setConfiguration(Configuration $configuration): void
    {
        // save config
        $this->configuration = $configuration;

        // share config
        $this->transactionProcessor->setConfiguration($configuration);
        $this->transactionGenerator->setConfiguration($configuration);

        // set identifier
        $this->transactionProcessor->setIdentifier($this->identifier);
        $this->transactionGenerator->setIdentifier($this->identifier);
        $this->transactionFilter->setIdentifier($this->identifier);
    }

    /**
     * @throws ImporterErrorException
     */
    public function start(): array
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        app('log')->debug(sprintf('The GoCardless API URL is %s', config('nordigen.url')));

        // get transactions from Nordigen
        app('log')->debug('Call transaction processor download.');

        try {
            $nordigen = $this->transactionProcessor->download();
        } catch (ImporterErrorException $e) {
            app('log')->error('Could not download transactions from Nordigen.');
            app('log')->error($e->getMessage());

            // add error to current error thing:
            $this->addError(0, sprintf('Could not download from GoCardless: %s', $e->getMessage()));
            $this->mergeMessages(1);
            $this->mergeWarnings(1);
            $this->mergeErrors(1);

            throw $e;
        }

        // collect accounts from the configuration, and join them with the rate limits
        $configAccounts = $this->configuration->getAccounts();
        $rateLimits     = [];
        foreach ($this->transactionProcessor->getRateLimits() as $account => $rateLimit) {
            app('log')->debug(sprintf('Rate limit for account %s: %d request(s) left, %d second(s)', $account, $rateLimit['remaining'], $rateLimit['reset']));
            if (!array_key_exists($account, $configAccounts)) {
                app('log')->error(sprintf('This account "%s" was not found in your configuration.', $account));
                continue;
            }
            $rateLimits[$configAccounts[$account]] = $rateLimit;
        }


        // generate Firefly III ready transactions:
        app('log')->debug('Generating Firefly III transactions.');

        try {
            $this->transactionGenerator->collectTargetAccounts();
        } catch (ApiHttpException $e) {
            $this->addError(0, sprintf('Error while collecting target accounts: %s', $e->getMessage()));
            $this->mergeMessages(1);
            $this->mergeWarnings(1);
            $this->mergeErrors(1);

            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }

        // Firefly III accounts, for debug:
        $userAccounts = $this->transactionGenerator->getUserAccounts();
        // now we can report on target limits:
        app('log')->debug('Add message about rate limits.');
        foreach ($rateLimits as $accountId => $info) {
            if ($info['reset'] > 1 && 0 === $info['remaining']) {
                app('log')->debug(sprintf('Add message about rate limits for account %s.', $accountId));
                // save message about the number of requests left.
                $accountInfo = $this->findAccountInfo($userAccounts, $accountId);

                if (null !== $accountInfo) {
                    app('log')->debug('Found Firefly III account to report on.');
                    $message = $this->addAccountMessage($accountInfo, $info);
                    $this->addMessage(0, $message);
                }
                if (null === $accountInfo) {
                    app('log')->debug('Found NO Firefly III account to report on.');
                }
            }
        }

        // collect errors from transactionProcessor.
        $total = 0;
        foreach ($nordigen as $transactions) {
            $total += count($transactions);
        }
        if (0 === $total) {
            app('log')->warning('Downloaded nothing, will return nothing.');
            // add error to current error thing:
            $this->addError(0, 'Zero transactions found at GoCardless');
            $this->mergeMessages(1);
            $this->mergeWarnings(1);
            $this->mergeErrors(1);

            return [];
        }

        try {
            $this->transactionGenerator->collectNordigenAccounts();
        } catch (ImporterErrorException $e) {
            app('log')->error('Could not collect info on all Nordigen accounts, but this info isn\'t used at the moment anyway.');
            app('log')->error($e->getMessage());
        } catch (AgreementExpiredException $e) {
            $this->addError(0, 'The connection between your bank and GoCardless has expired.');
            $this->mergeMessages(1);
            $this->mergeWarnings(1);
            $this->mergeErrors(1);

            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }

        $transactions = $this->transactionGenerator->getTransactions($nordigen);
        app('log')->debug(sprintf('Generated %d Firefly III transactions.', count($transactions)));

        $filtered = $this->transactionFilter->filter($transactions);
        app('log')->debug(sprintf('Filtered down to %d Firefly III transactions.', count($filtered)));

        $this->mergeMessages(count($transactions));
        $this->mergeWarnings(count($transactions));
        $this->mergeErrors(count($transactions));

        return $filtered;
    }

    private function mergeMessages(int $count): void
    {
        $this->allMessages = $this->mergeArrays(
            [
                $this->getMessages(),
                $this->transactionFilter->getMessages(),
                $this->transactionGenerator->getMessages(),
                $this->transactionProcessor->getMessages(),
            ],
            $count
        );
    }

    private function mergeWarnings(int $count): void
    {
        $this->allWarnings = $this->mergeArrays(
            [
                $this->getWarnings(),
                $this->transactionFilter->getWarnings(),
                $this->transactionGenerator->getWarnings(),
                $this->transactionProcessor->getWarnings(),
            ],
            $count
        );
    }

    private function mergeErrors(int $count): void
    {
        $this->allErrors = $this->mergeArrays(
            [
                $this->getErrors(),
                $this->transactionFilter->getErrors(),
                $this->transactionGenerator->getErrors(),
                $this->transactionProcessor->getErrors(),
            ],
            $count
        );
    }

    private function addAccountMessage(array $accountInfo, array $rateLimit): string
    {
        //$message = 'You have no requests left for bank account "%s" (with IBAN ABDCDE) (with account number XYZ). Read more about GoCardless rate limits.';
        if (0 === $rateLimit['remaining'] && $rateLimit['reset'] > 1) {
            $message = sprintf('You have no requests left for bank account "%s". The limit resets in %s. ', $accountInfo['name'], Request::formatTime($rateLimit['reset']));
        }
        if ($rateLimit['remaining'] > 0) {
            $message = sprintf('You have %d request(s) left for bank account "%s". ', $rateLimit['remaining'], $accountInfo['name']);
        }
        $message .= '[Read more about GoCardless rate limits](https://docs.firefly-iii.org/totally-still-todo).';
        return $message;

    }

    private function findAccountInfo(array $accounts, int $accountId): ?array
    {
        foreach ($accounts as $account) {
            if ($account['id'] === $accountId) {
                return $account;
            }
        }
        return null;
    }
}
