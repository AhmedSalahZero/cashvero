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
        Schema::create('outstanding_breakdowns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('settlement_date');
            $table->decimal('amount', 14);
            $table->integer('model_id')->comment('وليكن مثلا clean_overdraft_id');
            $table->string('model_type');
            $table->unsignedBigInteger('company_id')->index('outstanding_breakdowns_company_id_foreign');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outstanding_breakdowns');
    }
};
