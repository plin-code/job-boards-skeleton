<?php

declare(strict_types=1);

use PlinCode\JobBoards\Skeleton\SkeletonClient;

return [

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    |
    | The provider root every endpoint hangs off. Override it to point the
    | connector at a sandbox or a recorded fixture server.
    |
    */

    'base_url' => env('JOB_BOARDS_SKELETON_BASE_URL', SkeletonClient::API_BASE_URL),

    /*
    |--------------------------------------------------------------------------
    | Timeouts
    |--------------------------------------------------------------------------
    |
    | Seconds. "timeout" covers listing a whole board, "lookup_timeout" the
    | cheaper single-record calls behind validateSlug() and
    | fetchCompanyDescription(). Honoured only by PSR-18 clients that implement
    | PlinCode\JobBoards\Http\SupportsTimeout; other clients keep the timeout
    | they were built with.
    |
    */

    'timeout' => env('JOB_BOARDS_SKELETON_TIMEOUT', 30),

    'lookup_timeout' => env('JOB_BOARDS_SKELETON_LOOKUP_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Request Headers
    |--------------------------------------------------------------------------
    |
    | Sent with every request. Add an Authorization header here for providers
    | that need one.
    |
    */

    'headers' => [
        'Accept' => 'application/json',
    ],

];
