<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * * فهارس البحث بالـ odoo_id
 *
 * * استيراد أودو بيدوّر على كل شريك و كل فاتورة بالـ odoo_id قبل ما
 * * يقرر يعمل صف جديد ولا يحدّث الموجود :
 * *   - Partner::findByOdooId()  → WHERE odoo_id = ? AND company_id = ?
 * *   - IsInvoice::createForOdoo() → WHERE odoo_id = ? AND company_id = ?
 *
 * * الاتنين مكانش عليهم أي فهرس ، يعني مسح كامل للجدول في كل نداء.
 * * و بما ان النداء بيتكرر مرة لكل شريك و مرة لكل فاتورة ، التكلفة كانت
 * * بتزيد تربيعيا مع حجم الداتا — ده السبب الرئيسي ان الاستيراد كان
 * * شغال زمان و بقى بيضرب تايم أوت لما الداتا كبرت.
 *
 * * و (company_id, invoice_date) عشان syncDeletedInvoices() اللي بتقرا
 * * فواتير ٤٥٠ يوم في كل مزامنة.
 *
 * * كل فهرس بيتعمل بشرط انه مش موجود ، عشان المايجريشن تعدي على أي
 * * داتابيز مهما كانت فهارسها الحالية.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<string, array<int, string>>>
     */
    private const INDEXES = [
        'partners' => [
            'partners_company_odoo_idx' => ['company_id', 'odoo_id'],
        ],
        'customer_invoices' => [
            'customer_invoices_company_odoo_idx' => ['company_id', 'odoo_id'],
            'customer_invoices_company_invoice_date_idx' => ['company_id', 'invoice_date'],
        ],
        'supplier_invoices' => [
            'supplier_invoices_company_odoo_idx' => ['company_id', 'odoo_id'],
            'supplier_invoices_company_invoice_date_idx' => ['company_id', 'invoice_date'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $indexName => $columns) {
                if ($this->indexExists($table, $indexName)) {
                    continue;
                }

                /**
                 * * لو عمود من أعمدة الفهرس مش موجود على الداتابيز دي
                 * * بنسيب الفهرس بدل ما المايجريشن كلها توقع
                 */
                if (! $this->hasColumns($table, $columns)) {
                    continue;
                }

                Schema::table($table, function ($blueprint) use ($columns, $indexName) {
                    $blueprint->index($columns, $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $indexName => $columns) {
                if (! $this->indexExists($table, $indexName)) {
                    continue;
                }

                Schema::table($table, function ($blueprint) use ($indexName) {
                    $blueprint->dropIndex($indexName);
                });
            }
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function hasColumns(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }
};
