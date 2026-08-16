<?php

namespace App\Models;

use App\Helpers\HDate;
use App\Support\StatementCascade;
use App\Traits\IsBankStatement;
use App\Traits\Models\HasDeleteButTriggerChangeOnLastElement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * The drawdown/repayment statement of a Medium Term Loan used as a
 * source of payment.
 *
 * Structurally this is CleanOverdraftBankStatement MINUS interest and
 * MINUS the withdrawal/settlement machinery — see the header of
 * app/Triggers/Cashvero/medium_term_loan_bank_statements.sql for why.
 *
 * Sign convention (identical to every other credit facility here):
 *   credit = drawdown  -> end_balance goes negative, room shrinks
 *   debit  = principle repaid -> end_balance rises, room replenishes
 *
 * beginning_balance / end_balance / room / is_debit / is_credit are all
 * written by MySQL triggers, never by PHP. Touching updated_at in
 * full_date order is what re-cascades them, which is what
 * updateNextRows() below does.
 *
 * @property int $id
 * @property int $medium_term_loan_id
 * @property int $company_id
 * @property int|null $money_payment_id
 * @property int|null $loan_schedule_settlement_id
 * @property string|null $type
 * @property int $is_debit
 * @property int $is_credit
 * @property string|null $date
 * @property string|null $full_date
 * @property numeric $limit
 * @property numeric $beginning_balance
 * @property numeric|null $debit
 * @property numeric|null $credit
 * @property numeric $interest_amount الفايدة المدفوعة في الحركة دي — تسجيل بس، خارج معادلة الرصيد
 * @property numeric $end_balance
 * @property numeric $room
 * @property string|null $comment_en
 * @property string|null $comment_ar
 * @property-read \App\Models\MediumTermLoan|null $mediumTermLoan
 * @property-read \App\Models\MoneyPayment|null $moneyPayment
 * @property-read \App\Models\LoanScheduleSettlement|null $loanScheduleSettlement
 * @mixin \Eloquent
 */
class MediumTermLoanBankStatement extends Model
{
    use IsBankStatement, HasDeleteButTriggerChangeOnLastElement;

    /**
     * Written on the row produced when an installment is repaid —
     * distinguishes it from the drawdown rows, which carry the money type
     * ('outgoing-transfer', 'payable_cheque', ...).
     *
     * The row splits the payment: `debit` is the principle half (moves the
     * balance and frees room), `interest_amount` is the interest half
     * (recorded only — an MTL's interest is already inside the installment,
     * so paying it never touches the drawn balance).
     */
    const INSTALLMENT_REPAYMENT = 'installment_repayment';

    protected $guarded = ['id'];

    public static function updateNextRows(self $model): string
    {
        $minDate = $model->full_date;

        StatementCascade::touchRows(
            DB::table('medium_term_loan_bank_statements')
                ->where('full_date', '>=', $minDate)
                ->where('medium_term_loan_id', $model->medium_term_loan_id),
            'full_date asc , id asc'
        );

        return $minDate;
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->created_at = now();
            $date = $model->date;

            // Reuse the id the delete trigger parked in temp_deleted_statements
            // so a delete-then-recreate keeps its place in the ordering — same
            // pattern as every other statement model here.
            $row = DB::table('temp_deleted_statements')
                ->where('company_id', $model->company_id)
                ->where('table_name', 'medium_term_loan_bank_statements')
                ->first();

            if ($row) {
                $model->id = $row->deleted_id;
                DB::table('temp_deleted_statements')
                    ->where('company_id', $model->company_id)
                    ->where('table_name', 'medium_term_loan_bank_statements')
                    ->delete();
            }

            $time = now()->format('H:i:s');
            $fullDateTime = date('Y-m-d H:i:s', strtotime("$date $time"));
            $fullDateTime = HDate::generateUniqueDateTimeForModel(self::class, 'full_date', $fullDateTime, [
                ['medium_term_loan_id', '=', $model->medium_term_loan_id],
            ]);

            $model->full_date = $fullDateTime;
        });

        static::created(function (self $model) {
            self::updateNextRows($model);
        });

        static::updated(function (self $model) {
            $minDate = self::updateNextRows($model);

            // Row moved to a different loan — the old loan's remaining rows
            // have to re-cascade too, otherwise its room stays wrong.
            if ($model->isDirty('medium_term_loan_id')) {
                $oldLoanId = $model->getRawOriginal('medium_term_loan_id');

                StatementCascade::touchRows(
                    DB::table('medium_term_loan_bank_statements')->where('medium_term_loan_id', $oldLoanId),
                    'full_date asc , id asc'
                );

                DB::table('medium_term_loan_bank_statements')
                    ->where('full_date', '>=', $minDate)
                    ->where('medium_term_loan_id', $model->medium_term_loan_id)
                    ->orderByRaw('full_date asc , id asc')
                    ->update(['updated_at' => now()]);
            }
        });

        static::deleting(function (self $model) {
            // Zero it first so the rows after it re-cascade off a 0-effect row
            // before it actually disappears — same as clean overdraft.
            $model->debit = 0;
            $model->credit = 0;
            $model->save();
        });
    }

    public function mediumTermLoan(): BelongsTo
    {
        return $this->belongsTo(MediumTermLoan::class, 'medium_term_loan_id', 'id');
    }

    public function moneyPayment(): BelongsTo
    {
        return $this->belongsTo(MoneyPayment::class, 'money_payment_id', 'id');
    }

    public function loanScheduleSettlement(): BelongsTo
    {
        return $this->belongsTo(LoanScheduleSettlement::class, 'loan_schedule_settlement_id', 'id');
    }

    public function getId()
    {
        return $this->id;
    }

    public function getRoom()
    {
        return $this->room ?: 0;
    }

    public function getRoomFormatted()
    {
        return number_format($this->getRoom());
    }

    public function getEndBalance()
    {
        return $this->end_balance ?: 0;
    }

    public function getEndBalanceFormatted()
    {
        return number_format($this->getEndBalance());
    }

    public function setDateAttribute($value)
    {
        $date = explode('/', $value);
        if (count($date) != 3) {
            $this->attributes['date'] = $value;

            return;
        }
        $month = $date[0];
        $day = $date[1];
        $year = $date[2];

        $this->attributes['date'] = $year . '-' . $month . '-' . $day;
    }

    /**
     * The columns the cascade uses to tell one account's rows from
     * another's — see IsBankStatement::handleFullDateAfterDateEdit().
     */
    public function getForeignKeyNamesThatUsedInFilter(): array
    {
        return [
            'medium_term_loan_id',
        ];
    }
}
