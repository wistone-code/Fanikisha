<?php

namespace App\Services;

class EventThemeService
{
    /**
     * Cool-toned color per event type (blues/teals/violets — no warm reds/oranges),
     * applied as CSS custom properties on every authenticated page. See
     * resources/views/layouts/app.blade.php.
     */
    private const THEMES = [
        'Wedding' => ['primary' => '#3D5A99', 'primary_dark' => '#2C4270', 'accent' => '#7FB3D5'],
        'Engagement' => ['primary' => '#4A5FB0', 'primary_dark' => '#37478A', 'accent' => '#8FC1E3'],
        'Send-off' => ['primary' => '#1F6B6B', 'primary_dark' => '#17504F', 'accent' => '#6FBFBF'],
        'Kitchen Party' => ['primary' => '#1F7A8C', 'primary_dark' => '#175D6B', 'accent' => '#6FCAD6'],
        'Baby Shower' => ['primary' => '#2E8B7A', 'primary_dark' => '#236D61', 'accent' => '#7FC9BC'],
        'Birthday' => ['primary' => '#6B3FA0', 'primary_dark' => '#522F7D', 'accent' => '#B08FD1'],
        'Graduation' => ['primary' => '#1F3A52', 'primary_dark' => '#132836', 'accent' => '#7A93A8'],
        'Baptism' => ['primary' => '#2C5F8A', 'primary_dark' => '#22496B', 'accent' => '#7FAFD1'],
        'Confirmation' => ['primary' => '#3D4A8A', 'primary_dark' => '#2E386B', 'accent' => '#8B95D1'],
        'Communion' => ['primary' => '#4A6FA0', 'primary_dark' => '#385480', 'accent' => '#9BC0DE'],
        'Funeral' => ['primary' => '#3A4750', 'primary_dark' => '#262E34', 'accent' => '#8A97A0'],
        'Corporate' => ['primary' => '#24405C', 'primary_dark' => '#1A2E42', 'accent' => '#7A93A8'],
    ];

    private const DEFAULT = ['primary' => '#1F3A52', 'primary_dark' => '#132836', 'accent' => '#7A93A8'];

    /** Ready-made colors offered on the Setting page (name => hex). All are dark enough for white text. */
    public const PRESETS = [
        'Navy' => '#1F3A52',
        'Royal blue' => '#3D5A99',
        'Ocean' => '#1F7A8C',
        'Teal' => '#1F6B6B',
        'Emerald' => '#2E7D5B',
        'Purple' => '#6B3FA0',
        'Plum' => '#7B2D5B',
        'Burgundy' => '#8A2D3B',
        'Terracotta' => '#B0553A',
        'Gold' => '#8A6A1F',
        'Charcoal' => '#3A4750',
    ];

    public function for(?string $eventType): array
    {
        return self::THEMES[$eventType] ?? self::DEFAULT;
    }

    /**
     * Theme for a whole event: the organizer's chosen color if they picked one,
     * otherwise the default for the event's type.
     */
    public function forEvent(?\App\Models\Event $event): array
    {
        $base = $this->for($event?->event_type);
        $custom = $event?->theme_color;

        if (! $custom || ! $this->isValidColor($custom)) {
            return $base;
        }

        return $this->fromPrimary($custom);
    }

    public function isValidColor(?string $hex): bool
    {
        return is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1;
    }

    /** White text sits on the primary color, so very light colors would be unreadable. */
    public function isDarkEnough(string $hex): bool
    {
        return $this->luminance($hex) <= 0.4;
    }

    /** primary_dark = the color mixed 30% toward black; accent = mixed 55% toward white. */
    public function fromPrimary(string $hex): array
    {
        return [
            'primary' => strtolower($hex),
            'primary_dark' => $this->mix($hex, '#000000', 0.30),
            'accent' => $this->mix($hex, '#ffffff', 0.55),
        ];
    }

    private function mix(string $from, string $to, float $amount): string
    {
        $a = $this->rgb($from);
        $b = $this->rgb($to);

        return sprintf('#%02x%02x%02x',
            (int) round($a[0] + ($b[0] - $a[0]) * $amount),
            (int) round($a[1] + ($b[1] - $a[1]) * $amount),
            (int) round($a[2] + ($b[2] - $a[2]) * $amount),
        );
    }

    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** WCAG relative luminance, 0 (black) to 1 (white). */
    private function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function ($v) {
            $v /= 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, $this->rgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
