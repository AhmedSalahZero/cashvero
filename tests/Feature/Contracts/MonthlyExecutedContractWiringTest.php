<?php

namespace Tests\Feature\Contracts;

use App\Http\Requests\StoreContractRequest;
use App\Models\Contract;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Everything that has to agree for the monthly-executed flag to work
 * end to end: the column, the model, the cash flow forecast, the
 * validation rule that would otherwise block saving, and the form that
 * hides the now-meaningless Orders section.
 *
 * @see \App\Support\Contracts\MonthlyExecutionSchedule
 */
class MonthlyExecutedContractWiringTest extends TestCase
{
    private function trait(): string
    {
        return file_get_contents(app_path('Traits/Models/HasForecastedProjectCollection.php'));
    }

    private function form(): string
    {
        return file_get_contents(resource_path('js/Pages/Contracts/Form.vue'));
    }

    /**
     * Only the amount rule — the code rule is a Rule::unique() that
     * needs the contracts table, and it is not what these assertions
     * are about.
     */
    private function amountValidator(array $data): \Illuminate\Validation\Validator
    {
        $request = $this->request($data);
        $request->prepareForValidation();
        $rules = $request->rules();

        return Validator::make($request->all(), ['amount' => $rules['amount']]);
    }

    private function request(array $data): StoreContractRequest
    {
        $request = StoreContractRequest::create('/', 'POST', $data);
        $request->setRouteResolver(function () {
            $route = new Route(['POST'], '/', []);
            $route->parameters = ['company' => 148, 'type' => 'Customer'];

            return $route;
        });

        return $request;
    }

    // ---------------------------------------------------------------
    // the flag
    // ---------------------------------------------------------------

    public function test_the_model_reports_whether_a_contract_is_monthly_executed(): void
    {
        $monthly = new Contract(['is_monthly_executed' => 1]);
        $monthly->is_monthly_executed = 1;
        $this->assertTrue($monthly->isMonthlyExecuted());

        $normal = new Contract;
        $this->assertFalse($normal->isMonthlyExecuted(), 'Existing contracts must keep their current behaviour.');
    }

    public function test_the_flag_defaults_to_off_in_the_migration(): void
    {
        $migration = collect(glob(database_path('migrations/*add_is_monthly_executed_to_contracts.php')))->first();

        $this->assertNotNull($migration, 'No migration adds the column.');
        $body = file_get_contents($migration);
        $this->assertStringContainsString("boolean('is_monthly_executed')", $body);
        $this->assertStringContainsString('->default(0)', $body, 'Every existing contract must stay non-monthly.');
    }

    // ---------------------------------------------------------------
    // the cash flow forecast
    // ---------------------------------------------------------------

    public function test_the_forecast_branches_on_the_flag_before_reading_orders(): void
    {
        $trait = $this->trait();

        $this->assertStringContainsString('if ($contract->isMonthlyExecuted()) {', $trait);
        $this->assertStringContainsString('applyMonthlyExecutedContractBalance(', $trait);
        $this->assertMatchesRegularExpression(
            '/isMonthlyExecuted\(\)\).*?continue;.*?foreach \(\$contract->\{\$orderRelation\}/s',
            $trait,
            'A monthly contract must skip the order loop entirely, not run both paths.'
        );
    }

    /**
     * This used to assert the opposite: a monthly contract was picked
     * up on period overlap while a NORMAL one had to END inside the
     * report window — "because it pays out on one date".
     *
     * That premise was itself the bug. An ordinary contract only paid
     * out on one date because applyForecastedOrderBalance() read a
     * single execution phase and dropped the whole order amount on it.
     * Now every phase lands in its own bucket, so an ordinary contract
     * pays out across its phases exactly like a monthly one pays out
     * across its months, and end_date <= $endDate would hide a
     * contract whose phases are all collected inside the window.
     *
     * There is no correct contract-level date filter to replace it
     * with either: phase dates live on the ORDER
     * (end_date_N + collection_days_N), not on contracts.*. So the
     * selection carries no date filter at all, and the window is
     * applied per slice — see the two tests below.
     */
    public function test_contract_selection_carries_no_date_filter(): void
    {
        $trait = $this->trait();

        // من أول بناء الاستعلام لحد ->get() — الجزء اللي بيختار العقود
        $needle = '$contracts = Contract::where(';
        $from = strpos($trait, $needle);
        $this->assertNotFalse($from, 'The contract selection query moved or was renamed.');
        $to = strpos($trait, '->get();', $from);
        $this->assertNotFalse($to, 'The contract selection query is no longer terminated by ->get().');
        $selection = substr($trait, $from, $to - $from);

        $this->assertStringNotContainsString('end_date', $selection, 'A contract must not be dropped by its own end_date.');
        $this->assertStringNotContainsString('start_date', $selection, 'A contract must not be dropped by its own start_date.');
        $this->assertStringNotContainsString('is_monthly_executed', $selection, 'Both kinds are now selected the same way.');
    }

