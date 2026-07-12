<?php

namespace Tests\Feature\NonBanking;

use App\Models\NonBankingService\Study;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BalanceSheetTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_it_can_display_balance_sheet_page()
    {
		$study = Study::find(109);
		$company = $study->company;
        $response = $this->get(route('non_banking_services.balance_sheet.view', [
            'company' => $company->id,
            'study' => $study->id,
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('non_banking_services.balance_sheet.view');
        $response->assertViewHas('company');
        $response->assertViewHas('study');
    }
}
