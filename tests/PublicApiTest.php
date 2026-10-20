<?php

declare(strict_types=1);

namespace CambodiaGeo\Tests;

use CambodiaGeo\Geo;
use CambodiaGeo\Level;
use PHPUnit\Framework\TestCase;

/**
 * Guards against accidental breaking changes. If one of these fails you are changing the public API:
 * removing or renaming something requires a MAJOR version bump (see README: Stability).
 * Adding new methods or optional parameters is fine: extend the lists below in the same commit.
 */
final class PublicApiTest extends TestCase
{
    /** method => parameter names, in order */
    private const SIGNATURES = [
        'version' => [],
        'provinces' => [],
        'districts' => ['province'],
        'communes' => ['province', 'district'],
        'villages' => ['province', 'district', 'commune'],
        'getProvince' => ['query'],
        'getDistrict' => ['query', 'province'],
        'getCommune' => ['query', 'district', 'province'],
        'getVillage' => ['query', 'commune', 'district', 'province'],
        'search' => ['query', 'level', 'limit'],
    ];

    private const REQUIRED = [
        'version' => 0, 'provinces' => 0, 'districts' => 1, 'communes' => 2, 'villages' => 3,
        'getProvince' => 1, 'getDistrict' => 1, 'getCommune' => 1, 'getVillage' => 1, 'search' => 1,
    ];

    public function testMethodsAndParameterNamesAreStable(): void
    {
        $class = new \ReflectionClass(Geo::class);
        foreach (self::SIGNATURES as $method => $params) {
            self::assertTrue($class->hasMethod($method), "missing method {$method}");
            $reflection = $class->getMethod($method);
            self::assertTrue($reflection->isStatic() && $reflection->isPublic(), $method);
            $actual = array_map(static fn (\ReflectionParameter $p) => $p->getName(), $reflection->getParameters());
            self::assertSame($params, array_slice($actual, 0, count($params)), "parameters of {$method}");
            self::assertSame(self::REQUIRED[$method], $reflection->getNumberOfRequiredParameters(), $method);
        }
        self::assertTrue($class->hasConstant('DATA_YEAR'));
    }

    public function testLevelCasesAreStable(): void
    {
        self::assertSame(
            ['province', 'district', 'commune', 'village'],
            array_map(static fn (Level $l) => $l->value, Level::cases()),
        );
    }

    public function testRecordPropertiesAndTheirOrderAreStable(): void
    {
        self::assertSame(['code', 'nameKh', 'nameEn'], array_keys(get_object_vars(Geo::getProvince('17'))));
        self::assertSame(
            ['code', 'nameKh', 'nameEn', 'provinceCode'],
            array_keys(get_object_vars(Geo::getDistrict('1702'))),
        );
        self::assertSame(
            ['code', 'nameKh', 'nameEn', 'provinceCode', 'districtCode'],
            array_keys(get_object_vars(Geo::getCommune('170201'))),
        );
        self::assertSame(
            ['code', 'nameKh', 'nameEn', 'provinceCode', 'districtCode', 'communeCode'],
            array_keys(get_object_vars(Geo::villages('17', '1702', '170201')[0])),
        );
    }
}
