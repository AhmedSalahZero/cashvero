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
        Schema::create('lending_information', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('overdraft_against_commercial_paper_id')->nullable();
            $table->float('lending_rate')->nullable()->default(0);
            $table->integer('for_commercial_papers_due_within_days')->nullable()->default(0);
            $table->integer('company_id')->nullable();
            $table->dateTime('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lending_information');
    }
};
