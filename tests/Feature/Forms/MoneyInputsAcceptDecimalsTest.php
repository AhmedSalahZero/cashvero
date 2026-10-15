<?php

namespace Tests\Feature\Forms;

use Tests\TestCase;

/**
 * <input type="number"> with no step attribute defaults to step="1",
 * so the browser refuses any fractional value: typing 3300.50 into the
 * LG amount produced "Please enter a valid value" and the form would
 * not submit. The database columns are decimal(14,2) and the validation
 * rules are just required|gt:0 — the block was purely the missing
 * attribute.
 *
 * Guards every money field on every form, so a new one cannot be added
 * without a step.
 */
class MoneyInputsAcceptDecimalsTest extends TestCase
{
    /** Fields that hold money, a rate, or a percentage. */
    private const MONEY_FIELDS = [
        'limit', 'issuance_fees', 'outstanding_balance', 'amount', 'lg_amount', 'lc_amount',
        'admin_fees_rate', 'highest_debt_balance_rate', 'min_commission_fees', 'min_lg_commission_fees',
        'min_lc_commission_fees', 'break_charge_amount', 'break_interest_amount', 'actual_interest_amount',
        'amount_to_be_decreased', 'periodic_interest_amount', 'allocation_amount', 'lc_remaining_amount',
        'max_lending_limit_per_contract', 'max_lending_limit_per_customer',
    ];

    /** Genuinely whole numbers — a step of 1 is correct for these. */
    private const COUNT_FIELDS = [
        'to_be_setteled_max_within_days', 'duration', 'lc_duration_days', 'remaining_installment_count',
        'for_commercial_papers_due_within_days', 'clearance_days', 'financing_duration', 'max_users',
        'odoo_chart_of_account_number',
    ];

    /** @return array<int, array{file: string, field: string, tag: string}> */
    private function numberInputs(): array
    {
        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('js')));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }
            $body = file_get_contents($file->getPathname());
            preg_match_all('/<input\b[^>]*type="number"[^>]*>/s', $body, $matches);

            foreach ($matches[0] as $tag) {
                if (! preg_match('/v-model(?:\.number)?="([^"]+)"/', $tag, $model)) {
                    continue;
                }
                $parts = explode('.', $model[1]);
                $found[] = [
                    'file' => str_replace(resource_path('js').'/', '', $file->getPathname()),
                    'field' => end($parts),
                    'tag' => $tag,
                ];
            }
        }

        return $found;
    }

    public function test_the_scan_actually_finds_the_forms(): void
    {
        $this->assertGreaterThan(
            250,
            count($this->numberInputs()),
            'A scan that finds nothing would make every assertion below pass vacuously.'
        );
    }

    public function test_every_money_field_accepts_decimals(): void
    {
        $blocked = [];
        foreach ($this->numberInputs() as $input) {
            if (! in_array($input['field'], self::MONEY_FIELDS, true)) {
                continue;
            }
            if (! str_contains($input['tag'], 'step=')) {
                $blocked[] = "{$input['file']} -> {$input['field']}";
            }
        }

        $this->assertSame([], array_values(array_unique($blocked)), 'These reject any fractional amount in the browser.');
    }

    public function test_no_money_field_is_pinned_to_whole_numbers(): void
    {
        $pinned = [];
        foreach ($this->numberInputs() as $input) {
            if (! in_array($input['field'], self::MONEY_FIELDS, true)) {
                continue;
            }
            if (preg_match('/step="(\d+)"/', $input['tag'], $step) && (int) $step[1] >= 1) {
                $pinned[] = "{$input['file']} -> {$input['field']} (step={$step[1]})";
            }
        }

        $this->assertSame([], array_values(array_unique($pinned)), 'step="1" blocks decimals just as surely as no step at all.');
    }

    /** The two fields reported as broken. */
    public function test_the_reported_fields_are_fixed(): void
    {
        foreach (['lg_amount', 'outstanding_balance'] as $field) {
            $inputs = array_filter($this->numberInputs(), fn ($i) => $i['field'] === $field);
            $this->assertNotEmpty($inputs, "No input found for {$field}; the scan or the field name is wrong.");

            foreach ($inputs as $input) {
                $this->assertStringContainsString('step=', $input['tag'], "{$input['file']} -> {$field} still blocks decimals.");
            }
        }
    }

    public function test_counters_are_left_as_whole_numbers(): void
    {
        $loosened = [];
        foreach ($this->numberInputs() as $input) {
            if (! in_array($input['field'], self::COUNT_FIELDS, true)) {
                continue;
            }
            if (preg_match('/step="(any|0\.\d+)"/', $input['tag'])) {
                $loosened[] = "{$input['file']} -> {$input['field']}";
            }
        }

        $this->assertSame([], array_values(array_unique($loosened)), 'Day counts and installment counts are not money; they stay whole.');
    }
}
