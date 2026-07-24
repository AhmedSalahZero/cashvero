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
        Schema::create('sections', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->longText('name');
            $table->string('sub_of');
            $table->string('icon');
            $table->string('route')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('trash')->default(false);
            $table->enum('section_side', ['admin', 'company-admin', 'client']);
            $table->integer('updated_by')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
