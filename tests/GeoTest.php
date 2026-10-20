<?php

declare(strict_types=1);

namespace CambodiaGeo\Tests;

use CambodiaGeo\Commune;
use CambodiaGeo\District;
use CambodiaGeo\Geo;
use CambodiaGeo\Level;
use CambodiaGeo\Province;
use CambodiaGeo\Village;
use PHPUnit\Framework\TestCase;

final class GeoTest extends TestCase
{
    public function testProvincesAreReturned(): void
    {
        $list = Geo::provinces();
        self::assertNotEmpty($list);
        self::assertContainsOnlyInstancesOf(Province::class, $list);
        self::assertSame(['code', 'nameKh', 'nameEn'], array_keys(get_object_vars($list[0])));
    }

    public function testProvinceLookupAcceptsCodeKhmerEnglishNumberAndVariants(): void
    {
        $byCode = Geo::getProvince('17');
        self::assertInstanceOf(Province::class, $byCode);
        foreach ([17, 'សៀមរាប', 'Siemreap', '  SIEM reap ', '017', '១៧'] as $query) {
            self::assertSame($byCode, Geo::getProvince($query), 'query: ' . var_export($query, true));
        }
        self::assertSame(Geo::getProvince(1), Geo::getProvince('01'));
    }

    public function testUnknownAndInvalidInputGivesNullOrEmpty(): void
    {
        $bad = ['does-not-exist', '', '   ', '-', null, NAN, -1, 1.5, true, new \stdClass(), [], 9999];
        foreach ($bad as $query) {
            self::assertNull(Geo::getProvince($query), 'query: ' . var_export($query, true));
        }
        self::assertSame([], Geo::districts('does-not-exist'));
        self::assertSame([], Geo::communes('17', 'does-not-exist'));
        self::assertSame([], Geo::villages('17', '1702', 'does-not-exist'));
    }

    public function testHierarchyWithMixedQueryStyles(): void
    {
        self::assertContains('1702', array_map(static fn (District $d) => $d->code, Geo::districts('Siemreap')));

        $communes = Geo::communes('17', 'Angkor Thum');
        self::assertContains('170201', array_map(static fn (Commune $c) => $c->code, $communes));
        self::assertEquals($communes, Geo::communes('សៀមរាប', '1702'));

        $villages = Geo::villages('17', '1702', 'ជប់តាត្រាវ');
        self::assertNotEmpty($villages);
        foreach ($villages as $village) {
            self::assertSame('170201', $village->communeCode);
        }
        self::assertEquals($villages, Geo::villages('Siemreap', 'Angkor Thum', '170201'));
    }

    public function testRecordsCanBePassedBackIn(): void
    {
        $province = Geo::getProvince('17');
        $district = Geo::getDistrict('1702');
        $commune = Geo::communes($province, $district)[0];
        self::assertEquals(
            Geo::villages($province->code, $district->code, $commune->code),
            Geo::villages($province, $district, $commune),
        );
    }

    public function testARecordOfTheWrongLevelMatchesNothing(): void
    {
        $district = Geo::getDistrict('1702');
        self::assertNull(Geo::getProvince($district));
        self::assertSame([], Geo::districts($district));
    }

    public function testGetDistrictScopedAndUnscoped(): void
    {
        $d = Geo::getDistrict('1702');
        self::assertInstanceOf(District::class, $d);
        self::assertSame('17', $d->provinceCode);
        self::assertSame('Angkor Thum', $d->nameEn);
        self::assertSame($d, Geo::getDistrict('Angkor Thum', province: 'Siemreap'));
        self::assertNull(Geo::getDistrict('1702', province: 'does-not-exist'));
        self::assertNull(Geo::getDistrict('1702', province: '12'));
    }

    public function testGetCommuneAndGetVillage(): void
    {
        $c = Geo::getCommune('170201');
        self::assertInstanceOf(Commune::class, $c);
        self::assertSame('1702', $c->districtCode);
        self::assertSame($c, Geo::getCommune('ជប់តាត្រាវ', district: 'Angkor Thum', province: '17'));
        self::assertSame($c, Geo::getCommune('ជប់តាត្រាវ', province: '17')); // skipping a level is fine

        $v = Geo::villages('17', '1702', '170201')[0];
        self::assertInstanceOf(Village::class, $v);
        self::assertSame($v, Geo::getVillage($v->code));
        self::assertSame($v, Geo::getVillage($v->nameKh, commune: '170201', district: '1702', province: '17'));
    }

    public function testSearchOrdersExactMatchesFirstAndRespectsLimits(): void
    {
        $hits = Geo::search('Siemreap');
        self::assertSame(Geo::getProvince('17'), $hits[0]);
        self::assertEquals(array_slice($hits, 0, 1), Geo::search('Siemreap', limit: 1));
        self::assertSame([], Geo::search('Siemreap', limit: 0));
        self::assertSame([], Geo::search(''));
        self::assertGreaterThan(1, count(Geo::search('angkor', level: 'district', limit: null)));
        self::assertGreaterThan(50, count(Geo::search('a', limit: null)));
        self::assertCount(50, Geo::search('a'));
        self::assertContainsOnlyInstancesOf(District::class, Geo::search('angkor', level: Level::District));
    }

    public function testSearchValidatesItsArguments(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Geo::search('x', level: 'country');
    }

    public function testSearchRejectsANegativeLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Geo::search('x', limit: -1);
    }

    public function testListsAreIndependentCopies(): void
    {
        $first = Geo::provinces();
        $count = count($first);
        array_pop($first);
        self::assertCount($count, Geo::provinces());
    }

    public function testRecordsAreImmutableAndSerialisable(): void
    {
        $p = Geo::getProvince('17');
        try {
            $p->code = '00'; // @phpstan-ignore-line
            self::fail('records must be read-only');
        } catch (\Error) {
            self::assertSame('17', $p->code);
        }
        self::assertSame(['code' => '17', 'name_kh' => 'សៀមរាប', 'name_en' => 'Siemreap'], $p->toArray());
        self::assertSame('{"code":"17","name_kh":"សៀមរាប","name_en":"Siemreap"}', json_encode($p, JSON_UNESCAPED_UNICODE));
    }

    public function testToArrayUsesSnakeCaseKeys(): void
    {
        $v = Geo::villages('17', '1702', '170201')[0];
        self::assertSame(
            ['code', 'name_kh', 'name_en', 'province_code', 'district_code', 'commune_code'],
            array_keys($v->toArray()),
        );
        self::assertSame(Level::Village, $v->level());
    }

    public function testMetadata(): void
    {
        self::assertIsInt(Geo::DATA_YEAR);
        self::assertSame(trim((string) file_get_contents(__DIR__ . '/../VERSION')), Geo::version());
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Geo::version());
    }
}
