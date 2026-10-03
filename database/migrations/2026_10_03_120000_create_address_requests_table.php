<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('address_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Null for an addition, which has no address yet. Nulled rather than
            // cascaded when the address goes: the request has to outlive it, or
            // a decision on a deleted address could never be recorded. A null
            // here on a non-addition type is how "the address is gone" is read.
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type');
            $table->json('payload')->nullable();

            // The address as it stood when the request was raised. Staleness is
            // decided by comparing this against the current row, not by
            // comparing timestamps - updated_at also moves for a default toggle.
            $table->json('before')->nullable();

            $table->text('note')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            // The queue reads pending oldest-first; this is that read.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('address_requests');
    }
};
