<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        return true;
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('pay_amount', 10, 2)->nullable();
            $table->foreignId('shift_id')->constrained()->onDelete('cascade')->default(1);
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('pay_amount');
            $table->dropForeign('orders_shift_id_foreign');
            $table->dropColumn('shift_id');
        });
    }
};
