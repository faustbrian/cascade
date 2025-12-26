<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $primaryKeyType = config('cascade.primary_key_type', 'id');
        $tableName = config('cascade.tables.resolvers', 'resolvers');

        Schema::create($tableName, function (Blueprint $table) use ($primaryKeyType): void {
            // Primary key based on configuration
            match ($primaryKeyType) {
                'uuid' => $table->uuid('id')->primary(),
                'ulid' => $table->ulid('id')->primary(),
                default => $table->id(),
            };

            $table->string('name')->unique()->index();
            $table->text('description')->nullable();
            $table->json('definition');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            // Composite index for common queries
            $table->index(['is_active', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('cascade.tables.resolvers', 'resolvers');

        Schema::dropIfExists($tableName);
    }
};
