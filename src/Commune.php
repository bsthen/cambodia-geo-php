<?php

declare(strict_types=1);

namespace CambodiaGeo;

/** ឃុំ / សង្កាត់ */
final class Commune extends GeoRecord
{
    public function __construct(
        public readonly string $code,
        public readonly string $nameKh,
        public readonly string $nameEn,
        public readonly string $provinceCode,
        public readonly string $districtCode,
    ) {
    }

    public function level(): Level
    {
        return Level::Commune;
    }
}
