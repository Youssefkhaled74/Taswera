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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('barcode_prefix');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('staff_id')->constrained('staff');
            $table->integer('num_photos');
            $table->decimal('amount', 10, 2);
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('tax_amount', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->enum('invoice_method', ['whatsapp', 'print', 'both']);
            $table->enum('status', ['active', 'cancelled', 'completed'])->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Add indexes for common queries
            $table->index('barcode_prefix');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
}; 