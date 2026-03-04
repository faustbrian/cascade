<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Cascade\Database\Models\Resolver;
use Cline\VariableKeys\Enums\PrimaryKeyType;
use Cline\VariableKeys\Facades\VariableKeys;
use Cline\VariableKeys\VariableKeysServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        VariableKeys::map([
            Resolver::class => [
                'primary_key_type' => PrimaryKeyType::ID,
            ],
        ]);
    }

    /**
     * Get package providers.
     *
     * @param  mixed                    $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            VariableKeysServiceProvider::class,
        ];
    }

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
