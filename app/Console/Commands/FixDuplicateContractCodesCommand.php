<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\CustomerInvoice;
use App\Models\SupplierInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * FixDuplicateContractCodesCommand
 * ------------------------------------------------------------------
 * كود العقد المفروض يكون فريد جوه الشركة الواحدة، لأن الفواتير بتترابط
 * بيه مش بالـ id:
 *   customer_invoices.contract_code -> contracts.code
 *   supplier_invoices.contract_code -> contracts.code
 * فلو اتكرر الكود، الفاتورة الواحدة بتتعلّق بكذا عقد في نفس الوقت، وأي
 * تقرير بيعمل JOIN على العمود ده بيضخّم أرقامه (عدّ ٣٦ بدل ٣١ مثلًا).
 *
 * إزاي حصل التكرار: حقل الكود في نموذج إنشاء العقد نص حر (readonly عند
 * التعديل بس)، ومفيش قاعدة تحقق ولا فهرس فريد — ففحص التفرّد اللي جوه
 * Contract::generateRandomContract() بيتخطّى تمامًا لما الكود ييجي من
 * الفورم. المستخدم نسخ كود عقد موجود ولزقه في عقود جديدة.
 *
 * إزاي بيصلح: في كل مجموعة مكررة بيختار عقدًا واحدًا "يحتفظ بالكود"
 * (الأولوية للي جاي من أودو odoo_id، وبعدها الأقدم id) وبيولّد للباقي
 * كودًا جديدًا عن طريق نفس Contract::generateRandomContract() — يعني
 * بالبادئة الصح (c- للعميل / s- للمورد) وبتاريخ بداية العقد نفسه.
 *
 * ⚠️ الأمان: قبل ما يغيّر كود أي عقد بيعدّ الفواتير المرتبطة بالكود.
 *    العقد اللي بيحتفظ بالكود فواتيره ما بتتأثرش. لكن لو عقد هيتغيّر كوده
 *    وفيه فواتير من نوعه (فواتير عملاء لعقد Customer أو فواتير موردين
 *    لعقد Supplier) يبقى مينفعش نحدد هي بتاعة مين بالظبط — ساعتها الأمر
 *    بيوقف المجموعة دي ويسيبها للمراجعة اليدوية بدل ما يخمّن.
 *
 * الاستخدام:
 *   php artisan contracts:fix-duplicate-codes              # فحص فقط، لا يكتب
 *   php artisan contracts:fix-duplicate-codes --fix        # تنفيذ فعلي
 *   php artisan contracts:fix-duplicate-codes --company=92
 */
class FixDuplicateContractCodesCommand extends Command
{
    protected $signature = 'contracts:fix-duplicate-codes
        {--fix : نفّذ التعديل فعليًا. بدونها الأمر بيفحص ويطبع الخطة من غير أي كتابة}
        {--company= : قصر التنفيذ على شركة واحدة}';

    protected $description = 'يرصد ويصلّح تكرار كود العقد داخل الشركة الواحدة (الفواتير بتترابط بالكود)';

