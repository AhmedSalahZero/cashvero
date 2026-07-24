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
        Schema::create('last_upload_file_names', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('model_name');
            $table->string('status')->nullable()->comment('هل الملف دا اترفع فعلا وبالتالي هظهره ولا هو لسه بيترفع حاليا');
            $table->unsignedBigInteger('company_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('last_upload_file_names');
    }
};
