<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * كود العقد لازم يكون فريد جوه الشركة الواحدة.
 *
 * ليه: الفواتير مربوطة بالعقد عن طريق الكود مش الـ id
 *   customer_invoices.contract_code -> contracts.code
 *   supplier_invoices.contract_code -> contracts.code
 * فأي تكرار بيخلي الفاتورة الواحدة تتعلّق بكذا عقد، وأي تقرير بيعمل
 * JOIN على العمود ده بيضخّم أرقامه.
 *
 * ⚠️ الفهرس ده هو الحاجز الأخير. قبله فيه قاعدة تحقق في
 *    StoreContractRequest، لكن الفهرس بيحمي كمان أي مسار تاني
 *    (استيراد أودو، seeders، تعديل مباشر على الداتابيز).
 *
 * لازم يتنفّذ `php artisan contracts:fix-duplicate-codes --fix` الأول
 * لو لسه فيه تكرار — الميجريشن بيتوقف برسالة واضحة بدل ما يفشل بخطأ
 * فهرس مبهم.
 */
return new class extends Migration
{
    private const INDEX = 'contracts_company_id_code_unique';

    public function up(): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        if ($this->indexExists()) {
            return;
        }

        $duplicates = DB::table('contracts')
            ->select('company_id', 'code', DB::raw('COUNT(*) AS n'))
            ->whereNotNull('code')
            ->where('code', '<>', '')
            ->groupBy('company_id', 'code')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $sample = $duplicates->take(5)
                ->map(fn ($row) => "company {$row->company_id} / {$row->code} ({$row->n}×)")
                ->implode(', ');

            throw new RuntimeException(
                'لا يمكن إضافة الفهرس الفريد: فيه '.$duplicates->count().' كود عقد مكرر. '
                .'شغّل الأول:  php artisan contracts:fix-duplicate-codes --fix'
                .'   [عيّنة: '.$sample.']'
            );
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->unique(['company_id', 'code'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contracts') || ! $this->indexExists()) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropUnique(self::INDEX);
        });
    }

    private function indexExists(): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'contracts')
            ->where('index_name', self::INDEX)
            ->exists();
    }
};
