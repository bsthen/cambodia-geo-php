<?php

declare(strict_types=1);

namespace CambodiaGeo;

use CambodiaGeo\Internal\Index;
use CambodiaGeo\Internal\Text;

/**
 * Cambodia's provinces, districts, communes and villages.
 *
 *     use CambodiaGeo\Geo;
 *
 *     Geo::districts('Siemreap');                       // code, Khmer or English name
 *     Geo::villages('សៀមរាប', 1702, 'ជប់តាត្រាវ');
 *     Geo::getDistrict('Angkor Thum', province: 17);    // named arguments
 *
 * A *query* is a code (`'17'` or `17`), a Khmer name, an English name, or a record returned by this
 * library. Only the public methods of this class, the records and {@see Level} follow semantic
 * versioning. Behaviour is defined in SPEC.md and is identical to the Python and JavaScript packages.
 */
final class Geo
{
    /** Year of the bundled MEF dataset. */
    public const DATA_YEAR = Meta::DATA_YEAR;

    private static ?string $version = null;

    private function __construct()
    {
    }

    /** Version of this package (from the VERSION file). */
    public static function version(): string
    {
        return self::$version ??= trim((string) @file_get_contents(\dirname(__DIR__) . '/VERSION'));
    }

    /** @return list<Province> */
    public static function provinces(): array
    {
        $index = Index::of(Level::Province);
        $out = [];
        for ($i = 0, $n = $index->count(); $i < $n; $i++) {
            $out[] = $index->record($i);
        }

        /** @var list<Province> $out */
        return $out;
    }

    /**
     * @param GeoRecord|string|int|null $province
     *
     * @return list<District> Empty if the province is not found.
     */
    public static function districts(mixed $province): array
    {
        $p = self::resolve(Level::Province, $province);

        /** @var list<District> */
        return $p === null ? [] : self::childrenOf(Level::District, $p);
    }

    /**
     * @param GeoRecord|string|int|null $province
     * @param GeoRecord|string|int|null $district
     *
     * @return list<Commune> Empty if a parent is not found.
     */
    public static function communes(mixed $province, mixed $district): array
    {
        $p = self::resolve(Level::Province, $province);
        if ($p === null) {
            return [];
        }
        $d = self::resolve(Level::District, $district, $p);

        /** @var list<Commune> */
        return $d === null ? [] : self::childrenOf(Level::Commune, $d);
    }

    /**
     * @param GeoRecord|string|int|null $province
     * @param GeoRecord|string|int|null $district
     * @param GeoRecord|string|int|null $commune
     *
     * @return list<Village> Empty if a parent is not found.
     */
    public static function villages(mixed $province, mixed $district, mixed $commune): array
    {
        $p = self::resolve(Level::Province, $province);
        if ($p === null) {
            return [];
        }
        $d = self::resolve(Level::District, $district, $p);
        if ($d === null) {
            return [];
        }
        $c = self::resolve(Level::Commune, $commune, $d);

        /** @var list<Village> */
        return $c === null ? [] : self::childrenOf(Level::Village, $c);
    }

    /** One province by code, Khmer name or English name; null if not found. */
    public static function getProvince(mixed $query): ?Province
    {
        $found = self::resolve(Level::Province, $query);

        return $found instanceof Province ? $found : null;
    }

    /** One district; give `$province` to disambiguate names. Null if not found. */
    public static function getDistrict(mixed $query, mixed $province = null): ?District
    {
        [$ok, $scope] = self::scope($province);
        $found = $ok ? self::resolve(Level::District, $query, $scope) : null;

        return $found instanceof District ? $found : null;
    }

    /** One commune; give `$district` / `$province` to disambiguate names. Null if not found. */
    public static function getCommune(mixed $query, mixed $district = null, mixed $province = null): ?Commune
    {
        [$ok, $scope] = self::scope($province, $district);
        $found = $ok ? self::resolve(Level::Commune, $query, $scope) : null;

        return $found instanceof Commune ? $found : null;
    }

    /** One village; give `$commune` / `$district` / `$province` to disambiguate names. Null if not found. */
    public static function getVillage(
        mixed $query,
        mixed $commune = null,
        mixed $district = null,
        mixed $province = null,
    ): ?Village {
        [$ok, $scope] = self::scope($province, $district, $commune);
        $found = $ok ? self::resolve(Level::Village, $query, $scope) : null;

        return $found instanceof Village ? $found : null;
    }

