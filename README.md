# Job Boards Skeleton

Template for a [plin-code](https://github.com/plin-code) job board connector. Clone it, run the renaming checklist below, and you have a package that speaks the same contract as every other connector in the family.

It is built on [`plin-code/job-boards-core`](https://github.com/plin-code/job-boards-core), which supplies the `JobBoardClient` contract, the `JobPostingDTO` and a small PSR-18 wrapper. This package supplies the two files core deliberately does not: a concrete client and a Laravel service provider.

## What is in the box

```
src/
├── SkeletonClient.php            implements JobBoardClient, framework agnostic
└── SkeletonServiceProvider.php   the only Laravel aware file
config/job-boards-skeleton.php
tests/
├── Support/                      PSR-18 test doubles (FakePsrClient, RecordingLogger)
├── Unit/                         the client, no framework booted
└── Feature/                      the service provider, under Testbench
```

`SkeletonClient` is a working placeholder, not pseudo code. It talks to a fictional provider whose account endpoint answers with

```json
{ "name": "Acme", "about": "We build things", "openings": [ { "id": "ABC123", "title": "Backend Engineer" } ] }
```

and its tests pass against a faked PSR-18 client. Point it at a real provider, rename the payload keys, and it works.

## Framework agnostic on purpose

`SkeletonClient` takes core's `HttpClient` and an optional PSR-3 logger. It imports nothing from Laravel. A Symfony or plain PHP consumer builds it directly:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use PlinCode\JobBoards\Http\HttpClient;
use PlinCode\JobBoards\Skeleton\SkeletonClient;

$http = new HttpClient(new Client, new HttpFactory);

$client = new SkeletonClient($http);

$jobs = $client->fetchJobsForCompany('acme');       // list<JobPostingDTO>
$name = $client->validateSlug('acme');              // ?string
$about = $client->fetchCompanyDescription('acme');  // ?string
```

`SkeletonServiceProvider` exists only to do that same wiring out of the container. Keep it that way: if you find yourself reaching for a facade, a helper or `config()` inside the client, move it to the provider instead.

## Laravel usage

```bash
composer require plin-code/job-boards-skeleton
```

The provider is auto discovered. Resolve the client and go:

```php
$client = app(\PlinCode\JobBoards\Skeleton\SkeletonClient::class);
```

Publish the config if you need to change the base URL, the timeouts or the request headers:

```bash
php artisan vendor:publish --tag=job-boards-skeleton-config
```

The provider binds a PSR-18 client and a PSR-17 factory with `bindIf`, so an application that already binds its own keeps it. It deliberately does **not** bind `JobBoardClient` itself: several connectors implement that interface and would fight over the binding. Bind the one you want in your own application service provider.

### Error handling

`fetchJobsForCompany()` never throws at the caller. A failed status, a missing payload key or a dead connection is logged through the injected PSR-3 logger and returns an empty list, so one broken company cannot abort a sync over hundreds of them. When no logger is passed, a `NullLogger` is used and everything is silent.

`validateSlug()` and `fetchCompanyDescription()` return `null` for every failure, including transport failures. That is intentional: neither a 404 nor a dropped connection proves a slug is good.

### Timeouts

PSR-18 has no notion of a timeout, so core's `HttpClient::withTimeout()` is only honoured by clients implementing `PlinCode\JobBoards\Http\SupportsTimeout`. Guzzle's PSR-18 client does not, so the configured timeout is a request the transport may ignore. If timeouts matter to you, build the Guzzle client with `['timeout' => 30]` and bind it yourself, or wrap it in a small `SupportsTimeout` adapter.

## Renaming checklist

Everything below is mechanical. `Skeleton` becomes your provider name in PascalCase (`Greenhouse`), `skeleton` its kebab-case form (`greenhouse`).

1. **Directory**: rename the clone to `job-boards-greenhouse`.
2. **Namespace**: `PlinCode\JobBoards\Skeleton\` becomes `PlinCode\JobBoards\Greenhouse\` in `src/*.php`, in `composer.json` under `autoload.psr-4`, and in `extra.laravel.providers`.
3. **Test namespace**: `PlinCode\JobBoards\Skeleton\Tests\` becomes `PlinCode\JobBoards\Greenhouse\Tests\` in `composer.json` under `autoload-dev.psr-4` and in every file under `tests/`.
4. **Class names**: `SkeletonClient` becomes `GreenhouseClient`, `SkeletonServiceProvider` becomes `GreenhouseServiceProvider`. Rename the files to match.
5. **Config file**: rename `config/job-boards-skeleton.php` to `config/job-boards-greenhouse.php`. Update `->hasConfigFile('job-boards-greenhouse')` and `->name('job-boards-greenhouse')` in the provider, the `config('job-boards-greenhouse...')` key it reads, and the `JOB_BOARDS_SKELETON_*` env var names inside the file.
6. **Composer name**: `plin-code/job-boards-skeleton` becomes `plin-code/job-boards-greenhouse`. Update `description`, `keywords` and `homepage` too.
7. **Provider registration**: `extra.laravel.providers` becomes `["PlinCode\\JobBoards\\Greenhouse\\GreenhouseServiceProvider"]`.
8. **Client body**: replace `API_BASE_URL`, the two timeout constants, the payload keys in `fetchJobsForCompany()` and `lookup()`, and the field mapping in `mapToDTO()`. Update the log messages, which name the provider.
9. **Tests**: rename `tests/Unit/SkeletonClientTest.php` and `tests/Feature/SkeletonServiceProviderTest.php`, and update the fixtures to the real payload shape.
10. **README**: this file. Replace the fictional payload, keep the Framework agnostic and Error handling sections.

A `sed` pass covers steps 2, 3, 4, 6 and 7 in one go:

```bash
grep -rl 'Skeleton\|skeleton\|SKELETON' --exclude-dir=vendor --exclude-dir=build . \
  | xargs sed -i '' -e 's/Skeleton/Greenhouse/g' -e 's/skeleton/greenhouse/g' -e 's/SKELETON/GREENHOUSE/g'
git mv src/SkeletonClient.php src/GreenhouseClient.php
git mv src/SkeletonServiceProvider.php src/GreenhouseServiceProvider.php
git mv config/job-boards-skeleton.php config/job-boards-greenhouse.php
git mv tests/Unit/SkeletonClientTest.php tests/Unit/GreenhouseClientTest.php
git mv tests/Feature/SkeletonServiceProviderTest.php tests/Feature/GreenhouseServiceProviderTest.php
```

All three cases matter: `SKELETON` on its own catches the `JOB_BOARDS_SKELETON_*` env var names in the config file, which the other two miss.

Steps 8, 9 and 10 still need eyes on them.

## Depending on core

During local development this package resolves core through a path repository:

```json
"repositories": [
    { "type": "path", "url": "../job-boards-core", "options": { "symlink": true } }
]
```

That block and the `"plin-code/job-boards-core": "*"` constraint are **for local development only**. Once core is published, drop the `repositories` block and pin the real constraint:

```json
"require": {
    "plin-code/job-boards-core": "^0.1"
}
```

## Development

```bash
composer install
composer lint          # pint, writes
composer lint:check    # pint, read only
composer analyse       # phpstan level 10, matching core
composer test:unit     # pest
composer test          # analyse + lint:check + test:unit
```

Note that `tests/Support/` carries its own PSR-18 doubles. Core keeps its equivalents under its own `autoload-dev`, so they are not reachable from a dependent package.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
