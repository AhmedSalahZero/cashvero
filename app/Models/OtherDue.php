<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An amount owed between the company and a partner that is not an
 * invoice — a deposit held at a customer, a retention, a balance carried
 * in from before CashVero.
 *
 * It is dated on the company's opening balance date, because it is part
 * of the opening position rather than a transaction that happened on a
 * particular day.
 *
 * Where it SHOWS depends on the partner type it was entered under:
 *   - shareholder / employee / subsidiary / other partner / tax — those
 *     have a real ledger table, so a statement row is written there and
 *     the running balance cascades like any other movement;
 *   - customer / supplier — those have no ledger table; their statement
 *     is derived from invoices, so the due is injected into that report
 *     instead (see HasBalances::appendOtherDues).
 */
class OtherDue extends Model
{
    /** The partner owes us. */
    public const DUE_FROM = 'due_from';

    /** We owe the partner. */
    public const DUE_TO = 'due_to';

    /**
     * Partner types whose statement is a real ledger table, mapped to the
     * model that writes it. Anything not listed here (customer, supplier)
     * has its statement derived from invoices instead.
     */
    public const LEDGER_STATEMENTS = [
        'is_employee' => EmployeeStatement::class,
        'is_shareholder' => ShareholderStatement::class,
        'is_subsidiary_company' => SubsidiaryCompanyStatement::class,
        'is_other_partner' => OtherPartnerStatement::class,
        'is_tax' => TaxStatement::class,
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function directions(): array
    {
        return [
            self::DUE_FROM => __('Due From (they owe us)'),
            self::DUE_TO => __('Due To (we owe them)'),
        ];
    }

    public function isDueFrom(): bool
    {
        return $this->direction === self::DUE_FROM;
    }

    /**
     * The ledger model for this due's partner type, or null when the
     * partner type keeps no ledger (customer / supplier).
     */
    public function statementModel(): ?string
    {
        return self::LEDGER_STATEMENTS[$this->partner_type] ?? null;
    }

    public function getAmount(): float
    {
        return (float) $this->amount;
    }

    /**
     * The amount valued in the company's main currency. A due already in
     * the main currency needs no rate, so a missing rate means 1 rather
     * than zero — treating it as zero would quietly erase the amount from
     * every main-currency total.
     */
    public function getAmountInMainCurrency(): float
    {
        return round($this->getAmount() * ((float) ($this->exchange_rate ?: 1)), 2);
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }
}
