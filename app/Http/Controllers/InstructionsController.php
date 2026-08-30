<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Support\Instructions\PageInstructions;
use Inertia\Inertia;

/**
 * InstructionsController
 * ------------------------------------------------------------------
 * Serves the written guide behind each screen's "Instructions" button.
 *
 * One controller and one Vue page for every guide: the content comes
 * from PageInstructions, so adding a guide for another screen is a new
 * array entry there and a button on that screen — no new route, no new
 * component.
 *
 * Read-only. It shows no company data at all, only text, which is why
 * it needs no permission of its own beyond being signed in.
 */
class InstructionsController
{
    public function show(Company $company, string $page)
    {
        abort_unless(PageInstructions::has($page), 404);

        $content = PageInstructions::get($page);

        return Inertia::render('Instructions/Show', [
            'company' => ['id' => $company->id],
            'pageKey' => $page,
            'title' => $content['title'],
            'summary' => $content['summary'],
            'sections' => $content['sections'],
            /**
             * Where "Back" goes. Sent from the server so the guide can
             * be opened from anywhere and still return to the right
             * screen, rather than relying on browser history.
             */
            'backUrl' => $this->backUrlFor($company, $page),
        ]);
    }

    private function backUrlFor(Company $company, string $page): string
    {
        return match ($page) {
            PageInstructions::MONEY_RECEIVED_FORM,
            PageInstructions::MONEY_RECEIVED_DOWN_PAYMENT,
            PageInstructions::MONEY_RECEIVED_INDEX => route('view.money.receive', ['company' => $company->id]),
            default => route('home', ['company' => $company->id]),
        };
    }
}
