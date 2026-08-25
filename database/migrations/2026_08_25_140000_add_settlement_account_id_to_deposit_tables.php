<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * * حساب التسوية: الحساب الجاري اللي بيترد عليه أصل الوديعة وقت الاستحقاق أو الكسر.
 * * قبل كده كنا بنستعمل deducted_from_account_id، وده بيبقى فاضي لو الوديعة
 * * اتسجلت على انها opening balance — فا وقت الاستحقاق مكناش بنلاقي حساب أصلا.
 * * دلوقتي اليوزر بيختاره من البوب اب بتاع Apply Deposit / Break.
 */
return new class extends Migration
{
    private array $tables = [
        'certificates_of_deposits',
        'time_of_deposits',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'settlement_account_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->unsignedBigInteger('settlement_account_id')->nullable()->after('deducted_from_account_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'settlement_account_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('settlement_account_id');
                });
            }
        }
    }
};
