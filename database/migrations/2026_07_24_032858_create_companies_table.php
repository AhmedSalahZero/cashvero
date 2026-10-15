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
        Schema::create('companies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('odoo_integration_start_date')->nullable();
            $table->string('labeling_logo', 500)->nullable();
            $table->string('labeling_report_title')->nullable();
            $table->string('labeling_stamp', 300)->nullable();
            $table->string('labeling_logo_3', 300)->nullable();
            $table->string('labeling_logo_2', 300)->nullable();
            $table->string('labeling_logo_1', 300)->nullable();
            $table->longText('labeling_print_headers')->nullable();
            $table->string('no_rows_for_each_page_labeling')->nullable();
            $table->string('print_labeling_type')->nullable();
            $table->longText('generate_labeling_code_fields')->nullable();
            $table->integer('labeling_use_client_logo')->nullable()->default(0);
            $table->string('labeling_client_logo')->nullable();
            $table->string('labeling_pagination_per_page')->nullable()->default('20');
            $table->string('labeling_type')->nullable();
            $table->string('qrcode_height')->nullable();
            $table->string('qrcode_width')->nullable();
            $table->string('label_height')->nullable();
            $table->string('label_width')->nullable();
            $table->string('logo_width')->nullable();
            $table->string('labeling_paper_size')->nullable();
            $table->longText('name');
            $table->string('sub_of')->default('0');
            $table->boolean('is_caching_now')->default(false);
            $table->string('main_functional_currency')->nullable();
            $table->integer('updated_by')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('odoo_db_url')->nullable();
            $table->string('odoo_db_name')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
