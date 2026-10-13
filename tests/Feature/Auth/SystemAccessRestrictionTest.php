<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

class SystemAccessRestrictionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.allowed_email' => 'mahmoud.youssef@squadbcc.com']);
        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
            HandleInertiaRequests::class,
        ]);

        Route::middleware(['web', 'auth'])->get('/__test/system-access', fn () => response('ok'));
    }

    public function test_only_the_allowed_email_can_access_authenticated_routes(): void
    {
        $user = new User;
        $user->email = 'mahmoud.youssef@squadbcc.com';

        $this->actingAs($user)
            ->get('/__test/system-access')
            ->assertOk()
            ->assertSeeText('ok');
    }

    public function test_existing_sessions_for_other_users_are_logged_out(): void
    {
        $user = new User;
        $user->email = 'another.user@example.com';

        $response = $this->actingAs($user)->get('/__test/system-access');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_disabling_restriction_restores_access_for_other_users(): void
    {
        config(['auth.restrict_to_allowed_email' => false]);

        $user = new User;
        $user->email = 'another.user@example.com';

        $this->actingAs($user)
            ->get('/__test/system-access')
            ->assertOk()
            ->assertSeeText('ok');
    }

    public function test_login_rejects_emails_other_than_the_allowed_account(): void
    {
        $this->post(route('login'), [
            'email' => 'another.user@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    }
}
