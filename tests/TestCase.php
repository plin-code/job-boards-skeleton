<?php

declare(strict_types=1);

namespace PlinCode\JobBoards\Skeleton\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use PlinCode\JobBoards\Skeleton\SkeletonServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [SkeletonServiceProvider::class];
    }
}
