<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * * حساب "Investment In Subsidiary Company" بيتحط في نفس كارد
     * * الـ Liquidity / Treasury Accounts بعد Advances From Customers
     * * وبيتستخدم كـ transaction type جديد في الـ money payment
     * * لما الـ partner type يكون subsidiary company
     */
    public function up(): void
    {
        $hasAdvancesFromCustomers = Schema::hasColumn('odoo_settings', 'advances_from_customers_id');

        Schema::table('odoo_settings', function (Blueprint $table) use ($hasAdvancesFromCustomers) {
            if (!Schema::hasColumn('odoo_settings', 'investment_in_subsidiary_company_code')) {
                $column = $table->string('investment_in_subsidiary_company_code')->nullable();
                if ($hasAdvancesFromCustomers) {
                    $column->after('advances_from_customers_id');
                }
            }
            if (!Schema::hasColumn('odoo_settings', 'investment_in_subsidiary_company_id')) {
                $table->string('investment_in_subsidiary_company_id')->nullable()->after('investment_in_subsidiary_company_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('odoo_settings', function (Blueprint $table) {
            $table->dropColumn(['investment_in_subsidiary_company_code', 'investment_in_subsidiary_company_id']);
        });
    }
};
