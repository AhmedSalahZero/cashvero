<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sales_orders عندها odoo_id من زمان ، purchase_orders لأ.
 * من غير العمود ده كل مزامنة مع أودو هتعمل صف PO جديد بدل ما تحدّث
 * القديم ، والـ po allocations المربوطة بالصف القديم هتضيع.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('purchase_orders', 'odoo_id')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('odoo_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('purchase_orders', 'odoo_id')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['odoo_id']);
            $table->dropColumn('odoo_id');
        });
    }
};
