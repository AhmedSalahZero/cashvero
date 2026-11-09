<?php

namespace Tests\Feature\Serials;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Base for the serial-number tests: a company made by the test (and a second one, for "another
 * company counts on its own"), in the test database. Counters use the year 2099 so a number
 * can never be mistaken for one handed out by a real screen.
 */
abstract class SerialTestCase extends TestCase
{
    protected const YEAR = 2099;

    protected Company $company;

    protected Company $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeCompany();
        $this->otherCompany = $this->makeCompany();
    }

    protected function inRolledBackTransaction(callable $callback): void
    {
        DB::beginTransaction();

        try {
            $callback();
        } finally {
            DB::rollBack();
        }
    }
}
