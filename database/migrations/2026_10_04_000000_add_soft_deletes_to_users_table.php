<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soft deletes for user deactivation. The column is indexed because every
     * Eloquent query on the model appends a `where deleted_at is null` clause,
     * so the index is what keeps that filter cheap once the table grows.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The index must go before the column: SQLite drops a column by
            // rebuilding the table and chokes on an index still naming it.
            $table->dropIndex(['deleted_at']);
            $table->dropSoftDeletes();
        });
    }
};
