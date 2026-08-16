<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives a Medium Term Loan the same Odoo identity columns every other
 * payable facility already has (see create_clean_overdrafts_table).
 *
 * Why now: once the MTL became selectable as a paying account on the
 * Money Payment screen, the Odoo settlement sync started resolving it
 * through FinancialInstitution::getJournalIdForAccount() /
 * getOdooIdForAccount() — which call getJournalId() / getOdooId() on
 * whatever account model was picked. Those had never been reachable for
 * an MTL before (it could not be picked at all), so the model never
 * needed them. Without these it dies with "Call to undefined method".
 *
 * Nullable with no default, exactly like the overdrafts: a company with
 * no Odoo integration simply leaves them empty.
 *
 * The four *_payment_method_id columns go further than the overdrafts do
 * (they have none — see the standing TODO on
 * FinancialInstitution::getOdooPaymentIds()). They are needed because
 * fixing getJournalId() alone only moves the crash one step down the same
 * call chain: OdooPayment::createPayment() → getPaymentMethodLineId() →
 * IsMoney::getPaymentMethodId() → getOdooPaymentIds() → these four.
 *
 * With the columns present and empty, that chain now degrades the way it
 * was designed to for an unconfigured bank account — getPaymentMethodLineId()
 * returns 0 and the user gets the real message ("There Is No Payment Method
 * Line Configured In Odoo For The Account...") instead of a fatal error.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medium_term_loans', function (Blueprint $table) {
            $table->string('odoo_code')->nullable()->after('account_number');
            $table->unsignedBigInteger('odoo_id')->nullable()->after('odoo_code');
            $table->unsignedBigInteger('journal_id')->nullable()->after('odoo_id');
            $table->string('odoo_inbound_transfer_payment_method_id')->nullable()->after('journal_id');
            $table->string('odoo_outbound_transfer_payment_method_id')->nullable()->after('odoo_inbound_transfer_payment_method_id');
            $table->string('odoo_inbound_cheque_payment_method_id')->nullable()->after('odoo_outbound_transfer_payment_method_id');
            $table->string('odoo_outbound_cheque_payment_method_id')->nullable()->after('odoo_inbound_cheque_payment_method_id');
        });
    }

    public function down(): void
    {
        $columns = array_filter([
            'odoo_code',
            'odoo_id',
            'journal_id',
            'odoo_inbound_transfer_payment_method_id',
            'odoo_outbound_transfer_payment_method_id',
            'odoo_inbound_cheque_payment_method_id',
            'odoo_outbound_cheque_payment_method_id',
        ], fn (string $column) => Schema::hasColumn('medium_term_loans', $column));

        if ($columns === []) {
            return;
        }

        Schema::table('medium_term_loans', function (Blueprint $table) use ($columns) {
            $table->dropColumn(array_values($columns));
        });
    }
};
