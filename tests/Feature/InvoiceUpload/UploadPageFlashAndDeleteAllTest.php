<?php

namespace Tests\Feature\InvoiceUpload;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The upload page's own two fixes.
 *
 * FLASH: the page is Inertia. toastr() writes a php-flasher envelope,
 * and flasher's flash_bag bridge is disabled (config/flasher.php), so
 * nothing carried those messages into an Inertia response — they only
 * surfaced on the next FULL page load. That is the "the success message
 * only shows after I reload" report. HandleInertiaRequests reads
 * session('success') / session('fail'); AppLayout toasts them.
 *
 * DELETE ALL: deletes what it can and keeps back anything with money
 * recorded against it, saying how many and why.
 *
 * @see \App\Http\Controllers\SalesGatheringController::destroyAll()
 */
class UploadPageFlashAndDeleteAllTest extends TestCase
{
    /**
     * Controllers whose redirects land back on the Inertia upload page.
     */
    private const INERTIA_CONTROLLERS = [
        'SalesGatheringController',
        'DeletingClass',
    ];

    private function liveCode(string $controller): string
    {
        $lines = array_filter(
            file(app_path("Http/Controllers/{$controller}.php"), FILE_IGNORE_NEW_LINES),
            fn (string $line) => ! str_starts_with(ltrim($line), '//') && ! str_starts_with(ltrim($line), '*')
        );

        return implode("\n", $lines);
    }

    // ---------------------------------------------------------------
    // the flash channel
    // ---------------------------------------------------------------

    /**
     * @dataProvider inertiaControllerProvider
     */
    public function test_the_upload_pages_no_longer_flash_through_flasher(string $controller): void
    {
        $this->assertStringNotContainsString('toastr()->', $this->liveCode($controller), sprintf(
            '%s still uses toastr(); on an Inertia page that message only appears after a full reload.',
            $controller
        ));
    }

    public static function inertiaControllerProvider(): array
    {
        return array_map(fn ($controller) => [$controller], self::INERTIA_CONTROLLERS);
    }

    /**
     * A delete that worked must say so in green. It used to call
     * toastr()->ERROR('Deleted Successfully') — the wrong channel AND
     * the wrong colour.
     */
    public function test_a_successful_delete_flashes_success_not_fail(): void
    {
        $source = $this->liveCode('SalesGatheringController');

        $this->assertStringContainsString(
            "->with('success', __('Deleted Successfully'))",
            $source,
            'The single-row delete must flash success.'
        );
    }

    public function test_the_bulk_delete_flashes_success(): void
    {
        $this->assertStringContainsString(
            "->with('success', __('All Rows Were Deleted Successfully'))",
            $this->liveCode('DeletingClass')
        );
    }

    /**
     * The guards that refuse a delete have to reach the user too, and
     * on the failure channel.
     */
    public function test_a_refused_delete_flashes_fail(): void
    {
        $source = $this->liveCode('SalesGatheringController');

        $this->assertStringContainsString(
            "->with('fail', __('This installment has a payment recorded against it and can no longer be deleted.'))",
            $source
        );
    }

    /**
     * HandleInertiaRequests is what turns these into props. If it ever
     * stopped reading these keys, every message on every Inertia page
     * would go quiet — and nothing else would notice.
     */
    public function test_the_flash_keys_are_the_ones_inertia_actually_bridges(): void
    {
        $middleware = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));

        $this->assertStringContainsString("pull('success')", $middleware);
        $this->assertStringContainsString("pull('fail')", $middleware);
    }

    // ---------------------------------------------------------------
    // delete all
    // ---------------------------------------------------------------

    public function test_the_delete_all_route_exists_and_is_a_delete(): void
    {
        $this->assertTrue(Route::has('uploading.destroy.all'));

        $route = Route::getRoutes()->getByName('uploading.destroy.all');

        $this->assertContains('DELETE', $route->methods(),
            'A destructive action must not be reachable by following a link.');
    }

    public function test_the_delete_all_route_is_permission_gated(): void
    {
        $required = \App\Support\Permissions\RoutePermissionMap::for('uploading.destroy.all');

        $this->assertNotNull($required, 'Delete All must be gated.');

        foreach ($required as $permission) {
            $this->assertTrue(
                \App\Support\Permissions\PermissionRegistry::has($permission),
                "{$permission} is not in the registry, so nobody could ever be granted it."
            );
        }
    }

    /**
     * The route gate lets a user through on ANY of the three bulk-delete
     * rights; the action then has to check the one for the dataset
     * actually named in the URL, or someone with the loan-schedule right
     * could wipe the invoices.
     */
    public function test_the_action_rechecks_the_permission_for_the_named_dataset(): void
    {
        $source = $this->liveCode('SalesGatheringController');

        $this->assertMatchesRegularExpression(
            '/destroyAll\(.*?\)\s*\{.*?deletePermissionName.*?abort\(403/s',
            $source,
            'destroyAll must re-check the permission for the dataset in the URL before deleting.'
        );
    }

    /**
     * Deleting through the model is what keeps the statement and
     * balance cascades running; a query-builder mass delete would skip
     * every delete hook.
     */
    public function test_delete_all_goes_through_the_model_not_a_mass_delete(): void
    {
        $source = $this->liveCode('SalesGatheringController');

        $this->assertMatchesRegularExpression('/foreach \(\$modelClass::whereIn\(.*?\)->get\(\) as \$row\) \{\s*\$row->delete\(\);/s',
            $source, 'Rows must be deleted one at a time through the model.');
        $this->assertDoesNotMatchRegularExpression('/\$modelClass::where\(\'company_id\'[^;]*\)->delete\(\)/', $source,
            'A mass delete here would skip every model hook.');
    }

    public function test_the_page_is_told_where_to_send_the_delete_and_how_many_rows_there_are(): void
    {
        $source = $this->liveCode('SalesGatheringController');

        $this->assertStringContainsString("'deleteAllUrl' => route('uploading.destroy.all'", $source);
        $this->assertStringContainsString("'totalRows' => \$salesGatherings->total()", $source,
            'The confirmation names the whole dataset, not the current page.');
    }

    public function test_the_page_offers_the_button_and_warns_before_it_is_pressed(): void
    {
        $page = file_get_contents(resource_path('js/Pages/InvoiceUpload/Index.vue'));

        $this->assertStringContainsString('deleteAllUrl', $page);
        $this->assertStringContainsString('Delete All', $page);
        $this->assertStringContainsString('confirmingDeleteAll', $page, 'It must ask before wiping a dataset.');
        $this->assertStringContainsString('router.delete(props.deleteAllUrl', $page);
        $this->assertStringContainsString('money recorded against it', $page,
            'The user should know rows will be kept back BEFORE pressing it, not only after.');
    }
}
