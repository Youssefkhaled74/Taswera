<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        return true;
        Schema::table('photo_selections', function (Blueprint $table) {
            $table->foreignId('cloned_photo_id')->nullable()->constrained('photos')->cascadeOnDelete()->after('original_photo_id');
        });
    }

    public function down(): void
    {
        Schema::table('photo_selections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cloned_photo_id');
        });
    }
};

