<?php

namespace Tests\Feature\LetterOfGuarantee;

use App\Enums\LgTypes;
use App\Models\LetterOfGuaranteeFacilityTermAndCondition;
use Tests\TestCase;

/**
 * The Renew dialog's "New Term & Conditions — by LG Type" matrix.
 *
 * Reported as "only Bid Bond shows in the LG Type column". The matrix
 * was built from the facility's existing term rows, and each row's key
 * was recovered by matching its DISPLAY LABEL against LgTypes — two
 * labels produced by different code, which agree for exactly one type.
 * The three that missed all became key '', collapsed under one Vue
 * :key, and rendered as a single row.
 *
 * These tests pin the two halves of the fix: the label round-trip is
 * genuinely unusable, and the raw key is what gets sent instead.
 *
 * @see \App\Http\Controllers\LetterOfGuaranteeFacilityController::index()
 */
class LgFacilityRenewMatrixTest extends TestCase
{
    /**
     * Why the lookup could never have worked. If someone "fixes" the
     * two label sources to agree later, this test fails and points at
     * the dialog that no longer needs the workaround — rather than the
     * bug quietly coming back the next time someone reaches for a label.
     */
    public function test_the_display_label_does_not_identify_an_lg_type(): void
    {
        $labels = LgTypes::getAll();
        $resolvedByLabel = [];

        foreach (array_keys($labels) as $lgType) {
            $formatted = camelizeWithSpace($lgType);
            $resolvedByLabel[$lgType] = array_search($formatted, $labels, true);
        }

        $this->assertSame(LgTypes::BID_BOND, $resolvedByLabel[LgTypes::BID_BOND],
            'Bid Bond is the one type whose two labels agree — which is exactly why it was the only row that showed.');

        foreach ([LgTypes::FINAL_LGS, LgTypes::ADVANCED_PAYMENT_LGS, LgTypes::PERFORMANCE_LG] as $lgType) {
            $this->assertFalse($resolvedByLabel[$lgType], sprintf(
                '%s: camelizeWithSpace() says "%s" but LgTypes says "%s" — a label cannot be used as a key.',
                $lgType, camelizeWithSpace($lgType), $labels[$lgType]
            ));
        }
    }

    /**
     * The row now carries its own raw key, so the dialog never has to
     * guess it back from a label.
     */
    public function test_a_term_row_exposes_its_raw_lg_type(): void
    {
        foreach (array_keys(LgTypes::getAll()) as $lgType) {
            $term = new LetterOfGuaranteeFacilityTermAndCondition(['lg_type' => $lgType]);

            $this->assertSame($lgType, $term->getLgType());
            $this->assertArrayHasKey($term->getLgType(), LgTypes::getAll(),
                'getLgType() must return a key the front-end can look the label up with.');
        }
    }

    /**
     * The payload the Renew dialog reads has to include lg_type on
     * every term row. Guards the controller shape without needing the
     * whole facility fixture: if the key is dropped from the mapping,
     * the dialog silently falls back to blank rows again.
     */
    public function test_the_controller_sends_lg_type_on_every_term_row(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/LetterOfGuaranteeFacilityController.php'));

        $formattedCount = substr_count($source, "'lg_type_formatted' => \$tc->getLgTypeFormatted()");
        $rawCount = substr_count($source, "'lg_type' => \$tc->getLgType()");

        $this->assertGreaterThan(0, $formattedCount);
        $this->assertSame($formattedCount, $rawCount,
            'Every term row that sends a formatted label must send the raw lg_type beside it.');
    }

    /**
     * A renewal must offer EVERY LG type, not only the ones the
     * facility already has terms for: an LG issued after the renewal in
     * a type the matrix skipped would have no rate at all.
     */
    public function test_the_renew_dialog_builds_one_row_per_lg_type(): void
    {
        $source = file_get_contents(resource_path('js/Pages/LetterOfGuaranteeFacility/Index.vue'));

        $this->assertStringContainsString('blankRenewTermAndConditions().map(', $source,
            'The matrix must start from every LG type and only then pre-fill from existing rows.');
        $this->assertStringNotContainsString('lgTypes[k] === tc.lg_type_formatted', $source,
            'The label-matching lookup is what collapsed three rows into one.');
    }
}
