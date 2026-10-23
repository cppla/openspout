# OpenSpout PHP 8.3 maintenance fork

This fork retains the `openspout/openspout` Composer package name and preserves the original MIT and Apache-2.0 historical-code license notices.
It is based on official [v5.12.2](https://github.com/openspout/openspout/releases/tag/v5.12.2),
commit `0fe609da95101d537411f4a978345f2f882eb935`. Upstream source and copyright notices remain intact.
The intended maintenance release is `5.12.2-patch.1`, from branch `codex/php83-compat`.
No upstream tag is moved or replaced. This is a fork release, not an upstream release.

## Scope

- Extend the runtime Composer constraint to PHP 8.3. The upstream release requires PHP 8.4 or newer; this fork maintains and tests the additional runtime.
- Catch libxml errors from `XMLReader::expand()` before a later read clears them. With libxml 2.13.9, the upstream ODS quadratic-entity fixture otherwise leaves the row iterator returning empty rows indefinitely. Rejecting the malformed document prevents callers that collect rows from exhausting memory. This error handling applies to PHP 8.3 and newer; it is not a PHP 8.3-only syntax backport.
- Run the suite with maintained PHPUnit 11.5, adapting two test stub builders without changing production behavior.
- Permit the existing quadratic-entity tests to accept an explicit amplification rejection from newer libxml while retaining their original duration and memory bounds.
- Add an independent PHP 8.3/8.4/8.5 CI workflow and a bounded-memory streaming text round trip.

## Checks

```sh
composer install --prefer-dist --no-interaction
composer validate --strict
composer check-platform-reqs
vendor/bin/phpunit --no-coverage --no-logging
php -d memory_limit=64M tests/Compatibility/streaming.php
```

Run tests as an ordinary user: the upstream suite includes deliberately unreadable-file fixtures.
The streaming check writes and reads 100,000 XLSX rows and 2,000 rows each of ODS and CSV,
including Unicode, multiline values, leading zeroes, long numeric strings, and formula-like text.
It verifies that XLSX/ODS contain no formula elements/attributes and keeps PHP peak allocations
below 32 MiB under a 64 MiB PHP limit. Generated files are confined to its own temporary directory.
CSV round trips check literal preservation only: CSV has no cell typing and is not claimed safe
for opening untrusted formula-like data in a spreadsheet application.

## Integration requirements

OpenSpout 5 is not a namespace-only replacement for Box Spout 3 or Dcat EasyExcel.
Use a dedicated exporter that retains Dcat's chunked all/current/selected row scopes.
For card secrets and other untrusted strings, explicitly construct `StringCell` rather than
using `Cell::fromValue` or `Row::fromValues`, which can infer formulas.
Reuse immutable `Style` instances. In particular, supply one shared `new Style(shouldWrapText: true)`
for body text: constructing default multiline StringCells repeatedly creates distinct wrapping
styles, and the style registry deduplicates object identities rather than equal style values.
The included streaming check demonstrates this supported integration pattern.

## Maintenance

Track upstream changes and advisories; rebase or cherry-pick reviewed fixes and rerun all three
CI runtimes plus consuming-application export tests before each maintenance tag. Do not remove
parser-error or streaming checks to accommodate a failed upgrade. Newer upstream minor releases
may intentionally raise the PHP floor; assess each version before extending this fork.
The local pre-release check passed on PHP 8.3.36/libxml 2.13.9. The parser-error regression also
passed on PHP 8.5.12/libxml 2.13.9. Hosted CI and consuming-application testing are separate gates.
