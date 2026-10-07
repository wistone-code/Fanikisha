<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BeemSmsService
{
    /** Set by forEvent() for scheduled jobs, where there is no logged-in "current event". */
    private ?\App\Models\Event $eventOverride = null;

    private bool $skipQuota = false;

    /** A copy of this service that charges quota to the given event (used by scheduled commands). */
    public function forEvent(?\App\Models\Event $event): static
    {
        $copy = clone $this;
        $copy->eventOverride = $event;

        return $copy;
    }

    private function quotaEvent(): ?\App\Models\Event
    {
        return $this->skipQuota ? null : ($this->eventOverride ?? app('currentEvent'));
    }

    /** sendBulk() without the quota check/count — the caller (sendPersonalised) handles quota for the whole batch. */
    private function sendBulkUnchecked(string $message, Collection $recipients): array
    {
        $this->skipQuota = true;

        try {
            return $this->sendBulk($message, $recipients);
        } finally {
            $this->skipQuota = false;
        }
    }

    /**
     * Send a different message to each recipient (personalised cards, thank-yous…).
     * Quota is checked once up front for the whole batch.
     *
     * @param  Collection  $items  objects/arrays with ->phone and ->message
     * @return array{successful: bool, sent: int, failed: int, ok_keys?: array, error?: string}
     */
    public function sendPersonalised(Collection $items, ?callable $onSent = null, bool $partial = false): array
    {
        $items = $items->map(fn ($i) => (object) (array) $i)->filter(fn ($i) => filled($i->phone) && filled($i->message))->values();

        if ($items->isEmpty()) {
            return ['successful' => false, 'sent' => 0, 'failed' => 0, 'error' => 'No recipients with a phone number.'];
        }

        // People who opted out are never texted and never counted. Scheduled jobs are told they are "handled" so they are not retried every run.
        $optOuts = app(OptOutService::class);
        $optSet = $optOuts->suppressedAmong($items->pluck('phone')->all());
        [$blocked, $items] = $items->partition(fn ($i) => $optOuts->inSet($optSet, $i->phone));
        $items = $items->values();
        if ($onSent) {
            $blocked->each(fn ($i) => $onSent($i->key ?? null));
        }
        if ($items->isEmpty()) {
            return ['successful' => false, 'sent' => 0, 'failed' => 0, 'error' => 'Everyone on this list has opted out of messages.'];
        }

        $event = $this->quotaEvent();

        // Scheduled jobs send as many as the quota allows and carry on next run, rather than sending nothing.
        if ($partial && $event && ($left = $event->smsRemaining()) !== null && $left > 0 && $left < $items->count()) {
            $items = $items->take($left)->values();
        }

        if ($event && ! $event->hasSmsCapacity($items->count())) {
            $remaining = $event->smsRemaining();

            return ['successful' => false, 'sent' => 0, 'failed' => 0, 'error' => $remaining <= 0
                ? 'Quota finished — sending is paused. Contact your system admin to raise it.'
                : "Not enough quota remaining — {$remaining} left, but this would send {$items->count()}."];
        }

        $sent = 0;
        $failed = 0;
        $lastError = null;
        $okKeys = [];

        foreach ($items as $index => $item) {
            $result = $this->sendBulkUnchecked($item->message, collect([(object) ['phone' => $item->phone]]));

            if ($result['successful']) {
                $delivered = $result['valid'] ?? 1;
                $sent += $delivered;
                $okKeys[] = $item->key ?? $index;

                // Count the message and let the caller flag this guest straight away: if the run is cut short
                // (timeout, deploy, overlap with the next run) nobody who already got a text is texted again.
                if ($event) {
                    $event->increment('sms_sent_count', $delivered);
                }
                if ($onSent) {
                    $onSent($item->key ?? $index);
                }
            } else {
                $failed++;
                $lastError = $result['error'] ?? null;
            }
        }

        return ['successful' => $sent > 0, 'sent' => $sent, 'failed' => $failed, 'ok_keys' => $okKeys] + ($lastError && $sent === 0 ? ['error' => $lastError] : []);
    }

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

        // Numbers that asked to stop are never messaged (suppression list).
        $suppressed = app(OptOutService::class)->suppressedAmong(array_column($recipients, 'dest_addr'));

        if ($suppressed !== []) {
            $recipients = array_values(array_filter($recipients, fn ($r) => ! isset($suppressed[ltrim($r['dest_addr'], '+')])));
            $recipients = array_map(fn ($r, $i) => ['recipient_id' => (string) ($i + 1), 'dest_addr' => $r['dest_addr']], $recipients, array_keys($recipients));

            if (empty($recipients)) {
                return ['successful' => false, 'error' => 'This number asked not to receive messages, so nothing was sent.'];
            }
        }

        $event = $this->quotaEvent();

        if ($event && ! $event->hasSmsCapacity(count($recipients))) {
            $remaining = $event->smsRemaining();

            return [
                'successful' => false,
                'error' => $remaining <= 0
                    ? 'Quota finished — sending is paused. Contact your system admin to raise it.'
                    : "Not enough quota remaining — {$remaining} left, but this would send ".count($recipients).'.',
            ];
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
                $sentCount = $data['valid'] ?? count($recipients);

                if ($event) {
                    $event->increment('sms_sent_count', $sentCount);
                }

                return [
                    'successful' => true,
                    'valid' => $sentCount,
                    'invalid' => $data['invalid'] ?? 0,
                    'request_id' => $data['request_id'] ?? null,
                ];
            }

            Log::warning('Beem SMS send failed', ['status' => $response->status(), 'response' => $data]);

            return [
                'successful' => false,
                'error' => $data['message'] ?? 'Beem is currently unavailable. Please check your network and try again later.',
            ];
        } catch (Throwable $e) {
            Log::error('Beem SMS send exception', ['message' => $e->getMessage()]);

            return ['successful' => false, 'error' => 'Could not reach Beem — network unavailable. Please try again later.'];
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
        // Use the app-wide normaliser so opt-out matching, storage and sending all agree (and foreign numbers are not forced onto +255).
        $normalised = app(PhoneNumberService::class)->normalize($phone) ?? '';

        return preg_replace('/\D+/', '', $normalised) ?? '';
    }
}
