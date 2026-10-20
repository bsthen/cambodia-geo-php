<?php

declare(strict_types=1);

namespace CambodiaGeo;

/** ខេត្ត / រាជធានី */
final class Province extends GeoRecord
{
    public function __construct(
        public readonly string $code,
        public readonly string $nameKh,
        public readonly string $nameEn,
    ) {
    }

    public function level(): Level
    {
        return Level::Province;
    }
}
