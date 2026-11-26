<?php

namespace App\Services\Facts;

/**
 * Canonical list of business fact keys and how each one behaves.
 */
final class FactKeys
{
    /** Keys where only one value should be true; different values are a conflict. */
    public const SINGLE = ['business_name', 'phone', 'email', 'address', 'opening_hours'];

    /** Keys that can legitimately hold several values. */
    public const MULTI = ['category', 'description', 'service', 'product', 'highlight'];

    /** Keys where the owner may legitimately keep more than one confirmed value. */
    public const MAY_KEEP_SEVERAL = ['phone', 'email'];

    /** Information a good small-business website needs; shown as "missing" on review. */
    public const IMPORTANT = ['phone', 'email', 'address', 'opening_hours', 'service'];

    public const LABELS = [
        'business_name' => 'Business name',
        'category' => 'Category',
        'description' => 'Description',
        'phone' => 'Phone number',
        'email' => 'Email',
        'address' => 'Address',
        'opening_hours' => 'Opening hours',
        'service' => 'Service',
        'product' => 'Product',
        'highlight' => 'Highlight',
    ];

    public const MAX_LENGTH = [
        'business_name' => 160,
        'category' => 120,
        'description' => 1500,
        'phone' => 40,
        'email' => 190,
        'address' => 300,
        'opening_hours' => 400,
        'service' => 300,
        'product' => 300,
        'highlight' => 200,
    ];

    public static function all(): array
    {
        return array_merge(self::SINGLE, self::MULTI);
    }

    public static function isValid(string $key): bool
    {
        return in_array($key, self::all(), true);
    }

    public static function isSingle(string $key): bool
    {
        return in_array($key, self::SINGLE, true);
    }

    public static function label(string $key): string
    {
        return self::LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }
}
