<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A canonical Philippine mobile number, stored as +639XXXXXXXXX. Nullable on
     * purpose: existing accounts never gave one and backfilling would mean
     * inventing numbers for people, so the registration validator is what makes
     * it required for every account created from here on.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
