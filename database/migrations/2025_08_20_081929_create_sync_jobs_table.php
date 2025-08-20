<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSyncJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sync_jobs', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key
            $table->unsignedBigInteger('branch_id'); // Foreign key for branch
            $table->string('employeeName'); // Name of the employee
            $table->decimal('pay_amount', 10, 2); // Pay amount with 2 decimal places (e.g., 1000.50)
            $table->string('orderprefixcode'); // Order prefix code
            $table->string('status'); // Status of the job
            $table->string('shift_name'); // Name of the shift
            $table->string('orderphone'); // Phone number for the order
            $table->unsignedInteger('number_of_photos'); // Number of photos
            $table->timestamps(); // Created_at and updated_at timestamps
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sync_jobs');
    }
}