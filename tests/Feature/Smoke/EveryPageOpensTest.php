<?php

namespace Tests\Feature\Smoke;

use App\Models\Company;
use App\Models\User;
use App\Support\SidebarMenu;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * Every screen in the sidebar opens — for a brand-new company with nothing in it yet, which is
 * exactly what a new customer sees on their first day, and where an empty list or a sum over
 * nothing most often breaks a page.
 *
 * Run: php artisan test --filter=EveryPageOpensTest
 */
class EveryPageOpensTest extends TestCase
{
    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        $this->company = $this->makeCompany();
        $this->admin = $this->makeSuperAdmin($this->company);
    }

    /** @return array<string, string> title => url of every link a super admin sees */
    private function sidebarLinks(): array
    {
        $links = [];
        $walk = function (array $entries) use (&$walk, &$links) {
            foreach ($entries as $key => $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                if (($entry['type'] ?? null) === 'link' && ($entry['show'] ?? false) && ! empty($entry['link'])) {
                    $links[($entry['title'] ?? $key).' ('.parse_url($entry['link'], PHP_URL_PATH).')'] = $entry['link'];
                }
                if (isset($entry['items'])) {
                    $walk($entry['items']);
                }
            }
        };

        $this->actingAs($this->admin);
        $walk(SidebarMenu::build($this->company, $this->admin));

        return $links;
    }

    public function test_every_sidebar_page_opens_for_a_new_empty_company(): void
    {
        $links = $this->sidebarLinks();
        $this->assertGreaterThan(20, count($links), 'The sidebar should offer a super admin every screen.');

        $broken = [];

        foreach ($links as $title => $url) {
            $response = $this->actingAs($this->admin)->get($url);
            $status = $response->getStatusCode();

            // A screen may send you on to its first tab; that page must open too.
            if (in_array($status, [301, 302], true)) {
                $location = (string) $response->headers->get('Location');
                if (! str_contains($location, '/login')) {
                    $status = $this->actingAs($this->admin)->get($location)->getStatusCode();
                }
            }

            if ($status !== 200) {
                $detail = $status >= 500 ? ' — '.mb_substr(strip_tags((string) ($response->exception?->getMessage() ?? '')), 0, 160) : '';
                $broken[] = "{$title}: {$status}{$detail}";
            }
        }

        $this->assertSame([], $broken, "These screens do not open for a new company:\n  ".implode("\n  ", $broken));
    }

    /** The language follows the address: /ar is Arabic and right-to-left, /en is English. */
    public function test_the_language_and_direction_follow_the_address(): void
    {
        foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $direction) {
            app()->setLocale($locale);

            $this->actingAs($this->admin)
                ->get(route('view.bank.statement', ['company' => $this->company->id]))
                ->assertOk()
                ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                    ->where('locale', $locale)
                    ->where('dir', $direction)
                    ->etc());
        }
    }

    /** A company that is not subscribed to CashVero cannot open its screens. */
    public function test_a_company_without_cashvero_cannot_open_its_screens(): void
    {
        $unsubscribed = $this->makeCompany([], cashVero: false);
        $user = $this->makeUser(['report_bank_statement.view'], $unsubscribed);

        $this->actingAs($user)
            ->get(route('view.bank.statement', ['company' => $unsubscribed->id]))
            ->assertForbidden();
    }
}
