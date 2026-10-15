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
        Schema::create('foreign_exchange_rates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('from_currency');
            $table->string('to_currency');
            $table->date('date');
            $table->decimal('exchange_rate', 10, 4)->default(1);
            $table->unsignedBigInteger('company_id')->index('foreign_exchange_rates_company_id_foreign');
            $table->timestamps();

            $table->index(['company_id', 'from_currency', 'to_currency', 'date'], 'fx_co_currencies_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('foreign_exchange_rates');
    }
};
