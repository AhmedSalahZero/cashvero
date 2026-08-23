<?php

/**
 * Builds tests/e2e/mobile/catalog.json — every Inertia page URL the
 * mobile Playwright audit should visit.
 *
 * Sources:
 *  - SidebarMenu inertia links (primary app navigation)
 *  - Auth pages
 *  - Super-admin Companies / Users / Roles
 *  - Representative create + edit URLs when seed data exists
 *
 * Missing seed data ⇒ entry marked optional (audit skips, not fails).
 *
 * Run: php tests/e2e/mobile/page-catalog.php
 */

use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\User;
use App\Support\SidebarMenu;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../../../scripts/_bootstrap.php';
require __DIR__.'/../../../vendor/autoload.php';
$app = require_once __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$outPath = __DIR__.'/catalog.json';
$pages = [];

function addPage(array &$pages, string $id, string $title, string $url, ?string $page = null, bool $optional = false): void
{
    if ($url === '' || $url === '#') {
        return;
    }
    // Prefer path+query so Playwright baseURL applies cleanly.
    $parts = parse_url($url);
    $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    $pages[$id] = [
        'id' => $id,
        'title' => $title,
        'url' => $path,
        'page' => $page,
        'optional' => $optional,
    ];
}

function safeRoute(string $name, array $params = []): ?string
{
    try {
        if (! Route::has($name)) {
            return null;
        }

        return route($name, $params, false);
    } catch (Throwable $e) {
        return null;
    }
}

$user = User::whereHas('roles', fn ($q) => $q->where('roles.id', 1))->first() ?? User::first();
$company = Company::first();

if (! $user) {
    fwrite(STDERR, "No user found — cannot build catalog.\n");
    exit(1);
}
if (! $company) {
    fwrite(STDERR, "No company found — cannot build catalog.\n");
    exit(1);
}

auth()->login($user);
$companyId = $company->id;

echo "Catalog for user #{$user->id} ({$user->email}), company #{$companyId}\n";

/* ── Auth (no session needed for the pages themselves; audit visits after logout optional) ── */
addPage($pages, 'auth-login', 'Login', '/en/login', 'Auth/Login');
addPage($pages, 'auth-register', 'Register', '/en/register', 'Auth/Register', true);
addPage($pages, 'auth-forgot', 'Forgot Password', '/en/password/reset', 'Auth/ForgotPassword', true);

/* ── Home / company picker ── */
if ($home = safeRoute('home')) {
    addPage($pages, 'home', 'Home / Company Picker', $home, 'Home/CompanyPicker');
}

/* ── Super admin ── */
if ($url = safeRoute('companySection.index')) {
    addPage($pages, 'sa-companies', 'Companies', $url, 'SuperAdmin/Companies/Index');
}
if ($url = safeRoute('companySection.create')) {
    addPage($pages, 'sa-companies-create', 'Create Company', $url, 'SuperAdmin/Companies/Form', true);
}
if ($url = safeRoute('user.index')) {
    addPage($pages, 'sa-users', 'Users (global)', $url, 'SuperAdmin/Users/Index');
}
if ($url = safeRoute('roles.index', ['company' => $companyId])) {
    addPage($pages, 'roles', 'Roles', $url, 'SuperAdmin/Roles/Index');
}
if ($url = safeRoute('user.index', ['company' => $companyId])) {
    addPage($pages, 'company-users', 'Users (company)', $url, 'SuperAdmin/Users/Index');
}

/* ── Sidebar inertia links ── */
$menu = SidebarMenu::build($company, $user);
foreach ($menu as $sectionKey => $section) {
    if ($sectionKey === 'home') {
        continue;
    }
    $items = $section['items'] ?? [];
    foreach ($items as $i => $item) {
        if (($item['type'] ?? '') === 'action') {
            continue;
        }
        if (! ($item['inertia'] ?? false)) {
            // Still include Blade-linked sidebar items when they resolve —
            // many are already Inertia despite the flag lagging.
            // Prefer only inertia:true for the "all Inertia pages" scope.
            continue;
        }
        if (! ($item['show'] ?? false)) {
            continue;
        }
        $id = 'sidebar-'.$sectionKey.'-'.$i;
        addPage($pages, $id, $item['title'] ?? $id, $item['link'] ?? '', null, false);
    }
}

/* ── Also include non-inertia sidebar links that are known Inertia pages
     (flag lag in SidebarMenu — treasury / LG / reports often already Vue). ── */
