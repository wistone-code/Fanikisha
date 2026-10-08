<?php

namespace Tests\Feature;

use Tests\TestCase;

/** db:wait keeps the scheduler and deploys from failing when MySQL refuses the first connection. */
class DatabaseWaitCommandTest extends TestCase
{
    public function test_it_returns_at_once_when_the_database_is_reachable(): void
    {
        $this->artisan('db:wait', ['--timeout' => 5])
            ->expectsOutputToContain('Database ready after 1 attempt')
            ->assertExitCode(0);
    }

    public function test_it_fails_with_a_clear_message_when_the_database_stays_unreachable(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => '1',
        ]);

        $this->artisan('db:wait', ['--timeout' => 1, '--interval' => 1])
            ->expectsOutputToContain('Database: 127.0.0.1:1')
            ->expectsOutputToContain('DB_HOST is a local address')
            ->expectsOutputToContain('Attempt 1 failed')
            ->expectsOutputToContain('Database still unreachable after 1s')
            ->assertExitCode(1);
    }

    public function test_the_deploy_command_waits_for_the_database_before_migrating(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $this->assertStringContainsString('php artisan db:wait', $dockerfile);
        $this->assertLessThan(strpos($dockerfile, 'migrate --force'), strpos($dockerfile, 'db:wait'));
    }
}
