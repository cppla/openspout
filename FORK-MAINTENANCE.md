# OpenSpout maintenance fork

Version 6.0.0 requires PHP 8.6 or later in the 8.x series. This fork publishes standard semantic versions from `main` and keeps the Composer package name `openspout/openspout`. Version 5.12.2-patch.1 remains available to projects using PHP 8.3–8.5.

The library is based on upstream [v5.12.2](https://github.com/openspout/openspout/releases/tag/v5.12.2), commit `0fe609da95101d537411f4a978345f2f882eb935`. It retains the existing MIT and historical Apache-2.0 notices. The fork detects libxml failures from `XMLReader::expand()` while reading spreadsheets.

## Install

Configure this repository in the consuming project's root Composer configuration and require the release.

```sh
composer config repositories.cppla-openspout vcs https://github.com/cppla/openspout
composer require 'openspout/openspout:^6.0'
```

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

OpenSpout is not a namespace-only replacement for Box Spout 3 or Dcat EasyExcel.
Use a dedicated exporter that retains Dcat's chunked all/current/selected row scopes.
For card secrets and other untrusted strings, explicitly construct `StringCell` rather than
using `Cell::fromValue` or `Row::fromValues`, which can infer formulas.
Reuse immutable `Style` instances. In particular, supply one shared `new Style(shouldWrapText: true)`
for body text: constructing default multiline StringCells repeatedly creates distinct wrapping
styles, and the style registry deduplicates object identities rather than equal style values.
The included streaming check demonstrates this supported integration pattern.

## Maintenance

Run the PHP 8.6 suite, the parser regression, the streaming checks, and the consuming application's export tests for releases. The development image includes Xdebug for coverage. Coverage is enabled by the Makefile for PHPUnit and Infection.
