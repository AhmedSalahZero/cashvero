<?php

namespace Tests\Feature\Serials;

use App\Models\MultipleCashExpense;
use App\Support\Activity\ActivityLogger;
use App\Support\Serials\SerialAssigner;
use App\Support\Serials\SerialBackfiller;
use Illuminate\Support\Facades\DB;

/**
 * * الترقيم نفسه : العدّاد ، الإنشاء ، التعديل ، و ترقيم الداتا القديمة .
 *
 * * MultipleCashExpense هو الموديل اللي بنجرّب عليه : أقل أعمدة
 * * إجبارية و مالوش hooks بتعتمد على الريكويست . الـ trait نفسه واحد
 * * في العشر موديلات .
 */
class SerialNumberingTest extends SerialTestCase
{
    private function makeExpense(string $type, string $date): MultipleCashExpense
    {
        $expense = new MultipleCashExpense;
        $expense->forceFill([
            'company_id' => self::COMPANY,
            'type' => $type,
            'payment_date' => $date,
            'currency' => 'EGP',
        ])->save();

        return $expense->fresh();
    }

    /* ───────────── العدّاد ───────────── */

    public function test_the_counter_is_per_company_prefix_and_year(): void
    {
        $this->inRolledBackTransaction(function () {
            $this->assertSame(1, SerialAssigner::next(self::COMPANY, 'TST/A', self::YEAR));
            $this->assertSame(2, SerialAssigner::next(self::COMPANY, 'TST/A', self::YEAR));
            $this->assertSame(1, SerialAssigner::next(self::COMPANY, 'TST/B', self::YEAR), 'another type starts from 1');
            $this->assertSame(1, SerialAssigner::next(self::COMPANY, 'TST/A', self::YEAR - 1), 'another year starts from 1');
            $this->assertSame(1, SerialAssigner::next(self::COMPANY + 1, 'TST/A', self::YEAR), 'another company starts from 1');
            $this->assertSame(3, SerialAssigner::next(self::COMPANY, 'TST/A', self::YEAR));
        });
    }

    public function test_a_rolled_back_save_gives_its_number_back(): void
    {
        $this->inRolledBackTransaction(fn () => $this->assertSame(1, SerialAssigner::next(self::COMPANY, 'TST/A', self::YEAR)));
        $this->inRolledBackTransaction(fn () => $this->assertSame(1, SerialAssigner::next(self::COMPANY, 'TST/A', self::YEAR)));
    }

    /* ───────────── الإنشاء ───────────── */

    public function test_a_new_record_is_numbered_from_its_type_and_date(): void
    {
        $this->inRolledBackTransaction(function () {
            $first = $this->makeExpense(MultipleCashExpense::CASH_PAYMENT, self::YEAR.'-03-05');
            $second = $this->makeExpense(MultipleCashExpense::CASH_PAYMENT, self::YEAR.'-12-01');
            $cheque = $this->makeExpense(MultipleCashExpense::PAYABLE_CHEQUE, self::YEAR.'-03-05');

            $this->assertSame('MCE/CP/2099/03/00001', $first->serial);
            $this->assertSame('MCE/CP/2099/12/00002', $second->serial);
            $this->assertSame('MCE/PC/2099/03/00001', $cheque->serial);
        });
    }

    public function test_a_serial_sent_with_the_request_is_ignored(): void
    {
        $this->inRolledBackTransaction(function () {
            $expense = new MultipleCashExpense;
            $expense->forceFill([
                'company_id' => self::COMPANY,
                'type' => MultipleCashExpense::CASH_PAYMENT,
                'payment_date' => self::YEAR.'-01-01',
                'serial' => 'HACKED',
            ])->save();

            $this->assertSame('MCE/CP/2099/01/00001', $expense->fresh()->serial);
        });
    }

    /* ───────────── التعديل في نفس الصف ───────────── */

    public function test_an_edit_cannot_overwrite_the_serial(): void
    {
        $this->inRolledBackTransaction(function () {
            $expense = $this->makeExpense(MultipleCashExpense::CASH_PAYMENT, self::YEAR.'-01-01');
            $expense->forceFill(['serial' => 'HACKED'])->save();

            $this->assertSame('MCE/CP/2099/01/00001', $expense->fresh()->serial);
        });
    }

    public function test_a_date_edit_keeps_the_serial(): void
    {
        $this->inRolledBackTransaction(function () {
            $expense = $this->makeExpense(MultipleCashExpense::CASH_PAYMENT, self::YEAR.'-01-01');
            $expense->update(['payment_date' => self::YEAR.'-08-01']);

            $this->assertSame('MCE/CP/2099/01/00001', $expense->fresh()->serial);
        });
    }

    public function test_a_type_edit_takes_a_number_from_the_new_type(): void
    {
        $this->inRolledBackTransaction(function () {
            $expense = $this->makeExpense(MultipleCashExpense::CASH_PAYMENT, self::YEAR.'-01-01');
            $expense->update(['type' => MultipleCashExpense::PAYABLE_CHEQUE]);

            $this->assertSame('MCE/PC/2099/01/00001', $expense->fresh()->serial);
        });
    }