    /**
     * The window moved from the contract query down to each slice.
     * A normal contract filters phase by phase on its collection date.
     */
    public function test_an_ordinary_phase_is_filtered_on_its_own_collection_date(): void
    {
        $this->assertStringContainsString(
            '->between($startDate, $endDate)',
            $this->trait(),
            'Each execution phase must be checked against the window on its own.'
        );
    }

    public function test_the_monthly_row_uses_the_shared_schedule(): void
    {
        $this->assertStringContainsString('MonthlyExecutionSchedule::forContract(', $this->trait());
    }

    /**
     * Slices outside the report window still count toward the divisor.
     * Dividing only by the months visible in the window would inflate
     * every instalment — a year's remainder split across a three-month
     * view instead of across the twelve months it is really spread over.
     */
    public function test_slices_outside_the_window_are_skipped_not_redistributed(): void
    {
        $this->assertStringContainsString(
            'if (! isset($datesWithWeekNumber[$sliceDate])) {',
            $this->trait(),
            'Out-of-window months must be dropped at display time, after the split.'
        );
    }

    public function test_the_monthly_row_respects_the_display_currency(): void
    {
        $this->assertStringContainsString(
            'ForeignExchangeRate::getExchangeRateForDisplayCurrency(',
            $this->trait(),
            'The monthly row must not reintroduce the unconditional main-currency conversion.'
        );
    }

    // ---------------------------------------------------------------
    // validation
    // ---------------------------------------------------------------

    public function test_a_monthly_contract_saves_without_any_orders(): void
    {
        $validator = $this->amountValidator([
            'amount' => '1200000',
            'is_monthly_executed' => true,
            'salesOrders' => [],
        ]);

        $this->assertTrue(
            $validator->passes(),
            'Orders total zero would fail the equality rule: '.implode(' / ', $validator->errors()->all())
        );
    }

    public function test_a_normal_contract_still_has_to_match_its_orders(): void
    {
        $validator = $this->amountValidator([
            'amount' => '1200000',
            'is_monthly_executed' => false,
            'salesOrders' => [['amount' => '500000', 'so_number' => 'SO-1']],
        ]);

        $this->assertFalse($validator->passes(), 'The existing totals rule must be untouched for normal contracts.');
    }

    public function test_a_monthly_contract_still_needs_a_real_amount(): void
    {
        foreach (['0', ''] as $amount) {
            $this->assertFalse(
                $this->amountValidator([
                    'amount' => $amount,
                    'is_monthly_executed' => true,
                    'salesOrders' => [],
                ])->passes(),
                "Amount {$amount} must still be rejected; it is the number the whole schedule divides."
            );
        }
    }

    // ---------------------------------------------------------------
    // the form
    // ---------------------------------------------------------------

    public function test_the_form_offers_the_flag(): void
    {
        $this->assertStringContainsString('v-model="form.is_monthly_executed"', $this->form());
        $this->assertStringContainsString('Monthly Executed', $this->form());
    }

    public function test_the_orders_section_is_hidden_for_a_monthly_contract(): void
    {
        $this->assertStringContainsString(
            '<div v-if="!isMonthlyExecuted" class="cvr-card">',
            $this->form(),
            'The Orders section has no meaning once the value is spread by month.'
        );
    }

    /**
     * Omitting the relation key entirely (rather than sending an empty
     * array) is what leaves an existing contract's orders alone, so
     * unticking the box restores them instead of losing them.
     */
    public function test_orders_are_omitted_rather_than_cleared_when_monthly(): void
    {
        $form = $this->form();

        $this->assertStringContainsString('if (!isMonthlyExecuted.value) {', $form);
        $this->assertStringContainsString(
            'payload[props.salesOrderOrPurchaseOrderRelationName] = ordersPayload;',
            $form
        );
    }

    public function test_the_totals_warning_is_silenced_for_a_monthly_contract(): void
    {
        $this->assertStringContainsString(
            '!form.value.is_monthly_executed &&',
            $this->form(),
            'A monthly contract has no orders to match, so the mismatch warning would always show.'
        );
    }

    public function test_the_controller_sends_the_flag_back_when_editing(): void
    {
        $this->assertStringContainsString(
            "'is_monthly_executed' => \$model->isMonthlyExecuted(),",
            file_get_contents(app_path('Http/Controllers/ContractsController.php')),
            'Without this the checkbox resets itself every time the contract is opened.'
        );
    }
}
