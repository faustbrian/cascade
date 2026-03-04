<?php

declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Primary Key Type
    |--------------------------------------------------------------------------
    |
    | Determines the type of primary key used for all Cascade models.
    | Supported values: 'id' (auto-increment), 'uuid', 'ulid'
    |
    */

    'primary_key_type' => env('CASCADE_PRIMARY_KEY_TYPE', 'id'),

    /*
    |--------------------------------------------------------------------------
    | Database Usage
    |--------------------------------------------------------------------------
    |
    | Enable or disable database storage for resolvers. When false, only
    | file-based repositories (JSON, YAML, Array) will be available.
    |
    */

    'use_database' => env('CASCADE_USE_DATABASE', false),

    /*
    |--------------------------------------------------------------------------
    | Model Customization
    |--------------------------------------------------------------------------
    |
    | Override default Eloquent models with your own implementations.
    | Set to null to use the default models.
    |
    */

    'models' => [
        'resolver' => env('CASCADE_RESOLVER_MODEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Customize table names for Cascade models. This allows you to integrate
    | Cascade into existing database schemas without conflicts.
    |
    */

    'tables' => [
        'resolvers' => env('CASCADE_RESOLVERS_TABLE', 'resolvers'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Morph Key Map
    |--------------------------------------------------------------------------
    |
    | Define primary key types for polymorphic relationships. Maps model classes
    | to their primary key types (id, uuid, ulid) when different from the default.
    |
    | Example:
    | 'App\Models\User' => 'uuid',
    | 'App\Models\Team' => 'ulid',
    |
    */

    'morphKeyMap' => [
        // Add your model-to-key-type mappings here
    ],

    /*
    |--------------------------------------------------------------------------
    | Enforce Morph Key Map
    |--------------------------------------------------------------------------
    |
    | List of relationships that must use the morphKeyMap for primary key types.
    | Prevents automatic primary key type detection for these relationships.
    |
    | Example:
    | 'resolvable',
    | 'contextable',
    |
    */

    'enforceMorphKeyMap' => [
        // Add relationship names here
    ],
];
