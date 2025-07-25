<?php

/*
 * TransactionCurrencies.php
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

namespace App\Services\CSV\Mapper;

use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Authentication\SecretManager;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\TransactionCurrency;
use GrumpyDictator\FFIIIApiSupport\Request\GetCurrenciesRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetCurrenciesResponse;
use Illuminate\Support\Facades\Log;

/**
 * Class TransactionCurrencies
 */
class TransactionCurrencies implements MapperInterface
{
    /**
     * Get map of objects.
     *
     * @throws ImporterErrorException
     */
    public function getMap(): array
    {
        $result  = [];
        $url     = SecretManager::getBaseUrl();
        $token   = SecretManager::getAccessToken();

        $request = new GetCurrenciesRequest($url, $token);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));

        try {
            /** @var GetCurrenciesResponse $response */
            $response = $request->get();
        } catch (ApiHttpException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

            //            Log::error($e->getTraceAsString());
            throw new ImporterErrorException(sprintf('Could not download currencies: %s', $e->getMessage()));
        }

        /** @var TransactionCurrency $currency */
        foreach ($response as $currency) {
            $result[$currency->id] = sprintf('%s (%s)', $currency->name, $currency->code);
        }
        asort($result, SORT_STRING);

        return $result;
    }
}
