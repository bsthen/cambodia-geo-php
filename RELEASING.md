# Releasing & maintenance

Composer has no "publish" step: **Packagist reads the Git tags of this repository**. The workflow
`.github/workflows/release.yml` creates the tag `v<version>` (and a GitHub Release) whenever the number in the
`VERSION` file is new, and Packagist picks the tag up through its webhook.

## One-time setup

1. Create the GitHub repository `bsthen/cambodia-geo-php` (empty, no README) and push this project to `main`.
   The first workflow run tests the code and creates the tag `v1.0.0` and the GitHub Release.
2. Sign in at https://packagist.org with your GitHub account, click **Submit**, enter
   `https://github.com/bsthen/cambodia-geo-php` and submit. The package name comes from `composer.json`
   (`bsthen/cambodia-geo`). Versions come from the Git tags, never from a `version` field in `composer.json`.
3. Enable automatic updates so that every new tag is picked up: Packagist -> *Profile* -> *Settings* ->
   **Connect GitHub** (or add the Packagist webhook under the repository's *Settings -> Webhooks*).
   No token or secret is needed in GitHub Actions.
4. Check https://packagist.org/packages/bsthen/cambodia-geo shows version `1.0.0`.

## Every release (code or data)

1. Edit the number in `VERSION` and add a section `## [x.y.z] - date` to `CHANGELOG.md`.
2. Commit and push to `main`. The **Release** workflow checks the data files, runs the tests, then creates the tag
   `vx.y.z` and the GitHub Release. If `VERSION` did not change, nothing is released.

## Yearly data refresh (the only routine task)

1. Download the new CSVs and put them in `data/` with their original names (e.g. `CambodiaProvinceList2026.csv`).
   Delete the old-year files. The year is detected from the file names.
2. `composer build-data` regenerates `src/Data/*.php` and `src/Meta.php` (commit them: Composer installs the repository
   as it is, so the generated files must be in Git). Then `composer test`. `tests/DataTest.php` catches duplicate
   codes and orphaned rows; if it fails because of a *new* upstream quirk, review it and update the known-quirk lists
   in that test, never the CSVs.
3. Regenerate the expected results: `pip install -U cambodia-geo` (the Python package must already carry the new year)
   then `python spec/generate_conformance.py`, and commit `spec/conformance.json`.
4. Bump the **MINOR** version in `VERSION` (e.g. `1.0.0` -> `1.1.0`), update `CHANGELOG.md`, push to `main`.

## Rules that keep users safe from breaking changes

- Never remove or rename a public method, parameter name or record property without a MAJOR bump.
  `tests/PublicApiTest.php` fails if you do it by accident.
- New parameters must be optional and go at the end; new record properties are appended.
- Behaviour changes must first be made in the Python package and `SPEC.md`, then ported.
- Never move or delete a published tag. Fix forward with a PATCH release.
- Dependabot keeps GitHub Actions and dev dependencies up to date. The package has no runtime dependencies.
