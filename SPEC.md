# cambodia-geo specification

All implementations of cambodia-geo (Python, JavaScript, PHP, ...) must behave identically.
The reference implementation is the Python package. `spec/conformance.json` contains expected
results generated from it; every implementation runs those cases in its test suite.

## Data

- Source: the four MEF CSV files (`CambodiaProvinceList<year>.csv`, `CambodiaDistrictList<year>.csv`,
  `CambodiaCommuneList<year>.csv`, `CambodiaVillagesList<year>.csv`), shipped unmodified.
- Required columns: `<level>_code`, `<level>_kh`, `<level>_en` and the ancestor codes
  (`province_code`, `district_code`, `commune_code`) of the levels above. Extra columns are ignored.
- Row order of the CSV is the data order. Rows with an empty code are skipped. Cells are trimmed.
- Codes are strings and keep their leading zeros (`"01"`).

## Records

| Level | Fields (canonical snake_case, in this order) |
|---|---|
| province | `code`, `name_kh`, `name_en` |
| district | `code`, `name_kh`, `name_en`, `province_code` |
| commune | `code`, `name_kh`, `name_en`, `province_code`, `district_code` |
| village | `code`, `name_kh`, `name_en`, `province_code`, `district_code`, `commune_code` |

Records are immutable. Languages may use their own naming convention (JavaScript: `nameKh`,
`provinceCode`, ...) and must offer a way to get the canonical snake_case form (`to_dict()` / `toDict()`).
New fields may only be appended; none may be removed or renamed within a major version.

## Functions

| Canonical name | Arguments | Returns | Not found |
|---|---|---|---|
| `provinces` | | all provinces | |
| `districts` | `province` | districts of the province | `[]` |
| `communes` | `province`, `district` | communes of the district | `[]` |
| `villages` | `province`, `district`, `commune` | villages of the commune | `[]` |
| `get_province` | `query` | one province | null |
| `get_district` | `query`, optional `province` | one district | null |
| `get_commune` | `query`, optional `district`, `province` | one commune | null |
| `get_village` | `query`, optional `commune`, `district`, `province` | one village | null |
| `search` | `query`, optional `level`, `limit` (default 50, null = unlimited) | list of records | `[]` |

Plus the constants `DATA_YEAR` (integer) and the package version. Lists are returned in data order and
every call returns a fresh list. Language idiom applies to names (`get_province` / `getProvince`).

## Queries

A *query* is a code, a Khmer name, an English name, or a record returned by the library
(a record of the right level is used as is; a record of another level matches nothing).
A query given as a number must be a non-negative integer; any other type (float, boolean, array, object of another class, null) matches nothing (null / `[]`).

**Normalisation of names** (both the query and the data): Unicode NFC, remove all whitespace, remove
the characters U+200B, U+200C, U+200D, U+2060, U+FEFF and `- ' ’ . ,`, then lowercase.
An empty result after normalisation matches nothing.

**Normalisation of codes**: trim; if it consists only of ASCII or Khmer digits (max 32 characters),
convert to ASCII digits and remove leading zeros (`"01"`, `"1"`, `1`, `"១"`, `"០១"` are the same code;
`"0"` stays `"0"`); otherwise use the name normalisation.

## Resolution

- **Without a parent** (`get_province`, or `get_district("1702")` with no `province`): look up the code
  among all records of the level; if there are hits, return the single hit, or null if there are several
  (ambiguous). If the code has no hit, do the same with the normalised name (Khmer or English).
- **With a parent**: search only the records below it, in data order, and return the first record whose
  code, Khmer name or English name matches. Ancestors may be skipped (`get_commune(q, province=...)`).
- Each given ancestor is resolved first (province, then district inside it, then commune inside it).
  If a given ancestor is not found the result is null / `[]`. An ancestor equal to null/undefined is ignored.
- `districts`, `communes`, `villages` resolve their arguments strictly top-down and return the
  children of the last one, in data order.

## Search

Normalise the query like a name and like a code. For every record (levels in order province, district,
commune, village; data order inside a level) compute a rank:
0 if the code matches or the Khmer/English name equals the query; 1 if a name starts with the query;
2 if a name contains the query; otherwise skip it. Sort by (rank, level, data position) and apply `limit`.
`level` restricts the search to one level. An invalid `level` or a negative `limit` is an error
(`ValueError` / `RangeError`).

## Language bindings

The behaviour is identical; only the idiom differs.

| Canonical | Python | JavaScript | PHP |
|---|---|---|---|
| package | `cambodia-geo` (PyPI) | `cambodia-geo` (npm) | `bsthen/cambodia-geo` (Packagist) |
| entry point | module `cambodia_geo` | `import ... from 'cambodia-geo'` | static methods of `CambodiaGeo\Geo` |
| `provinces` | `provinces()` | `provinces()` | `Geo::provinces()` |
| `districts` | `districts(province)` | `districts(province)` | `Geo::districts($province)` |
| `communes` | `communes(province, district)` | `communes(province, district)` | `Geo::communes($province, $district)` |
| `villages` | `villages(province, district, commune)` | `villages(province, district, commune)` | `Geo::villages($province, $district, $commune)` |
| `get_province` | `get_province(query)` | `getProvince(query)` | `Geo::getProvince($query)` |
| `get_district` | `get_district(query, province=)` | `getDistrict(query, { province })` | `Geo::getDistrict($query, province: )` |
| `get_commune` | `get_commune(query, district=, province=)` | `getCommune(query, { district, province })` | `Geo::getCommune($query, district: , province: )` |
| `get_village` | `get_village(query, commune=, district=, province=)` | `getVillage(query, { commune, district, province })` | `Geo::getVillage($query, commune: , district: , province: )` |
| `search` | `search(query, level=, limit=)` | `search(query, { level, limit })` | `Geo::search($query, level: , limit: )` |
| not found | `None` | `null` | `null` |
| record fields | `name_kh`, `province_code` | `nameKh`, `provinceCode` | `$nameKh`, `$provinceCode` |
| canonical form | `record.to_dict()` | `toDict(record)` | `$record->toArray()` (also `json_encode`) |
| data year | `DATA_YEAR` | `DATA_YEAR` | `Geo::DATA_YEAR` |
| package version | `__version__` | `VERSION` | `Geo::version()` |
| invalid `level` / negative `limit` | `ValueError` | `RangeError` | `InvalidArgumentException` |
| `limit` default / unlimited | `50` / `None` | `50` / `null` | `50` / `null` |

## Known quirks of the MEF 2025 data (reproduced as published, do not "fix" them)

- Several village codes are shared by multiple villages of the same commune (e.g. `08120101`, and the
  placeholder `0506140-`). Lists return all of them; unscoped `get_village(code)` returns null.
- Commune `120913` has villages but is missing from the commune list, so `villages("12","1209","120913")`
  returns `[]`.

## Conformance file

`spec/conformance.json`: `{ "data_year", "reference", "cases": [ { "fn", "args", "kwargs", ... } ] }`
where each case has either `"expect"` (null, a record, or a list of records in canonical snake_case;
`search` results also carry `"level"`), `"expect_len"` (only the length is compared) or
`"raises": "invalid_argument"`. Regenerate it with `spec/generate_conformance.py` after every data update.
