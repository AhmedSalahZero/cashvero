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
        Schema::create('caching_company', function (Blueprint $table) {
            $table->increments('id');
            $table->string('model');
            $table->integer('company_id');
            $table->integer('job_id');
            $table->string('key_name', 100);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caching_company');
    }
};
