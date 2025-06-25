<?php

declare(strict_types=1);

namespace App\Services\Camt\Conversion;

use App\Services\Camt\Transaction;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ProgressInformation;
use Genkgo\Camt\Camt053\DTO\Statement as CamtStatement;
use Genkgo\Camt\DTO\Message;
use Illuminate\Support\Facades\Log;

class TransactionExtractor
{
    use ProgressInformation;

    private Configuration $configuration;

    public function __construct(Configuration $configuration)
    {
        Log::debug('Now in TransactionExtractor.');
        $this->configuration = $configuration;
    }

    public function extractTransactions(Message $message): array
    {
        Log::debug('Now in extractTransactions.');
        // get transactions from XML file
        $transactions = [];
        $statements   = $message->getRecords();
        $totalCount   = count($statements);

        /**
         * @var int           $index
         * @var CamtStatement $statement
         */
        foreach ($statements as $i => $statement) { // -> Level B
            $entries    = $statement->getEntries();
            $entryCount = count($entries);
            Log::debug(sprintf('[%d/%d] Now working on statement with %d entries.', $i + 1, $totalCount, $entryCount));
            foreach ($entries as $ii => $entry) {                // -> Level C
                $count = count($entry->getTransactionDetails()); // count level D entries.
                Log::debug(sprintf('[%d/%d] Now working on entry with %d detail entries.', $ii + 1, $entryCount, $count));
                if (0 === $count) {
                    // TODO Create a single transaction, I guess?
                    $transactions[] = new Transaction($this->configuration, $message, $statement, $entry, []);
                }
                if (0 !== $count) {
                    $handling = $this->configuration->getGroupedTransactionHandling();
                    if ('split' === $handling) {
                        $transactions[] = new Transaction($this->configuration, $message, $statement, $entry, $entry->getTransactionDetails());
                    }
                    if ('single' === $handling) {
                        foreach ($entry->getTransactionDetails() as $detail) {
                            $transactions[] = new Transaction($this->configuration, $message, $statement, $entry, [$detail]);
                        }
                    }
                    if ('group' === $handling) {
                        if (1 === $count) {
                            $transactions[] = new Transaction($this->configuration, $message, $statement, $entry, $entry->getTransactionDetails());
                        }
                        if ($count > 1) {
                            $transactions[] = new Transaction($this->configuration, $message, $statement, $entry, []);
                        }
                    }
                }
                Log::debug(sprintf('[%d/%d] Done working on entry with %d detail entries.', $ii + 1, $entryCount, $count));
            }
        }
        Log::debug(sprintf('Extracted %d transaction(s)', count($transactions)));

        return $transactions;
    }
}
