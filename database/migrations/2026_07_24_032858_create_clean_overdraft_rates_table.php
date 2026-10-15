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
        Schema::create('clean_overdraft_rates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('clean_overdraft_id');
            $table->date('date');
            $table->decimal('borrowing_rate', 14)->default(0);
            $table->decimal('margin_rate', 14)->default(0);
            $table->decimal('interest_rate', 14)->default(0);
            $table->decimal('min_interest_rate', 14)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clean_overdraft_rates');
    }
};
