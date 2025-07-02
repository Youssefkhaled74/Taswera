<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('frames', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file_path');
            $table->string('thumbnail_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('branch_id')->nullable(); // Null means available at all branches
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('filters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code'); // CSS filter or processing code
            $table->string('thumbnail_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('branch_id')->nullable(); // Null means available at all branches
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filters');
        Schema::dropIfExists('frames');
    }
}; 