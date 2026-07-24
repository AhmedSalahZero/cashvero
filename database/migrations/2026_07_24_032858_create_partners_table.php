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
        Schema::create('partners', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('odoo_id')->nullable();
            $table->integer('company_id');
            $table->string('name');
            $table->boolean('is_customer')->default(false);
            $table->boolean('is_supplier')->default(false);
            $table->unsignedBigInteger('is_employee')->default(0);
            $table->unsignedBigInteger('is_shareholder')->default(0);
            $table->unsignedBigInteger('is_other_partner')->default(0);
            $table->unsignedBigInteger('is_subsidiary_company')->default(0);
            $table->boolean('is_tax')->default(false)->comment('هنضيف حساب');
            $table->timestamps();
            $table->integer('due_to_chart_of_account_number_odoo_code')->nullable()->comment('خاصين بال subsidiary');
            $table->integer('due_to_chart_of_account_number_odoo_id')->nullable()->comment('خاصين بال subsidiary');
            $table->integer('due_from_chart_of_account_number_odoo_code')->nullable()->comment('خاصين بال subsidiary');
            $table->integer('due_from_chart_of_account_number_odoo_id')->nullable()->comment('خاصين بال subsidiary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
