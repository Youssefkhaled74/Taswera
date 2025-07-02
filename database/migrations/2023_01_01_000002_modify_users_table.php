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
        Schema::table('users', function (Blueprint $table) {
            // Drop existing columns that we don't need
            $table->dropColumn(['name', 'email', 'email_verified_at', 'password', 'remember_token']);
            
            // Add new columns for our Taswera system
            $table->string('barcode')->unique()->after('id'); // Unique barcode starting with 8-digit code
            $table->string('phone_number')->after('barcode');
            $table->unsignedBigInteger('branch_id')->nullable()->after('phone_number');
            $table->timestamp('last_visit')->nullable()->after('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove the columns we added
            $table->dropColumn(['barcode', 'phone_number', 'branch_id', 'last_visit']);
            
            // Add back the original columns
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
        });
    }
}; 