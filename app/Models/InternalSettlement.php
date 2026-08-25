<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One offset of a partner against themselves.
 *
 * See the create_internal_settlements_table migration for what the
 * concept is and why it is its own table. In short: the partner is both
 * a customer and a supplier, and this row moves an agreed amount off
 * BOTH balances at once — they owe us less, and we owe them less.
 *
 * @property int $id
 * @property int $company_id
 * @property int $partner_id
 * @property string $currency
 * @property string $settlement_date
 * @property numeric $amount
 * @property numeric $exchange_rate
 * @property numeric $amount_in_main_currency
 * @property string|null $user_comment
 * @property-read \App\Models\Partner|null $partner
 */
class InternalSettlement extends Model
{
    protected $table = 'internal_settlements';

    protected $guarded = ['id'];

    /**
     * The label this settlement carries wherever it is shown — the
     * statement's Document Type column on both sides.
     */
    public static function documentType(): string
    {
        return __('Internal Settlement');
    }

    /**
     * The comment shown next to the row on the statement.
     *
     * It names the OTHER side deliberately: on the supplier's statement
     * the useful thing to know is that the money came off their
     * customer balance, and vice versa — otherwise the row reads as an
     * unexplained movement.
     */
    public static function statementComment(bool $isCustomerStatement): string
    {
        return $isCustomerStatement
            ? __('Internal Settlement — settled against the same partner as a supplier')
            : __('Internal Settlement — settled from the same partner as a customer');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id', 'id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function scopeOnlyForCompany(Builder $builder, int $companyId): Builder
    {
        return $builder->where('company_id', $companyId);
    }

    public function getAmount(): float
    {
        return (float) $this->amount;
    }

    public function getAmountInMainCurrency(): float
    {
        return (float) $this->amount_in_main_currency;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getDate(): ?string
    {
        return $this->settlement_date;
    }

    public function getDateFormatted(): ?string
    {
        return $this->settlement_date ? Carbon::make($this->settlement_date)->format('d-m-Y') : null;
    }

    public function getUserComment(): ?string
    {
        return $this->user_comment;
    }

    /**
     * Everything settled so far for one partner in one currency.
     *
     * The single source of the number the balances page subtracts and
     * the cap check measures against, so the amount a user is allowed
     * to settle can never disagree with the balance they were shown.
     */
    public static function totalFor(int $companyId, int $partnerId, string $currency): float
    {
        return (float) static::query()
            ->where('company_id', $companyId)
            ->where('partner_id', $partnerId)
            ->where('currency', $currency)
            ->sum('amount');
    }

    /**
     * Settled totals for many partners at once, keyed
     * "partnerId|CURRENCY" — the balances page needs one number per
     * row and must not issue a query per row to get it.
     *
     * @param  list<int>  $partnerIds
     * @return array<string, float>
     */
    public static function totalsByPartnerAndCurrency(int $companyId, array $partnerIds): array
    {
        if ($partnerIds === []) {
            return [];
        }

        $rows = static::query()
            ->where('company_id', $companyId)
            ->whereIn('partner_id', $partnerIds)
            ->selectRaw('partner_id, currency, SUM(amount) as total, SUM(amount_in_main_currency) as total_main')
            ->groupBy('partner_id', 'currency')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[$row->partner_id.'|'.$row->currency] = (float) $row->total;

            /**
             * The balances page's "main currency" tab shows ONE row per
             * partner covering every currency at once, so its figure
             * accumulates across the per-currency groups rather than
             * being set by whichever group happened to be read last.
             */
            $mainKey = $row->partner_id.'|main_currency';
            $totals[$mainKey] = ($totals[$mainKey] ?? 0.0) + (float) $row->total_main;
        }

        return $totals;
    }
}
