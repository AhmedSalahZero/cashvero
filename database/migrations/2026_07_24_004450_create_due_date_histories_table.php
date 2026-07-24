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
        Schema::create('due_date_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('model_id')->index('due_date_histories_customer_invoice_id_foreign');
            $table->string('due_date');
            $table->decimal('amount', 14);
            $table->integer('company_id');
            $table->timestamps();
            $table->string('model_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('due_date_histories');
    }
};
