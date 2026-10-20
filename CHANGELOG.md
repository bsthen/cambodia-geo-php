# Changelog

All notable changes are documented here. The project follows [Semantic Versioning](https://semver.org/):
the **code/API** drives MAJOR.MINOR.PATCH, and every yearly data refresh is a **MINOR** release.
The dataset year is available at runtime as `Geo::DATA_YEAR`.

## [1.0.0] - 2026-10-20
### Added
- First stable release. Bundled MEF dataset: **2025**.
- `Geo::provinces()`, `districts()`, `communes()`, `villages()` list methods.
- `Geo::getProvince()`, `getDistrict()`, `getCommune()`, `getVillage()` single lookups.
- `Geo::search()` across all levels.
- Lookups accept codes, Khmer names or English names (case / space insensitive).
- Immutable `Province`, `District`, `Commune`, `Village` records with `toArray()` (snake_case) and `json_encode` support.
- Behaviour defined by `SPEC.md`, identical to the Python and JavaScript packages (verified by shared conformance tests).
- No runtime dependencies (only `ext-mbstring`). PHP 8.1+.
