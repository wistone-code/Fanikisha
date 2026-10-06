<?php

namespace Tests\Feature;

use Tests\TestCase;

class EmailFooterTest extends TestCase
{
    public function test_system_emails_carry_the_no_reply_notice(): void
    {
        $a = view('emails.password-reset-code', ['name' => 'A', 'appName' => 'Fanikisha', 'code' => '123456', 'minutes' => 15])->render();
        $b = view('emails.account-created', ['name' => 'A', 'appName' => 'Fanikisha', 'username' => 'a', 'password' => 'x', 'loginUrl' => 'https://fanikisha.app/login'])->render();

        foreach ([$a, $b] as $html) {
            $this->assertStringContainsString('do not reply', $html);
            $this->assertStringContainsString('usijibu', $html);
            $this->assertStringContainsString('info@fanikisha.app', $html);
        }
    }
}