$extraSidebarRoutes = [
    ['view.cashflow.report', [], 'Cash Flow Report'],
    ['view.contract.cashflow.report', [], 'Contract Cash Flow Report'],
    ['reports.consolidated-cash-flow.index', [], 'Consolidated Cash Flow'],
    ['factoring.with-recourse.index', [], 'Factoring With Recourse'],
    ['factoring.without-recourse.index', [], 'Factoring Without Recourse'],
    ['lc-settlement-internal-money-transfers.index', [], 'LC Settlement Transfer'],
    ['view.cash.expense', [], 'Cash Expense'],
    ['internal-money-transfers.index', [], 'Internal Money Transfer'],
    ['buy-or-sell-currencies.index', [], 'Buy Or Sell Currency'],
    ['view.foreign.exchange.rate', [], 'Foreign Exchange Rate'],
    ['view.letter.of.guarantee.issuance', [], 'LG Issuance'],
    ['view.letter.of.credit.issuance', [], 'LC Issuance'],
    ['branches.index', [], 'Safe Accounts'],
    ['odoo-settings.index', [], 'Odoo Settings'],
    ['profile.edit', [], 'Profile'],
    ['opening-balance.manage', [], 'Opening Balance Manage'],
];

foreach ($extraSidebarRoutes as [$name, $extra, $title]) {
    $params = array_merge(['company' => $companyId], $extra);
    if ($name === 'profile.edit') {
        $params = [];
    }
    if ($url = safeRoute($name, $params)) {
        addPage($pages, 'extra-'.str_replace('.', '-', $name), $title, $url, null, true);
    }
}

/* ── Facility hubs that need a financial institution ── */
$fi = FinancialInstitution::where('company_id', $companyId)->orderBy('id')->first();
if ($fi) {
    $fiParams = ['company' => $companyId, 'financialInstitution' => $fi->id];
    $facilityRoutes = [
        ['view.clean.overdraft', 'Clean Overdraft'],
        ['view.fully.secured.overdraft', 'Fully Secured Overdraft'],
        ['view.overdraft.against.commercial.paper', 'Overdraft Against CP'],
        ['view.overdraft.against.assignment.of.contract', 'Overdraft Against Assignment'],
        ['view.letter.of.guarantee.facility', 'LG Facility'],
        ['view.letter.of.credit.facility', 'LC Facility'],
        ['view.all.bank.accounts', 'Bank Accounts (FI)'],
        ['view.time.of.deposits', 'Time Of Deposits'],
        ['view.certificates.of.deposits', 'Certificates Of Deposits'],
        ['view.medium.term.loans', 'Medium Term Loans'],
        ['view.leasing.contracts', 'Leasing Contracts'],
        ['view.factoring.contracts', 'Factoring Contracts'],
    ];
    foreach ($facilityRoutes as [$name, $title]) {
        if ($url = safeRoute($name, $fiParams)) {
            addPage($pages, 'fi-'.str_replace('.', '-', $name), $title, $url, null, true);
        }
    }

    // Create forms
    $createRoutes = [
        ['create.clean.overdraft', 'Create Clean Overdraft'],
        ['create.fully.secured.overdraft', 'Create Fully Secured Overdraft'],
        ['create.overdraft.against.commercial.paper', 'Create OCP'],
        ['create.overdraft.against.assignment.of.contract', 'Create OAC'],
        ['create.letter.of.guarantee.facility', 'Create LG Facility'],
        ['create.letter.of.credit.facility', 'Create LC Facility'],
        ['create.time.of.deposit', 'Create TD'],
        ['create.certificates.of.deposit', 'Create CD'],
        ['create.medium.term.loan', 'Create MTL'],
    ];
    foreach ($createRoutes as [$name, $title]) {
        if ($url = safeRoute($name, $fiParams)) {
            addPage($pages, 'create-'.str_replace('.', '-', $name), $title, $url, null, true);
        }
    }
}

/* ── Money received / payment create ── */
foreach ([
    ['create.money.receive', 'Create Money Received'],
    ['create.money.payment', 'Create Money Payment'],
    ['create.cash.expense', 'Create Cash Expense'],
    ['create.internal-money-transfer', 'Create Internal Transfer'],
    ['create.buy-or-sell-currency', 'Create Buy/Sell Currency'],
] as [$name, $title]) {
    // route names vary — try a few conventions
    $candidates = [$name, str_replace('.', '-', $name), str_replace('-', '.', $name)];
    foreach ($candidates as $candidate) {
        if ($url = safeRoute($candidate, ['company' => $companyId])) {
            addPage($pages, 'create-'.md5($candidate), $title, $url, null, true);
            break;
        }
    }
}

/* ── Contracts list (customer/supplier) ── */
foreach (['Customer', 'Supplier'] as $type) {
    if ($url = safeRoute('contracts.index', ['company' => $companyId, 'type' => $type])) {
        addPage($pages, 'contracts-'.strtolower($type), "{$type} Contracts", $url, 'Contracts/Index', true);
    }
}

$list = array_values($pages);
usort($list, fn ($a, $b) => strcmp($a['id'], $b['id']));

file_put_contents($outPath, json_encode([
    'generatedAt' => now()->toIso8601String(),
    'companyId' => $companyId,
    'userId' => $user->id,
    'userEmail' => $user->email,
    'count' => count($list),
    'pages' => $list,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

echo 'Wrote '.count($list)." pages to {$outPath}\n";
