<?php

declare(strict_types=1);

namespace CambodiaGeo;

/** ស្រុក / ខណ្ឌ / ក្រុង */
final class District extends GeoRecord
{
    public function __construct(
        public readonly string $code,
        public readonly string $nameKh,
        public readonly string $nameEn,
        public readonly string $provinceCode,
    ) {
    }

    public function level(): Level
    {
        return Level::District;
    }
}
