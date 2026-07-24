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
        Schema::create('customized_fields_exportations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->longText('fields');
            $table->string('model_name');
            $table->unsignedBigInteger('company_id')->index('customized_fields_exportations_company_id_foreign');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customized_fields_exportations');
    }
};
