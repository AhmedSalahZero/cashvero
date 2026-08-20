<?php

namespace Tests\Feature\LetterOfGuarantee;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The smallest schema that lets an LG issuance actually be deleted
 * through Eloquent, with every hook on the way down doing real work.
 *
 * Only the columns those hooks read are here — the production tables
 * are much wider. What matters is that the delete path is the real one:
 * LetterOfGuaranteeIssuance::deleting() → deleteAllRelations() →
 * each statement model's own deleting()/updated() hooks.
 */
trait LgSchemaFixture
{
    /** @var list<string> */
    private array $lgTables = [
        'record_activities',
        'temp_deleted_statements',
        'lg_renewal_date_histories',
        'lg_issuance_advanced_payment_histories',
        'letter_of_guarantee_cash_cover_statements',
        'letter_of_guarantee_statements',
        'current_account_bank_statements',
        'financial_institution_accounts',
        'letter_of_guarantee_issuances',
        'companies',
    ];

    private function assertOnTestDatabase(): void
    {
        $database = DB::connection()->getDatabaseName();
        $this->assertStringContainsString(
            'test',
            $database,
            "Refusing to run: connected to '{$database}', which is not a test database."
        );
    }

    private function createLgSchema(): void
    {
        $this->dropLgSchema();

        Schema::create('companies', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('odoo_db_url')->nullable();
            $table->string('odoo_db_name')->nullable();
        });

        Schema::create('letter_of_guarantee_issuances', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->string('source')->nullable();
            $table->string('status')->nullable();
            $table->string('lg_type')->nullable();
            $table->date('renewal_date')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('commission_fees_journal_entry_id')->nullable();
            $table->unsignedBigInteger('issuance_fees_journal_entry_id')->nullable();
            $table->unsignedBigInteger('cancel_journal_entry_id')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_institution_accounts', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->date('balance_date')->nullable();
            $table->json('synced_end_of_month_years')->nullable();
        });

        foreach (['letter_of_guarantee_statements', 'letter_of_guarantee_cash_cover_statements'] as $statementTable) {
            Schema::create($statementTable, function ($table) {
                $table->bigIncrements('id');
                $table->string('type')->nullable();
                $table->string('source')->nullable();
                $table->unsignedBigInteger('cd_or_td_id')->default(0);
                $table->unsignedBigInteger('financial_institution_id')->default(0);
                $table->unsignedBigInteger('letter_of_guarantee_issuance_id')->default(0);
                $table->unsignedBigInteger('lg_facility_id')->default(0);
                $table->unsignedBigInteger('lg_advanced_payment_history_id')->default(0);
                $table->string('lg_type')->nullable();
                $table->string('currency')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->boolean('is_debit')->default(0);
                $table->boolean('is_credit')->default(0);
                $table->date('date')->nullable();
                $table->dateTime('full_date')->nullable();
                $table->decimal('beginning_balance', 14)->default(0);
                $table->decimal('debit', 14)->default(0);
                $table->decimal('credit', 14)->default(0);
                $table->decimal('end_balance', 14)->default(0);
                $table->string('comment_en')->nullable();
                $table->string('comment_ar')->nullable();
                $table->timestamps();
            });
        }

        Schema::create('current_account_bank_statements', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->integer('financial_institution_account_id')->default(0);
            $table->unsignedBigInteger('letter_of_guarantee_issuance_id')->nullable();
            $table->boolean('is_beginning_balance')->default(0);
            $table->boolean('is_active')->default(1);
            $table->boolean('is_debit')->default(0);
            $table->boolean('is_credit')->default(0);
            $table->boolean('is_commission_fees')->default(0);
            $table->boolean('is_issuance_fees')->default(0);
            $table->boolean('is_renewal_fees')->default(0);
            $table->string('type')->nullable();
            $table->string('interest_type')->nullable();
            $table->date('date')->nullable();
            $table->dateTime('full_date')->nullable();
            $table->decimal('beginning_balance', 14)->default(0);
            $table->decimal('debit', 14)->default(0);
            $table->decimal('credit', 14)->default(0);
            $table->decimal('end_balance', 14)->default(0);
            $table->unsignedBigInteger('interest_journal_entry_id')->nullable();
            $table->string('interest_odoo_reference')->nullable();
            $table->unsignedBigInteger('interest_account_bank_statement_odoo_id')->nullable();
            $table->string('comment_en')->nullable();
            $table->string('comment_ar')->nullable();
            $table->timestamps();
        });

        Schema::create('lg_issuance_advanced_payment_histories', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('letter_of_guarantee_issuance_id');
        });

        Schema::create('lg_renewal_date_histories', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('letter_of_guarantee_issuance_id');
            $table->date('renewal_date')->nullable();
        });

        /**
         * RecordActivityObserver logs the delete, so the real delete path
         * needs somewhere to write it.
         */
        Schema::create('record_activities', function ($table) {
            $table->bigIncrements('id');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('event')->nullable();
            $table->text('description')->nullable();
            $table->longText('field_changes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('temp_deleted_statements', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->default(0);
            $table->string('table_name')->nullable();
            $table->unsignedBigInteger('deleted_id')->default(0);
            $table->timestamps();
        });
    }

    private function dropLgSchema(): void
    {
        foreach ($this->lgTables as $table) {
            Schema::dropIfExists($table);
        }
    }
}
