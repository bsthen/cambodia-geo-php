<?php

declare(strict_types=1);

namespace CambodiaGeo;

/**
 * Base class of the immutable records returned by {@see Geo}.
 *
 * Stability promise: properties may be added in minor releases (always at the end), but are never
 * renamed, removed or re-typed within a major version.
 */
abstract class GeoRecord implements \JsonSerializable
{
    private const SNAKE = [
        'code' => 'code',
        'nameKh' => 'name_kh',
        'nameEn' => 'name_en',
        'provinceCode' => 'province_code',
        'districtCode' => 'district_code',
        'communeCode' => 'commune_code',
    ];

    abstract public function level(): Level;

    /**
     * The record as a plain array with snake_case keys (the names used by the other languages).
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $out = [];
        foreach (get_object_vars($this) as $name => $value) {
            $out[self::SNAKE[$name] ?? $name] = $value;
        }

        return $out;
    }

    /** @return array<string, string> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
