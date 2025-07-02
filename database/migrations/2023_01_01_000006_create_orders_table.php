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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('package_id')->constrained();
            $table->decimal('total_price', 10, 2);
            $table->string('status')->default('pending'); // pending, completed, cancelled
            $table->unsignedBigInteger('processed_by')->nullable(); // Staff ID
            $table->foreign('processed_by')->references('id')->on('staff')->onDelete('set null');
            $table->unsignedBigInteger('branch_id');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->string('whatsapp_link')->nullable();
            $table->timestamp('link_expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
}; 