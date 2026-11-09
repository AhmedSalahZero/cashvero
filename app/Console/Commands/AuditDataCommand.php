<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan cashvero:audit-data`
 * ==================================================================
 * Read-only health checks of the REAL data (the database in your .env).
 *
 * These checks used to live inside the test suite, which meant the tests depended on whatever
 * happened to be in the developer's database. The tests now prove the RULES on fake data; this
 * command answers the other question — "does the data we actually have break any of them?" —
 * and is run by hand (or on a schedule) against the real database.
 *
 * Nothing is changed: every check is a SELECT. Exit code 1 when a check finds something, so it
 * can also be used in a deploy pipeline.
 *
 *   php artisan cashvero:audit-data                 every company
 *   php artisan cashvero:audit-data --company=148   one company
 */
class AuditDataCommand extends Command
{
    protected $signature = 'cashvero:audit-data {--company= : Only this company id} {--show=5 : How many example rows to print per finding}';

    protected $description = 'Read-only checks of the real data for the problems the test suite guards against';

    public function handle(): int
    {
        $company = $this->option('company') !== null ? (int) $this->option('company') : null;
        $show = max(0, (int) $this->option('show'));
        $findings = 0;

        foreach ($this->checks() as $check) {
            $rows = collect();

            foreach ($check['queries'] as $query) {
                $rows = $rows->concat(DB::select($query['sql'].($company !== null ? ' AND '.$query['company'].' = ?' : ''), $company !== null ? [$company] : []));
            }

            if ($rows->isEmpty()) {
                $this->line("  <fg=green>✓</> {$check['title']}");

                continue;
            }

            $findings++;
            $this->line("  <fg=red>✗</> {$check['title']} — {$rows->count()} found");
            $this->line("      {$check['why']}");

            foreach ($rows->take($show) as $row) {
                $this->line('      · '.collect((array) $row)->map(fn ($v, $k) => "{$k}={$v}")->implode(', '));
            }
        }

        $this->newLine();

        if ($findings === 0) {
            $this->info('No problems found.');

            return self::SUCCESS;
        }

        $this->warn("{$findings} check(s) found something — see above. Nothing was changed.");

        return self::FAILURE;
    }

    /**
     * Each check: a title, why it matters, and one or more SELECTs. `company` is the column the
     * --company option filters on; every query already ends in a WHERE so it can be appended.
     *
     * @return list<array{title: string, why: string, queries: list<array{sql: string, company: string}>}>
     */
    private function checks(): array
    {
        $statementTables = [
            'clean_overdraft_bank_statements', 'fully_secured_overdraft_bank_statements',
            'overdraft_against_commercial_paper_bank_statements', 'overdraft_against_assignment_of_contract_bank_statements',
            'lc_overdraft_bank_statements', 'current_account_bank_statements',
        ];
        $rateTables = [
            'clean_overdraft_rates', 'fully_secured_overdraft_rates',
            'overdraft_against_commercial_paper_rates', 'overdraft_against_assignment_of_contract_rates',
        ];

        return [
            [
                'title' => 'Invoices whose currency is written in Arabic',
                'why' => 'Reports group by the currency code; "جنيه" is a different currency from EGP for them.',
                'queries' => array_map(fn ($t) => [
                    'sql' => "SELECT '{$t}' AS source, id, company_id, currency FROM {$t} WHERE currency REGEXP '[ء-ي]'",
                    'company' => 'company_id',
                ], ['customer_invoices', 'supplier_invoices']),
            ],
            [
                'title' => 'Invoice balances with more than two decimals',
                'why' => 'A balance of 0.004 shows as 0.00 but still counts as open.',
                'queries' => array_map(fn ($t) => [
                    'sql' => "SELECT '{$t}' AS source, id, company_id, net_balance FROM {$t} WHERE net_balance <> ROUND(net_balance, 2)",
                    'company' => 'company_id',
                ], ['customer_invoices', 'supplier_invoices']),
            ],
            [
                'title' => 'Settlements with an amount but no invoice',
                'why' => 'The settlement popup shows "N/A 0.00" next to a real amount.',
                'queries' => array_map(fn ($t) => [
                    'sql' => "SELECT '{$t}' AS source, id, company_id, settlement_amount FROM {$t} WHERE COALESCE(settlement_amount, 0) <> 0 AND invoice_id IS NULL",
                    'company' => 'company_id',
                ], ['settlements', 'payment_settlements']),
            ],
            [
                'title' => 'Settlements whose invoice no longer exists',
                'why' => 'Money is shown as settling an invoice nobody can open.',
                'queries' => [
                    ['sql' => "SELECT 'settlements' AS source, s.id, s.company_id, s.invoice_id FROM settlements s LEFT JOIN customer_invoices i ON i.id = s.invoice_id WHERE s.invoice_id IS NOT NULL AND i.id IS NULL", 'company' => 's.company_id'],
                    ['sql' => "SELECT 'payment_settlements' AS source, s.id, s.company_id, s.invoice_id FROM payment_settlements s LEFT JOIN supplier_invoices i ON i.id = s.invoice_id WHERE s.invoice_id IS NOT NULL AND i.id IS NULL", 'company' => 's.company_id'],
                ],
            ],
            [
                'title' => "Settlements on another company's invoice",
                'why' => 'Before the fix in IsMoney, a settlement could name any invoice id and reduce another company\'s balance.',
                'queries' => [
                    ['sql' => "SELECT 'settlements' AS source, s.id, s.company_id, i.company_id AS invoice_company_id FROM settlements s JOIN customer_invoices i ON i.id = s.invoice_id WHERE s.company_id <> i.company_id", 'company' => 's.company_id'],
                    ['sql' => "SELECT 'payment_settlements' AS source, s.id, s.company_id, i.company_id AS invoice_company_id FROM payment_settlements s JOIN supplier_invoices i ON i.id = s.invoice_id WHERE s.company_id <> i.company_id", 'company' => 's.company_id'],
                ],
            ],
            [
                'title' => 'Invoices whose net amount is not amount + VAT − discount',
                'why' => 'The settlement popup and the aging reports read the net amount.',
                'queries' => array_map(fn ($t) => [
                    'sql' => "SELECT '{$t}' AS source, id, company_id, invoice_amount, vat_amount, discount_amount, net_invoice_amount FROM {$t} WHERE vat_amount > 0 AND ABS(net_invoice_amount - (invoice_amount + vat_amount - COALESCE(discount_amount, 0))) > 0.01",
                    'company' => 'company_id',
                ], ['customer_invoices', 'supplier_invoices']),
            ],
            [
                'title' => 'Statement rows whose closing balance does not follow from the row',
                'why' => 'end_balance must be beginning_balance + debit − credit. Month-end interest rows get the interest in credit after end_balance was worked out (trigger order), so the statement total is short by that interest.',
                'queries' => array_map(fn ($t) => [
                    'sql' => "SELECT '{$t}' AS source, id, company_id, date, beginning_balance, debit, credit, end_balance FROM {$t} WHERE ABS(end_balance - (beginning_balance + COALESCE(debit, 0) - COALESCE(credit, 0))) > 0.01",
                    'company' => 'company_id',
                ], $statementTables),
            ],
            [
                'title' => 'Overdraft rates with a fraction of a percent',
                'why' => 'The statement triggers keep the rate as decimal(5,2) after dividing by 100, so 22.50% is charged as 23% — interest on these facilities is not what the contract says.',
                'queries' => array_map(fn ($t) => [
                    'sql' => "SELECT '{$t}' AS source, id, company_id, date, interest_rate, min_interest_rate FROM {$t} WHERE (interest_rate <> ROUND(interest_rate, 0) OR min_interest_rate <> ROUND(min_interest_rate, 0))",
                    'company' => 'company_id',
                ], $rateTables),
            ],
            [
                'title' => 'LG cash cover released on a future date',
                'why' => 'A cancellation movement dated after today makes today\'s cash cover look already free.',
                'queries' => [['sql' => "SELECT id, company_id, letter_of_guarantee_issuance_id, date FROM letter_of_guarantee_cash_cover_statements WHERE type = 'for-cancellation' AND date > CURDATE()", 'company' => 'company_id']],
            ],
            [
                'title' => 'Cancelled LGs whose cash cover was never released',
                'why' => 'The money stays frozen on the cash cover statement after the LG is gone.',
                'queries' => [['sql' => "SELECT i.id, i.company_id, i.cash_cover_amount FROM letter_of_guarantee_issuances i WHERE i.status = 'cancelled' AND i.cash_cover_amount > 0 AND NOT EXISTS (SELECT 1 FROM letter_of_guarantee_cash_cover_statements s WHERE s.letter_of_guarantee_issuance_id = i.id AND s.type = 'for-cancellation')", 'company' => 'i.company_id']],
            ],
            [
                'title' => 'LCs pointing at a purchase order that does not exist',
                'why' => 'The contract deletion guard follows LCs through purchase orders; such an LC is invisible to it.',
                'queries' => [['sql' => 'SELECT t.id, t.company_id, t.purchase_order_id FROM letter_of_credit_issuances t WHERE t.purchase_order_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM purchase_orders o WHERE o.id = t.purchase_order_id)', 'company' => 't.company_id']],
            ],
            [
                'title' => 'Odoo companies without a cheques-receivable account',
                'why' => 'Collecting a cheque cannot clear the cheques-receivable account in Odoo.',
                'queries' => [['sql' => 'SELECT company_id FROM odoo_settings WHERE IFNULL(cheques_receivable_id, 0) = 0', 'company' => 'company_id']],
            ],
            [
                'title' => 'Down-payment cheques with no advances account in Odoo',
                'why' => 'A down-payment cheque is posted to the advances account; without one Odoo refuses it.',
                'queries' => [
                    ['sql' => "SELECT 'customer' AS side, m.id, m.company_id FROM money_received m LEFT JOIN odoo_settings o ON o.company_id = m.company_id WHERE m.type = 'cheque' AND m.money_type = 'down-payment' AND IFNULL(o.advances_from_customers_id, 0) = 0 AND o.company_id IS NOT NULL", 'company' => 'm.company_id'],
                    ['sql' => "SELECT 'supplier' AS side, m.id, m.company_id FROM money_payments m LEFT JOIN odoo_settings o ON o.company_id = m.company_id WHERE m.type = 'payable_cheque' AND m.money_type = 'down-payment' AND IFNULL(o.advances_to_suppliers_id, 0) = 0 AND o.company_id IS NOT NULL", 'company' => 'm.company_id'],
                ],
            ],
        ];
    }
}
