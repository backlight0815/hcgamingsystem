<?php

namespace App\Observers;

use App\Models\TradingJournal;
use App\Services\TradingJournalSyncLedger;

class TradingJournalSyncObserver
{
    public function __construct(private readonly TradingJournalSyncLedger $ledger) {}

    public function created(TradingJournal $journal): void
    {
        $this->ledger->record($journal, 'create');
    }

    public function updated(TradingJournal $journal): void
    {
        $this->ledger->record($journal, 'update');
    }

    public function deleted(TradingJournal $journal): void
    {
        $this->ledger->record($journal, 'delete');
    }
}
