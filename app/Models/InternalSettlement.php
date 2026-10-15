<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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

    /**
     * The comment shown on ONE side's statement, naming the invoices
     * the money actually landed on over on the OTHER side.
     *
     * That is the useful direction: reading the customer statement, the
     * question is "where did this go?" — and the answer is the supplier
     * invoices it paid. Reading the supplier statement, the question is
     * "where did this come from?".
     */
    public function statementCommentWithInvoices(bool $isCustomerStatement): string
    {
        $otherSide = $isCustomerStatement ? self::SIDE_SUPPLIER : self::SIDE_CUSTOMER;
        $numbers = $this->invoiceNumbersFor($otherSide);

        $comment = self::statementComment($isCustomerStatement);

        if ($numbers !== []) {
            $comment .= ' — '.($isCustomerStatement
                ? __('settling invoices [ :numbers ]', ['numbers' => implode(' / ', $numbers)])
                : __('paid from invoices [ :numbers ]', ['numbers' => implode(' / ', $numbers)]));
        }

        if ($this->getUserComment()) {
            $comment .= ' — '.$this->getUserComment();
        }

        return $comment;
    }

    /**
     * Invoice numbers this settlement touched on one side.
     *
     * @return list<string>
     */
    public function invoiceNumbersFor(string $side): array
    {
        $meta = self::sideTables($side);

        return DB::table($meta['table'].' as a')
            ->join($meta['invoice_table'].' as i', 'i.id', '=', 'a.invoice_id')
            ->where('a.internal_settlement_id', $this->id)
            ->orderBy('i.invoice_date')
            ->pluck('i.invoice_number')
            ->filter()
            ->values()
            ->all();
    }

    /** The two sides an allocation can sit on. */
    public const SIDE_CUSTOMER = 'customer';

    public const SIDE_SUPPLIER = 'supplier';

    /**
     * Where each side's allocation rows live. These are the app's own
     * settlement tables — the same ones a collection and a payment
     * write to — so the invoice triggers move net_balance for us
     * instead of us adjusting a displayed number.
     *
     * @return array{table: string, invoice_table: string, partner_column: string}
     */
    public static function sideTables(string $side): array
    {
        return $side === self::SIDE_CUSTOMER
            ? ['table' => 'settlements', 'invoice_table' => 'customer_invoices', 'partner_column' => 'customer_id']
            : ['table' => 'payment_settlements', 'invoice_table' => 'supplier_invoices', 'partner_column' => 'supplier_id'];
    }

    /**
     * Writes this settlement's allocations as real settlement rows.
     *
     * Nothing here updates an invoice directly: inserting the row is
     * what makes the trigger recompute that invoice's collected/paid
     * amount and, from it, its net_balance.
     *
     * @param  array<string, array<int, float>>  $allocations  side => [invoice_id => amount]
     */
    public function applyAllocations(array $allocations): void
    {
        foreach ([self::SIDE_CUSTOMER, self::SIDE_SUPPLIER] as $side) {
            $meta = self::sideTables($side);

            foreach ($allocations[$side] ?? [] as $invoiceId => $amount) {
                $amount = round((float) $amount, 2);
                if ($amount <= 0) {
                    continue;
                }

                $row = [
                    'invoice_id' => $invoiceId,
                    'partner_id' => $this->partner_id,
                    'company_id' => $this->company_id,
                    'settlement_amount' => $amount,
                    'withhold_amount' => 0,
                    'internal_settlement_id' => $this->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                /**
                 * payment_settlements.letter_of_credit_issuance_id is
                 * NOT NULL with no default — every existing row uses 0
                 * for "not an LC settlement", so an internal settlement
                 * says the same thing the same way.
                 */
                if ($side === self::SIDE_SUPPLIER) {
                    $row['letter_of_credit_issuance_id'] = 0;
                }

                DB::table($meta['table'])->insert($row);
            }
        }
    }

    /**
     * Takes every allocation back.
     *
     * Deleted one row at a time on purpose: the delete trigger runs per
     * row and recomputes the invoice it belonged to. A bulk delete on
     * the query builder still fires row triggers in MySQL, but doing it
     * explicitly keeps the intent — "put each invoice back" — legible.
     */
    public function reverseAllocations(): void
    {
        foreach ([self::SIDE_CUSTOMER, self::SIDE_SUPPLIER] as $side) {
            $meta = self::sideTables($side);

            $ids = DB::table($meta['table'])->where('internal_settlement_id', $this->id)->pluck('id');
            foreach ($ids as $id) {
                DB::table($meta['table'])->where('id', $id)->delete();
            }
        }
    }

    /**
     * What this settlement currently puts on each invoice, by side.
     *
     * The edit form needs it to show what is already allocated, and the
     * ceiling check needs it to add this settlement's own effect back
     * before measuring — otherwise raising an allocation from 80k to
     * 90k is judged against a balance the 80k already reduced, and a
     * legitimate edit is refused.
     *
     * @return array<string, array<int, float>>
     */
    public function allocationsBySide(): array
    {
        $out = [];

        foreach ([self::SIDE_CUSTOMER, self::SIDE_SUPPLIER] as $side) {
            $meta = self::sideTables($side);
            $out[$side] = DB::table($meta['table'])
                ->where('internal_settlement_id', $this->id)
                ->pluck('settlement_amount', 'invoice_id')
                ->map(fn ($amount) => (float) $amount)
                ->toArray();
        }

        return $out;
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
