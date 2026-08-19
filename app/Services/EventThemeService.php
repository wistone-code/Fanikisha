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

    public function for(?string $eventType): array
    {
        return self::THEMES[$eventType] ?? self::DEFAULT;
    }
}
