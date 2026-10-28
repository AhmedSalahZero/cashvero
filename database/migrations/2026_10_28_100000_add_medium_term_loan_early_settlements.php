<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * * السداد المعجل للقرض متوسط الاجل (Early Settlement)
 *
 * * العميل بيسدد اللي باقي من القرض مرة واحدة قبل ميعاده .. البنك بيجمعله
 * * ال principle المتبقي لحد النهاية وبيحط عليه غرامة سداد معجل.
 * * الفلوس بتتحرك مرة واحدة بس (القيمة + الغرامة) من الحساب اللي اختاره ،
 * * وكل الاقساط اللي لسه مفتوحة بتتقفل من غير حركة فلوس لكل قسط.
 *
 * * عمود
 * * medium_term_loan_early_settlement_id
 * * على
 * * loan_schedules
 * * هو اللي بيعلّم الاقساط اللي اتقفلت بالسداد المعجل .. وعلى جداول الكشوف
 * * هو اللي بيربط صف الحركة بالسداد المعجل علشان يتمسح لو اتحذف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medium_term_loan_early_settlements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('medium_term_loan_id')->unique('mtl_early_settlements_loan_unique');
            $table->date('date');
            // القيمة اللي البنك اتفق عليها مع العميل (بدون الغرامة)
            $table->decimal('amount', 14)->default(0);
            $table->decimal('fee_amount', 14)->default(0);
            $table->unsignedBigInteger('account_type_id');
            $table->string('account_number');
            // بيتحسبوا وقت الحفظ من الاقساط المفتوحة (الفايدة بتتسدد الاول)
            $table->decimal('principle_settled', 14)->default(0);
            $table->decimal('interest_waived', 14)->default(0);
            $table->unsignedBigInteger('first_loan_schedule_id')->nullable();
            $table->timestamps();
        });

        foreach ($this->linkedTables() as $tableName => $indexName) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->unsignedBigInteger('medium_term_loan_early_settlement_id')->nullable()->index($indexName);
            });
        }

        $this->createStatusTriggers(true);
    }

    public function down(): void
    {
        $this->createStatusTriggers(false);

        foreach ($this->linkedTables() as $tableName => $indexName) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
                $table->dropColumn('medium_term_loan_early_settlement_id');
            });
        }

        Schema::dropIfExists('medium_term_loan_early_settlements');
    }

    /** @return array<string, string> اسم الجدول => اسم الـ index */
    private function linkedTables(): array
    {
        return [
            'loan_schedules' => 'loan_schedules_mtl_early_settlement_idx',
            'current_account_bank_statements' => 'cabs_mtl_early_settlement_idx',
            'clean_overdraft_bank_statements' => 'cobs_mtl_early_settlement_idx',
            'fully_secured_overdraft_bank_statements' => 'fsobs_mtl_early_settlement_idx',
            'overdraft_against_commercial_paper_bank_statements' => 'oacpbs_mtl_early_settlement_idx',
            'overdraft_against_assignment_of_contract_bank_statements' => 'oaacbs_mtl_early_settlement_idx',
            'medium_term_loan_bank_statements' => 'mtlbs_mtl_early_settlement_idx',
        ];
    }

    /**
     * * نفس التريجرز اللي في
     * * app/Triggers/Cashvero/loan_schedules_triggers.sql
     * * بالظبط .. ومتحطوش هنا كمان علشان ال deploy يمشي حتى لو حد نسي
     * * `php artisan run:sql` .
     * * الفرق الوحيد: القسط اللي اتقفل بسداد معجل حالته
     * * early_settled
     * * قبل اي شرط تاني.
     */
    private function createStatusTriggers(bool $withEarlySettlement): void
    {
        $earlySettlementCondition = $withEarlySettlement
            ? "IF (NEW.medium_term_loan_early_settlement_id IS NOT NULL) THEN SET NEW.status = 'early_settled'; ELSEIF"
            : 'IF';

        $body = "BEGIN
            {$earlySettlementCondition} (NEW.remaining = 0) THEN SET NEW.status = 'paid';
            ELSEIF (IFNULL(NEW.remaining,0) > 0 AND IFNULL(NEW.remaining,0) < NEW.schedule_payment AND DATE(NEW.date) < DATE(NOW())) THEN SET NEW.status = 'partially_paid_and_past_due';
            ELSEIF (DATE(NEW.date) > DATE(NOW())) THEN SET NEW.status = 'not_due_yet';
            ELSEIF (DATE(NEW.date) = DATE(NOW())) THEN SET NEW.status = 'due_to_day';
            ELSEIF (IFNULL(NEW.remaining,0) = NEW.schedule_payment AND DATE(NEW.date) < DATE(NOW())) THEN SET NEW.status = 'past_due';
            END IF;
        END";

        foreach ([
            'insert_loan_schedule_status_for_customers' => 'INSERT',
            'update_loan_schedule_status' => 'UPDATE',
        ] as $name => $timing) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$name}`");
            DB::unprepared("CREATE TRIGGER `{$name}` BEFORE {$timing} ON `loan_schedules` FOR EACH ROW {$body}");
        }
    }
};