    /**
     * Searches names (Khmer / English) and codes. Results: exact matches first, then prefix matches,
     * then substring matches; ties are ordered province -> village, then by data order.
     *
     * @param Level|string|null $level Restrict to one level ('province', 'district', 'commune', 'village').
     * @param int|null          $limit Maximum number of results (default 50); null means no limit.
     *
     * @return list<GeoRecord>
     *
     * @throws \InvalidArgumentException for an unknown level or a negative limit
     */
    public static function search(mixed $query, Level|string|null $level = null, ?int $limit = 50): array
    {
        if (\is_string($level)) {
            $level = Level::tryFrom($level)
                ?? throw new \InvalidArgumentException("level must be one of province, district, commune, village or null, got '{$level}'");
        }
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('limit must be >= 0 or null');
        }

        $text = self::queryText($query);
        if ($text === null) {
            return [];
        }
        $nameKey = Text::norm($text);
        if ($nameKey === '') {
            return [];
        }
        $code = Text::codeKey($text);

        $scored = [];
        foreach ($level === null ? Level::cases() : [$level] as $levelPos => $current) {
            $index = Index::of($current);
            for ($i = 0, $n = $index->count(); $i < $n; $i++) {
                $kh = $index->kh($i);
                $en = $index->en($i);
                if ($index->ck($i) === $code || $nameKey === $kh || $nameKey === $en) {
                    $rank = 0;
                } elseif (str_starts_with($kh, $nameKey) || str_starts_with($en, $nameKey)) {
                    $rank = 1;
                } elseif (str_contains($kh, $nameKey) || str_contains($en, $nameKey)) {
                    $rank = 2;
                } else {
                    continue;
                }
                $scored[] = [$rank, $levelPos, $i, $current];
            }
        }
        usort($scored, static fn (array $a, array $b): int => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);
        if ($limit !== null) {
            $scored = \array_slice($scored, 0, $limit);
        }

        return array_map(static fn (array $hit): GeoRecord => Index::of($hit[3])->record($hit[2]), $scored);
    }

    // ------------------------------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------------------------------

    /** The text of a string / non-negative int query, or null for anything else. */
    private static function queryText(mixed $query): ?string
    {
        if (\is_string($query)) {
            return Text::trim($query);
        }
        if (\is_int($query) && $query >= 0) {
            return (string) $query;
        }

        return null;
    }

    /**
     * Finds one record of `$level` by code / Khmer name / English name.
     * Without a scope a match must be unambiguous, otherwise null is returned.
     */
    private static function resolve(Level $level, mixed $query, ?GeoRecord $scope = null): ?GeoRecord
    {
        if ($query instanceof GeoRecord) {
            return $query->level() === $level ? $query : null;
        }
        $text = self::queryText($query);
        if ($text === null) {
            return null;
        }
        $nameKey = Text::norm($text);
        if ($nameKey === '') {
            return null;
        }
        $code = Text::codeKey($text);
        $index = Index::of($level);

        if ($scope === null) {
            $hits = $index->codeHits($code) ?? $index->nameHits($nameKey);
            if ($hits === null) {
                return null;
            }

            return \count($hits) === 1 ? $index->record($hits[0]) : null;
        }

        foreach (self::pool($level, $scope) as $i) {
            if ($index->ck($i) === $code || $index->kh($i) === $nameKey || $index->en($i) === $nameKey) {
                return $index->record($i);
            }
        }

        return null;
    }

    /**
     * Row numbers of `$level` that live somewhere below `$scope`.
     *
     * @return list<int>
     */
    private static function pool(Level $level, GeoRecord $scope): array
    {
        $index = Index::of($level);
        $scopePath = Index::pathOf($scope);
        if ($scope->level()->position() + 1 === $level->position()) {
            return $index->childrenOf($scopePath);
        }
        $prefix = $scopePath . '/';
        $rows = [];
        for ($i = 0, $n = $index->count(); $i < $n; $i++) {
            if (str_starts_with($index->path($i), $prefix)) {
                $rows[] = $i;
            }
        }

        return $rows;
    }

    /**
     * Resolves the given ancestors in order. `ok` is false if one of them was given but not found.
     *
     * @return array{0: bool, 1: ?GeoRecord}
     */
    private static function scope(mixed $province = null, mixed $district = null, mixed $commune = null): array
    {
        $scope = null;
        foreach ([[Level::Province, $province], [Level::District, $district], [Level::Commune, $commune]] as [$level, $query]) {
            if ($query === null) {
                continue;
            }
            $scope = self::resolve($level, $query, $scope);
            if ($scope === null) {
                return [false, null];
            }
        }

        return [true, $scope];
    }

    /** @return list<GeoRecord> */
    private static function childrenOf(Level $level, GeoRecord $parent): array
    {
        $index = Index::of($level);
        $out = [];
        foreach ($index->childrenOf(Index::pathOf($parent)) as $i) {
            $out[] = $index->record($i);
        }

        return $out;
    }
}
