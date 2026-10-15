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
        Schema::create('po_allocations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('contract_id')->nullable()->index('allocations_contract_id_foreign');
            $table->unsignedBigInteger('purchase_order_id')->nullable()->index('po_allocations__id_foreign');
            $table->unsignedBigInteger('partner_id')->nullable()->index('po_allocations_partner_id_foreign');
            $table->decimal('allocation_percentage', 14)->default(0);
            $table->decimal('allocation_amount', 14)->default(0);
            $table->timestamps();

            $table->index(['contract_id'], 'po_alloc_contract_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('po_allocations');
    }
};
