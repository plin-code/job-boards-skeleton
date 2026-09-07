<?php

declare(strict_types=1);

use PlinCode\JobBoards\Data\JobPostingDTO;
use PlinCode\JobBoards\Skeleton\SkeletonClient;
use PlinCode\JobBoards\Testing\FakePsrClient;
use PlinCode\JobBoards\Testing\RecordingLogger;

function skeletonClient(FakePsrClient $fake, ?RecordingLogger $logger = null): SkeletonClient
{
    return new SkeletonClient($fake->asHttpClient(), logger: $logger);
}

it('fetches jobs for a valid company slug', function (): void {
    $fake = (new FakePsrClient)->respondWithJson([
        'openings' => [
            [
                'id' => 'ABC123',
                'title' => 'Backend Engineer',
                'location' => 'Paris, France',
                'department' => 'Engineering',
                'url' => 'https://api.example.com/j/ABC123',
            ],
        ],
    ]);

    $jobs = skeletonClient($fake)->fetchJobsForCompany('testco');

    expect($jobs)->toHaveCount(1)
        ->and($jobs[0])->toBeInstanceOf(JobPostingDTO::class)
        ->and($jobs[0]->externalId)->toBe('ABC123')
        ->and($jobs[0]->title)->toBe('Backend Engineer')
        ->and($jobs[0]->location)->toBe('Paris, France')
        ->and($jobs[0]->url)->toBe('https://api.example.com/j/ABC123')
        ->and($jobs[0]->department)->toBe('Engineering')
        ->and($fake->lastUri())->toBe('https://api.example.com/v1/boards/testco');
});

it('returns an empty list for an empty openings array', function (): void {
    $fake = (new FakePsrClient)->respondWithJson(['openings' => []]);

    expect(skeletonClient($fake)->fetchJobsForCompany('testco'))->toBe([]);
});

it('logs a warning and returns an empty list on a failed response', function (): void {
    $fake = (new FakePsrClient)->respondWith(500, 'Server Error');
    $logger = new RecordingLogger;

    expect(skeletonClient($fake, $logger)->fetchJobsForCompany('broken'))->toBe([])
        ->and($logger->messages())->toBe(['Skeleton API request failed'])
        ->and($logger->levels())->toBe(['warning']);
});

it('logs an error and returns an empty list on a transport failure', function (): void {
    $fake = (new FakePsrClient)->throwNetworkError();
    $logger = new RecordingLogger;

    expect(skeletonClient($fake, $logger)->fetchJobsForCompany('timeout'))->toBe([])
        ->and($logger->messages())->toBe(['Skeleton API connection error']);
});

it('logs a warning and returns an empty list when the openings key is missing', function (): void {
    $fake = (new FakePsrClient)->respondWithJson(['error' => 'not found']);
    $logger = new RecordingLogger;

    expect(skeletonClient($fake, $logger)->fetchJobsForCompany('invalid'))->toBe([])
        ->and($logger->messages())->toBe(['Skeleton API response missing openings array']);
});

it('falls back to a placeholder title and the account url', function (): void {
    $fake = (new FakePsrClient)->respondWithJson(['openings' => [['id' => 7]]]);

    $jobs = skeletonClient($fake)->fetchJobsForCompany('testco');

    expect($jobs[0]->externalId)->toBe('7')
        ->and($jobs[0]->title)->toBe('Untitled Position')
        ->and($jobs[0]->location)->toBeNull()
        ->and($jobs[0]->department)->toBeNull()
        ->and($jobs[0]->url)->toBe('https://api.example.com/v1/boards/testco')
        ->and($jobs[0]->rawPayload)->toBe(['id' => 7]);
});

it('never throws at the caller when the body is not json', function (): void {
    $fake = (new FakePsrClient)->respondWith(200, '<html>nope</html>');
    $logger = new RecordingLogger;

    expect(skeletonClient($fake, $logger)->fetchJobsForCompany('testco'))->toBe([])
        ->and($logger->messages())->toBe(['Unexpected error fetching Skeleton jobs']);
});

it('asks for the long timeout when listing and the short one when looking up', function (): void {
    $fake = (new FakePsrClient)
        ->respondWithJson(['openings' => []])
        ->respondWithJson(['name' => 'TestCo']);

    $client = skeletonClient($fake);
    $client->fetchJobsForCompany('testco');
    $client->validateSlug('testco');

    expect($fake->appliedTimeouts)->toBe([30.0, 15.0]);
});

it('percent encodes the slug in the url', function (): void {
    $fake = (new FakePsrClient)->respondWithJson(['openings' => []]);

    skeletonClient($fake)->fetchJobsForCompany('a b/../c');

    expect($fake->lastUri())->toBe('https://api.example.com/v1/boards/a%20b%2F..%2Fc');
});

it('validates a slug and returns the company name', function (): void {
    $fake = (new FakePsrClient)->respondWithJson(['name' => 'TestCo Inc', 'openings' => []]);

    expect(skeletonClient($fake)->validateSlug('testco'))->toBe('TestCo Inc');
});

it('returns null from validateSlug on a 404, a transport failure or a missing name', function (): void {
    expect(skeletonClient((new FakePsrClient)->respondWith(404, 'Not Found'))->validateSlug('nope'))->toBeNull()
        ->and(skeletonClient((new FakePsrClient)->throwNetworkError())->validateSlug('timeout'))->toBeNull()
        ->and(skeletonClient((new FakePsrClient)->respondWithJson(['openings' => []]))->validateSlug('testco'))->toBeNull()
        ->and(skeletonClient((new FakePsrClient)->respondWithJson(['name' => '']))->validateSlug('testco'))->toBeNull()
        ->and(skeletonClient((new FakePsrClient)->respondWith(200, 'not json'))->validateSlug('testco'))->toBeNull();
});

it('fetches the company description and returns null when it is absent', function (): void {
    expect(skeletonClient((new FakePsrClient)->respondWithJson(['about' => 'We build things']))->fetchCompanyDescription('testco'))
        ->toBe('We build things')
        ->and(skeletonClient((new FakePsrClient)->respondWithJson(['about' => '']))->fetchCompanyDescription('testco'))->toBeNull()
        ->and(skeletonClient((new FakePsrClient)->respondWithJson(['name' => 'TestCo']))->fetchCompanyDescription('testco'))->toBeNull();
});

it('is safe with no logger at all', function (): void {
    $fake = (new FakePsrClient)->throwNetworkError();

    expect((new SkeletonClient($fake->asHttpClient()))->fetchJobsForCompany('testco'))->toBe([]);
});
