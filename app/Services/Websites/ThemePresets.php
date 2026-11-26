<?php

namespace App\Services\Websites;

final class ThemePresets
{
    public const FONTS = ['inter', 'dm-sans', 'playfair'];

    public const BUTTON_STYLES = ['rounded', 'pill', 'square'];

    public const PRESETS = [
        'modern' => [
            'preset' => 'modern', 'primary' => '#4F46E5', 'secondary' => '#EEF2FF',
            'background' => '#FFFFFF', 'text' => '#111827', 'font' => 'inter', 'button_style' => 'rounded',
        ],
        'warm' => [
            'preset' => 'warm', 'primary' => '#B45309', 'secondary' => '#FEF3C7',
            'background' => '#FFFBF5', 'text' => '#292524', 'font' => 'playfair', 'button_style' => 'pill',
        ],
        'fresh' => [
            'preset' => 'fresh', 'primary' => '#047857', 'secondary' => '#ECFDF5',
            'background' => '#FFFFFF', 'text' => '#0F172A', 'font' => 'dm-sans', 'button_style' => 'square',
        ],
    ];

    public static function default(): array
    {
        return self::PRESETS['modern'];
    }
}
