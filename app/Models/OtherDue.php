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
 * It is only recordable against a partner type that keeps a ledger of
 * its own — subsidiary company, shareholder, employee, other partner, and
 * taxes. A statement row is written there and the running balance
 * cascades like any other movement.
 *
 * Customers and suppliers are deliberately NOT offered: they keep no
 * ledger, their statement is derived from invoices, and an amount that
 * is not an invoice has no honest place in it. An earlier version
 * injected such dues into that report at read time; that was withdrawn
 * at the project owner's decision.
 */
class OtherDue extends Model
{
    /** The partner owes us. */
    public const DUE_FROM = 'due_from';

    /** We owe the partner. */
    public const DUE_TO = 'due_to';

    /**
     * The only partner types a due can be recorded against, mapped to the
     * model that writes their statement row. These are exactly the types
     * the Partner Statement report reads, so every due entered is a due
     * somebody can go and look at.
     */
    public const LEDGER_STATEMENTS = [
        'is_subsidiary_company' => SubsidiaryCompanyStatement::class,
        'is_shareholder' => ShareholderStatement::class,
        'is_employee' => EmployeeStatement::class,
        'is_other_partner' => OtherPartnerStatement::class,
        // Taxes keep their own ledger and their own statement screen
        // (TaxesInsuranceStatementController), so a due here is readable
        // there just as the other four are on the Partner Statement.
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
     * The ledger model for this due's partner type, or null for a type
     * that is no longer offered — a row left over from before the list
     * was narrowed. Nothing is written for those.
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
