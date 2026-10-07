<?php

namespace App\Support;

/** A short, human label for the phone/computer a request came from, e.g. "iPhone, Safari" (no tracking, just the browser's own User-Agent text). */
class DeviceLabel
{
    public static function fromUserAgent(?string $ua): string
    {
        $ua = (string) $ua;
        if ($ua === '') {
            return 'unknown device';
        }

        $device = match (true) {
            (bool) preg_match('/iPhone|iPod/i', $ua) => 'iPhone',
            (bool) preg_match('/iPad/i', $ua) => 'iPad',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/Macintosh|Mac OS X/i', $ua) => 'Mac',
            (bool) preg_match('/Linux|CrOS/i', $ua) => 'Linux',
            default => 'unknown device',
        };

        $browser = match (true) {
            (bool) preg_match('/FBAN|FBAV/i', $ua) => 'Facebook app',
            (bool) preg_match('/Instagram/i', $ua) => 'Instagram app',
            (bool) preg_match('/WhatsApp/i', $ua) => 'WhatsApp',
            (bool) preg_match('/CriOS|Chrome\//i', $ua) && ! preg_match('/Edg|OPR|SamsungBrowser/i', $ua) => 'Chrome',
            (bool) preg_match('/EdgiOS|Edg\//i', $ua) => 'Edge',
            (bool) preg_match('/FxiOS|Firefox/i', $ua) => 'Firefox',
            (bool) preg_match('/SamsungBrowser/i', $ua) => 'Samsung Internet',
            (bool) preg_match('/OPR|Opera/i', $ua) => 'Opera',
            (bool) preg_match('/Safari/i', $ua) => 'Safari',
            // An iPhone with no browser name in the text is the Home-Screen app (it has no "Safari" in its User-Agent).
            in_array($device, ['iPhone', 'iPad'], true) && preg_match('/AppleWebKit/i', $ua) => 'Home Screen app',
            default => null,
        };

        return $browser ? "{$device}, {$browser}" : $device;
    }

    public static function current(): string
    {
        return self::fromUserAgent(request()->userAgent());
    }
}
