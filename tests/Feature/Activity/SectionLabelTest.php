<?php

namespace Tests\Feature\Activity;

use App\Support\SectionLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * اسم القسم اللي بيتسجل في logs: اسم الموديول من PermissionRegistry لو
 * الـ route مربوط بصلاحية واحدة، وإلا اسم الـ route نفسه بشكل مقروء.
 */
class SectionLabelTest extends TestCase
{
    public static function routes(): array
    {
        return [
            'registry label for a view route' => ['view.money.receive', [], 'Money Received'],
            'registry label keeps the action' => ['create.money.receive', [], 'Money Received - Create'],
            'route with two permissions falls back to its name' => ['view.balances', ['company' => '92', 'modelType' => 'CustomerInvoice'], 'Balances (Customer Invoice)'],
            'contracts keep their type' => ['contracts.index', ['company' => '92', 'type' => 'Supplier'], 'Contracts (Supplier)'],
            'unmapped route uses its name' => ['view.section.visit.probe', [], 'Section Visit Probe'],
            'unmapped create route' => ['section.visit.probe.create', [], 'Section Visit Probe - Create'],
            'camel case route name' => ['viewSectionProbe', [], 'Section Probe'],
            'ids and dates are ignored' => ['view.section.visit.probe', ['id' => '15', 'date' => '2026-01-01'], 'Section Visit Probe'],
        ];
    }

    #[DataProvider('routes')]
    public function test_it_builds_a_readable_label(string $routeName, array $parameters, string $expected): void
    {
        $this->assertSame($expected, SectionLabel::fromName($routeName, $parameters));
    }

    public function test_the_label_fits_the_activity_column(): void
    {
        $this->assertLessThanOrEqual(200, strlen(SectionLabel::fromName(str_repeat('very.long.', 60).'index')));
    }
}
