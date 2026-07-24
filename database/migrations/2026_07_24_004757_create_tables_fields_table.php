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
        Schema::create('tables_fields', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('model_name', 191)->nullable();
            $table->string('field_name')->nullable();
            $table->string('view_name')->nullable();
            $table->boolean('is_sales_trend')->default(false);
            $table->integer('company_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tables_fields');
    }
};
