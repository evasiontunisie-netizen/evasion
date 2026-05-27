<?php

declare(strict_types=1);

namespace App\Core;

final class I18n
{
    private const TRANSLATIONS = [
        'fr' => ['dashboard' => 'Tableau de bord', 'products' => 'Produits', 'orders' => 'Commandes', 'tickets' => 'Tickets SAV'],
        'en' => ['dashboard' => 'Dashboard', 'products' => 'Products', 'orders' => 'Orders', 'tickets' => 'Support tickets'],
        'ar' => ['dashboard' => 'لوحة التحكم', 'products' => 'المنتجات', 'orders' => 'الطلبات', 'tickets' => 'تذاكر الدعم'],
    ];

    public static function t(string $key, string $locale = 'fr'): string
    {
        return self::TRANSLATIONS[$locale][$key] ?? self::TRANSLATIONS['fr'][$key] ?? $key;
    }

    public static function supported(): array
    {
        return array_keys(self::TRANSLATIONS);
    }
}
