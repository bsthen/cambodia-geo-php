# cambodia-geo (PHP)

Lightweight, **zero-dependency** library for Cambodia's administrative divisions:
**provinces (ខេត្ត), districts (ស្រុក/ខណ្ឌ), communes (ឃុំ/សង្កាត់) and villages (ភូមិ)**.
Look things up by **code**, **Khmer name** or **English name**, in any mix.
Same behavior as the [Python](https://github.com/bsthen/cambodia-geo-python) and
[JavaScript](https://github.com/bsthen/cambodia-geo-js) packages (see [SPEC.md](SPEC.md)).

Data source: [data.mef.gov.kh](https://data.mef.gov.kh) (bundled dataset year: `Geo::DATA_YEAR`).

```bash
composer require bsthen/cambodia-geo
```

Requires PHP 8.1+ and `ext-mbstring` (`ext-intl` is recommended for Unicode normalisation of queries).
Works with any framework (Laravel, Symfony, WordPress, plain PHP).

## Quick start

```php
<?php

require 'vendor/autoload.php';

use CambodiaGeo\Geo;

Geo::provinces();                                   // all provinces
Geo::districts('Siemreap');                         // districts of a province
Geo::communes('17', 'Angkor Thum');                 // communes of a district
Geo::villages('សៀមរាប', 1702, 'ជប់តាត្រាវ');          // villages of a commune

$p = Geo::getProvince('សៀមរាប');                     // Province: $p->code, $p->nameKh, $p->nameEn
Geo::getDistrict('Angkor Thum', province: 'Siemreap');   // named arguments
Geo::search('angkor', level: 'district');           // search names / codes, best matches first

echo json_encode($p, JSON_UNESCAPED_UNICODE);       // {"code":"17","name_kh":"សៀមរាប","name_en":"Siemreap"}
```

Records can be passed straight back in:

```php
foreach (Geo::provinces() as $province) {
    foreach (Geo::districts($province) as $district) { /* ... */ }
}
```

## API

All methods are static on `CambodiaGeo\Geo`.

| Method | Returns | Not found |
|---|---|---|
| `provinces()` | `list<Province>` | - |
| `districts($province)` | `list<District>` | `[]` |
| `communes($province, $district)` | `list<Commune>` | `[]` |
| `villages($province, $district, $commune)` | `list<Village>` | `[]` |
| `getProvince($query)` | `?Province` | `null` |
| `getDistrict($query, province: null)` | `?District` | `null` |
| `getCommune($query, district: null, province: null)` | `?Commune` | `null` |
| `getVillage($query, commune: null, district: null, province: null)` | `?Village` | `null` |
| `search($query, level: null, limit: 50)` | `list<GeoRecord>` (`limit: null` = no limit) | `[]` |

Also `Geo::DATA_YEAR` (int) and `Geo::version()` (string). `level` is a `CambodiaGeo\Level` enum case or one of
`'province'`, `'district'`, `'commune'`, `'village'`; an unknown level or a negative limit throws
`InvalidArgumentException`.

Records are immutable (`readonly` properties) and extend `GeoRecord`:

| Class | Properties |
|---|---|
| `Province` | `code, nameKh, nameEn` |
| `District` | `code, nameKh, nameEn, provinceCode` |
| `Commune` | `code, nameKh, nameEn, provinceCode, districtCode` |
| `Village` | `code, nameKh, nameEn, provinceCode, districtCode, communeCode` |

Every record has `level(): Level`, `toArray()` (snake_case keys such as `name_kh`) and is `JsonSerializable`
(snake_case). Codes are always **strings** (`'01'`); queries may also be ints.

### Matching rules

- A *query* is a code (`'17'`, `17`, `'017'` and Khmer digits `'១៧'` are equal), a Khmer name or an English name.
  Floats, booleans, arrays and `null` match nothing.
- Names ignore case, spaces and `- ' . ,`: `'Siem Reap'` equals `'siemreap'`.
- With a parent (`province:`, `district:`, ...) the first match inside it is returned.
- **Without** a parent, a name must be unambiguous; if several places share it, `null` is returned instead of
  guessing. Codes are always safe to use alone.

## Performance

Data is stored as PHP arrays, so OPcache (enabled by default on most hosts) keeps it in shared memory. A level is
loaded only when it is used, and indexes are built lazily. Measured on PHP 8.3 CLI **without** OPcache (the worst case):

| Operation (first call in a process) | Time | Memory |
|---|---|---|
| provinces + districts + communes | ~3 ms | ~1 MB |
| villages of a commune (loads 14,576 villages) | ~21 ms | ~7 MB |
| `search()` across all levels (cold) | ~40 ms | ~20 MB |
| any lookup afterwards | microseconds | - |

## Data notes

The bundled MEF files are shipped exactly as published (only re-encoded as PHP arrays). The 2025 dataset contains a few quirks:

- Some village codes are shared by several villages of the same commune (e.g. `08120101`, and the placeholder `0506140-`).
  Listing methods return all of them; `getVillage($code)` without a parent returns `null` for these codes.
- Commune `120913` (Phnom Penh, district `1209`) has villages but is missing from the commune list, so
  `Geo::villages('12', '1209', '120913')` returns `[]`.

## Stability (no surprise breaking changes)

- [Semantic Versioning](https://semver.org/). Public API: the `Geo` class, `Level`, `GeoRecord` and the four record classes.
  Everything in `CambodiaGeo\Internal` is not.
- Within a major version, methods, parameter names and record properties are never removed or renamed; new
  parameters are optional and added at the end, new properties are appended.
- Removals are announced as deprecations and in the changelog first.
- **Data updates** (once a year) ship as a MINOR release. Government reorganisations can change codes or names
  between years: pin the version (`"bsthen/cambodia-geo": "~1.0.0"`) if you store codes long-term, and check `Geo::DATA_YEAR`.

## Data & licence

The code is MIT licensed. The bundled data comes from the Ministry of Economy and Finance
([data.mef.gov.kh](https://data.mef.gov.kh), [terms of use](https://data.mef.gov.kh/terms-of-use)), used with
attribution, and remains subject to its terms (see [NOTICE](NOTICE)). This project is not affiliated with or
endorsed by the Ministry of Economy and Finance.

## ភាសាខ្មែរ (សង្ខេប)

បណ្ណាល័យ PHP ស្រាល គ្មាន dependency សម្រាប់ស្វែងរក ខេត្ត ស្រុក ឃុំ ភូមិ នៃកម្ពុជា តាមលេខកូដ ឈ្មោះខ្មែរ ឬឈ្មោះអង់គ្លេស។
ដំឡើងដោយ `composer require bsthen/cambodia-geo` ហើយប្រើ `CambodiaGeo\Geo::districts('Siemreap')`។
ទិន្នន័យអាប់ដេតរៀងរាល់ឆ្នាំ (មើល `Geo::DATA_YEAR`)។

## Contributing

`composer install && composer test`. See [RELEASING.md](RELEASING.md) and [SPEC.md](SPEC.md).
