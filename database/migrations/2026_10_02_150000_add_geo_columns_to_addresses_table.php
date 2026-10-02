<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Geographic identifiers alongside the existing free-text columns.
     *
     * The text columns stay authoritative for display, so every existing row and
     * every existing query keeps working; these add the stable PSGC codes the
     * cascading dropdowns submit and the coordinates the map pins need. All are
     * nullable because an address does not have to come from the dataset.
     */
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('region_code', 10)->nullable()->after('country');
            $table->string('province_code', 10)->nullable()->after('region_code');
            $table->string('city_code', 10)->nullable()->after('province_code');
            $table->decimal('latitude', 10, 7)->nullable()->after('city_code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn([
                'region_code',
                'province_code',
                'city_code',
                'latitude',
                'longitude',
            ]);
        });
    }
};
