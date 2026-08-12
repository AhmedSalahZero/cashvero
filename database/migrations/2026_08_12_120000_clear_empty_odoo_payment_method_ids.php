<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * * قبل كدا لما اودو ما كانش بيرجع payment method line كانت الدالة بترجع []
 * * و Laravel كان بيعملها json_encode و يخزنها في العمود كنص '[]'
 * * و بعدين تتحول لصفر و تتبعت لاودو فيطلع ايرور مبهم
 * * "Please define a payment method line on your payment"
 * * دلوقتي بترجع null , فا بنصلح البيانات القديمة علشان تبقى null هي كمان
 * * ملحوظة : دا مبيصلحش الاعداد الناقص في اودو , بس بيخلي البيانات صادقة
 */
return new class extends Migration
{
    private array $tables = [
        'branch',
        'financial_institution_accounts',
    ];

    private array $columns = [
        'odoo_inbound_transfer_payment_method_id',
        'odoo_outbound_transfer_payment_method_id',
        'odoo_inbound_cheque_payment_method_id',
        'odoo_outbound_cheque_payment_method_id',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            foreach ($this->columns as $column) {
                DB::table($table)->whereIn($column, ['[]', ''])->update([
                    $column => null,
                ]);
            }
        }
    }

    public function down(): void
    {
        // القيمة القديمة كانت غلط اصلا فمفيش داعي نرجعها
    }
};
