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
use Orchestra\Testbench\TestCase as BaseTestCase;
use Override;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    #[Override()]
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
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
