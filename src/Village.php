<?php

declare(strict_types=1);

namespace CambodiaGeo;

/** ភូមិ */
final class Village extends GeoRecord
{
    public function __construct(
        public readonly string $code,
        public readonly string $nameKh,
        public readonly string $nameEn,
        public readonly string $provinceCode,
        public readonly string $districtCode,
        public readonly string $communeCode,
    ) {
    }

    public function level(): Level
    {
        return Level::Village;
    }
}
