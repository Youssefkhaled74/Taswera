<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Make order_items.photo_id nullable (keeps FK intact)
        DB::statement('ALTER TABLE `order_items` MODIFY `photo_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Revert to NOT NULL (will fail if nulls exist)
        DB::statement('ALTER TABLE `order_items` MODIFY `photo_id` BIGINT UNSIGNED NOT NULL');
    }
};

