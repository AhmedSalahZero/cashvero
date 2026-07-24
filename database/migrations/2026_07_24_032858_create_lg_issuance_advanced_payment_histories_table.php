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
        Schema::create('lg_issuance_advanced_payment_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->date('date')->nullable();
            $table->decimal('amount', 14);
            $table->unsignedBigInteger('letter_of_guarantee_issuance_id')->index('lg_issuance_advanced_foreign');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lg_issuance_advanced_payment_histories');
    }
};
