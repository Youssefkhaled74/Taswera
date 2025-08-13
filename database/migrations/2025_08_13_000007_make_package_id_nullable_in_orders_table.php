<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Make orders.package_id nullable without requiring doctrine/dbal
        DB::statement('ALTER TABLE `orders` MODIFY `package_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Revert to NOT NULL (will fail if nulls exist)
        DB::statement('ALTER TABLE `orders` MODIFY `package_id` BIGINT UNSIGNED NOT NULL');
    }
};

