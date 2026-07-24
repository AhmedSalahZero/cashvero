<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared("CREATE DEFINER=`root`@`localhost` PROCEDURE `refresh_customer_invoices_status`()
begin
			update customer_invoices set updated_at = CURRENT_TIMESTAMP where net_balance > 0 ;
			update supplier_invoices set updated_at = CURRENT_TIMESTAMP where net_balance > 0 ;
	end");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS refresh_customer_invoices_status");
    }
};