    /* ───────────── التعديل بالحذف و الإنشاء ───────────── */

    /** نفس اللي الكونترولرز بتعمله : احذف القديم و اعمل صف جديد جوه asUpdate() */
    private function replace(MultipleCashExpense $old, string $type): MultipleCashExpense
    {
        return ActivityLogger::asUpdate($old, function () use ($old, $type) {
            $old->delete();

            return $this->makeExpense($type, self::YEAR.'-02-01');
        });
    }

    public function test_a_replacing_edit_keeps_the_serial(): void
    {
        $this->inRolledBackTransaction(function () {
            $old = $this->makeExpense(MultipleCashExpense::CASH_PAYMENT, self::YEAR.'-01-01');
            $new = $this->replace($old, MultipleCashExpense::CASH_PAYMENT);

            $this->assertNotSame($old->id, $new->id);
            $this->assertSame('MCE/CP/2099/01/00001', $new->fresh()->serial);

            // و الرقم ما اتصرفش : الحركة الجاية بتاخد 00002
            $this->assertSame('MCE/CP/2099/03/00002', $this->makeExpense(MultipleCashExpense::CASH_PAYMENT, self::YEAR.'-03-01')->serial);
        });
    }

    public function test_a_replacing_edit_keeps_the_serial_even_with_logging_off(): void
    {
        config(['activity.enabled' => false]);

        $this->inRolledBackTransaction(function () {
            $old = $this->makeExpense(MultipleCashExpense::CASH_PAYMENT, self::YEAR.'-01-01');

            $this->assertSame('MCE/CP/2099/01/00001', $this->replace($old, MultipleCashExpense::CASH_PAYMENT)->fresh()->serial);
        });
    }

    public function test_a_replacing_edit_that_changes_the_type_takes_a_new_number(): void
    {
        $this->inRolledBackTransaction(function () {
            $old = $this->makeExpense(MultipleCashExpense::CASH_PAYMENT, self::YEAR.'-01-01');

            $this->assertSame('MCE/PC/2099/02/00001', $this->replace($old, MultipleCashExpense::PAYABLE_CHEQUE)->fresh()->serial);
        });
    }

    public function test_the_kept_serial_does_not_leak_past_a_failed_edit(): void
    {
        $this->inRolledBackTransaction(function () {
            $old = $this->makeExpense(MultipleCashExpense::CASH_PAYMENT, self::YEAR.'-01-01');

            try {
                SerialAssigner::preserving($old, fn () => throw new \RuntimeException('edit failed'));
            } catch (\RuntimeException) {
            }

            $old->delete();

            $this->assertSame('MCE/CP/2099/02/00002', $this->makeExpense(MultipleCashExpense::CASH_PAYMENT, self::YEAR.'-02-01')->serial);
        });
    }

    /* ───────────── ترقيم الداتا القديمة ───────────── */

    public function test_the_backfill_numbers_old_rows_by_date_then_id(): void
    {
        $this->inRolledBackTransaction(function () {
            $insert = fn (string $date) => DB::table('multiple_cash_expenses')->insertGetId([
                'company_id' => self::COMPANY,
                'type' => MultipleCashExpense::CASH_PAYMENT,
                'payment_date' => $date,
                'currency' => 'EGP',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $march = $insert(self::YEAR.'-03-01');
            $januaryFirst = $insert(self::YEAR.'-01-15');
            $januarySecond = $insert(self::YEAR.'-01-15');

            $report = app(SerialBackfiller::class)->run(self::COMPANY, MultipleCashExpense::class);
            $this->assertSame(3, $report[MultipleCashExpense::class]['assigned']);

            $serials = DB::table('multiple_cash_expenses')->whereIn('id', [$march, $januaryFirst, $januarySecond])->pluck('serial', 'id');
            $this->assertSame('MCE/CP/2099/01/00001', $serials[$januaryFirst]);
            $this->assertSame('MCE/CP/2099/01/00002', $serials[$januarySecond]);
            $this->assertSame('MCE/CP/2099/03/00003', $serials[$march]);

            // تشغيله تاني ما بيغيّرش حاجة ، و الحركة الجديدة بتكمّل بعدهم
            $again = app(SerialBackfiller::class)->run(self::COMPANY, MultipleCashExpense::class);
            $this->assertSame(0, $again[MultipleCashExpense::class]['assigned']);
            $this->assertSame($serials->all(), DB::table('multiple_cash_expenses')->whereIn('id', [$march, $januaryFirst, $januarySecond])->pluck('serial', 'id')->all());
            $this->assertSame('MCE/CP/2099/04/00004', $this->makeExpense(MultipleCashExpense::CASH_PAYMENT, self::YEAR.'-04-01')->serial);
        });
    }
}
