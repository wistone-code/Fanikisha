<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventAsset;
use App\Models\Pledge;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends approved WhatsApp templates through Meta's WhatsApp Cloud API.
 * Without WHATSAPP_TOKEN and WHATSAPP_PHONE_NUMBER_ID nothing is sent and the
 * guest list keeps its normal "open WhatsApp" button.
 */
class WhatsAppCloudService
{
    private const SW_DAYS = ['Jumapili', 'Jumatatu', 'Jumanne', 'Jumatano', 'Alhamisi', 'Ijumaa', 'Jumamosi'];

    private const SW_MONTHS = ['Januari', 'Februari', 'Machi', 'Aprili', 'Mei', 'Juni', 'Julai', 'Agosti', 'Septemba', 'Oktoba', 'Novemba', 'Desemba'];

    public function isConfigured(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    /**
     * @param  array<int, string>  $bodyParams  values for {{1}}, {{2}}, … in order
     * @param  string|null  $imageUrl  public https link for a template with an Image header
     * @param  string|null  $buttonSuffix  the part that fills {{1}} in a dynamic "Visit website" button
     * @return array{successful: bool, message_id?: string, error?: string}
     */
    public function sendTemplate(string $toDigits, string $template, string $language, array $bodyParams, ?string $imageUrl = null, ?string $buttonSuffix = null): array
    {
        if (! $this->isConfigured()) {
            return ['successful' => false, 'error' => 'WhatsApp is not set up yet (WHATSAPP_TOKEN / WHATSAPP_PHONE_NUMBER_ID).'];
        }

        $components = [];

        if ($imageUrl) {
            $components[] = ['type' => 'header', 'parameters' => [['type' => 'image', 'image' => ['link' => $imageUrl]]]];
        }

        $components[] = [
            'type' => 'body',
            'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => $this->clean($v)], array_values($bodyParams)),
        ];

        if ($buttonSuffix !== null) {
            $components[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $buttonSuffix]]];
        }

        $url = sprintf('https://graph.facebook.com/%s/%s/messages', config('services.whatsapp.api_version', 'v25.0'), config('services.whatsapp.phone_number_id'));

        try {
            $response = Http::timeout(20)->withToken(config('services.whatsapp.token'))->acceptJson()->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $toDigits,
                'type' => 'template',
                'template' => ['name' => $template, 'language' => ['code' => $language], 'components' => $components],
            ]);
        } catch (Throwable $e) {
            Log::warning('WhatsApp send failed (network)', ['template' => $template, 'error' => $e->getMessage()]);

            return ['successful' => false, 'error' => 'Could not reach WhatsApp. Try again in a moment.'];
        }

        if ($response->successful()) {
            return ['successful' => true, 'message_id' => (string) data_get($response->json(), 'messages.0.id', '')];
        }

        $raw = (string) data_get($response->json(), 'error.message', 'WhatsApp refused the message.');
        $code = (int) data_get($response->json(), 'error.code', 0);
        // The token is never logged; the guest's number is not logged either. The raw Meta text stays in the log for the admin.
        Log::warning('WhatsApp send refused', ['template' => $template, 'status' => $response->status(), 'code' => $code, 'error' => $raw]);

        return ['successful' => false, 'error' => self::friendlyError($code, $raw, $response->status())];
    }

    /**
     * Turns Meta's technical error into a sentence the event host can act on.
     * Problems only the system admin can fix (token, billing, template) say so.
     */
    public static function friendlyError(int $code, string $raw = '', int $status = 0): string
    {
        $sms = 'Send this one by SMS instead.';

        return match (true) {
            $code === 190, $code === 102, $status === 401
                => "WhatsApp is temporarily unavailable (the account connection needs renewing). {$sms} Please tell your system admin.",
            $code === 131030
                => "This number can't receive WhatsApp messages from us yet. {$sms}",
            in_array($code, [131026, 133010], true)
                => "This number is not on WhatsApp, or can't be reached there. {$sms}",
            in_array($code, [131047, 131049, 131048], true)
                => "WhatsApp declined to deliver to this number right now. {$sms}",
            in_array($code, [132000, 132001, 132005, 132007, 132012, 132015, 132016], true)
                => "The WhatsApp invitation message isn't ready yet (not approved, or the wrong language). {$sms} Please tell your system admin.",
            in_array($code, [131042, 131031], true)
                => "WhatsApp billing needs attention. {$sms} Please tell your system admin.",
            in_array($code, [4, 80007, 130429, 131056], true), $status === 429
                => 'Too many messages were sent too quickly. Wait a minute and try again.',
            $code === 100
                => "WhatsApp did not accept this phone number or message. Check the number, or {$sms}",
            default
                => "WhatsApp could not send this message (code {$code}). {$sms}",
        };
    }

    /** Sends the invitation template for one guest; picks English or Swahili, and the card-image version when the event has a design. */
    public function sendInvitation(Event $event, Pledge $pledge, string $toDigits): array
    {
        $sw = $event->sms_language === 'sw';
        $image = $this->cardImageUrl($event, $pledge);

        $template = $sw
            ? ($image ? config('services.whatsapp.invite_card_template_sw') : config('services.whatsapp.invite_template_sw'))
            : ($image ? config('services.whatsapp.invite_card_template') : config('services.whatsapp.invite_template'));

        return $this->sendTemplate(
            $toDigits,
            $template,
            $sw ? 'sw' : 'en',
            [
                $pledge->name,
                $event->name,
                $this->dateText($event, $sw),
                $event->place ?: ($sw ? 'ukumbi ulioonyeshwa kwenye kadi yako' : 'the venue shown on your card'),
            ],
            $image,
            $pledge->invite_token,
        );
    }

    /** The host's own card design (or the card photo) as a public link WhatsApp can fetch — JPG or PNG only. */
    public function cardImageUrl(Event $event, Pledge $pledge): ?string
    {
        if (! $pledge->invite_token) {
            return null;
        }

        $ok = ['image/jpeg', 'image/jpg', 'image/png'];

        if ($event->card_has_custom_design) {
            $mime = EventAsset::where('event_id', $event->id)->where('kind', 'design')->value('mime');

            if (in_array(strtolower((string) $mime), $ok, true)) {
                return route('guest.rsvp.design', $pledge->invite_token);
            }
        }

        if ($event->hasCardPhoto() && in_array(strtolower((string) $event->card_photo_mime), $ok, true)) {
            return route('guest.rsvp.photo', $pledge->invite_token);
        }

        return null;
    }

    private function dateText(Event $event, bool $sw): string
    {
        $d = $event->event_date;

        return $sw
            ? self::SW_DAYS[$d->dayOfWeek].' '.$d->day.' '.self::SW_MONTHS[$d->month - 1].' '.$d->year
            : $d->format('l j F Y');
    }

    /** Meta rejects values with line breaks, tabs or 4+ spaces in a row. */
    private function clean(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? '-' : mb_substr($value, 0, 200);
    }
}
