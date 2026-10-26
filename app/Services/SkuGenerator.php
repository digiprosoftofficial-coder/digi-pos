<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;

class SkuGenerator
{
    /**
     * Return a store-scoped SKU. Uses the provided value when present;
     * otherwise allocates the next date-based serial (P-YYYYMMDD-0001).
     */
    public function next(?string $sku = null): string
    {
        $sku = trim((string) $sku);

        if ($sku !== '') {
            return $sku;
        }

        return $this->allocateForDate(now()->format('Ymd'));
    }

    public function forVariant(string $parentSku, int $position, ?string $sku = null): string
    {
        return $this->resolveVariantSku($parentSku, $position, $sku);
    }

    /**
     * Keep a real SKU, but treat blanks and the broken "-00" suffix as missing
     * so each variant gets a unique -01, -02, … serial.
     */
    public function resolveVariantSku(string $parentSku, int $position, ?string $incoming = null, ?string $existing = null): string
    {
        $incoming = trim((string) $incoming);
        $existing = trim((string) $existing);
        $chosen = $incoming !== '' ? $incoming : $existing;

        if ($chosen === '' || $this->isBrokenVariantSku($chosen, $parentSku)) {
            return $parentSku.'-'.str_pad((string) $position, 2, '0', STR_PAD_LEFT);
        }

        return $chosen;
    }

    protected function isBrokenVariantSku(string $sku, string $parentSku): bool
    {
        return $sku === $parentSku.'-00';
    }

    protected function allocateForDate(string $date): string
    {
        $prefix = 'P-'.$date.'-';
        $pattern = '/^P-'.$date.'-(\d{4})(?:-\d+)?$/';

        $candidates = Product::query()
            ->where('sku', 'like', $prefix.'%')
            ->pluck('sku')
            ->concat(
                ProductVariant::query()
                    ->where('sku', 'like', $prefix.'%')
                    ->pluck('sku')
            );

        $max = 0;
        foreach ($candidates as $existing) {
            if (preg_match($pattern, (string) $existing, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $prefix.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
