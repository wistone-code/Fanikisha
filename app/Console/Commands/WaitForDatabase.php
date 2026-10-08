<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Waits until the database accepts connections. Railway cron containers (and fresh deploys) can start before the
 * private network reaches MySQL, so the first query is refused. Run this before the scheduler and before migrating.
 * It also prints where it is connecting, so a wrong or missing DB_HOST shows up in the logs straight away.
 */
class WaitForDatabase extends Command
{
    protected $signature = 'db:wait {--timeout=90 : Seconds to keep trying} {--interval=3 : Seconds between tries}';

    protected $description = 'Wait until the database accepts connections (used before migrations and scheduled runs).';

    public function handle(): int
    {
        $config = config('database.connections.'.config('database.default'), []);
        $host = (string) ($config['host'] ?? '');
        $port = (string) ($config['port'] ?? '');
        $timeout = max(1, (int) $this->option('timeout'));
        $interval = max(1, (int) $this->option('interval'));
        $started = microtime(true);

        if ($host !== '') {
            $resolved = gethostbyname($host);
            $this->line("Database: {$host}:{$port}".($resolved !== $host ? " (resolves to {$resolved})" : ''));
            if (in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
                $this->warn('DB_HOST is a local address. On Railway it must point at the MySQL service, so check this service\'s variables.');
            }
        }

        for ($try = 1; ; $try++) {
            try {
                DB::select('select 1');
                $this->info("Database ready after {$try} attempt(s), ".round(microtime(true) - $started, 1).'s.');

                return self::SUCCESS;
            } catch (\Throwable $e) {
                DB::purge(); // drop the failed connection so the next try connects afresh
                $reason = Str::limit(trim(Str::before($e->getMessage(), "\n")), 180);
                $this->warn("Attempt {$try} failed: {$reason}");

                if (microtime(true) - $started + $interval >= $timeout) {
                    $this->error("Database still unreachable after {$timeout}s.");

                    return self::FAILURE;
                }

                sleep($interval);
            }
        }
    }
}
