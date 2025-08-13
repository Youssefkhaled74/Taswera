<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('selected_photo_id')->nullable()->after('photo_id');
            $table->unsignedBigInteger('original_photo_id')->nullable()->after('selected_photo_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['selected_photo_id', 'original_photo_id']);
        });
    }
};

