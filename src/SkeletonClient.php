<?php

declare(strict_types=1);

namespace PlinCode\JobBoards\Skeleton;

use PlinCode\JobBoards\Contracts\JobBoardClient;
use PlinCode\JobBoards\Data\JobPostingDTO;
use PlinCode\JobBoards\Exceptions\InvalidResponseException;
use PlinCode\JobBoards\Exceptions\TransportException;
use PlinCode\JobBoards\Http\HttpClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * A working placeholder connector. It talks to a fictional provider that
 * answers with the shape below, and exists to show where a real connector puts
 * each piece:
 *
 *   { "name": "Acme", "about": "...", "openings": [ { "id": ..., ... } ] }
 *
 * Nothing in this class knows about Laravel. It is handed core's HttpClient and
 * a PSR-3 logger, both of which a Symfony or plain PHP consumer can build by
 * hand. {@see SkeletonServiceProvider} is the only Laravel aware file here.
 */
final class SkeletonClient implements JobBoardClient
{
    /**
     * The provider root. Every endpoint this connector calls hangs off it.
     */
    public const string API_BASE_URL = 'https://api.example.com/v1/boards';

    /**
     * Listing a whole board can be slow, so it gets a longer budget than the
     * cheap single-record lookups below.
     */
    public const float TIMEOUT_SECONDS = 30.0;

    public const float LOOKUP_TIMEOUT_SECONDS = 15.0;

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly HttpClient $http,
        private readonly string $baseUrl = self::API_BASE_URL,
        private readonly float $timeout = self::TIMEOUT_SECONDS,
        private readonly float $lookupTimeout = self::LOOKUP_TIMEOUT_SECONDS,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger;
    }

    /**
     * The contract for every connector: never throw at the caller, log what went
     * wrong and return an empty list, so one broken company does not abort a
     * sync over hundreds of them.
     *
     * @return list<JobPostingDTO>
     */
    public function fetchJobsForCompany(string $slug): array
    {
        try {
            // get() rather than tryGet() so the transport error message survives
            // into the log. tryGet() would flatten it to a null.
            $response = $this->http->withTimeout($this->timeout)->get($this->endpoint($slug));

            if ($response->failed()) {
                $this->logger->warning('Skeleton API request failed', [
                    'company_slug' => $slug,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $openings = $response->json('openings');

            if (! is_array($openings)) {
                $this->logger->warning('Skeleton API response missing openings array', [
                    'company_slug' => $slug,
                    'response' => $response->json(),
                ]);

                return [];
            }

            $jobs = [];

            foreach ($openings as $opening) {
                if (! is_array($opening)) {
                    throw InvalidResponseException::unexpectedShape(
                        $response->url(),
                        'openings.*',
                        get_debug_type($opening),
                    );
                }

                /** @var array<string, mixed> $opening */
                $jobs[] = $this->mapToDTO($slug, $opening);
            }

            return $jobs;
        } catch (TransportException $e) {
            $this->logger->error('Skeleton API connection error', [
                'company_slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return [];
        } catch (Throwable $e) {
            $this->logger->error('Unexpected error fetching Skeleton jobs', [
                'company_slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Returns the company name when the slug resolves, null otherwise. Callers
     * use this to validate user input, so a transport failure and a 404 are
     * deliberately indistinguishable: neither proves the slug is good.
     */
    public function validateSlug(string $slug): ?string
    {
        return $this->lookup($slug, 'name');
    }

    public function fetchCompanyDescription(string $slug): ?string
    {
        return $this->lookup($slug, 'about');
    }

    /**
     * Read one non empty string field out of the account payload, or null if
     * anything at all goes wrong.
     */
    private function lookup(string $slug, string $key): ?string
    {
        try {
            // tryGet() here: these two are silent by contract, so there is no
            // message to keep.
            $response = $this->http->withTimeout($this->lookupTimeout)->tryGet($this->endpoint($slug));

            if ($response === null || $response->failed()) {
                return null;
            }

            $value = $response->json($key);

            return is_string($value) && $value !== '' ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function endpoint(string $slug): string
    {
        return sprintf('%s/%s', rtrim($this->baseUrl, '/'), rawurlencode($slug));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mapToDTO(string $slug, array $data): JobPostingDTO
    {
        $id = $data['id'] ?? null;
        $title = $data['title'] ?? null;
        $location = $data['location'] ?? null;
        $url = $data['url'] ?? null;
        $department = $data['department'] ?? null;

        return new JobPostingDTO(
            externalId: is_scalar($id) ? (string) $id : '',
            title: is_string($title) && $title !== '' ? $title : 'Untitled Position',
            location: is_string($location) && $location !== '' ? $location : null,
            url: is_string($url) ? $url : sprintf('%s/%s', self::API_BASE_URL, rawurlencode($slug)),
            department: is_string($department) && $department !== '' ? $department : null,
            rawPayload: $data,
        );
    }
}
