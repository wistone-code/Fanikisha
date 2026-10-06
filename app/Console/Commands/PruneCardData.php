<?php

namespace App\Console\Commands;

use App\Services\CardTracker;
use Illuminate\Console\Command;

class PruneCardData extends Command
{
    protected $signature = 'cards:prune';

    protected $description = 'Delete card-view records older than 30 days.';

    public function handle(CardTracker $tracker): int
    {
        $this->info('Removed '.$tracker->prune().' old card view record(s).');

        return self::SUCCESS;
    }
}
