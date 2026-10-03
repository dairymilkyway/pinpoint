<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Null means the change was not made by a signed-in person - the
            // seeder, a console command - and those are not written at all. The
            // column is nullable so the rule can be relaxed without a migration.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('event');
            $table->nullableMorphs('subject');

            // Only the attributes that moved, not the whole row, so a log entry
            // stays readable as the schema grows columns.
            $table->json('before')->nullable();
            $table->json('after')->nullable();

            // Append-only: no updated_at, and nothing in the app updates a row.
            $table->timestamp('created_at')->nullable();

            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
