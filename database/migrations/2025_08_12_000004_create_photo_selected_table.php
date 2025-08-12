<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('photo_selected', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('original_photo_id')->constrained('photos')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('barcode_prefix', 32);

            // Keep it similar to photos table to represent a full-fledged photo record
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->string('thumbnail_path')->nullable();
            $table->string('status')->default('pending');
            $table->string('sync_status')->default('pending');
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_selected');
    }
};

