<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Pledge;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Meta's WhatsApp webhook: one address that answers Meta's set-up check (GET) and receives delivery
 * reports (POST). Public by design, so every POST must carry a valid signature made with the app secret.
 */
class WhatsAppWebhookController extends Controller
{
    /** Higher = further along. A late "sent" report must never overwrite "delivered" or "read". */
    private const RANK = ['sent' => 1, 'delivered' => 2, 'read' => 3];

    /** Meta calls this once when the webhook is saved in the developer console. */
    public function verify(Request $request): Response
    {
        // PHP turns the dots in "hub.mode" into underscores, so accept both spellings.
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));
        $expected = (string) config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /** Delivery reports. Anything without a valid signature is refused before it is read. */
    public function receive(Request $request): Response
    {
        $secret = (string) config('services.whatsapp.app_secret');
        $given = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if ($secret === '' || ! hash_equals($expected, $given)) {
            Log::warning('WhatsApp webhook refused: missing or wrong signature');

            return response('Forbidden', 403);
        }

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) data_get($entry, 'changes', []) as $change) {
                foreach ((array) data_get($change, 'value.statuses', []) as $status) {
                    $this->applyStatus((array) $status);
                }
            }
        }

        // Always 200 for a signed request, otherwise Meta keeps retrying the same report.
        return response('ok', 200);
    }

    private function applyStatus(array $status): void
    {
        $id = (string) ($status['id'] ?? '');
        $state = (string) ($status['status'] ?? '');

        if ($id === '' || ! in_array($state, ['sent', 'delivered', 'read', 'failed'], true)) {
            return;
        }

        $pledge = Pledge::where('whatsapp_message_id', $id)->first();

        if (! $pledge) {
            return;
        }

        $at = isset($status['timestamp']) && is_numeric($status['timestamp']) ? now()->setTimestamp((int) $status['timestamp']) : now();
        $current = $pledge->whatsapp_status;

        if ($state === 'failed') {
            // A message that already reached the phone cannot fail afterwards; ignore a stray late report.
            if (in_array($current, ['delivered', 'read', 'failed'], true)) {
                return;
            }

            $code = (int) data_get($status, 'errors.0.code', 0);
            $reason = \App\Services\WhatsAppCloudService::friendlyError(
                $code,
                (string) (data_get($status, 'errors.0.message') ?: data_get($status, 'errors.0.title') ?: ''),
            );
            Log::info('WhatsApp invitation failed', ['pledge' => $pledge->id, 'code' => $code, 'error' => data_get($status, 'errors.0')]);
            $pledge->update(['whatsapp_status' => 'failed', 'whatsapp_status_at' => $at, 'whatsapp_error' => mb_substr($reason.($code ? " [{$code}]" : ''), 0, 250)]);

            // Never delivered, so Meta does not bill it: give the invitation back to the event's quota.
            $event = Event::find($pledge->event_id);
            $event?->releaseWhatsapp();

            // Meta could not load the card picture: send the same invitation once more as a plain WhatsApp message.
            if ($event && in_array($code, [131052, 131053], true) && $this->resendWithoutPicture($pledge, $event)) {
                return;
            }

            // The guest still gets the card: by normal SMS, straight away.
            if ($event && app(\App\Services\WhatsAppSmsFallback::class)->send($pledge, $event)) {
                $pledge->update(['whatsapp_error' => mb_substr('Sent by SMS instead. '.$pledge->whatsapp_error, 0, 250)]);
            }

            return;
        }

        if ($current === 'failed' || (self::RANK[$state] ?? 0) <= (self::RANK[$current] ?? 0)) {
            return;
        }

        $pledge->update(['whatsapp_status' => $state, 'whatsapp_status_at' => $at, 'whatsapp_error' => null]);
    }

    /** One retry only (guarded by a short lock), and only while the event still has WhatsApp quota. */
    private function resendWithoutPicture(Pledge $pledge, Event $event): bool
    {
        $digits = app(\App\Services\PhoneNumberService::class)->digitsOnly($pledge->phone);

        if (! $digits || ! Cache::add('wa-picture-retry:'.$pledge->id, 1, now()->addDay()) || ! $event->reserveWhatsapp()) {
            return false;
        }

        $result = app(\App\Services\WhatsAppCloudService::class)->sendInvitation($event, $pledge, $digits, false);

        if (! $result['successful']) {
            $event->releaseWhatsapp();

            return false;
        }

        $pledge->update(['whatsapp_message_id' => $result['message_id'] ?? null, 'whatsapp_status' => 'sent', 'whatsapp_status_at' => now(), 'whatsapp_error' => null]);

        return true;
    }
}
