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
        Schema::create('account_interests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('financial_institution_account_id')->index('account_foreign');
            $table->date('start_date')->nullable();
            $table->decimal('interest_rate', 5)->nullable()->default(0);
            $table->decimal('min_balance', 15)->nullable()->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_interests');
    }
};
