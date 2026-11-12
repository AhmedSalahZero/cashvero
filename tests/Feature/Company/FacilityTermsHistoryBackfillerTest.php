<?php

namespace Tests\Feature\Company;

use App\Support\Facilities\FacilityTermsHistoryBackfiller;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A company imported from the source system arrives without facility terms histories (the source
 * has none). The backfiller gives each facility its original chapter, tags its rate rows onto it,
 * and leaves facilities that already have a chapter alone.
 */
class FacilityTermsHistoryBackfillerTest extends TestCase
{
    private function plantLgFacility(int $companyId, ?string $startDate = '2026-01-01'): int
    {
        $facilityId = (int) DB::table('letter_of_guarantee_facilities')->insertGetId([
            'company_id' => $companyId,
            'name' => 'Imported LG Facility',
            'currency' => 'EGP',
            'limit' => 1000000,
            'contract_start_date' => $startDate,
            'contract_end_date' => '2026-12-31',
        ]);

        DB::table('letter_of_guarantee_facility_term_and_conditions')->insert([
            'company_id' => $companyId,
            'letter_of_guarantee_facility_id' => $facilityId,
        ]);

        return $facilityId;
    }

    private function chapters(int $facilityId)
    {
        return DB::table('letter_of_guarantee_facility_terms_histories')->where('letter_of_guarantee_facility_id', $facilityId)->get();
    }

    public function test_a_facility_without_history_gets_its_original_chapter_and_its_rates_are_tagged(): void
    {
        $company = $this->makeCompany();
        $facilityId = $this->plantLgFacility($company->id);

        $report = app(FacilityTermsHistoryBackfiller::class)->run($company->id);

        $chapters = $this->chapters($facilityId);
        $this->assertCount(1, $chapters);
        $this->assertSame(1, (int) $chapters->first()->is_original);
        $this->assertSame('2026-01-01', $chapters->first()->effective_date);
        $this->assertSame(1, $report['letter_of_guarantee_facilities']['created']);

        $this->assertSame(
            0,
            DB::table('letter_of_guarantee_facility_term_and_conditions')
                ->where('letter_of_guarantee_facility_id', $facilityId)
                ->where(fn ($q) => $q->whereNull('terms_history_id')->orWhere('terms_history_id', '!=', $chapters->first()->id))
                ->count(),
            'every rate row belongs to the new chapter'
        );
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $company = $this->makeCompany();
        $facilityId = $this->plantLgFacility($company->id);
        $backfiller = app(FacilityTermsHistoryBackfiller::class);

        $backfiller->run($company->id);
        $second = $backfiller->run($company->id);

        $this->assertSame([], $second);
        $this->assertCount(1, $this->chapters($facilityId));
    }

    public function test_a_facility_without_a_start_date_is_skipped_not_crashed(): void
    {
        $company = $this->makeCompany();
        $facilityId = $this->plantLgFacility($company->id, null);

        $report = app(FacilityTermsHistoryBackfiller::class)->run($company->id);

        $this->assertCount(0, $this->chapters($facilityId));
        $this->assertSame(1, $report['letter_of_guarantee_facilities']['skipped_no_start_date']);
    }

    public function test_other_companies_are_not_touched(): void
    {
        $company = $this->makeCompany();
        $other = $this->makeCompany();
        $otherFacilityId = $this->plantLgFacility($other->id);

        app(FacilityTermsHistoryBackfiller::class)->run($company->id);

        $this->assertCount(0, $this->chapters($otherFacilityId));
    }
}
