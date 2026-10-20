<?php

declare(strict_types=1);

namespace CambodiaGeo;

/** The four administrative levels, from largest to smallest. */
enum Level: string
{
    case Province = 'province';
    case District = 'district';
    case Commune = 'commune';
    case Village = 'village';

    /** 0 for province ... 3 for village. */
    public function position(): int
    {
        return match ($this) {
            self::Province => 0,
            self::District => 1,
            self::Commune => 2,
            self::Village => 3,
        };
    }
}
