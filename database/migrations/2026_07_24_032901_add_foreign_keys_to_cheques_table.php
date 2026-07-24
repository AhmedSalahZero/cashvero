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
        Schema::table('cheques', function (Blueprint $table) {
            $table->foreign(['drawee_bank_id'])->references(['id'])->on('banks')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['drawl_bank_id'])->references(['id'])->on('financial_institutions')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['money_received_id'])->references(['id'])->on('money_received')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            $table->dropForeign('cheques_drawee_bank_id_foreign');
            $table->dropForeign('cheques_drawl_bank_id_foreign');
            $table->dropForeign('cheques_money_received_id_foreign');
        });
    }
};
