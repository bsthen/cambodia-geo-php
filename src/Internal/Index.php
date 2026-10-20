<?php

declare(strict_types=1);

namespace CambodiaGeo\Internal;

use CambodiaGeo\Commune;
use CambodiaGeo\District;
use CambodiaGeo\GeoRecord;
use CambodiaGeo\Level;
use CambodiaGeo\Province;
use CambodiaGeo\Village;

/**
 * Lookup tables of one level. The rows and the parent => children map are precomputed in
 * src/Data (by scripts/build-data.php); everything else is computed lazily, only when needed.
 * Internal: not part of the public API.
 *
 * @internal
 */
final class Index
{
    /** @var array<string, self> */
    private static array $cache = [];

    /** @var array<int, GeoRecord> */
    private array $records = [];
    /** @var array<int, string> */
    private array $ckCache = [];
    /** @var array<int, string> */
    private array $khCache = [];
    /** @var array<int, string> */
    private array $enCache = [];
    /** @var array<int, string> */
    private array $pathCache = [];
    /** @var array<int|string, list<int>>|null */
    private ?array $byCode = null;
    /** @var array<int|string, list<int>>|null */
    private ?array $byName = null;

    /**
     * @param list<list<string>>              $rows     [code, nameKh, nameEn, ...parent codes] in CSV order
     * @param array<int|string, list<int>>    $children canonical parent path => row numbers
     */
    private function __construct(
        private readonly Level $level,
        private readonly array $rows,
        private readonly array $children,
    ) {
    }

    public static function of(Level $level): self
    {
        if (!isset(self::$cache[$level->value])) {
            /** @var array{rows: list<list<string>>, children: array<int|string, list<int>>} $data */
            $data = require \dirname(__DIR__) . '/Data/' . $level->value . 's.php';
            self::$cache[$level->value] = new self($level, $data['rows'], $data['children']);
        }

        return self::$cache[$level->value];
    }

    public function count(): int
    {
        return \count($this->rows);
    }

    /** Canonical own code of row `$i`. */
    public function ck(int $i): string
    {
        return $this->ckCache[$i] ??= Text::codeKey($this->rows[$i][0]);
    }

    /** Normalised Khmer name of row `$i`. */
    public function kh(int $i): string
    {
        return $this->khCache[$i] ??= Text::norm($this->rows[$i][1]);
    }

    /** Normalised English name of row `$i`. */
    public function en(int $i): string
    {
        return $this->enCache[$i] ??= Text::norm($this->rows[$i][2]);
    }

    /** Canonical codes of the ancestors and the row itself, joined with "/". */
    public function path(int $i): string
    {
        if (!isset($this->pathCache[$i])) {
            $row = $this->rows[$i];
            $keys = [];
            for ($p = 3, $n = \count($row); $p < $n; $p++) {
                $keys[] = Text::codeKey($row[$p]);
            }
            $keys[] = $this->ck($i);
            $this->pathCache[$i] = implode('/', $keys);
        }

        return $this->pathCache[$i];
    }

    /**
     * Row numbers directly below the record with canonical path `$parentPath`.
     *
     * @return list<int>
     */
    public function childrenOf(string $parentPath): array
    {
        return $this->children[$parentPath] ?? [];
    }

    /** @return list<int>|null rows whose canonical code is `$code` */
    public function codeHits(string $code): ?array
    {
        if ($this->byCode === null) {
            $this->byCode = [];
            for ($i = 0, $n = $this->count(); $i < $n; $i++) {
                $this->byCode[$this->ck($i)][] = $i;
            }
        }

        return $this->byCode[$code] ?? null;
    }

    /** @return list<int>|null rows whose normalised Khmer or English name is `$name` */
    public function nameHits(string $name): ?array
    {
        if ($this->byName === null) {
            $this->byName = [];
            for ($i = 0, $n = $this->count(); $i < $n; $i++) {
                $kh = $this->kh($i);
                $en = $this->en($i);
                if ($kh !== '') {
                    $this->byName[$kh][] = $i;
                }
                if ($en !== '' && $en !== $kh) {
                    $this->byName[$en][] = $i;
                }
            }
        }

        return $this->byName[$name] ?? null;
    }

    /** The (cached) record of row `$i`. */
    public function record(int $i): GeoRecord
    {
        return $this->records[$i] ??= $this->make($this->rows[$i]);
    }

    /** Canonical path key of a record, e.g. "17/1702/170201". */
    public static function pathOf(GeoRecord $record): string
    {
        $parts = match (true) {
            $record instanceof Village => [$record->provinceCode, $record->districtCode, $record->communeCode],
            $record instanceof Commune => [$record->provinceCode, $record->districtCode],
            $record instanceof District => [$record->provinceCode],
            default => [],
        };
        $parts[] = $record->code;

        return implode('/', array_map(Text::codeKey(...), $parts));
    }

    /** @param list<string> $row */
    private function make(array $row): GeoRecord
    {
        return match ($this->level) {
            Level::Province => new Province($row[0], $row[1], $row[2]),
            Level::District => new District($row[0], $row[1], $row[2], $row[3]),
            Level::Commune => new Commune($row[0], $row[1], $row[2], $row[3], $row[4]),
            Level::Village => new Village($row[0], $row[1], $row[2], $row[3], $row[4], $row[5]),
        };
    }
}
