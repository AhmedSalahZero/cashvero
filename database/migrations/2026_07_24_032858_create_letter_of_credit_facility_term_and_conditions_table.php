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
        Schema::create('letter_of_credit_facility_term_and_conditions', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('letter_of_credit_facility_id')->nullable();
            $table->integer('company_id');
            $table->string('lc_type')->nullable();
            $table->string('cash_cover_rate')->nullable();
            $table->string('commission_rate')->nullable();
            $table->string('commission_interval')->nullable();
            $table->decimal('min_commission_fees', 20)->default(0);
            $table->decimal('issuance_fees', 20)->default(0);
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_of_credit_facility_term_and_conditions');
    }
};
