<?php

namespace App\Support\Facilities;

use App\Models\CleanOverdraft;
use App\Models\FactoringContract;
use App\Models\FullySecuredOverdraft;
use App\Models\LetterOfCreditFacility;
use App\Models\LetterOfGuaranteeFacility;
use App\Models\OverdraftAgainstAssignmentOfContract;
use App\Models\OverdraftAgainstCommercialPaper;
use Illuminate\Support\Facades\DB;

/**
 * Gives every facility of a company that has no terms history its "original" chapter.
 *
 * The August 2026 migrations (2026_08_10_200000 … 2026_08_14_130000) did this once for every
 * facility that existed then; a facility created since gets it from its controller. A company
 * imported from the source system (company:import-from-source) arrives with neither, because the
 * source has no terms histories. This fills the gap through each model's own
 * createOriginalTermsHistory(), then tags the facility's untagged rate rows onto the new chapter,
 * exactly as the tagging migrations did.
 *
 * Idempotent: a facility that already has any chapter is skipped.
 */
class FacilityTermsHistoryBackfiller
{
    /** @var array<int, class-string> */
    public const MODELS = [
        CleanOverdraft::class,
        FullySecuredOverdraft::class,
        OverdraftAgainstCommercialPaper::class,
        OverdraftAgainstAssignmentOfContract::class,
        LetterOfGuaranteeFacility::class,
        LetterOfCreditFacility::class,
        FactoringContract::class,
    ];

    /**
     * Rate rows that belong to a chapter, as the tagging migrations set them up
     * (2026_08_11_180100, 2026_08_11_220100, 2026_08_14_120100). Only LC's
     * createOriginalTermsHistory() tags its own rows, so the rest is done here.
     *
     * @var array<class-string, array{table: string, foreign_key: string}>
     */
    private const RATE_ROWS = [
        OverdraftAgainstCommercialPaper::class => ['table' => 'lending_information', 'foreign_key' => 'overdraft_against_commercial_paper_id'],
        LetterOfGuaranteeFacility::class => ['table' => 'letter_of_guarantee_facility_term_and_conditions', 'foreign_key' => 'letter_of_guarantee_facility_id'],
        LetterOfCreditFacility::class => ['table' => 'letter_of_credit_facility_term_and_conditions', 'foreign_key' => 'letter_of_credit_facility_id'],
    ];

    /**
     * @return array<string, array{created: int, skipped_no_start_date: int}> keyed by table, only tables with facilities to fix
     */
    public function run(int $companyId): array
    {
        $report = [];

        foreach (self::MODELS as $modelClass) {
            $created = 0;
            $skipped = 0;

            $modelClass::query()
                ->where('company_id', $companyId)
                ->whereDoesntHave('termsHistories')
                ->orderBy('id')
                ->each(function ($facility) use (&$created, &$skipped, $modelClass) {
                    // effective_date is NOT NULL; the model has no fallback (the migrations used
                    // 2000-01-01). Leave these for the facility's first renewal, which creates
                    // the chapter itself.
                    if (blank($facility->contract_start_date)) {
                        $skipped++;

                        return;
                    }

                    $chapter = $facility->createOriginalTermsHistory();

                    if ($rateRows = self::RATE_ROWS[$modelClass] ?? null) {
                        DB::table($rateRows['table'])
                            ->where($rateRows['foreign_key'], $facility->id)
                            ->whereNull('terms_history_id')
                            ->update(['terms_history_id' => $chapter->id]);
                    }

                    $created++;
                });

            if ($created || $skipped) {
                $report[(new $modelClass)->getTable()] = [
                    'created' => $created,
                    'skipped_no_start_date' => $skipped,
                ];
            }
        }

        return $report;
    }
}
