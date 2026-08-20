<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BeemSmsService
{
    /**
     * Send one message to many recipients through Beem Africa's bulk SMS API.
     *
     * @param  Collection  $pledgers  Any collection of objects/models with a ->phone attribute.
     * @return array{successful: bool, valid?: int, invalid?: int, request_id?: mixed, error?: string}
     */
    public function sendBulk(string $message, Collection $pledgers): array
    {
        $apiKey = config('services.beem.api_key');
        $secretKey = config('services.beem.secret_key');
        $senderId = config('services.beem.sender_id', 'INFO');

        if (! $apiKey || ! $secretKey) {
            return ['successful' => false, 'error' => 'Beem API credentials are not configured.'];
        }

        $recipients = $pledgers
            ->filter(fn ($p) => filled($p->phone))
            ->values()
            ->map(fn ($p, $index) => [
                'recipient_id' => (string) ($index + 1),
                'dest_addr' => $this->normalizePhone($p->phone),
            ])
            ->all();

        if (empty($recipients)) {
            return ['successful' => false, 'error' => 'No recipients with a phone number.'];
        }

        try {
            $response = Http::withBasicAuth($apiKey, $secretKey)
                ->acceptJson()
                ->post('https://apisms.beem.africa/v1/send', [
                    'source_addr' => $senderId,
                    'encoding' => 0,
                    'schedule_time' => '',
                    'message' => $message,
                    'recipients' => $recipients,
                ]);

            $data = $response->json() ?? [];

            if ($response->successful() && ($data['successful'] ?? false)) {
                return [
                    'successful' => true,
                    'valid' => $data['valid'] ?? count($recipients),
                    'invalid' => $data['invalid'] ?? 0,
                    'request_id' => $data['request_id'] ?? null,
                ];
            }

            Log::warning('Beem SMS send failed', ['status' => $response->status(), 'response' => $data]);

            return [
                'successful' => false,
                'error' => $data['message'] ?? $response->body() ?: ('Beem rejected the request (HTTP '.$response->status().').'),
            ];
        } catch (Throwable $e) {
            Log::error('Beem SMS send exception', ['message' => $e->getMessage()]);

            return ['successful' => false, 'error' => 'Could not reach Beem: '.$e->getMessage()];
        }
    }

    /**
     * Send one message to a single phone number. Thin wrapper around sendBulk()
     * so individual reminders/notifications share the same request/response logic.
     *
     * @return array{successful: bool, valid?: int, invalid?: int, request_id?: mixed, error?: string}
     */
    public function sendSingle(string $message, ?string $phone): array
    {
        if (blank($phone)) {
            return ['successful' => false, 'error' => 'No phone number on file.'];
        }

        return $this->sendBulk($message, collect([(object) ['phone' => $phone]]));
    }

    /**
     * Beem expects full international format, e.g. 255700000000 (no +, no leading 0).
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        } elseif (! str_starts_with($digits, '255')) {
            $digits = '255'.$digits;
        }

        return $digits;
    }
}
