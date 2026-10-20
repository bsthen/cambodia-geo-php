<?php

declare(strict_types=1);

namespace CambodiaGeo\Tests;

use CambodiaGeo\Internal\Text;
use PHPUnit\Framework\TestCase;

/**
 * Data checks every yearly CSV update must pass.
 *
 * The CSVs are published by MEF and bundled *unmodified*. The 2025 files contain a few upstream quirks,
 * listed below. The tests allow exactly those, so any NEW problem in a future dataset fails the build and
 * gets reviewed by a human (update the lists consciously, never the CSVs).
 */
final class DataTest extends TestCase
{
    // Village codes shared by several villages of the same commune (incl. the placeholder "0506140-").
    private const KNOWN_SHARED_VILLAGE_CODES = [
        '0506140-', '08120101', '08120301', '08120501', '12091101', '12091301', '12091701', '12091801',
    ];
    // Communes that have villages but are missing from the commune list.
    private const KNOWN_MISSING_COMMUNES = ['12/1209/120913'];

    /** @var array<string, list<list<string>>> */
    private static array $csv = [];
    /** @var array<string, list<list<string>>> */
    private static array $generated = [];

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__);
        $levels = [
            'provinces' => ['CambodiaProvinceList', 'province', []],
            'districts' => ['CambodiaDistrictList', 'district', ['province_code']],
            'communes' => ['CambodiaCommuneList', 'commune', ['province_code', 'district_code']],
            'villages' => ['CambodiaVillagesList', 'village', ['province_code', 'district_code', 'commune_code']],
        ];
        foreach ($levels as $key => [$prefix, $level, $parents]) {
            $files = glob("{$root}/data/{$prefix}[0-9][0-9][0-9][0-9].csv") ?: [];
            self::assertCount(1, $files, "exactly one {$prefix}<year>.csv expected");
            self::$csv[$key] = self::readCsv($files[0], $level, $parents);
            self::$generated[$key] = (require "{$root}/src/Data/{$key}.php")['rows'];
        }
    }

    /**
     * @param list<string> $parents
     *
     * @return list<list<string>>
     */
    private static function readCsv(string $file, string $level, array $parents): array
    {
        $handle = fopen($file, 'rb');
        $header = array_map(static fn ($h) => trim((string) $h), fgetcsv($handle, 0, ',', '"', '') ?: []);
        $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        $pos = array_flip($header);
        $rows = [];
        while (($r = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($r === [null]) {
                continue;
            }
            $cell = static fn (string $c): string => trim((string) ($r[$pos[$c]] ?? ''));
            if ($cell("{$level}_code") === '') {
                continue;
            }
            $rows[] = array_merge(
                [$cell("{$level}_code"), $cell("{$level}_kh"), $cell("{$level}_en")],
                array_map($cell, $parents),
            );
        }
        fclose($handle);

        return $rows;
    }

    /** @param list<string> $row */
    private static function path(array $row, int $parents): string
    {
        $keys = array_map(Text::codeKey(...), array_slice($row, 3, $parents));
        $keys[] = Text::codeKey($row[0]);

        return implode('/', $keys);
    }

    public function testGeneratedDataMatchesTheCsvFilesExactly(): void
    {
        foreach (self::$csv as $key => $rows) {
            self::assertNotEmpty($rows, $key);
            self::assertSame($rows, self::$generated[$key], "src/Data/{$key}.php is out of date: run composer build-data");
        }
    }

    public function testCodesAndKhmerNamesArePresent(): void
    {
        foreach (self::$generated as $key => $rows) {
            foreach ($rows as $row) {
                self::assertNotSame('', $row[0], $key);
                self::assertNotSame('', $row[1], $key);
            }
        }
    }

    public function testProvinceDistrictAndCommuneCodesAreUnique(): void
    {
        foreach (['provinces' => 0, 'districts' => 1, 'communes' => 2] as $key => $parents) {
            $paths = array_map(fn (array $r) => self::path($r, $parents), self::$generated[$key]);
            self::assertSame(count($paths), count(array_unique($paths)), "duplicate {$key} code(s)");
        }
    }

    public function testOnlyTheKnownVillageCodeCollisions(): void
    {
        $paths = array_map(fn (array $r) => self::path($r, 3), self::$generated['villages']);
        $counts = array_count_values($paths);
        $colliding = [];
        foreach (self::$generated['villages'] as $i => $row) {
            if ($counts[$paths[$i]] > 1) {
                $colliding[] = $row[0];
            }
        }
        self::assertSame([], array_values(array_diff($colliding, self::KNOWN_SHARED_VILLAGE_CODES)));

        $named = array_map(fn (array $r) => self::path($r, 3) . '|' . $r[1], self::$generated['villages']);
        self::assertSame(count($named), count(array_unique($named)), 'exact duplicate villages');
    }

    public function testNoOrphansExceptTheKnownOnes(): void
    {
        $provinces = array_flip(array_map(fn ($r) => self::path($r, 0), self::$generated['provinces']));
        $districts = array_flip(array_map(fn ($r) => self::path($r, 1), self::$generated['districts']));
        $communes = array_flip(array_map(fn ($r) => self::path($r, 2), self::$generated['communes']));

        foreach (self::$generated['districts'] as $r) {
            self::assertArrayHasKey(Text::codeKey($r[3]), $provinces);
        }
        foreach (self::$generated['communes'] as $r) {
            self::assertArrayHasKey(Text::codeKey($r[3]) . '/' . Text::codeKey($r[4]), $districts);
        }
        $missing = [];
        foreach (self::$generated['villages'] as $r) {
            $communeKey = implode('/', array_map(Text::codeKey(...), array_slice($r, 3, 3)));
            if (!isset($communes[$communeKey])) {
                $missing[$communeKey] = true;
            }
        }
        self::assertSame([], array_values(array_diff(array_keys($missing), self::KNOWN_MISSING_COMMUNES)));
    }
}