    public function handle(): int
    {
        $groups = $this->duplicateGroups();

        if ($groups->isEmpty()) {
            $this->info('✔ لا يوجد أي كود عقد مكرر — لا حاجة لأي إصلاح.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->warn('مجموعات مكررة: '.$groups->count());
        $this->line('');

        $plan = [];
        $blocked = [];

        foreach ($groups as $group) {
            $contracts = Contract::where('company_id', $group->company_id)
                ->where('code', $group->code)
                ->orderByRaw('odoo_id IS NULL, id')   // اللي من أودو الأول، وبعدها الأقدم
                ->get();

            $keeper = $contracts->first();
            $others = $contracts->slice(1);

            $this->line("  <fg=yellow>{$group->code}</>  (شركة {$group->company_id}) — {$contracts->count()} عقود");
            $this->line("    يحتفظ بالكود : #{$keeper->id} [{$keeper->model_type}]"
                .($keeper->odoo_id ? " odoo_id={$keeper->odoo_id}" : ' (يدوي)')
                .'  فواتير: '.$this->invoiceCountFor($keeper));

            foreach ($others as $contract) {
                $linked = $this->invoiceCountFor($contract);
                // فواتير من نوع العقد ده مربوطة بالكود -> مينفعش نحدد صاحبها
                if ($linked > 0) {
                    $blocked[] = [$group->code, $contract->id, $contract->model_type, $linked];
                    $this->line("    <fg=red>يحتاج مراجعة</> : #{$contract->id} [{$contract->model_type}] مرتبط بـ {$linked} فاتورة");

                    continue;
                }
                $plan[] = $contract;
                $this->line("    يتغيّر كوده  : #{$contract->id} [{$contract->model_type}] (بلا فواتير مرتبطة)");
            }
            $this->line('');
        }

        if ($blocked !== []) {
            $this->warn('عقود لم تُدرج في الخطة لأن لها فواتير مرتبطة بالكود المكرر:');
            $this->table(['الكود', 'العقد', 'النوع', 'عدد الفواتير'], $blocked);
            $this->warn('لازم تتحدد يدويًا الفواتير دي بتاعة أي عقد قبل ما الكود يتغيّر.');
        }

        if ($plan === []) {
            $this->error('مفيش أي عقد ينفع يتصلح آليًا.');

            return self::FAILURE;
        }

        if (! $this->option('fix')) {
            $this->line('');
            $this->warn('ده فحص فقط ولم تُكتب أي بيانات. أضف --fix للتنفيذ الفعلي.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->info('══════ التنفيذ ══════');
        $changed = [];

        DB::transaction(function () use ($plan, &$changed) {
            foreach ($plan as $contract) {
                $old = $contract->code;
                $new = Contract::generateRandomContract(
                    (int) $contract->company_id,
                    $contract->getClientName(),
                    (string) $contract->start_date,
                    (string) $contract->model_type,
                );
                // بنكتب بـ query builder عشان ما نشغّلش أي observers على العقد
                DB::table('contracts')->where('id', $contract->id)->update(['code' => $new]);
                $changed[] = [$contract->id, $contract->model_type, $old, $new];
            }
        });

        $this->table(['العقد', 'النوع', 'الكود القديم', 'الكود الجديد'], $changed);

        $this->line('');
        $this->info('══════ التحقق بعد التنفيذ ══════');
        $remaining = $this->duplicateGroups();
        $this->line('  مجموعات مكررة متبقية : '.$remaining->count()
            .($remaining->isEmpty() ? '  ✔' : '  (فيها عقود محتاجة مراجعة يدوية)'));

        foreach ($changed as [$id, , , $new]) {
            $dupes = Contract::where('company_id', Contract::find($id)->company_id)->where('code', $new)->count();
            if ($dupes !== 1) {
                $this->error("  ✖ الكود الجديد للعقد #{$id} مكرر ({$dupes})");

                return self::FAILURE;
            }
        }
        $this->line('  كل كود جديد فريد داخل شركته : ✔');

        return $remaining->isEmpty() ? self::SUCCESS : self::SUCCESS;
    }

    /**
     * الفواتير المرتبطة بكود العقد ده ومن نوعه — فواتير العملاء تخص عقد
     * Customer وفواتير الموردين تخص عقد Supplier.
     */
    private function invoiceCountFor(Contract $contract): int
    {
        $model = $contract->model_type === 'Supplier' ? SupplierInvoice::class : CustomerInvoice::class;

        return $model::where('company_id', $contract->company_id)
            ->where('contract_code', $contract->code)
            ->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function duplicateGroups()
    {
        return DB::table('contracts')
            ->select('company_id', 'code', DB::raw('COUNT(*) AS n'))
            ->whereNotNull('code')
            ->where('code', '<>', '')
            ->when($this->option('company'), fn ($q) => $q->where('company_id', (int) $this->option('company')))
            ->groupBy('company_id', 'code')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('company_id')
            ->orderBy('code')
            ->get();
    }
}
