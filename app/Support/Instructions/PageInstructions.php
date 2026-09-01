<?php

namespace App\Support\Instructions;

/**
 * PageInstructions
 * ------------------------------------------------------------------
 * The written guide behind each screen's "Instructions" button.
 *
 * Content lives here, in one place, rather than inside each Vue page,
 * so a screen and its explanation cannot drift apart in different
 * files — and so a new screen's guide is a new array entry, not a new
 * component.
 *
 * Every string is an English phrase used as its own translation key,
 * exactly like the rest of the app: the page renders each one through
 * $t(), and resources/lang/ar.json carries the Arabic.
 *
 * WRITING RULES (they are what make this useful rather than decorative)
 *   - Say what the user sees and what happens when they touch it.
 *   - Every rule gets a real example with real numbers.
 *   - Quote validation messages VERBATIM, so the reader can match the
 *     red text on their screen to the explanation here.
 *
 * Shape of one page:
 *   title    — the screen being explained
 *   summary  — one or two lines: what this screen is for
 *   sections — [{ heading, body: [paragraphs], fields: [{ label, text, example? }], notes: [...] }]
 */
class PageInstructions
{
    /** Every screen that has a guide. */
    public const MONEY_RECEIVED_INDEX = 'money-received.index';

    public const MONEY_RECEIVED_FORM = 'money-received.form';

    public const MONEY_RECEIVED_DOWN_PAYMENT = 'money-received.down-payment';

    public const MONEY_PAYMENT = 'money-payment';

    public const CASH_EXPENSE = 'cash-expense';

    public const INTERNAL_TRANSFER = 'internal-money-transfer';

    public const CURRENCY_EXCHANGE = 'buy-or-sell-currency';

    public const LG_ISSUANCE = 'lg-issuance';

    public const LC_ISSUANCE = 'lc-issuance';

    public const LC_SETTLEMENT = 'lc-settlement-transfer';

    public const FACTORING = 'factoring';

    public const SETTINGS = 'settings.master-lists';

    public const CUSTOMER_BALANCES = 'customer-balances';

    public const SUPPLIER_BALANCES = 'supplier-balances';

    public const FINANCIAL_INSTITUTIONS = 'financial-institutions';

    /*
     * One guide per account type a bank relationship can hold. The keys
     * mirror the `slug` column of `account_types`, so a reader landing
     * on "Clean Overdraft" in the app reaches the guide of the same name.
     *
     * `discounting-cheques` is deliberately absent: it is a row in
     * `account_types` with no model, controller or screen behind it, so
     * there is nothing yet to explain.
     */
    public const CURRENT_ACCOUNT = 'account.current-account';

    public const TIME_OF_DEPOSIT = 'account.time-of-deposit';

    public const CERTIFICATE_OF_DEPOSIT = 'account.certificate-of-deposit';

    public const FULLY_SECURED_OVERDRAFT = 'account.fully-secured-overdraft';

    public const CLEAN_OVERDRAFT = 'account.clean-overdraft';

    public const OVERDRAFT_COMMERCIAL_PAPER = 'account.overdraft-against-commercial-paper';

    public const OVERDRAFT_ASSIGNMENT_OF_CONTRACTS = 'account.overdraft-against-assignment-of-contracts';

    public const LG_FACILITY = 'account.letter-of-guarantee-facility';

    public const LC_FACILITY = 'account.letter-of-credit-facility';

    public const MEDIUM_TERM_LOAN = 'account.medium-term-loan';

    /*
     * Drill-down screens reached FROM a documented screen. Each one gets
     * its own guide rather than borrowing its parent's: a reader who
     * opens the guide from the invoice report needs that report explained,
     * not the balances list they came through.
     */
    public const INVOICE_REPORT = 'balances.invoice-report';

    public const INVOICE_STATEMENT = 'balances.statement';

    public const NET_BALANCE_DETAILS = 'balances.net-balance-details';

    public const DOWN_PAYMENT_SETTLEMENT = 'balances.down-payment-settlement';

    public const ADJUST_DUE_DATE = 'balances.adjust-due-date';

    public const TD_PERIOD_INTEREST = 'account.time-of-deposit.period-interest';

    public const TD_RENEWAL_HISTORY = 'account.time-of-deposit.renewal-history';

    public const CD_PERIOD_INTEREST = 'account.certificate-of-deposit.period-interest';

    public const MTL_STATEMENT = 'account.medium-term-loan.statement';

    public const LG_RENEWAL_HISTORY = 'lg-issuance.renewal-history';

    /*
     * Entry forms. A list screen and the form that feeds it are different
     * screens with different questions — "what is this row?" versus "what
     * do I type here?" — so each form has its own guide rather than
     * borrowing the list's, which explains none of its fields.
     */
    public const MONEY_PAYMENT_FORM = 'money-payment.form';

    public const MONEY_PAYMENT_DOWN_PAYMENT = 'money-payment.down-payment';

    public const CASH_EXPENSE_FORM = 'cash-expense.form';

    public const INTERNAL_TRANSFER_FORM = 'internal-money-transfer.form';

    public const CURRENCY_EXCHANGE_FORM = 'buy-or-sell-currency.form';

    public const LC_SETTLEMENT_FORM = 'lc-settlement-transfer.form';

    public const LG_ISSUANCE_FORM = 'lg-issuance.form';

    public const LC_ISSUANCE_FORM = 'lc-issuance.form';

    public const FACTORING_FORM = 'factoring.form';

    public const FACTORING_CONTRACT_FORM = 'factoring.contract-form';

    public const FACTORING_CONTRACTS = 'factoring.contracts';

    public const FINANCIAL_INSTITUTION_FORM = 'financial-institutions.form';

    public const CURRENT_ACCOUNT_FORM = 'account.current-account.form';

    public const TIME_OF_DEPOSIT_FORM = 'account.time-of-deposit.form';

    public const CERTIFICATE_OF_DEPOSIT_FORM = 'account.certificate-of-deposit.form';

    public const CLEAN_OVERDRAFT_FORM = 'account.clean-overdraft.form';

    public const FULLY_SECURED_OVERDRAFT_FORM = 'account.fully-secured-overdraft.form';

    public const OVERDRAFT_COMMERCIAL_PAPER_FORM = 'account.overdraft-against-commercial-paper.form';

    public const OVERDRAFT_ASSIGNMENT_FORM = 'account.overdraft-against-assignment-of-contracts.form';

    public const LG_FACILITY_FORM = 'account.letter-of-guarantee-facility.form';

    public const LC_FACILITY_FORM = 'account.letter-of-credit-facility.form';

    public const MEDIUM_TERM_LOAN_FORM = 'account.medium-term-loan.form';

    public const OTHER_DUES = 'other-dues';

    public static function keys(): array
    {
        return [
            self::MONEY_RECEIVED_INDEX, self::MONEY_RECEIVED_FORM, self::MONEY_RECEIVED_DOWN_PAYMENT,
            self::MONEY_PAYMENT, self::CASH_EXPENSE, self::INTERNAL_TRANSFER, self::CURRENCY_EXCHANGE,
            self::LG_ISSUANCE, self::LC_ISSUANCE, self::LC_SETTLEMENT, self::FACTORING, self::SETTINGS,
            self::CUSTOMER_BALANCES, self::SUPPLIER_BALANCES, self::FINANCIAL_INSTITUTIONS,
            self::CURRENT_ACCOUNT, self::TIME_OF_DEPOSIT, self::CERTIFICATE_OF_DEPOSIT,
            self::FULLY_SECURED_OVERDRAFT, self::CLEAN_OVERDRAFT, self::OVERDRAFT_COMMERCIAL_PAPER,
            self::OVERDRAFT_ASSIGNMENT_OF_CONTRACTS, self::LG_FACILITY, self::LC_FACILITY,
            self::MEDIUM_TERM_LOAN,
            self::INVOICE_REPORT, self::INVOICE_STATEMENT, self::NET_BALANCE_DETAILS,
            self::DOWN_PAYMENT_SETTLEMENT, self::ADJUST_DUE_DATE,
            self::TD_PERIOD_INTEREST, self::TD_RENEWAL_HISTORY, self::CD_PERIOD_INTEREST,
            self::MTL_STATEMENT, self::LG_RENEWAL_HISTORY,
            self::MONEY_PAYMENT_FORM, self::MONEY_PAYMENT_DOWN_PAYMENT, self::CASH_EXPENSE_FORM,
            self::INTERNAL_TRANSFER_FORM, self::CURRENCY_EXCHANGE_FORM, self::LC_SETTLEMENT_FORM,
            self::LG_ISSUANCE_FORM, self::LC_ISSUANCE_FORM, self::FACTORING_FORM,
            self::FACTORING_CONTRACT_FORM, self::FACTORING_CONTRACTS,
            self::FINANCIAL_INSTITUTION_FORM, self::CURRENT_ACCOUNT_FORM,
            self::TIME_OF_DEPOSIT_FORM, self::CERTIFICATE_OF_DEPOSIT_FORM,
            self::CLEAN_OVERDRAFT_FORM, self::FULLY_SECURED_OVERDRAFT_FORM,
            self::OVERDRAFT_COMMERCIAL_PAPER_FORM, self::OVERDRAFT_ASSIGNMENT_FORM,
            self::LG_FACILITY_FORM, self::LC_FACILITY_FORM, self::MEDIUM_TERM_LOAN_FORM,
            self::OTHER_DUES,
        ];
    }

    public static function has(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $key): ?array
    {
        return match ($key) {
            self::MONEY_RECEIVED_INDEX => self::moneyReceivedIndex(),
            self::MONEY_RECEIVED_FORM => self::moneyReceivedForm(),
            self::MONEY_RECEIVED_DOWN_PAYMENT => self::moneyReceivedDownPayment(),
            self::MONEY_PAYMENT => self::moneyPayment(),
            self::CASH_EXPENSE => self::cashExpense(),
            self::INTERNAL_TRANSFER => self::internalTransfer(),
            self::CURRENCY_EXCHANGE => self::currencyExchange(),
            self::LG_ISSUANCE => self::lgIssuance(),
            self::LC_ISSUANCE => self::lcIssuance(),
            self::LC_SETTLEMENT => self::lcSettlement(),
            self::FACTORING => self::factoring(),
            self::SETTINGS => self::settings(),
            self::CUSTOMER_BALANCES => self::customerBalances(),
            self::SUPPLIER_BALANCES => self::supplierBalances(),
            self::FINANCIAL_INSTITUTIONS => self::financialInstitutions(),
            self::CURRENT_ACCOUNT => self::currentAccount(),
            self::TIME_OF_DEPOSIT => self::timeOfDeposit(),
            self::CERTIFICATE_OF_DEPOSIT => self::certificateOfDeposit(),
            self::FULLY_SECURED_OVERDRAFT => self::fullySecuredOverdraft(),
            self::CLEAN_OVERDRAFT => self::cleanOverdraft(),
            self::OVERDRAFT_COMMERCIAL_PAPER => self::overdraftCommercialPaper(),
            self::OVERDRAFT_ASSIGNMENT_OF_CONTRACTS => self::overdraftAssignmentOfContracts(),
            self::LG_FACILITY => self::lgFacility(),
            self::LC_FACILITY => self::lcFacility(),
            self::MEDIUM_TERM_LOAN => self::mediumTermLoan(),
            self::INVOICE_REPORT => self::invoiceReport(),
            self::INVOICE_STATEMENT => self::invoiceStatement(),
            self::NET_BALANCE_DETAILS => self::netBalanceDetails(),
            self::DOWN_PAYMENT_SETTLEMENT => self::downPaymentSettlement(),
            self::ADJUST_DUE_DATE => self::adjustDueDate(),
            self::TD_PERIOD_INTEREST => self::tdPeriodInterest(),
            self::TD_RENEWAL_HISTORY => self::tdRenewalHistory(),
            self::CD_PERIOD_INTEREST => self::cdPeriodInterest(),
            self::MTL_STATEMENT => self::mtlStatement(),
            self::LG_RENEWAL_HISTORY => self::lgRenewalHistory(),
            self::MONEY_PAYMENT_FORM => self::moneyPaymentForm(),
            self::MONEY_PAYMENT_DOWN_PAYMENT => self::moneyPaymentDownPayment(),
            self::CASH_EXPENSE_FORM => self::cashExpenseForm(),
            self::INTERNAL_TRANSFER_FORM => self::internalTransferForm(),
            self::CURRENCY_EXCHANGE_FORM => self::currencyExchangeForm(),
            self::LC_SETTLEMENT_FORM => self::lcSettlementForm(),
            self::LG_ISSUANCE_FORM => self::lgIssuanceForm(),
            self::LC_ISSUANCE_FORM => self::lcIssuanceForm(),
            self::FACTORING_FORM => self::factoringForm(),
            self::FACTORING_CONTRACT_FORM => self::factoringContractForm(),
            self::FACTORING_CONTRACTS => self::factoringContracts(),
            self::FINANCIAL_INSTITUTION_FORM => self::financialInstitutionForm(),
            self::CURRENT_ACCOUNT_FORM => self::currentAccountForm(),
            self::TIME_OF_DEPOSIT_FORM => self::depositForm('Time Of Deposit', 'deposit'),
            self::CERTIFICATE_OF_DEPOSIT_FORM => self::depositForm('Certificate Of Deposit', 'certificate'),
            self::CLEAN_OVERDRAFT_FORM => self::cleanOverdraftForm(),
            self::FULLY_SECURED_OVERDRAFT_FORM => self::fullySecuredOverdraftForm(),
            self::OVERDRAFT_COMMERCIAL_PAPER_FORM => self::overdraftCommercialPaperForm(),
            self::OVERDRAFT_ASSIGNMENT_FORM => self::overdraftAssignmentForm(),
            self::LG_FACILITY_FORM => self::lgFacilityForm(),
            self::LC_FACILITY_FORM => self::lcFacilityForm(),
            self::MEDIUM_TERM_LOAN_FORM => self::mediumTermLoanForm(),
            self::OTHER_DUES => self::otherDues(),
            default => null,
        };
    }

    private static function moneyReceivedIndex(): array
    {
        return [
            'title' => 'Money Received — the list screen',
            'summary' => 'Every amount you received: from a customer, a shareholder, an employee, a subsidiary company or any other partner. It may arrive as cash into a safe, cash into a bank, an incoming transfer, or a cheque. Each row is one payment received.',
            'sections' => [
                [
                    'heading' => 'The tabs across the top',
                    'body' => ['Each tab is one way the money arrived. A payment appears in exactly one tab — the one matching how you received it.'],
                    'fields' => [
                        ['label' => 'Cash In Safe', 'text' => 'The customer paid cash and it went into a branch safe. Needs a receipt number.'],
                        ['label' => 'Cash In Bank', 'text' => 'The customer deposited cash straight into one of your bank accounts.'],
                        ['label' => 'Incoming Transfer', 'text' => 'The money arrived by bank transfer into one of your accounts.'],
                        ['label' => 'Cheque', 'text' => 'You received a cheque. It is not money yet — it moves through the cheque tabs below until it clears.'],
                        ['label' => 'Cheque Under Collection', 'text' => 'You sent the cheque to the bank to collect. Waiting for it to clear.'],
                        ['label' => 'Cheque Collected', 'text' => 'The bank collected it. The money is now in your account.'],
                        ['label' => 'Cheque Rejected', 'text' => 'The cheque bounced. The invoice it was meant to settle goes back to unpaid.'],
                    ],
                ],
                [
                    'heading' => 'The life of a cheque',
                    'body' => ['A cheque is the only type that moves between tabs, because it is a promise before it is money. Use the buttons on the row to move it along.'],
                    'example' => 'A customer hands you a cheque for 50,000 due in 30 days. It sits in "Cheque". You send it to the bank → it moves to "Cheque Under Collection". Thirty days later the bank pays it → press Apply Collection → it moves to "Cheque Collected" and your bank balance goes up by 50,000. If the bank returns it instead, it moves to "Cheque Rejected" and the 50,000 goes back onto the customer\'s invoice as unpaid.',
                    'fields' => [
                        ['label' => 'Send Under Collection', 'text' => 'You handed the cheque to the bank. Enter the deposit date and the account you deposited it into.'],
                        ['label' => 'Apply Collection', 'text' => 'The bank cleared it. Enter the date the money actually landed. Your bank balance rises on that date.'],
                        ['label' => 'Send In Safe', 'text' => 'Brings a cheque back from the bank into your own safe, undoing "Send Under Collection".'],
                        ['label' => 'Rejected', 'text' => 'The cheque bounced. The settlement is reversed and the invoice becomes outstanding again.'],
                    ],
                ],
                [
                    'heading' => 'Filtering and finding a payment',
                    'fields' => [
                        ['label' => 'From / To date', 'text' => 'Filters by the receiving date. Each tab keeps its own dates, so narrowing one tab does not narrow the others.'],
                        ['label' => 'Search', 'text' => 'Matches the customer name, so you can jump to one customer\'s payments.'],
                    ],
                ],
                [
                    'heading' => 'What the row buttons do',
                    'fields' => [
                        ['label' => '✎ Edit', 'text' => 'Opens the payment again. Changing the amount re-does the settlement against the invoices — read the Edit warning on the form guide before changing a saved amount.'],
                        ['label' => '🗑 Delete', 'text' => 'Removes the payment and gives the money back to the invoices it settled, so they become outstanding again.'],
                        ['label' => '🕘 History', 'text' => 'Who created or changed this payment and when.'],
                        ['label' => '💬 User Comment', 'text' => 'The note whoever entered the payment left on it.'],
                        ['label' => '👍 / 🐞 Odoo', 'text' => 'Whether this payment reached Odoo. The bug icon shows the error Odoo returned, so you can fix it and resend.'],
                    ],
                ],
                [
                    'heading' => 'Before you delete',
                    'notes' => [
                        'Deleting is not just removing a line. The invoices this payment settled go back to unpaid by the same amounts, and any bank or safe movement it created is removed too.',
                        'If deleting would take a bank or safe below zero on that date, it is refused with "This Money Received Can Not Be Deleted .. There Is No Enough Balance". Remove the later movements first.',
                    ],
                ],
            ],
        ];
    }

    private static function moneyReceivedForm(): array
    {
        return [
            'title' => 'Money Received — recording a payment',
            'summary' => 'Use this form to record money you received from a customer, shareholder, employee, subsidiary company or other partner. The form changes with who paid and how the money arrived, so only the fields that apply are shown.',
            'sections' => [
                [
                    'heading' => 'Fill it in this order',
                    'body' => ['Each step below decides what the next one offers you, so working top to bottom saves re-doing it.'],
                    'fields' => [
                        ['label' => '1. Receiving Date', 'text' => 'The day the money actually reached you. It cannot be before the opening balance date of the account or safe receiving it, and it cannot be in the future.'],
                        ['label' => '2. Partner Type', 'text' => 'Who the money came from. This is the single most important choice on the form, because it decides whether there are invoices to settle at all.'],
                        ['label' => 'Customer', 'text' => 'Money against sales invoices. This is the only type that shows the invoice table at the bottom, and the only one that requires you to allocate the payment to invoices.'],
                        ['label' => 'Shareholder', 'text' => 'Money put in by an owner — capital introduced, or a shareholder repaying the company. No invoices are involved, so no invoice table appears.'],
                        ['label' => 'Employee', 'text' => 'Money coming back from a member of staff, for example the unspent part of a cash advance or custody being returned.'],
                        ['label' => 'Subsidiary Company', 'text' => 'Money received from a company you own, such as a loan being repaid between the two companies.'],
                        ['label' => 'Other Partner', 'text' => 'Anyone who does not fit the types above.'],
                        ['label' => '3. Name', 'text' => 'Which partner paid, filtered to the type you chose. Changing it reloads that partner\'s open invoices, and clears anything you had already allocated.'],
                        ['label' => '4. Invoice Currency', 'text' => 'The currency the invoices are in. Only invoices in this currency are listed.'],
                        ['label' => '5. Receive Currency', 'text' => 'The currency the money actually arrived in. It can differ from the invoice currency — see the exchange rate below.'],
                        ['label' => '6. Money Type', 'text' => 'How it arrived. This switches the middle of the form to the fields that method needs.'],
                    ],
                ],
                [
                    'heading' => 'What each Money Type asks for',
                    'body' => ['Only one of these groups is shown at a time — whichever you picked in Money Type.'],
                    'fields' => [
                        ['label' => 'Cash In Safe', 'text' => 'Receiving Branch (which safe), Received Amount, and a Receipt Number. The receipt number must be unique within that branch.'],
                        ['label' => 'Cash In Bank', 'text' => 'Receiving Bank, Account Type, Account Number and the Deposit Amount. The account list only shows accounts of the chosen bank in the chosen currency.'],
                        ['label' => 'Incoming Transfer', 'text' => 'Same as Cash In Bank — bank, account type, account number and the Transfer Amount.'],
                        ['label' => 'Cheque', 'text' => 'Drawee Bank (the customer\'s bank the cheque is drawn on), Cheque Amount, Due Date and Cheque Number. The cheque number must be unique for that drawee bank.'],
                    ],
                ],
                [
                    'heading' => 'Exchange Rate and "Amount In Invoice Currency"',
                    'body' => ['This pair only matters when the money arrived in a different currency from the invoices.'],
                    'example' => 'The invoice is 10,000 USD. The customer transfers 500,000 EGP. Set Receive Currency to EGP, enter 500,000, and put the rate you actually got — say 50. "Amount In Invoice Currency" then shows 10,000 USD, and that is the figure the invoices are settled with. The rate is stored on this payment, so a later change to the market rate never moves this record.',
                    'notes' => ['If both currencies are the same, leave the rate at 1 and the two amounts stay equal.'],
                ],
                [
                    'heading' => 'The invoice table — where the money goes',
                    'body' => ['This table only appears when Partner Type is Customer — the other types have no invoices to settle, and for them the payment is recorded on its own. It lists that customer\'s unpaid invoices; type into Settlement Amount on each invoice this payment is settling.'],
                    'fields' => [
                        ['label' => 'Net Balance', 'text' => 'What is still open on that invoice. Your settlement cannot exceed it.'],
                        ['label' => 'Settlement Amount', 'text' => 'How much of this payment goes to this invoice.'],
                        ['label' => 'Withhold Amount', 'text' => 'Tax the customer deducted at source and paid to the authority instead of to you. It clears the invoice without you receiving the cash.'],
                        ['label' => 'Unapplied Amount', 'text' => 'Money received that you are not putting on any invoice yet — an advance. Leave it 0 unless that is really what happened.'],
                    ],
                    'example' => 'A customer owes 30,000 on invoice A and 20,000 on invoice B, and pays you 45,000. Put 30,000 against A and 15,000 against B. Invoice A is fully settled; invoice B is left with 5,000 open.',
                ],
                [
                    'heading' => 'What has to be true before it will save',
                    'body' => ['These are checked when you press Save. The exact wording below is what you will see on screen.'],
                    'fields' => [
                        ['label' => '"Please Select Money Type"', 'text' => 'You did not choose how the money arrived.'],
                        ['label' => '"Please Enter Received Amount" / "Received Amount Must Be Greater Than Zero"', 'text' => 'The amount is empty or zero. A payment of nothing cannot be recorded.'],
                        ['label' => '"At Least One Settlement Is Required"', 'text' => 'Only applies when Partner Type is Customer: the payment must be put against at least one invoice. If it is genuinely an advance, use the Down Payment form instead. Shareholder, employee, subsidiary and other-partner payments save without any invoice.'],
                        ['label' => '"Settlement Amount Must Be Equal Or Less Than Net Balance For Invoice"', 'text' => 'You allocated more to an invoice than it still owes. Settlement + withholding together cannot exceed the invoice\'s net balance.'],
                        ['label' => '"Please Select Account Type" / "Please Select Account Number"', 'text' => 'Cash In Bank and Incoming Transfer must say exactly which account the money entered.'],
                        ['label' => '"Cheque Number Already Exist"', 'text' => 'That cheque number is already recorded for the same drawee bank. Check you are not entering the same cheque twice.'],
                        ['label' => '"Receipt Number For This Branch Already Exist"', 'text' => 'That receipt number is already used in that branch.'],
                        ['label' => '"Cheque Due Date Is Required"', 'text' => 'A cheque must say when it becomes due.'],
                        ['label' => '"Please Enter New Branch Name"', 'text' => 'You left the receiving branch unselected for a cash-in-safe payment.'],
                        ['label' => '"Invalid Unapplied Amount"', 'text' => 'The unapplied amount cannot be negative.'],
                    ],
                ],
                [
                    'heading' => 'Editing a payment you already saved',
                    'notes' => [
                        'Editing re-does the whole payment: the old settlements are taken off the invoices and the new ones applied. Invoices you remove from the table go back to being outstanding.',
                        'Changing the amount changes your bank or safe balance on the receiving date, and every balance after it.',
                        'Its own cheque or receipt number is not treated as a duplicate of itself, so you can edit a record without touching that field.',
                    ],
                ],
            ],
        ];
    }

    private static function moneyPayment(): array
    {
        return [
            'title' => 'Money Payment — paying someone',
            'summary' => 'Every amount you paid out: to a supplier, a shareholder, an employee, a subsidiary company, the tax authority, or any other partner. It can leave as cash from a safe, an outgoing bank transfer, or a cheque you issue.',
            'sections' => [
                [
                    'heading' => 'Who you are paying',
                    'body' => ['Partner Type decides whether there are invoices to settle. Only Supplier has them.'],
                    'fields' => [
                        ['label' => 'Supplier', 'text' => 'Paying purchase invoices. This is the only type that shows the invoice table, and the only one where the payment must be allocated to invoices.'],
                        ['label' => 'Taxes & Social Insurance', 'text' => 'Paying the tax authority or social insurance. No invoices — the payment stands on its own.'],
                        ['label' => 'Shareholder', 'text' => 'Money going out to an owner, such as a dividend or a repayment of what they put in.'],
                        ['label' => 'Employee', 'text' => 'Paying staff, for example a cash advance or custody handed over.'],
                        ['label' => 'Subsidiary Company', 'text' => 'Money sent to a company you own.'],
                        ['label' => 'Other Partner', 'text' => 'Anyone who does not fit the types above.'],
                    ],
                ],
                [
                    'heading' => 'How the money leaves',
                    'body' => ['Pick one in Money Type; the form then shows only that method\'s fields.'],
                    'fields' => [
                        ['label' => 'Cash Payment', 'text' => 'Paid in cash out of a branch safe. Needs the branch and a receipt number, unique within that branch.'],
                        ['label' => 'Outgoing Transfer', 'text' => 'A bank transfer out. Needs the bank, account type and account number the money left from.'],
                        ['label' => 'Payable Cheque', 'text' => 'A cheque you issue. Needs the bank it is drawn on, the due date and the cheque number. The money does not leave your account until the cheque is marked paid on its due date.'],
                    ],
                ],
                [
                    'heading' => 'Allocating to supplier invoices',
                    'body' => ['For a supplier, the table at the bottom lists their unpaid invoices. Enter how much of this payment goes to each one.'],
                    'example' => 'You owe a supplier 80,000 on invoice X and 40,000 on invoice Y, and you pay 100,000. Put 80,000 on X and 20,000 on Y. X is fully paid; Y still has 20,000 open.',
                    'fields' => [
                        ['label' => 'Withhold Amount', 'text' => 'Tax you deducted from the supplier and will pay to the authority yourself. It reduces the invoice even though that part never leaves your bank.'],
                        ['label' => 'Unapplied Amount', 'text' => 'Money paid that you are not putting on an invoice yet — an advance to the supplier.'],
                    ],
                ],
                [
                    'heading' => 'What has to be true before it will save',
                    'fields' => [
                        ['label' => '"Please Select Money Type"', 'text' => 'You did not say how the money left.'],
                        ['label' => '"Please Enter Paid Amount" / "Paid Amount Must Be Greater Than Zero"', 'text' => 'The amount is empty or zero.'],
                        ['label' => '"Please Select Payment Date"', 'text' => 'Every payment needs the day it happened.'],
                        ['label' => '"Please Select Account Type" / "Please Select Account Number"', 'text' => 'An outgoing transfer must name exactly which account the money left.'],
                        ['label' => '"Please Insert Cheque Number" / "Cheque Number Already Exist"', 'text' => 'A payable cheque needs a number, and it must not repeat for the same bank.'],
                        ['label' => '"Cheque Due Date Is Required"', 'text' => 'A cheque must say when it falls due.'],
                        ['label' => '"Cheque Due Date Must Be Greater Than Or Equal Account Opening Date"', 'text' => 'A cheque cannot be dated before the account it is drawn on even existed.'],
                        ['label' => '"Please Enter New Branch Name"', 'text' => 'A cash payment must say which branch safe it came out of.'],
                        ['label' => '"There Is No Enough Balance To Make This Transaction"', 'text' => 'The account or safe does not hold enough on that date. Check the balance on the payment date, not today.'],
                    ],
                ],
                [
                    'heading' => 'Deleting a payment',
                    'notes' => [
                        'The supplier invoices it paid go back to unpaid, and the bank or safe movement is removed.',
                        'If removing it would leave a bank or safe negative on that date, it is refused with "This Money Payment Can Not Be Deleted .. There Is No Enough Balance".',
                    ],
                ],
            ],
        ];
    }

    private static function cashExpense(): array
    {
        return [
            'title' => 'Cash Expense — the company\'s own costs',
            'summary' => 'Money spent running the company rather than paying a partner: rent, electricity, salaries, bank charges. There is no invoice and no supplier account behind it — you say what it was for by choosing a category.',
            'sections' => [
                [
                    'heading' => 'How it differs from a Money Payment',
                    'body' => ['Use Money Payment when you are settling a supplier\'s invoice. Use Cash Expense when the money is simply a cost — nobody\'s account is being cleared by it.'],
                    'example' => 'Paying the electricity bill for the office is a Cash Expense under a category like Utilities → Electricity. Paying a supplier for goods you received on invoice 512 is a Money Payment against that invoice.',
                ],
                [
                    'heading' => 'The three tabs',
                    'fields' => [
                        ['label' => 'Outgoing Transfer', 'text' => 'Paid by bank transfer. Can be marked as a bank charge, which records it as the bank\'s own fee.'],
                        ['label' => 'Cash Payment', 'text' => 'Paid in cash from a branch safe, with a receipt number unique to that branch.'],
                        ['label' => 'Payable Cheque', 'text' => 'Paid by a cheque you issued, tracked until you mark it paid.'],
                    ],
                ],
                [
                    'heading' => 'Saying what the money was for',
                    'fields' => [
                        ['label' => 'Expense Category', 'text' => 'The broad heading, for example Utilities. Managed under Settings → Cash Expense Categories.'],
                        ['label' => 'Expense Name', 'text' => 'The specific item inside that category, for example Electricity. The list changes when you change the category.'],
                        ['label' => 'Allocating With Customer Contracts', 'text' => 'Optional. If the cost belongs to a customer contract, split it across contracts here so the contract carries its true cost.'],
                    ],
                ],
                [
                    'heading' => 'The Copy button',
                    'body' => ['On the list, Copy opens the create form already filled in from an existing expense — useful for costs that repeat every month.'],
                    'notes' => [
                        'The cheque number, receipt number and the date are left empty on a copy, because they must be new for the new expense.',
                        'Everything else, including the contract allocations, is copied so you only change what differs.',
                    ],
                ],
                [
                    'heading' => 'What has to be true before it will save',
                    'fields' => [
                        ['label' => '"Please Enter Expense Name" / "Please Enter Expense Amount"', 'text' => 'An expense needs to say what it was and how much.'],
                        ['label' => '"Cheque Number Already Exist"', 'text' => 'That cheque number is already used for the same bank.'],
                        ['label' => '"Receipt Number For This Branch Already Exist"', 'text' => 'That receipt number is already used in that branch.'],
                    ],
                ],
            ],
        ];
    }

    private static function internalTransfer(): array
    {
        return [
            'title' => 'Internal Money Transfer — moving your own money',
            'summary' => 'Moving money between places you already own: between two bank accounts, between a bank and a branch safe, or between two safes. Nothing is earned or spent — your total stays the same, only where it sits changes.',
            'sections' => [
                [
                    'heading' => 'The four kinds of move',
                    'fields' => [
                        ['label' => 'Bank To Bank', 'text' => 'From one of your accounts to another. Both sides need bank, account type and account number.'],
                        ['label' => 'Bank To Safe', 'text' => 'Cash withdrawn from an account into a branch safe.'],
                        ['label' => 'Safe To Bank', 'text' => 'Cash from a branch safe deposited into an account.'],
                        ['label' => 'Safe To Safe', 'text' => 'Cash moved between two branch safes.'],
                    ],
                ],
                [
                    'heading' => 'What happens when you save',
                    'body' => ['Two movements are written at once — one out of the source and one into the destination, both on the transaction date.'],
                    'example' => 'You move 200,000 from the main bank account into the Cairo branch safe on 1 March. The bank balance drops 200,000 on 1 March and the Cairo safe rises 200,000 on the same day. Company-wide cash is unchanged.',
                ],
                [
                    'heading' => 'What has to be true before it will save',
                    'fields' => [
                        ['label' => '"Transaction Date Is Required"', 'text' => 'A transfer needs the day it happened.'],
                        ['label' => '"Transaction Date Can Not Be Greater Than Today"', 'text' => 'You cannot record a move that has not happened yet.'],
                        ['label' => '"Transaction Date Must Be Greater Than Or Equal Account Opening Balance Date"', 'text' => 'A move cannot be dated before the account\'s opening balance date.'],
                        ['label' => '"There Is No Enough Balance To Make This Transaction"', 'text' => 'The source does not hold that much on that date.'],
                    ],
                    'notes' => ['Source and destination must be different — moving money to the same place would be a transaction that changes nothing.'],
                ],
            ],
        ];
    }

    private static function currencyExchange(): array
    {
        return [
            'title' => 'Sell Or Buy Currencies',
            'summary' => 'Exchanging one currency for another out of your own accounts — selling dollars for pounds, or buying dollars with pounds. One account goes down in one currency and another goes up in a different one.',
            'sections' => [
                [
                    'heading' => 'How to read the two sides',
                    'fields' => [
                        ['label' => 'Currency To Sell / Amount', 'text' => 'What leaves you, and from which account. This account\'s balance goes down.'],
                        ['label' => 'Currency To Buy / Amount', 'text' => 'What you get back, and into which account. This account\'s balance goes up.'],
                        ['label' => 'Exchange Rate', 'text' => 'The rate you actually dealt at. It is stored on the transaction, so a later market move never changes this record.'],
                        ['label' => 'Reciprocal Exchange Rate', 'text' => 'The same rate the other way round, shown so you can sanity-check what you typed.'],
                    ],
                    'example' => 'You sell 10,000 USD and receive 500,000 EGP. Currency To Sell is USD 10,000 out of the dollar account; Currency To Buy is EGP 500,000 into the pound account; the rate is 50. The dollar account falls 10,000 and the pound account rises 500,000 on the transaction date.',
                ],
                [
                    'heading' => 'What has to be true before it will save',
                    'fields' => [
                        ['label' => '"Transaction Date Is Required" / "Transaction Date Can Not Be Greater Than Today"', 'text' => 'The deal needs a date, and it cannot be in the future.'],
                        ['label' => '"There Is No Enough Balance To Make This Transaction"', 'text' => 'The account you are selling from does not hold that much on that date.'],
                        ['label' => '"Exchange rate must be greater than zero."', 'text' => 'A rate of zero would make one side of the deal worthless.'],
                    ],
                    'notes' => ['The two currencies must be different — otherwise it is a transfer, not an exchange.'],
                ],
            ],
        ];
    }
    private static function lgIssuance(): array
    {
        return [
            'title' => 'Letter of Guarantee Issuance',
            'summary' => 'A Letter of Guarantee is a promise your bank makes to your customer on your behalf: if you fail to deliver, the bank pays them. This screen records each LG the bank issued for you, and what it costs you to hold it.',
            'sections' => [
                [
                    'heading' => 'The four tabs — the kind of guarantee',
                    'body' => ['Tabs are by what the LG is for, not by how it is funded.'],
                    'fields' => [
                        ['label' => 'Bid Bond', 'text' => 'Backs your bid in a tender. If you win and then walk away, the customer can claim it.'],
                        ['label' => 'Final LG', 'text' => 'Backs the contract itself once you have won it.'],
                        ['label' => 'Advanced Payment LG', 'text' => 'Given when a customer pays you in advance, guaranteeing you will deliver. Its value goes down as you deliver — see Amount To Be Decreased.'],
                        ['label' => 'Performance LG', 'text' => 'Backs the quality and completion of the work.'],
                    ],
                ],
                [
                    'heading' => 'The four ways to fund one',
                    'body' => ['This is chosen by which Create button you press, and it decides which form opens.'],
                    'fields' => [
                        ['label' => 'Via LG Facility', 'text' => 'Issued against a credit limit the bank granted you. Uses up part of that limit until the LG expires.'],
                        ['label' => 'Against CD', 'text' => 'Backed by a certificate of deposit you hold, which is blocked while the LG is live.'],
                        ['label' => 'Against TD', 'text' => 'Backed by a time deposit, blocked the same way.'],
                        ['label' => '100% Cash Cover', 'text' => 'You deposit the full value in cash. That cash is blocked until the LG ends.'],
                    ],
                ],
                [
                    'heading' => 'What it costs you',
                    'fields' => [
                        ['label' => 'Cash Cover Rate', 'text' => 'The share of the LG value the bank blocks as security. 100% Cash Cover means the whole amount.'],
                        ['label' => 'Commission Rate and Interval', 'text' => 'What the bank charges to keep the LG open, and how often — quarterly, for example. Charged repeatedly for as long as the LG lives.'],
                        ['label' => 'Min Commission Fees', 'text' => 'A floor: if the calculated commission is smaller than this, the bank charges this instead.'],
                        ['label' => 'Issuance Fees', 'text' => 'A one-off fee when the LG is issued.'],
                    ],
                    'example' => 'A 1,000,000 Final LG with 20% cash cover and a 0.4% quarterly commission blocks 200,000 of your money, and costs 4,000 every quarter until it expires.',
                ],
                [
                    'heading' => 'Keeping an LG alive, and ending it',
                    'fields' => [
                        ['label' => '🔄 Renewal', 'text' => 'Extends the expiry date. Commission keeps being charged for the new period.'],
                        ['label' => '⚖️ Amount To Be Decreased', 'text' => 'Advanced Payment LGs only. As you deliver, the guarantee shrinks — record each reduction here and the outstanding value drops.'],
                        ['label' => '🚫 Cancel Letter', 'text' => 'Ends the LG. The blocked cash cover is released back to you on the cancellation date.'],
                        ['label' => '↩️ Back To Running', 'text' => 'Undoes a cancellation, if it was recorded by mistake.'],
                    ],
                ],
                [
                    'heading' => 'Things that will stop you',
                    'fields' => [
                        ['label' => '"This exceeds what is left of the LG Facility. Reduce the amount or pick another facility."', 'text' => 'The facility does not have enough unused limit for this LG.'],
                        ['label' => '"Contract is required for this LG type."', 'text' => 'This beneficiary requires the LG to be tied to a contract.'],
                        ['label' => '"This LG is cancelled and can no longer be edited. Set it back to Running first."', 'text' => 'Cancelled LGs are read-only. Use Back To Running first if you must change it.'],
                        ['label' => '"There Is No Enough Balance In Current Account To Apply LG Cash Cover And Commission"', 'text' => 'The account cannot cover the cash cover plus the fees on the issuance date.'],
                    ],
                ],
            ],
        ];
    }

    private static function lcIssuance(): array
    {
        return [
            'title' => 'Letter of Credit Issuance',
            'summary' => 'A Letter of Credit is your bank promising to pay YOUR supplier once they prove they shipped what you ordered. It is how imports are usually paid for. This screen records each LC and tracks it until it is settled.',
            'sections' => [
                [
                    'heading' => 'How an LC differs from an LG',
                    'body' => ['An LG is a promise that you will perform — the bank pays only if you fail. An LC is a promise that the supplier will be paid — the bank pays as soon as the documents are correct. An LG usually costs you nothing beyond fees; an LC ends in a real payment.'],
                ],
                [
                    'heading' => 'The three types',
                    'fields' => [
                        ['label' => 'Sight LC', 'text' => 'The bank pays the supplier as soon as correct documents arrive.'],
                        ['label' => 'Deferred', 'text' => 'The bank pays a set period after the documents, giving you time to sell the goods first.'],
                        ['label' => 'Cash Against Document', 'text' => 'Payment is released against the documents themselves.'],
                    ],
                ],
                [
                    'heading' => 'Funding, cost and duration',
                    'fields' => [
                        ['label' => 'Via LC Facility / Against CD / Against TD / 100% Cash Cover', 'text' => 'The same four funding routes as an LG: a bank limit, a blocked deposit, or your own cash.'],
                        ['label' => 'LC Duration (Days)', 'text' => 'How long the LC stays open. The expiry is the issuance date plus this many days.'],
                        ['label' => 'LC Commission Rate', 'text' => 'What the bank charges to open and hold it.'],
                    ],
                ],
                [
                    'heading' => 'From issued to settled',
                    'body' => ['An LC is not finished when it is issued. The supplier is paid, and then you settle with the bank.'],
                    'notes' => [
                        'Once the bank has paid your supplier, the LC appears under Pending LC Settlements, waiting for you to settle with the bank.',
                        'That settlement is recorded on the LC Settlement Internal Transfers screen — see its own guide.',
                        'To take an LC out of the pending list entirely, set it back to Running from this screen.',
                    ],
                ],
                [
                    'heading' => 'Things that will stop you',
                    'fields' => [
                        ['label' => '"This exceeds what is left of the LC Facility. Reduce the amount or pick another facility."', 'text' => 'Not enough unused limit on the facility.'],
                        ['label' => '"This LC Code Already Exist For This Bank"', 'text' => 'That LC code is already recorded for the same bank.'],
                        ['label' => '"This exceeds what is left to settle on this LC."', 'text' => 'You are trying to settle more than the LC still owes.'],
                    ],
                ],
            ],
        ];
    }

    private static function lcSettlement(): array
    {
        return [
            'title' => 'LC Settlement Internal Transfers',
            'summary' => 'Settling with the bank for a Letter of Credit it already paid your supplier. The bank paid on your behalf; this screen records you paying the bank back.',
            'sections' => [
                [
                    'heading' => 'When you use this screen',
                    'body' => ['Only after the bank has paid the supplier under an LC. Until then there is nothing to settle.'],
                    'example' => 'You open a 500,000 LC for a supplier. The goods ship, the documents are correct, and the bank pays the supplier 500,000. You now owe the bank 500,000 — that is what you record here, moving the money from your account to close the LC.',
                ],
                [
                    'heading' => 'What you enter',
                    'fields' => [
                        ['label' => 'The LC being settled', 'text' => 'Only LCs the bank has already paid appear. Each shows how much is still outstanding on it.'],
                        ['label' => 'Paying account', 'text' => 'Which of your accounts the money comes from. Its balance falls on the transfer date.'],
                        ['label' => 'Amount', 'text' => 'How much of the LC you are settling. You may settle in parts.'],
                    ],
                ],
                [
                    'heading' => 'Undoing a settlement',
                    'fields' => [
                        ['label' => 'Reset', 'text' => 'Undoes every settlement made so far on that LC and returns the full amount to outstanding. It cannot be undone, so use it only to correct a genuine mistake.'],
                    ],
                    'notes' => ['"This exceeds what is left to settle on this LC." means you entered more than the LC still owes — check the outstanding figure on the row.'],
                ],
            ],
        ];
    }

    private static function factoring(): array
    {
        return [
            'title' => 'Factoring',
            'summary' => 'Selling an unpaid customer invoice to a factoring company so you get most of the cash now instead of waiting for the due date. The factoring company keeps a fee for the wait.',
            'sections' => [
                [
                    'heading' => 'With Recourse or Without Recourse',
                    'body' => ['This is the whole decision: who carries the loss if the customer never pays.'],
                    'fields' => [
                        ['label' => 'With Recourse', 'text' => 'If the customer does not pay, you must repay the factoring company. The risk stays with you, so the invoice remains yours until it is collected.'],
                        ['label' => 'Without Recourse', 'text' => 'The factoring company carries the risk. Once sold, the invoice is off your books — it shows on the customer statement as settled through factoring.'],
                    ],
                    'example' => 'A 100,000 invoice due in 90 days is factored. You receive 92,000 now, the factoring company keeps 7,000 interest and 1,000 other charges. With recourse: if the customer never pays, you owe the 100,000 back. Without recourse: you do not.',
                ],
                [
                    'heading' => 'The numbers must balance',
                    'body' => ['The three parts of the deal must add up to what you factored.'],
                    'fields' => [
                        ['label' => 'Received Amount', 'text' => 'The cash the factoring company actually sent you.'],
                        ['label' => 'Factoring Interest Amount', 'text' => 'Their charge for advancing the money early.'],
                        ['label' => 'Other Charges', 'text' => 'Any remaining fees.'],
                    ],
                    'notes' => ['"Received amount, factoring interest amount, and other charges must equal factoring amount." — the three together must equal the invoice amount you factored, to the piastre.'],
                ],
                [
                    'heading' => 'Which invoices can be factored',
                    'fields' => [
                        ['label' => '"Only invoices with an outstanding balance can be factored."', 'text' => 'A fully collected invoice has nothing left to sell.'],
                        ['label' => '"Only invoices with zero collection amount can be factored."', 'text' => 'A partly collected invoice cannot be factored — it must be untouched.'],
                        ['label' => '"Factoring date must be less than invoice due date."', 'text' => 'The point is getting paid early, so it must be factored before it falls due.'],
                        ['label' => '"The selected contract is not active on the factoring date."', 'text' => 'The factoring contract must be running on that date.'],
                        ['label' => '"Factoring amount cannot exceed the remaining contract limit."', 'text' => 'Each factoring contract has a limit; this deal would go past it.'],
                        ['label' => '"This invoice has already been used in a factoring transaction."', 'text' => 'An invoice can only be factored once.'],
                    ],
                ],
                [
                    'heading' => 'Afterwards — collection or rejection',
                    'fields' => [
                        ['label' => 'Collected', 'text' => 'The customer paid. Confirm you received the amount from the factoring company.'],
                        ['label' => 'Rejected', 'text' => 'With recourse only: the customer did not pay, so you repay the factoring company.'],
                        ['label' => 'Revert', 'text' => 'Undoes a collection or a rejection recorded by mistake, removing the bank and factoring statement entries it created.'],
                    ],
                ],
            ],
        ];
    }

    private static function settings(): array
    {
        return [
            'title' => 'Settings — the master lists',
            'summary' => 'The short lists the rest of the app chooses from: business sectors, business units, sales channels, sales persons and deduction types. They exist so the same wording is used everywhere instead of being typed differently each time.',
            'sections' => [
                [
                    'heading' => 'What each list is used for',
                    'fields' => [
                        ['label' => 'Business Sectors', 'text' => 'Groups customer invoices by industry, so reports can compare one sector against another.'],
                        ['label' => 'Business Units', 'text' => 'Groups invoices by the part of the company that earned them.'],
                        ['label' => 'Sales Channels', 'text' => 'How the sale was made — direct, distributor, online, and so on.'],
                        ['label' => 'Sales Persons', 'text' => 'Who sold it, so collection and sales reports can be read per person.'],
                        ['label' => 'Deductions', 'text' => 'The named reasons an invoice can be reduced, used when recording a deduction against one.'],
                    ],
                ],
                [
                    'heading' => 'Adding and editing',
                    'body' => ['Type the name in the box at the top and press Add. Editing a name changes it everywhere it is shown, because invoices point at the entry rather than storing a copy of the word.'],
                    'notes' => [
                        'Names must be unique in their list — "This Business Sector Already Exist" means it is already there.',
                        'Think before renaming: a rename is not a new entry, so every past invoice using it now reads with the new name.',
                    ],
                ],
                [
                    'heading' => 'Deleting',
                    'notes' => [
                        'Delete only entries nothing uses. If an invoice already points at one, remove or change that invoice first.',
                    ],
                ],
            ],
        ];
    }

    private static function moneyReceivedDownPayment(): array
    {
        return [
            'title' => 'Money Received — down payment (advance)',
            'summary' => 'Use this form when a customer pays you before there is an invoice to settle. The money is held as an advance and used later, instead of being applied to invoices now.',
            'sections' => [
                [
                    'heading' => 'Choosing the Down Payment Type',
                    'fields' => [
                        ['label' => 'Over Contract', 'text' => 'The advance belongs to one specific contract. You then split it across that contract\'s sales orders, and the total split must equal the amount received.'],
                        ['label' => 'General', 'text' => 'An advance not tied to any contract. It sits against the customer until you settle it against a future invoice.'],
                        ['label' => 'Settlement Of Opening Balance', 'text' => 'For money that relates to balances brought in from before you started using CashVero.'],
                    ],
                    'example' => 'A customer pays 200,000 up front on a 1,000,000 contract with two sales orders. Choose Over Contract, pick the contract, and split the 200,000 across the two orders — for example 120,000 and 80,000. The split must add up to exactly 200,000.',
                ],
                [
                    'heading' => 'The rest of the form',
                    'body' => ['Receiving date, customer, currency, money type and the bank/safe/cheque fields all behave exactly as they do on the normal Money Received form — see that guide for the detail.'],
                ],
                [
                    'heading' => 'What has to be true before it will save',
                    'fields' => [
                        ['label' => '"Total amounts assigned to SOs must be equal down payment amount"', 'text' => 'For Over Contract, the amounts you split across the sales orders must add up to exactly the amount received.'],
                        ['label' => '"Total Settlements Must Be Equal Or Less Than Down Payment Amount"', 'text' => 'You cannot use more of an advance than it holds.'],
                    ],
                ],
                [
                    'heading' => 'Where the advance shows up afterwards',
                    'notes' => [
                        'It appears in the Down Payments column on Customers Balances, and reduces what the customer owes overall.',
                        'When a real invoice arrives, settle it against the advance rather than recording a second payment.',
                    ],
                ],
            ],
        ];
    }

    /* ══════════════ Balances ══════════════ */

    private static function customerBalances(): array
    {
        return [
            'title' => 'Customer Balances',
            'summary' => 'How much every customer still owes you. One row per customer per currency: what they were invoiced, what they have paid, and what is left.',
            'sections' => [
                [
                    'heading' => 'What the numbers mean',
                    'body' => ['Read a row left to right and it tells the whole story of one customer in one currency.'],
                    'fields' => [
                        ['label' => 'Name', 'text' => 'The customer. The same customer appears once per currency — a customer invoiced in both EGP and USD has two rows, because the two amounts must never be added together.'],
                        ['label' => 'Down Payments', 'text' => 'Money the customer paid you IN ADVANCE that is not yet tied to an invoice. It is money you hold on their behalf, so it reduces what they owe.'],
                        ['label' => 'Internal Settlements', 'text' => 'Only shown for a partner who is a customer AND a supplier at once. It is the amount already offset between the two sides. See the Internal Settlement section below.'],
                        ['label' => 'Net Balance', 'text' => 'The bottom line: what this customer still owes you right now, after every payment, down payment and settlement.'],
                    ],
                    'example' => 'A customer was invoiced 1,000,000 EGP. They paid 300,000 against specific invoices and left a 50,000 advance not yet applied to any invoice. The row shows Down Payments 50,000 and Net Balance 650,000 — the 1,000,000 less the 300,000 paid less the 50,000 you already hold.',
                ],
                [
                    'heading' => 'The three buttons on each currency card',
                    'body' => ['Each currency has its own card at the top with three ways into the detail.'],
                    'fields' => [
                        ['label' => 'All Invoices', 'text' => 'Every invoice in that currency that still has a balance, whatever its due date.'],
                        ['label' => 'Coming Dues', 'text' => 'Invoices not due yet. This is your expected incoming cash.'],
                        ['label' => 'Past Due', 'text' => 'Invoices whose due date has passed and are still unpaid. This is the list to chase.'],
                    ],
                ],
                [
                    'heading' => 'Internal Settlement — when a partner is both customer and supplier',
                    'body' => [
                        'Some partners buy from you and sell to you. Rather than each of you transferring money to the other, you agree to cancel one debt against the other. That is an internal settlement.',
                        'The button only appears for a partner who is marked both customer and supplier, and only when they have open invoices on BOTH sides in the SAME currency. With open invoices on one side only there is nothing to offset, and the screen says so.',
                    ],
                    'example' => 'A partner owes you 1,000,000 EGP on a sales invoice. You owe them 797,711 EGP across five purchase invoices. You settle 225,703 EGP: their invoice to you drops to 774,297 and your invoices to them drop by the same 225,703 in total. No money moved — both sides simply shrank by the same amount.',
                    'fields' => [
                        ['label' => 'Settlement Amount', 'text' => 'How much to cancel. It cannot be more than the smaller of the two sides — you cannot offset more than one side actually owes.'],
                        ['label' => 'Take from these customer invoices', 'text' => 'Which of their unpaid invoices to you the amount comes off. Distribute the full amount across them.'],
                        ['label' => 'Pay these supplier invoices', 'text' => 'Which of your unpaid invoices to them the same amount comes off. The two sides must total the SAME figure.'],
                        ['label' => 'Comment', 'text' => 'Written onto both statements, naming the invoice numbers on each side, so anyone reading either statement later can see exactly what was offset against what.'],
                        ['label' => 'Edit', 'text' => 'Re-opens a settlement you already saved. Editing first gives the old amounts back to the invoices, then applies the new ones — so the invoices are never double-counted.'],
                        ['label' => 'Delete', 'text' => 'Removes the settlement completely and returns the full amount to the invoices on both sides.'],
                    ],
                    'notes' => [
                        'Both sides must be the same currency. You cannot offset an EGP invoice against a USD one — the exchange rate would change the result.',
                        'Net Balance falls on both sides at once. That is the point of the settlement, not a mistake.',
                    ],
                ],
                [
                    'heading' => 'The reports',
                    'fields' => [
                        ['label' => 'Statement Report', 'text' => 'The full movement history for that customer: every invoice, every payment, every settlement, in date order.'],
                        ['label' => 'Invoice Report', 'text' => 'The invoice list with its own filters, for exporting or sending on.'],
                    ],
                ],
                [
                    'heading' => 'If the numbers look wrong',
                    'notes' => [
                        'A customer missing from the list has no outstanding balance in any currency — they are fully settled, not lost.',
                        'Totals are per currency and never mixed. The page total is the total of that currency only.',
                        'A balance that will not go down usually means the payment was recorded without being allocated to an invoice; it then sits in Down Payments instead.',
                    ],
                ],
            ],
        ];
    }

    private static function supplierBalances(): array
    {
        return [
            'title' => 'Supplier Balances',
            'summary' => 'How much you still owe every supplier. The mirror image of Customer Balances: one row per supplier per currency, showing what you were invoiced, what you have paid, and what is left to pay.',
            'sections' => [
                [
                    'heading' => 'What the numbers mean',
                    'fields' => [
                        ['label' => 'Name', 'text' => 'The supplier, once per currency. A supplier billing you in both EGP and USD has two rows.'],
                        ['label' => 'Down Payments', 'text' => 'Money you paid the supplier IN ADVANCE that is not yet applied to any of their invoices. You have already parted with it, so it reduces what you still owe.'],
                        ['label' => 'Internal Settlements', 'text' => 'Only for a partner who is both supplier and customer: the amount already offset between the two sides.'],
                        ['label' => 'Net Balance', 'text' => 'What you still owe this supplier right now, after every payment and advance.'],
                    ],
                    'example' => 'A supplier invoiced you 500,000 EGP. You paid 200,000 against invoices and sent a 30,000 advance not yet applied. Net Balance is 270,000 — the amount still to pay.',
                ],
                [
                    'heading' => 'The three buttons on each currency card',
                    'fields' => [
                        ['label' => 'All Invoices', 'text' => 'Every supplier invoice in that currency still carrying a balance.'],
                        ['label' => 'Coming Dues', 'text' => 'Not due yet — your planned outgoing cash.'],
                        ['label' => 'Past Due', 'text' => 'Already overdue and still unpaid. Pay these first: they are the ones damaging the relationship.'],
                    ],
                ],
                [
                    'heading' => 'Internal Settlement',
                    'body' => ['Identical to the customer side, entered from whichever screen you happen to be on. Offsetting what a partner owes you against what you owe them, with no money moving.'],
                    'notes' => [
                        'One settlement is recorded once. It shows on both the customer and the supplier side of the same partner — it has not been counted twice.',
                        'Both sides must be in the same currency.',
                    ],
                ],
                [
                    'heading' => 'If the numbers look wrong',
                    'notes' => [
                        'A supplier missing from the list is fully paid.',
                        'A payment recorded without choosing invoices lands in Down Payments and will not reduce any specific invoice until it is allocated.',
                    ],
                ],
            ],
        ];
    }

    /* ══════════════ Financial institutions ══════════════ */

    private static function financialInstitutions(): array
    {
        return [
            'title' => 'Financial Institutions',
            'summary' => 'Every bank, leasing company and factoring company you deal with, and every account and facility you hold with them. This is where the whole banking side of the system begins.',
            'sections' => [
                [
                    'heading' => 'The three kinds of relationship',
                    'fields' => [
                        ['label' => 'Banks', 'text' => 'Where you keep money and borrow it. Accounts and credit facilities live under a bank.'],
                        ['label' => 'Leasing Companies', 'text' => 'For finance-lease contracts and their instalments.'],
                        ['label' => 'Factoring Companies', 'text' => 'Companies that buy your customer invoices early, in exchange for a fee.'],
                    ],
                ],
                [
                    'heading' => 'Debit accounts and credit facilities — the key distinction',
                    'body' => [
                        'Every account under a bank is one of two kinds, and the difference decides what the balance means.',
                        'A DEBIT account holds YOUR money. A CREDIT facility is the bank\'s money you are allowed to use. On a debit account the balance is what you own; on a credit facility it is what you owe.',
                    ],
                    'fields' => [
                        ['label' => 'Add Debit Accounts', 'text' => 'Current Account, Time Of Deposit (T/D), Certificate Of Deposit (C/D). Your own money.'],
                        ['label' => 'Add Credit Facilities', 'text' => 'The overdrafts, the Letter of Guarantee and Letter of Credit limits, and Medium Term Loans. The bank\'s money.'],
                        ['label' => 'Add Current Account', 'text' => 'The ordinary day-to-day account. Add this first — the other accounts settle into it and take their fees from it.'],
                    ],
                    'example' => 'A current account showing 2,000,000 EGP means you hold 2,000,000. A clean overdraft showing an outstanding balance of 2,000,000 EGP means you OWE the bank 2,000,000. The same figure, opposite meanings.',
                ],
                [
                    'heading' => 'Adding a bank',
                    'fields' => [
                        ['label' => '+ New Bank', 'text' => 'Choose the bank from the list and name the branch you deal with. The list itself is maintained in Settings.'],
                        ['label' => 'Branch Name', 'text' => 'The specific branch. Two branches of the same bank are two separate relationships here.'],
                        ['label' => 'Company Account Number', 'text' => 'Your number with that bank, used on statements and reports.'],
                    ],
                ],
                [
                    'heading' => 'Finding things',
                    'fields' => [
                        ['label' => 'Search name or branch', 'text' => 'Filters the list as you type.'],
                        ['label' => 'Show All Accounts', 'text' => 'Expands a bank to reveal every account and facility under it, instead of the bank line alone.'],
                    ],
                ],
                [
                    'heading' => 'Before you delete',
                    'notes' => [
                        'A bank cannot be removed while accounts, facilities or transactions still hang off it. Remove what is under it first — the message on screen names what is blocking.',
                        'Deleting a bank you have real history with erases that history. Almost always the right move is to leave it in place.',
                    ],
                ],
            ],
        ];
    }

    /* ══════════════ Debit accounts — your own money ══════════════ */

    private static function currentAccount(): array
    {
        return [
            'title' => 'Current Account',
            'summary' => 'The ordinary day-to-day bank account. Money comes in from customers and goes out to suppliers, and almost every other account settles into it.',
            'sections' => [
                [
                    'heading' => 'What this account is for',
                    'body' => ['This is the account the business actually runs on. Add it before any other account with this bank: deposits mature into it, facilities settle into it, and fees and interest are taken from it.'],
                ],
                [
                    'heading' => 'The fields',
                    'fields' => [
                        ['label' => 'Account Number', 'text' => 'The number the bank gave you. It appears on every statement and every transfer, so enter it exactly as the bank writes it.'],
                        ['label' => 'IBAN', 'text' => 'The international number, used for incoming and outgoing transfers.'],
                        ['label' => 'Currency', 'text' => 'One currency per account. An account in EGP and one in USD at the same bank are two separate accounts.'],
                        ['label' => 'Balance Amount', 'text' => 'The balance on the day you start using the system — the opening figure everything after is built on.'],
                        ['label' => 'Balance Date', 'text' => 'The date that opening balance was true. Every later movement is applied from this date onward, so getting it right matters more than the amount.'],
                        ['label' => 'Exchange Rate', 'text' => 'For an account not in the company\'s main currency: the rate used to show this balance alongside the others.'],
                        ['label' => 'Interest Rate', 'text' => 'The credit interest the bank pays on the balance, if any.'],
                        ['label' => 'Min Balance', 'text' => 'The minimum the bank requires you to keep. Useful to see how much of the balance is genuinely free to spend.'],
                        ['label' => 'Odoo Code', 'text' => 'The matching account code in Odoo, so entries reach the right account when they are sent across.'],
                    ],
                    'example' => 'You start using the system on 1 January with 2,500,000 EGP in the account. Balance Amount 2,500,000, Balance Date 01/01. A 100,000 receipt dated 5 January raises it to 2,600,000. A receipt dated 20 December — before the balance date — does not, because that money was already inside the opening figure.',
                ],
                [
                    'heading' => 'Common mistakes',
                    'notes' => [
                        'A wrong Balance Date is the usual cause of a balance that will not match the bank: the opening figure is being applied from the wrong day.',
                        'Do not create a second account for the same account number in another currency unless the bank really gave you a separate account.',
                    ],
                ],
            ],
        ];
    }

    private static function timeOfDeposit(): array
    {
        return [
            'title' => 'Time Of Deposit (T/D)',
            'summary' => 'Money locked with the bank for a fixed period at an agreed interest rate. You cannot spend it until it matures, and the bank pays you interest for the wait.',
            'sections' => [
                [
                    'heading' => 'How a deposit works',
                    'body' => ['You move an amount out of a current account into the deposit. It stays there until the end date. The bank pays interest — either at the end, or at regular intervals along the way.'],
                    'example' => 'On 1 January you place 1,000,000 EGP for one year at 20%. The current account falls by 1,000,000 that day. On 31 December the deposit matures and 200,000 interest is due. If you chose to add the maturity amount back to the account, 1,200,000 returns to the current account.',
                ],
                [
                    'heading' => 'The fields',
                    'fields' => [
                        ['label' => 'Deducted From Account #', 'text' => 'The current account the money leaves. That account drops by the deposit amount on the start date — this is a real movement, not just a record.'],
                        ['label' => 'Start Date / End Date', 'text' => 'The locked period. The end date is when the deposit matures and the money becomes available again.'],
                        ['label' => 'Amount', 'text' => 'How much is locked away.'],
                        ['label' => 'Interest Amount [At Maturity]', 'text' => 'The interest the bank will pay over the whole period.'],
                        ['label' => 'Interest Amount Interval', 'text' => 'When the interest is actually paid: monthly, quarterly, semi-annually, annually, or all at once at maturity. This changes WHEN the cash arrives, not how much.'],
                        ['label' => 'Add Maturity Amount To Account', 'text' => 'Whether the deposit plus its interest returns to the current account at the end. Leave it off if the bank rolls the deposit over instead.'],
                    ],
                ],
                [
                    'heading' => 'Renewal',
                    'body' => ['A deposit the bank rolls over for another period is a renewal, kept in the renewal history so the original terms and the new ones are both preserved.'],
                    'notes' => ['A deposit cannot be renewed before its end date has passed — the screen says so if you try.'],
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        'A deposit is not spendable cash. It appears separately from your current accounts for exactly that reason.',
                        'The deducted-from account must have the money on the start date, otherwise its balance goes negative from that day forward.',
                    ],
                ],
            ],
        ];
    }

    private static function certificateOfDeposit(): array
    {
        return [
            'title' => 'Certificate Of Deposit (C/D)',
            'summary' => 'A savings certificate bought from the bank for a fixed term at a fixed return. Like a Time Of Deposit, but usually longer and often paying its interest periodically.',
            'sections' => [
                [
                    'heading' => 'How it differs from a Time Of Deposit',
                    'body' => ['The mechanics are the same: money leaves a current account, stays locked to the end date, and earns interest. Certificates simply tend to run for years rather than months, and more often pay interest monthly or quarterly instead of all at the end.'],
                    'example' => 'You buy a 3-year certificate for 5,000,000 EGP at 18%, interest paid monthly. The current account drops by 5,000,000 on the start date and then receives 75,000 every month. At the end the 5,000,000 comes back.',
                ],
                [
                    'heading' => 'The fields',
                    'fields' => [
                        ['label' => 'Financial Institution Name', 'text' => 'Which bank issued the certificate.'],
                        ['label' => 'Deducted From Account #', 'text' => 'The current account the purchase money leaves.'],
                        ['label' => 'Start Date / End Date', 'text' => 'The life of the certificate.'],
                        ['label' => 'Amount', 'text' => 'The face value you bought.'],
                        ['label' => 'Interest Amount [At Maturity]', 'text' => 'The total return over the full term.'],
                        ['label' => 'Interest Amount Interval', 'text' => 'How often the interest is actually paid to you.'],
                        ['label' => 'Add Maturity Amount To Account', 'text' => 'Whether the face value returns to the current account at the end.'],
                    ],
                ],
                [
                    'heading' => 'Why it matters beyond the interest',
                    'body' => ['A certificate can be pledged as collateral for a Fully Secured Overdraft. If you intend to borrow against it, record it here first — the overdraft form will ask you to pick it.'],
                ],
            ],
        ];
    }

    /* ══════════════ Credit facilities — the bank's money ══════════════ */

    private static function cleanOverdraft(): array
    {
        return [
            'title' => 'Clean Overdraft',
            'summary' => 'An overdraft limit the bank grants against nothing but your standing — no deposit, no cheques, no contracts pledged. "Clean" means unsecured.',
            'sections' => [
                [
                    'heading' => 'What you are recording',
                    'body' => ['The bank lets you go overdrawn up to a limit. You pay interest only on what you actually use, for the days you use it. This screen holds the limit, the rates, and how much of it is currently drawn.'],
                    'example' => 'The bank grants a 10,000,000 EGP clean overdraft at 22%. You draw 3,000,000. You pay interest on the 3,000,000 only — the remaining 7,000,000 costs nothing until you use it.',
                ],
                [
                    'heading' => 'The main fields',
                    'fields' => [
                        ['label' => 'Limit', 'text' => 'The most the bank allows you to owe on this facility.'],
                        ['label' => 'Outstanding Balance', 'text' => 'How much of the limit is drawn right now. This is money you OWE.'],
                        ['label' => 'Balance Date', 'text' => 'The date that outstanding figure was true. Movements are applied from this date onward, so it must match the bank\'s statement.'],
                        ['label' => 'Contract End Date', 'text' => 'When the facility expires. After this date it must be renewed or it is no longer available.'],
                        ['label' => 'Settlement Days', 'text' => 'How many days the bank allows before a drawn amount must be repaid.'],
                    ],
                ],
                [
                    'heading' => 'The rates — this is where the cost comes from',
                    'body' => ['A facility carries several rates at once, and each one is charged on a different basis. Getting one wrong quietly misstates every interest figure afterwards.'],
                    'fields' => [
                        ['label' => 'Borrowing Rate', 'text' => 'The main interest rate on the amount you have drawn.'],
                        ['label' => 'Margin Rate', 'text' => 'The bank\'s margin added on top of the base rate.'],
                        ['label' => 'Min Interest Rate', 'text' => 'A floor: the bank charges at least this much, even if the calculated rate comes out lower.'],
                        ['label' => 'Highest-Debt Rate', 'text' => 'A rate charged on the highest balance you reached during the period, not the balance at the end. It can cost far more than the closing balance suggests.'],
                        ['label' => 'Admin Fees Rate', 'text' => 'The administration fee, usually charged on the whole limit rather than on what you drew.'],
                        ['label' => 'Effective Date', 'text' => 'The date a rate starts applying. Old rates are kept, so interest before that date is still calculated at the old rate.'],
                    ],
                    'example' => 'You drew 3,000,000 for most of the month but touched 8,000,000 for two days. The borrowing rate applies to what you actually owed day by day; the highest-debt rate applies to the 8,000,000 peak. This is why the bank\'s charge can look larger than expected.',
                ],
                [
                    'heading' => 'Renewing the facility',
                    'fields' => [
                        ['label' => 'Renew', 'text' => 'Extends the facility past its contract end date, optionally with a new limit, new rates or new settlement days.'],
                        ['label' => 'Renewal Effective Date', 'text' => 'When the new terms start. It must be after the current contract end date.'],
                        ['label' => 'Unchanged', 'text' => 'Leave a field as Unchanged and the renewal keeps the existing value rather than clearing it.'],
                        ['label' => 'Renewals / Archived Facilities', 'text' => 'The history of previous terms. Nothing is overwritten, so you can always see what the rate was at any past date.'],
                    ],
                ],
                [
                    'heading' => 'Lock',
                    'body' => ['Locking an account freezes it against further changes — used once a period is closed and agreed with the bank, so nobody edits history by accident. It can be unlocked again.'],
                ],
            ],
        ];
    }

    private static function fullySecuredOverdraft(): array
    {
        return [
            'title' => 'Fully Secured Overdraft',
            'summary' => 'An overdraft backed by a deposit or a certificate you already hold with the bank. Because the bank is holding your own money as security, the rate is far lower than a clean overdraft.',
            'sections' => [
                [
                    'heading' => 'What "fully secured" means',
                    'body' => ['You pledge a Time Of Deposit or a Certificate Of Deposit. The bank knows it can take that money if you do not repay, so it lends more cheaply. The pledged deposit stays yours and keeps earning its own interest — but you cannot break it while it secures the overdraft.'],
                    'example' => 'You hold a 5,000,000 EGP certificate earning 18%. The bank grants an overdraft against it at 20% instead of the 25% it would charge unsecured. You earn 18% on the certificate and pay 20% on what you draw — a real cost of about 2% on the amount used, instead of 25%.',
                ],
                [
                    'heading' => 'The collateral fields',
                    'fields' => [
                        ['label' => 'Account Type', 'text' => 'Whether the security is a Time Of Deposit or a Certificate Of Deposit.'],
                        ['label' => 'Account Number', 'text' => 'Which specific deposit or certificate is pledged. It must already be recorded on this bank.'],
                        ['label' => 'Amount', 'text' => 'How much of that deposit is pledged. It can be part of it, not necessarily all.'],
                        ['label' => 'CD Or TD Interest Rate', 'text' => 'What the pledged deposit itself earns. Recorded here so the true net cost of borrowing can be seen: the rate you pay less the rate you earn.'],
                    ],
                ],
                [
                    'heading' => 'The facility fields',
                    'body' => ['Limit, Outstanding Balance, Balance Date, rates and renewal work exactly as they do on a Clean Overdraft — read that guide for the rate detail, which is the same.'],
                    'notes' => [
                        'The limit is normally a percentage of the pledged amount, not the whole of it. The bank keeps a cushion.',
                        'Record the deposit or certificate FIRST. It cannot be selected here until it exists on this bank.',
                    ],
                ],
            ],
        ];
    }

    private static function overdraftCommercialPaper(): array
    {
        return [
            'title' => 'Overdraft Against Commercial Paper',
            'summary' => 'An overdraft secured by customer cheques you hand to the bank. The cheques are the collateral: the bank lends against paper your customers have signed.',
            'sections' => [
                [
                    'heading' => 'What you are recording',
                    'body' => ['You deposit customer cheques with the bank and it lets you draw against them before they clear. If a cheque bounces, the bank comes back to you for that amount.'],
                    'example' => 'You hold 5,000,000 EGP of customer cheques due in 90 days. The bank lends up to 80% of them — 4,000,000 — immediately. When the cheques clear, the lending is repaid.',
                ],
                [
                    'heading' => 'The field that makes this facility different',
                    'fields' => [
                        ['label' => 'Max Lending Limit Per Customer', 'text' => 'The most the bank will lend against ONE customer\'s cheques, regardless of the overall limit. It stops the whole facility resting on a single customer who might default.'],
                    ],
                    'example' => 'The facility limit is 10,000,000 but the per-customer limit is 2,000,000. Even if one customer gives you 6,000,000 of cheques, only 2,000,000 of them can be borrowed against. To use the rest of the facility you need cheques from other customers.',
                ],
                [
                    'heading' => 'The rest of the fields',
                    'body' => ['Limit, Outstanding Balance, Balance Date, the rates and renewal behave exactly as on a Clean Overdraft — that guide covers them in full.'],
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        'A bounced cheque does not just reverse the customer\'s payment; it also reduces what this facility is secured on.',
                        'Set the per-customer limit even when the bank states it only verbally — without it the concentration risk is invisible.',
                    ],
                ],
            ],
        ];
    }

    private static function overdraftAssignmentOfContracts(): array
    {
        return [
            'title' => 'Overdraft Against Assignment Of Contracts',
            'summary' => 'An overdraft secured by contracts you have signed with your customers. You assign the contract to the bank: the customer pays the bank directly, and the bank lends you against the money to come.',
            'sections' => [
                [
                    'heading' => 'What assignment means',
                    'body' => ['You sign a contract worth a known amount over a known period. You hand the right to be paid under it to the bank, and the bank advances you money now against the payments the customer will make later.'],
                    'example' => 'You sign a 20,000,000 EGP contract to be delivered over two years. The bank lends up to 60% of it — 12,000,000 — now. As the customer pays the bank under the contract, the lending is repaid.',
                ],
                [
                    'heading' => 'The field that makes this facility different',
                    'fields' => [
                        ['label' => 'Max Lending Limit Per Contract', 'text' => 'The most the bank will lend against a SINGLE contract, whatever the overall limit. It prevents the entire facility depending on one contract that might be cancelled or delayed.'],
                    ],
                    'example' => 'The facility limit is 30,000,000 and the per-contract limit is 12,000,000. A 25,000,000 contract can still only carry 12,000,000 of borrowing; the remaining facility needs other contracts assigned to it.',
                ],
                [
                    'heading' => 'The rest of the fields',
                    'body' => ['Limit, Outstanding Balance, Balance Date, the rates and renewal behave exactly as on a Clean Overdraft.'],
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        'A contract that finishes or is cancelled stops being security. The facility must be reviewed when that happens.',
                        'Only contracts actually assigned to this bank belong here — not every contract the company holds.',
                    ],
                ],
            ],
        ];
    }

    private static function lgFacility(): array
    {
        return [
            'title' => 'Letter Of Guarantee Facility',
            'summary' => 'The limit the bank gives you for issuing letters of guarantee, and the commission terms for each type. This is the ceiling; individual guarantees are issued from the Letter of Guarantee Issuance screen.',
            'sections' => [
                [
                    'heading' => 'Facility versus issuance',
                    'body' => [
                        'This screen holds the AGREEMENT with the bank: how much you may guarantee in total and what it costs. It does not itself guarantee anything.',
                        'Each actual guarantee you issue to a beneficiary consumes part of this limit, and is entered on the issuance screen instead.',
                    ],
                    'example' => 'The bank grants a 50,000,000 EGP LG facility. You issue a 5,000,000 performance bond to a customer. 45,000,000 of the facility remains available. When that bond is released, the 5,000,000 comes back.',
                ],
                [
                    'heading' => 'The main fields',
                    'fields' => [
                        ['label' => 'LG Contract Name', 'text' => 'Your own name for this agreement, so you can tell two facilities at the same bank apart.'],
                        ['label' => 'Limit', 'text' => 'The total value of guarantees that may be outstanding at once.'],
                        ['label' => 'Contract Start / End Date', 'text' => 'The life of the agreement. It must be renewed before the end date to keep issuing.'],
                    ],
                ],
                [
                    'heading' => 'Terms & Conditions — by LG Type',
                    'body' => ['The bank charges differently for different kinds of guarantee, so the terms are entered per type. Add a row for every type you actually use — a type with no row cannot be issued against this facility.'],
                    'fields' => [
                        ['label' => 'LG Type', 'text' => 'Which kind of guarantee this row prices — bid bond, performance bond, advance payment guarantee, and so on.'],
                        ['label' => 'Cash Cover Rate (%)', 'text' => 'How much of the guarantee value the bank freezes from your account as cover. A 100% rate means the whole amount is locked away for the life of the guarantee.'],
                        ['label' => 'Commission Rate (%)', 'text' => 'What the bank charges for holding the guarantee open.'],
                        ['label' => 'Commission Interval', 'text' => 'How often that commission is charged — monthly, quarterly, semi-annually or annually.'],
                        ['label' => 'Min Commission Fees', 'text' => 'A floor. On a small or short guarantee the bank charges this instead of the calculated percentage.'],
                        ['label' => 'Issuance Fees', 'text' => 'A one-off charge when the guarantee is first issued.'],
                    ],
                    'example' => 'A bid bond of 1,000,000 EGP at 10% cash cover and 2% annual commission: 100,000 is frozen from your account, and the commission is 20,000 a year — unless the minimum fee is higher, in which case the minimum is charged.',
                ],
                [
                    'heading' => 'Renewal',
                    'body' => ['Renewing extends the facility with a new limit or new terms. Previous terms are kept, so a guarantee issued last year is still costed at the rate that applied then.'],
                    'notes' => ['If the renewal popup shows only one LG type, it means only that type has a terms row on the facility. Add the missing types on the facility first.'],
                ],
            ],
        ];
    }

    private static function lcFacility(): array
    {
        return [
            'title' => 'Letter Of Credit Facility',
            'summary' => 'The limit the bank gives you for opening letters of credit — the instrument used to pay foreign suppliers. This is the agreement; each individual credit is opened from the Letter of Credit Issuance screen.',
            'sections' => [
                [
                    'heading' => 'What a letter of credit is for',
                    'body' => ['When you import goods, the supplier wants certainty of payment and you want certainty of delivery. The bank stands between you: it promises to pay the supplier once the shipping documents prove the goods were sent. This facility is your permission to make such promises, up to a limit.'],
                    'example' => 'You import machinery worth 500,000 USD. Rather than paying in advance, you open a letter of credit. The bank pays the supplier only once the documents arrive proving shipment.',
                ],
                [
                    'heading' => 'The two types',
                    'fields' => [
                        ['label' => 'Unsecured', 'text' => 'The bank opens credits on your standing alone, with no deposit pledged. Costlier, but your money stays free.'],
                        ['label' => 'Fully Secured', 'text' => 'Backed by a Time Of Deposit or a Certificate Of Deposit you pledge. Cheaper, but the pledged amount is locked.'],
                    ],
                ],
                [
                    'heading' => 'The fields',
                    'fields' => [
                        ['label' => 'Name', 'text' => 'Your own name for the agreement.'],
                        ['label' => 'Limit', 'text' => 'The total value of credits that may be open at once.'],
                        ['label' => 'Contract Start / End Date', 'text' => 'The life of the agreement.'],
                        ['label' => 'Account Type / Account Number', 'text' => 'For a fully secured facility: which deposit or certificate is pledged. It must already exist on this bank.'],
                        ['label' => 'CD Or TD Interest Rate', 'text' => 'What the pledged deposit earns, so the real net cost of the facility can be seen.'],
                    ],
                ],
                [
                    'heading' => 'How it differs from a Letter of Guarantee',
                    'body' => ['A guarantee is a promise the bank pays only if you FAIL to perform — most guarantees are never called. A letter of credit is a promise the bank pays when the supplier DOES perform — it is expected to be paid, and it is a way of paying, not a safety net.'],
                ],
            ],
        ];
    }

    private static function mediumTermLoan(): array
    {
        return [
            'title' => 'Medium Term Loan',
            'summary' => 'A loan drawn once and repaid over a fixed schedule of instalments, usually across several years. Unlike an overdraft, the amount is fixed and the repayment dates are agreed in advance.',
            'sections' => [
                [
                    'heading' => 'How it differs from an overdraft',
                    'body' => ['An overdraft is a limit you dip into and out of freely, paying interest only on what is drawn. A loan is a single amount taken at the start and repaid on a schedule. You cannot re-borrow what you have repaid.'],
                    'example' => 'A 12,000,000 EGP loan over 4 years repaid monthly is 250,000 of principal a month plus interest. After a year you have repaid 3,000,000 — and that 3,000,000 is not available to draw again.',
                ],
                [
                    'heading' => 'New or Existing — choose this correctly',
                    'body' => ['This single choice changes what the system lets you do with the loan afterwards, so it is the most important field on the form.'],
                    'fields' => [
                        ['label' => 'New (not consumed yet — can pay suppliers)', 'text' => 'The money has not been drawn yet. The loan can be used to pay suppliers, and the system tracks how much of it remains unused.'],
                        ['label' => 'Existing (already drawn — repayment only)', 'text' => 'The money was already taken, usually before you began using the system. Only the repayment schedule is tracked; the loan cannot be used to pay anyone.'],
                    ],
                    'notes' => ['Recording a loan you already drew as "New" makes the system believe money is available that you have in fact already spent.'],
                ],
                [
                    'heading' => 'The fields',
                    'fields' => [
                        ['label' => 'Limit', 'text' => 'The full amount of the loan.'],
                        ['label' => 'Start Date / End Date', 'text' => 'The life of the loan, from drawing to final instalment.'],
                        ['label' => 'Installment Payment Interval', 'text' => 'How often you repay: monthly, quarterly, semi-annually or annually.'],
                        ['label' => 'First Installment Date', 'text' => 'When repayment begins. Banks often allow a grace period, so this can be well after the start date.'],
                        ['label' => 'Remaining Installment Count', 'text' => 'How many instalments are still to pay.'],
                        ['label' => 'Already Paid Amount', 'text' => 'How much has been repaid so far. For a loan taken before you started using the system, this is what you had already paid on that date.'],
                        ['label' => 'Available Room', 'text' => 'For a New loan: how much of it has not yet been spent.'],
                        ['label' => 'Net Balance', 'text' => 'What is still owed on the loan.'],
                        ['label' => 'Account Number', 'text' => 'The current account the loan money lands in and the instalments are taken from.'],
                    ],
                    'example' => 'A 12,000,000 loan taken two years ago with 5,000,000 already repaid: Limit 12,000,000, Already Paid Amount 5,000,000, Net Balance 7,000,000 — and the type is Existing, because the money is long since spent.',
                ],
                [
                    'heading' => 'The statement',
                    'body' => ['The loan statement lists every instalment, what was principal and what was interest, and what remains after each one — the document to check against the bank\'s own schedule.'],
                ],
            ],
        ];
    }

    /* ══════════════ Balances drill-downs ══════════════ */

    private static function invoiceReport(): array
    {
        return [
            'title' => 'Invoice Report — one partner, one currency',
            'summary' => 'Every invoice for the partner you clicked, in the currency you clicked, with what is still open on each one. This is where you work on a single invoice: change its due date, deduct from it, or settle an advance against it.',
            'sections' => [
                [
                    'heading' => 'Where you are',
                    'body' => [
                        'You reached this screen from Balances, by choosing one partner and one currency. Everything here is limited to that pair — it is not a company-wide invoice list.',
                        '"← Back to Balances" returns you to the list of all partners.',
                    ],
                ],
                [
                    'heading' => 'Reading a row',
                    'fields' => [
                        ['label' => 'Invoice Number', 'text' => 'The number on the invoice itself, as issued.'],
                        ['label' => 'Invoice Date', 'text' => 'When the invoice was raised.'],
                        ['label' => 'Invoice Due Date', 'text' => 'When it is meant to be paid. Everything about lateness is measured from this date, not the invoice date.'],
                        ['label' => 'Adjusted', 'text' => 'Shown instead of "Adjust Due Date" once the due date has been moved at least once. Press it to see the full history of changes.'],
                        ['label' => 'Aging', 'text' => 'How many days past the due date the invoice is. It is 0 while the invoice is not due yet, and a dash once it is fully settled — so a number here always means real lateness.'],
                        ['label' => 'Project Name', 'text' => 'Appears only when the company records a project on its invoices.'],
                        ['label' => 'Invoice Amount', 'text' => 'The value of the invoice before tax.'],
                        ['label' => 'VAT Amount', 'text' => 'The tax added on top.'],
                        ['label' => 'Withhold Amount', 'text' => 'Tax withheld at source. It is money the partner keeps back and pays to the tax authority on your behalf, so it will never be collected from them — and it reduces the net balance.'],
                        ['label' => 'Total Deductions', 'text' => 'Everything deducted from this invoice by hand. See the Deductions section below.'],
                        ['label' => 'Net Balance', 'text' => 'What is still open on this invoice.'],
                        ['label' => 'Status', 'text' => 'Where the invoice stands. The four values are explained below.'],
                    ],
                    'example' => 'An invoice of 100,000 with 14,000 VAT, 5,000 withheld and 20,000 already collected shows a Net Balance of 89,000 — that is 100,000 plus 14,000 VAT, less the 5,000 withheld, less the 20,000 collected.',
                ],
                [
                    'heading' => 'The four statuses',
                    'fields' => [
                        ['label' => 'notDueYet', 'text' => 'The due date has not arrived. Nothing is late; this is expected future cash.'],
                        ['label' => 'pastDue', 'text' => 'The due date has passed and nothing has been collected. Shown in red.'],
                        ['label' => 'partiallyCollectedAndPastDue', 'text' => 'Part of it was collected, the rest is late. Also red — the remainder is still overdue.'],
                        ['label' => 'collected', 'text' => 'Fully settled. Nothing is owed, and Aging shows a dash.'],
                    ],
                ],
                [
                    'heading' => 'Adjust Due Date',
                    'body' => [
                        'Use this when the payment date is genuinely renegotiated with the partner — not to hide lateness. Because Aging and the Past Due lists are measured from the due date, moving it changes how this invoice appears in every report.',
                        'Every change is kept in a history, with the original date preserved, so the adjustment can always be audited.',
                    ],
                    'example' => 'An invoice due 1 March is 40 days late on 10 April. The customer agrees a new date of 30 April. After adjusting, Aging returns to 0 and the invoice leaves the Past Due list — until 30 April passes.',
                ],
                [
                    'heading' => 'Deductions',
                    'body' => ['A deduction is an amount taken off the invoice that the partner will never pay — a penalty, a retention, an agreed discount, a quality claim. It is not a payment: no money arrives, the invoice simply becomes smaller.'],
                    'fields' => [
                        ['label' => 'Deduct', 'text' => 'Opens the deductions for that invoice.'],
                        ['label' => '+ Add Deduction', 'text' => 'Adds a line. Choose the deduction type and enter the amount.'],
                        ['label' => 'Select', 'text' => 'The type of deduction. The list is maintained in Settings.'],
                        ['label' => 'Remove', 'text' => 'Deletes a deduction line and gives that amount back to the invoice.'],
                        ['label' => 'Save', 'text' => 'Applies the lines. The Net Balance drops immediately by the total deducted.'],
                    ],
                    'example' => 'An invoice of 200,000 with a 15,000 late-delivery penalty agreed with the customer: add a 15,000 deduction and the Net Balance becomes 185,000. The customer now owes 185,000 and the 15,000 will never be collected.',
                    'notes' => [
                        'A deduction reduces the net balance exactly like a payment does, but no cash is recorded anywhere. Never use it for money actually received — record that as Money Received, or the cash will be missing from your bank.',
                    ],
                ],
                [
                    'heading' => 'Down Payment Amount Settlement',
                    'body' => ['Opens the advances this partner has paid that are not yet tied to any invoice, so you can apply them to the invoices on this screen. That is what moves money out of "Down Payments" on the Balances list and onto a specific invoice.'],
                ],
                [
                    'heading' => 'Export to Excel',
                    'body' => ['Downloads exactly the rows you are looking at — the same partner, the same currency, the same columns including Aging.'],
                ],
                [
                    'heading' => 'If a number looks wrong',
                    'notes' => [
                        'A Net Balance that will not fall is usually a payment recorded without choosing this invoice; it sits in Down Payments until it is settled against the invoice here.',
                        'Aging counts from the ADJUSTED due date once one exists, so an invoice that looks less late than expected may simply have had its date moved — press "Adjusted" to see when and by whom.',
                        'Only this currency is shown. The same partner may have open invoices in another currency on a separate row back in Balances.',
                    ],
                ],
            ],
        ];
    }

    private static function invoiceStatement(): array
    {
        return [
            'title' => 'Statement Report — the running account',
            'summary' => 'The account statement for one partner in one currency: every invoice and every payment in date order, with the balance after each movement. This is the document you send when someone asks "what do we owe each other?"',
            'sections' => [
                [
                    'heading' => 'How it differs from the Invoice Report',
                    'body' => [
                        'The Invoice Report answers "what is open on each invoice?" — one row per invoice, showing what remains.',
                        'This screen answers "what happened, and in what order?" — one row per MOVEMENT, invoices and payments alike, each with the running balance after it. An invoice paid in three instalments is one row in the Invoice Report and four rows here.',
                    ],
                ],
                [
                    'heading' => 'Choosing what to show',
                    'fields' => [
                        ['label' => 'Name', 'text' => 'Which partner. The list holds only partners who have movements in this currency, so anyone missing simply has none.'],
                        ['label' => 'Start Date / End Date', 'text' => 'The window of movements to display.'],
                        ['label' => 'Submit', 'text' => 'Applies the partner and dates you chose.'],
                    ],
                    'notes' => ['The dates filter the MOVEMENTS shown, so the first End Balance in the list already includes everything that happened before the start date.'],
                ],
                [
                    'heading' => 'Reading a row',
                    'fields' => [
                        ['label' => 'Date', 'text' => 'When the movement happened.'],
                        ['label' => 'Document Type', 'text' => 'What kind of movement it was — an invoice, a collection, a cheque, an internal settlement.'],
                        ['label' => 'Document No', 'text' => 'The invoice or document number, so a row can be traced back to its record.'],
                        ['label' => 'Debit', 'text' => 'What increases the balance owed to you: an invoice issued to a customer.'],
                        ['label' => 'Credit', 'text' => 'What decreases it: money collected, a deduction, an internal settlement.'],
                        ['label' => 'End Balance', 'text' => 'The balance after that movement. The last row is what the partner owes today.'],
                        ['label' => 'Comment', 'text' => 'The note left on the movement. An internal settlement writes the invoice numbers it offset here, so you can see exactly what was cancelled against what.'],
                    ],
                    'example' => 'Invoice 1001 for 500,000 on 1 January → Debit 500,000, End Balance 500,000. A 200,000 collection on 15 January → Credit 200,000, End Balance 300,000. An internal settlement of 50,000 on 20 January → Credit 50,000, End Balance 250,000. The partner owes 250,000.',
                ],
                [
                    'heading' => 'On the supplier side',
                    'body' => ['The same screen serves suppliers, with the meaning mirrored: a supplier invoice increases what YOU owe, and a payment you make decreases it. The final End Balance is what you still owe that supplier.'],
                ],
                [
                    'heading' => 'Export to Excel',
                    'body' => ['Downloads the statement exactly as filtered — the usual way to send it to the partner for agreement.'],
                ],
                [
                    'heading' => 'If the statement looks wrong',
                    'notes' => [
                        'A balance that does not match the partner\'s own books is usually a movement recorded on a different date, or a payment sitting unallocated as a down payment.',
                        'Only one currency appears. A partner dealt with in two currencies has two statements, and they must never be added together.',
                        '"No movements found for this date range" means the window is empty, not that the partner has no history — widen the dates.',
                    ],
                ],
            ],
        ];
    }

    private static function netBalanceDetails(): array
    {
        return [
            'title' => 'Net Balance Details',
            'summary' => 'The invoices behind one figure on the Balances screen. You arrive here by pressing All Invoices, Coming Dues or Past Due on a currency card, and you see exactly the invoices that make up that number.',
            'sections' => [
                [
                    'heading' => 'Which invoices you are looking at',
                    'body' => ['The list depends on the button you pressed, and it covers every partner in that currency — not one partner.'],
                    'fields' => [
                        ['label' => 'All Invoices', 'text' => 'Every invoice still carrying a balance in this currency, whatever its due date.'],
                        ['label' => 'Coming Dues', 'text' => 'Only invoices not due yet — the money you expect to move, and when.'],
                        ['label' => 'Past Due', 'text' => 'Only invoices whose due date has passed and are still unpaid.'],
                    ],
                ],
                [
                    'heading' => 'The columns',
                    'fields' => [
                        ['label' => 'Invoice Number', 'text' => 'The invoice, as issued.'],
                        ['label' => 'Invoice Date / Invoice Due Date', 'text' => 'When it was raised and when it falls due.'],
                        ['label' => 'Currency', 'text' => 'Always the currency you drilled into — shown so an exported copy is unambiguous.'],
                        ['label' => 'Net Balance', 'text' => 'What is still open on that invoice. These add up to the figure you clicked.'],
                        ['label' => 'Status', 'text' => 'notDueYet, pastDue, partiallyCollectedAndPastDue, or collected.'],
                    ],
                ],
                [
                    'heading' => 'What to do from here',
                    'body' => ['Use the row actions to open the invoice and work on it. To act on ONE partner\'s invoices instead, go back and use that partner\'s Invoice Report, which is where deductions and due-date changes are made.'],
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        'The count in the header is invoices, not partners: one partner can appear many times.',
                        'The total is for this currency only and must never be added to another currency\'s total.',
                    ],
                ],
            ],
        ];
    }

    private static function downPaymentSettlement(): array
    {
        return [
            'title' => 'Down Payment Settlement',
            'summary' => 'Advances this partner has paid that are not yet attached to any invoice, and the screen where you attach them. Until you do, the money sits as a down payment and no invoice is reduced by it.',
            'sections' => [
                [
                    'heading' => 'Why an advance needs settling',
                    'body' => [
                        'When money arrives before there is an invoice to put it against — a deposit on a contract, a payment on account — it is recorded as a down payment. It correctly reduces what the partner owes overall, but no individual invoice shows it.',
                        'Settling it moves the amount from the general down-payment pot onto specific invoices, so those invoices show as paid.',
                    ],
                    'example' => 'A customer paid a 500,000 advance on a contract. Two invoices of 300,000 and 250,000 are later issued. Settle 300,000 against the first and 200,000 against the second: the first closes, the second is left owing 50,000, and the advance is used up.',
                ],
                [
                    'heading' => 'The columns',
                    'fields' => [
                        ['label' => 'Contract Name', 'text' => 'The contract the advance was paid against.'],
                        ['label' => 'Contract Amount', 'text' => 'The full value of that contract.'],
                        ['label' => 'Down Payment Amount', 'text' => 'The advance originally received.'],
                        ['label' => 'Net Amount', 'text' => 'How much of that advance is still unsettled and available to apply.'],
                        ['label' => 'Currency', 'text' => 'An advance can only be settled against invoices in the same currency.'],
                        ['label' => 'Settlement Amount', 'text' => 'How much of it to apply now. It cannot exceed the Net Amount.'],
                        ['label' => 'Start Settlement', 'text' => 'Applies the amount to the invoices. The invoices\' net balances drop straight away.'],
                    ],
                ],
                [
                    'heading' => 'Odoo columns',
                    'fields' => [
                        ['label' => 'Fully Integrated', 'text' => 'Whether the settlement reached Odoo successfully.'],
                        ['label' => 'Odoo Error', 'text' => 'What Odoo rejected, so it can be corrected and sent again.'],
                        ['label' => 'Odoo References', 'text' => 'The matching entries on the Odoo side.'],
                        ['label' => 'Failed invoice settlements', 'text' => 'Invoices the settlement could not be applied to, with the reason. The rest still went through — only the listed ones need attention.'],
                    ],
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        '"No open down payments found" means every advance from this partner is already applied — not that they never paid one.',
                        'Settling does not create any bank movement. The money arrived when the advance was recorded; this only decides which invoices it belongs to.',
                    ],
                ],
            ],
        ];
    }

    private static function adjustDueDate(): array
    {
        return [
            'title' => 'Adjust Due Date — history',
            'summary' => 'Every time this invoice\'s due date was moved, who moved it and to when. The original date is never overwritten, so the full trail stays auditable.',
            'sections' => [
                [
                    'heading' => 'Why the due date matters so much',
                    'body' => ['Aging, the Past Due list, the aging report and the collection-effectiveness figures are all measured from the due date. Moving it changes how this invoice appears in every one of them — which is exactly why each change is recorded here instead of simply overwriting the old date.'],
                ],
                [
                    'heading' => 'What you see at the top',
                    'fields' => [
                        ['label' => 'Invoice Number', 'text' => 'The invoice being adjusted.'],
                        ['label' => 'Invoice Due Date', 'text' => 'The date in force now — the latest adjustment, or the original if there has been none.'],
                        ['label' => 'Invoice Net Balance', 'text' => 'What is still open on it.'],
                        ['label' => 'Invoice Currency', 'text' => 'The currency of the invoice.'],
                    ],
                ],
                [
                    'heading' => 'The history table',
                    'fields' => [
                        ['label' => 'Adjusted Due Date', 'text' => 'The date it was moved to.'],
                        ['label' => '(Original Due Date)', 'text' => 'Marks the very first row — the date the invoice was issued with. It can never be edited away.'],
                        ['label' => 'Days Count', 'text' => 'How many days that adjustment added compared with the date before it.'],
                        ['label' => 'Date', 'text' => 'When the adjustment itself was recorded.'],
                    ],
                ],
                [
                    'heading' => 'Adding and changing an adjustment',
                    'fields' => [
                        ['label' => 'Adjusted Collection Date', 'text' => 'The new agreed payment date. Enter it and press Submit.'],
                        ['label' => 'Edit', 'text' => 'Changes an adjustment already recorded.'],
                        ['label' => 'Editing the most recent adjustment', 'text' => 'Only the latest adjustment can be edited, because the ones before it are what the later dates were measured against.'],
                        ['label' => 'Delete', 'text' => 'Removes an adjustment. The invoice reverts to the date in force before it, and Aging is recalculated from that date.'],
                    ],
                    'example' => 'An invoice due 1 March is moved to 30 April, then to 31 May. Deleting the 31 May row returns the due date to 30 April; deleting that one returns it to the original 1 March.',
                ],
                [
                    'heading' => 'Use it honestly',
                    'notes' => [
                        'Adjust the date only when the payment date was genuinely renegotiated. Moving it to clear a Past Due figure hides the very problem the report exists to show.',
                        'The history is permanent, so an unjustified change stays visible.',
                    ],
                ],
            ],
        ];
    }

    /* ══════════════ Account drill-downs ══════════════ */

    private static function tdPeriodInterest(): array
    {
        return self::periodInterestGuide('Time Of Deposit', 'deposit');
    }

    private static function cdPeriodInterest(): array
    {
        return self::periodInterestGuide('Certificate Of Deposit', 'certificate');
    }

    /**
     * The two period-interest screens are the same screen over a different
     * account type, so they share one piece of writing rather than two
     * copies that would drift apart.
     */
    private static function periodInterestGuide(string $account, string $noun): array
    {
        return [
            'title' => 'Period Interest — '.$account,
            'summary' => 'Each interest payment the bank has actually made on this '.$noun.', with its date and amount. This is the record of interest RECEIVED, not the interest expected.',
            'sections' => [
                [
                    'heading' => 'What belongs on this screen',
                    'body' => [
                        'When the '.$noun.' pays its interest periodically rather than all at maturity, each payment is posted here as it arrives. Every posting is a real movement: your current account goes up on that date.',
                        'The total expected interest was set on the '.$noun.' itself. This screen shows how much of it has actually been paid so far.',
                    ],
                    'example' => 'A 5,000,000 EGP '.$noun.' at 18% paying monthly earns 75,000 a month. After four months there are four postings of 75,000 here, and the current account has risen by 300,000.',
                ],
                [
                    'heading' => 'The columns',
                    'fields' => [
                        ['label' => 'Date', 'text' => 'When the interest was credited. The bank statement moves on this date, so it must match what the bank did.'],
                        ['label' => 'Amount', 'text' => 'How much was credited for that period.'],
                        ['label' => 'Delete', 'text' => 'Removes a posting. The interest is taken back out of the account on that date, so use it only for a posting entered in error.'],
                    ],
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        '"No period interest postings yet" means none has been recorded — either the '.$noun.' pays everything at maturity, or the payments that have arrived were never entered.',
                        'A wrong date moves the money in the wrong month and will make that month\'s bank reconciliation fail, even when the amount is right.',
                    ],
                ],
            ],
        ];
    }

    private static function tdRenewalHistory(): array
    {
        return [
            'title' => 'Renewal History — Time Of Deposit',
            'summary' => 'Every time this deposit was rolled over for another period, and on what terms. The original maturity date and rate are kept, so the whole life of the deposit can be read back.',
            'sections' => [
                [
                    'heading' => 'What a renewal is',
                    'body' => ['When a deposit reaches its end date, the bank may roll it over rather than return the money. That is a renewal: a new period, often at a new rate. Recording it here keeps the deposit alive with its new terms instead of ending it and creating a second one, so the history stays in one place.'],
                    'example' => 'A one-year deposit of 1,000,000 at 20% matures. The bank renews it for another year at 22%. The renewal records the new date and the new 22% — and the original 20% period stays visible above it.',
                ],
                [
                    'heading' => 'What you see',
                    'fields' => [
                        ['label' => 'Financial Institution / Account Number', 'text' => 'Which deposit this history belongs to.'],
                        ['label' => 'Expiry Date', 'text' => 'The maturity date currently in force.'],
                        ['label' => 'Current Interest Rate', 'text' => 'The rate in force now.'],
                        ['label' => 'Adjusted Renewal Date', 'text' => 'The date this renewal moved maturity to.'],
                        ['label' => '(Original Renewal Date)', 'text' => 'Marks the deposit\'s first maturity date, before any renewal.'],
                        ['label' => 'Days Count', 'text' => 'How many days the renewal added.'],
                        ['label' => 'Interest Amount', 'text' => 'The interest for that period.'],
                    ],
                ],
                [
                    'heading' => 'Recording a renewal',
                    'fields' => [
                        ['label' => 'New Renewal Date', 'text' => 'The new maturity date. It must be after the date currently in force.'],
                        ['label' => 'New Interest Rate (%)', 'text' => 'The rate agreed for the new period. Leave it as it is if the bank kept the same rate.'],
                        ['label' => 'Edit', 'text' => 'Corrects a renewal already recorded.'],
                        ['label' => 'Delete', 'text' => 'Removes it. Maturity returns to the date in force before that renewal.'],
                    ],
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        'A deposit cannot be renewed before its current end date has passed.',
                        'A renewal at a new rate does not change the interest already earned in earlier periods — those were earned at the old rate and stay as they were.',
                    ],
                ],
            ],
        ];
    }

    private static function mtlStatement(): array
    {
        return [
            'title' => 'Medium Term Loan Statement',
            'summary' => 'The full life of one loan: what was drawn, what has been repaid, what interest was paid, and what is still owed. This is the page to check against the bank\'s own schedule.',
            'sections' => [
                [
                    'heading' => 'The two halves of the page',
                    'body' => [
                        'The Facility Ledger shows the MONEY: what was drawn from the loan and what remains available to draw.',
                        'The Installment Breakdown shows the REPAYMENT: each scheduled instalment, split into principal and interest, and what is still due.',
                    ],
                ],
                [
                    'heading' => 'Facility Ledger',
                    'fields' => [
                        ['label' => 'Limit', 'text' => 'The full amount of the loan.'],
                        ['label' => 'Beginning', 'text' => 'The balance at the start of the period shown.'],
                        ['label' => 'Drawn (Credit)', 'text' => 'Money taken from the loan — for a New loan, this is what was used to pay suppliers.'],
                        ['label' => 'Available Room', 'text' => 'How much of the limit has not been drawn yet and is still available.'],
                        ['label' => 'End Balance', 'text' => 'The balance after each movement.'],
                    ],
                    'example' => 'A 12,000,000 loan with 8,000,000 drawn shows Available Room 4,000,000 — money you may still use. "Nothing drawn from this loan yet" means the full limit is still available.',
                ],
                [
                    'heading' => 'Installment Breakdown',
                    'fields' => [
                        ['label' => 'Principle (Debit)', 'text' => 'The part of the instalment that repays the loan itself. Only this reduces what you owe.'],
                        ['label' => 'Interest', 'text' => 'The part that is the cost of borrowing. It reduces nothing — it is simply the price of the money.'],
                        ['label' => 'Due', 'text' => 'What that instalment demands in total.'],
                        ['label' => 'Paid', 'text' => 'What has actually been paid against it.'],
                        ['label' => 'Remaining / Left', 'text' => 'What is still outstanding.'],
                        ['label' => 'Status', 'text' => 'Whether the instalment is settled, partly settled, or still open.'],
                        ['label' => 'Total Due (scheduled)', 'text' => 'Everything the schedule demands over the loan\'s life — principal plus all interest.'],
                    ],
                    'example' => 'A 250,000 instalment made up of 200,000 principal and 50,000 interest reduces the loan by 200,000, not 250,000. The other 50,000 is the cost of the loan and buys down nothing.',
                ],
                [
                    'heading' => 'The percentages at the top',
                    'fields' => [
                        ['label' => '% of the loan itself repaid', 'text' => 'How much of the principal is behind you. This is the honest measure of progress.'],
                        ['label' => '% of scheduled interest paid', 'text' => 'How much of the total cost has been paid.'],
                        ['label' => '% of the loan still available to pay suppliers', 'text' => 'For a New loan: how much has not been drawn yet.'],
                    ],
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        '"No schedule uploaded for this loan yet" means the instalment plan has not been entered, so nothing can be tracked against it. Add the schedule on the loan itself.',
                        'A loan marked "existing" was drawn before you began using the system, so its Facility Ledger shows no drawing — only repayment is tracked.',
                    ],
                ],
            ],
        ];
    }

    private static function lgRenewalHistory(): array
    {
        return [
            'title' => 'Renewal History — Letter Of Guarantee',
            'summary' => 'Every time this guarantee was extended, on what terms, and what it cost. Extending a guarantee is never free: fresh commission is charged and the cash cover stays frozen for longer.',
            'sections' => [
                [
                    'heading' => 'What a renewal costs you',
                    'body' => [
                        'A guarantee is issued until a fixed expiry date. If the beneficiary still needs it, the bank extends it — and charges commission for the new period, exactly as it did for the first.',
                        'The cash cover also stays frozen for the whole extension. Money you expected back stays with the bank.',
                    ],
                    'example' => 'A 1,000,000 EGP guarantee at 10% cash cover and 2% annual commission is extended six months. A further 10,000 commission is charged, and the 100,000 cover stays frozen for another six months instead of returning to your account.',
                ],
                [
                    'heading' => 'What identifies the guarantee',
                    'fields' => [
                        ['label' => 'LG Code', 'text' => 'The bank\'s reference for this guarantee.'],
                        ['label' => 'Transaction Name', 'text' => 'Your own name for it.'],
                        ['label' => 'Issuance Date / Expiry Date', 'text' => 'When it was issued and when it currently expires.'],
                        ['label' => 'Source', 'text' => 'How it was funded — against the LG facility, against a deposit or certificate, or with full cash cover.'],
                    ],
                ],
                [
                    'heading' => 'The history table',
                    'fields' => [
                        ['label' => 'Adjusted Renewal Date', 'text' => 'The expiry date that renewal moved the guarantee to.'],
                        ['label' => '(Original Renewal Date)', 'text' => 'Marks the guarantee\'s first expiry date, before any extension.'],
                        ['label' => 'Days Count', 'text' => 'How many days the extension added.'],
                        ['label' => 'LG Commission Amount', 'text' => 'The commission charged for that period.'],
                        ['label' => 'Cash Cover', 'text' => 'How much stayed frozen for it.'],
                        ['label' => 'Renewal Fees / Fees Amount', 'text' => 'Any additional charge for the extension itself.'],
                        ['label' => 'Min LG Commission Fees', 'text' => 'The floor: charged instead of the calculated percentage when the extension is short or the amount small.'],
                    ],
                ],
                [
                    'heading' => 'Recording a renewal',
                    'fields' => [
                        ['label' => 'New Expiry Date', 'text' => 'The new expiry. It must be after the current one.'],
                        ['label' => 'Terms For The New Period', 'text' => 'The commission rate and cover for the extension. Banks often reprice on renewal, so check them against the bank\'s letter rather than assuming they carry over.'],
                        ['label' => 'To be deducted', 'text' => 'What will be taken from your account for this extension.'],
                        ['label' => 'To be refunded', 'text' => 'What comes back to you — for example cover released because the new terms need less of it.'],
                        ['label' => 'Edit / Delete', 'text' => 'Corrects or removes a renewal. Deleting returns the expiry date to the one in force before it and reverses that period\'s charges.'],
                    ],
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        'Letting a guarantee expire without renewing when the beneficiary still requires it can be treated as a breach of the underlying contract. Watch the expiry dates.',
                        'The cash cover only returns when the guarantee is finally released — not when a period ends.',
                    ],
                ],
            ],
        ];
    }

    /* ══════════════ Transaction forms ══════════════ */

    private static function moneyPaymentForm(): array
    {
        return [
            'title' => 'Money Payment — the form',
            'summary' => 'Recording one payment you made. Choose who you paid, in what currency, and how the money left you. The form changes as you choose, showing only the fields that method needs.',
            'sections' => [
                [
                    'heading' => 'Who you paid',
                    'fields' => [
                        ['label' => 'Partner Type', 'text' => 'Not only suppliers. A payment can go to a supplier, a subsidiary company, a shareholder, an employee, another partner, or to taxes and social insurance.'],
                        ['label' => 'Name', 'text' => 'The specific partner of that type.'],
                        ['label' => 'Payment Date', 'text' => 'The day the money actually left. Bank balances move on this date, so it must match the bank — not the day you entered the record.'],
                    ],
                    'notes' => ['The invoice table appears only for a supplier. Other partner types have no invoices to settle, so the payment is saved on its own.'],
                ],
                [
                    'heading' => 'The two currencies — read this carefully',
                    'body' => ['The currency the invoice is in and the currency you actually pay with need not be the same. That is why there are two, plus an exchange rate between them.'],
                    'fields' => [
                        ['label' => 'Invoice Currency', 'text' => 'The currency the supplier billed you in. It decides which invoices appear below.'],
                        ['label' => 'Pay Currency', 'text' => 'The currency the money actually leaves in.'],
                        ['label' => 'Exchange Rate', 'text' => 'The rate between the two. Needed only when they differ.'],
                        ['label' => 'Amount In Invoice Currency', 'text' => 'Calculated for you: what your payment is worth in the invoice\'s currency. This is the figure that settles the invoice.'],
                    ],
                    'example' => 'An invoice of 10,000 USD paid with 500,000 EGP at a rate of 50: Paid Amount 500,000 EGP, Exchange Rate 50, Amount In Invoice Currency 10,000 USD — and the invoice closes exactly.',
                ],
                [
                    'heading' => 'How the money left — Money Type',
                    'fields' => [
                        ['label' => 'Cash Payment', 'text' => 'Cash out of a branch safe. Needs the Paying Branch and a Receipt Number.'],
                        ['label' => 'Payable Cheques', 'text' => 'You wrote a cheque. Needs the Payment Bank, Account Type, Account Number, Cheque Number, Cheque Amount and Due Date. The bank balance does NOT move until the cheque is marked paid.'],
                        ['label' => 'Outgoing Transfer', 'text' => 'A bank transfer. Needs the bank and account it left from. Also not final until marked paid.'],
                        ['label' => 'Leasing Company', 'text' => 'Payment through a leasing company against a contract, rather than from your own account.'],
                    ],
                    'notes' => ['A cheque or transfer is a promise until it is marked paid. Only then does the bank balance move — which is why the list screen has a Mark As Paid step.'],
                ],
                [
                    'heading' => 'Settling against invoices',
                    'body' => ['The supplier\'s open invoices appear with what remains on each. Distribute the payment across them.'],
                    'fields' => [
                        ['label' => 'Net Balance', 'text' => 'What is still open on that invoice before this payment.'],
                        ['label' => 'Settlement Amount', 'text' => 'How much of your payment goes to that invoice.'],
                        ['label' => 'Withhold Amount', 'text' => 'Tax you withheld from the supplier and will pay to the authority yourself. It settles the invoice without any money leaving you.'],
                        ['label' => 'Unapplied Amount', 'text' => 'What is left over after allocating. If it is not zero, that remainder becomes a down payment sitting against the supplier rather than against any invoice.'],
                    ],
                    'example' => 'You pay 100,000 against an invoice of 120,000 and withhold 5,000 tax. Settlement Amount 100,000, Withhold Amount 5,000 — the invoice drops to 15,000, but only 100,000 left your bank.',
                ],
                [
                    'heading' => 'Contract and purchase order',
                    'fields' => [
                        ['label' => 'Contract / PO Number', 'text' => 'Ties the payment to the contract or purchase order it belongs to, so it shows in that contract\'s reporting.'],
                        ['label' => 'Comment', 'text' => 'A note written onto the supplier\'s statement. Worth filling in — it is what someone reading the statement in six months will see.'],
                    ],
                ],
                [
                    'heading' => 'Editing a saved payment',
                    'notes' => [
                        'Changing the amount re-does the settlement: the old amounts go back to the invoices first, then the new ones are applied. Nothing is double-counted, but the invoices you originally settled will change.',
                        'Changing the payment date moves the bank movement to the new date, which will alter that month\'s reconciliation.',
                    ],
                ],
            ],
        ];
    }

    private static function moneyPaymentDownPayment(): array
    {
        return [
            'title' => 'Advance Payment to a Supplier — the form',
            'summary' => 'Money you pay a supplier BEFORE any invoice exists — a deposit on a contract or purchase order. It is recorded against the contract, and later applied to invoices as they arrive.',
            'sections' => [
                [
                    'heading' => 'When to use this instead of an ordinary payment',
                    'body' => ['Use it when there is no invoice yet. If an invoice already exists, use the normal Money Payment form and settle against it — an advance recorded when an invoice was available leaves that invoice showing unpaid.'],
                    'example' => 'You sign a 2,000,000 supply contract and pay a 20% deposit of 400,000 before delivery. Record it here against the contract. When invoices arrive later, settle the 400,000 against them.',
                ],
                [
                    'heading' => 'The fields',
                    'fields' => [
                        ['label' => 'Down Payment Type', 'text' => 'Whether the advance is against a contract or a purchase order.'],
                        ['label' => 'Supplier Name', 'text' => 'Who is receiving the advance.'],
                        ['label' => 'Contract Currency', 'text' => 'The currency of the contract. It decides what the advance is worth against it.'],
                        ['label' => 'Pay Currency', 'text' => 'The currency the money actually leaves in — it may differ from the contract\'s.'],
                        ['label' => 'Amount In Contract Currency', 'text' => 'Calculated: what you paid, valued in the contract\'s currency. This is what reduces the contract.'],
                        ['label' => 'Contract Name / PO Number', 'text' => 'Which contract or order the advance belongs to.'],
                        ['label' => 'Select Money Type', 'text' => 'How the money left: cash, cheque, or transfer — the same three methods as an ordinary payment.'],
                    ],
                ],
                [
                    'heading' => 'What happens afterwards',
                    'body' => ['The advance appears on the supplier\'s balance as a down payment, reducing what you owe overall. It stays there until you settle it against real invoices from the Supplier Balances screen.'],
                    'notes' => ['An advance left unsettled makes the supplier\'s invoices look unpaid even though you have already paid. Settle it as soon as the invoices exist.'],
                ],
            ],
        ];
    }

    private static function cashExpenseForm(): array
    {
        return [
            'title' => 'Cash Expense — the form',
            'summary' => 'Recording one running cost — rent, fuel, salaries, utilities. Unlike a supplier payment, there is no invoice to settle: you record what was spent, on what, and where the money came from.',
            'sections' => [
                [
                    'heading' => 'How this differs from a supplier payment',
                    'body' => ['A supplier payment settles an invoice and reduces what you owe. An expense settles nothing — it simply records money spent. If the cost has an invoice on the supplier\'s account, it belongs in Money Payment, not here.'],
                ],
                [
                    'heading' => 'What was spent',
                    'fields' => [
                        ['label' => 'Expense Category', 'text' => 'The broad group — rent, transport, utilities. Chosen first, because it decides which expense names are available.'],
                        ['label' => 'Expense Name', 'text' => 'The specific item within that category. Both lists are maintained in Settings.'],
                        ['label' => 'Payment Date', 'text' => 'The day the money left. Bank and safe balances move on this date.'],
                        ['label' => 'Paid Amount', 'text' => 'How much was spent.'],
                        ['label' => 'Currency', 'text' => 'The currency it was spent in.'],
                        ['label' => 'Exchange Rate / Amount In', 'text' => 'When the expense is not in the company\'s main currency: the rate, and the resulting value in the main currency, so all expenses can be compared.'],
                    ],
                ],
                [
                    'heading' => 'Where the money came from — Type',
                    'fields' => [
                        ['label' => 'Cash Payment', 'text' => 'Cash out of a branch safe. Needs the Branch and a Receipt Number.'],
                        ['label' => 'Payable Cheque', 'text' => 'A cheque you wrote. Needs the bank, account, cheque number and due date. The bank balance moves only when the cheque is marked paid.'],
                        ['label' => 'Outgoing Transfer', 'text' => 'A bank transfer out. Needs the bank and account it left.'],
                    ],
                ],
                [
                    'heading' => 'Settling an expense against a customer contract',
                    'body' => ['An expense can optionally be allocated to a customer\'s contract — a cost you incurred on their behalf and will recover. This section is entirely optional; leave it empty for ordinary running costs.'],
                    'fields' => [
                        ['label' => 'Contract Name / Contract Code', 'text' => 'Which contract the cost belongs to.'],
                        ['label' => 'Contract Amount / Balance', 'text' => 'The contract\'s value and what remains available on it.'],
                        ['label' => 'Allocate Amount', 'text' => 'How much of this expense to charge to that contract.'],
                    ],
                    'notes' => ['"No Enough Balance Amount to Process The Payment" means the contract does not have room for the amount you are allocating. Reduce it, or check you picked the right contract.'],
                ],
                [
                    'heading' => 'Other fields',
                    'fields' => [
                        ['label' => 'This transfer is a bank charge', 'text' => 'Marks the expense as a bank fee rather than an ordinary cost, so it is reported with banking charges.'],
                        ['label' => 'User Comment', 'text' => 'A note kept with the record and shown on the list.'],
                    ],
                ],
                [
                    'heading' => 'The Copy button',
                    'body' => ['On the list, Copy opens this form pre-filled from an existing expense — for a cost that repeats every month. The date, receipt and cheque numbers are deliberately left blank, because those are never the same twice.'],
                ],
            ],
        ];
    }

    private static function internalTransferForm(): array
    {
        return [
            'title' => 'Internal Money Transfer — the form',
            'summary' => 'Moving your own money between your own places — bank to bank, bank to safe, safe to bank, safe to safe. Nothing is earned or spent; the total only sits somewhere different.',
            'sections' => [
                [
                    'heading' => 'One transfer, two movements',
                    'body' => ['Every transfer writes two entries at once: money out of the source and money into the destination, both on the same date. That is why the form has a "From" half and a "To" half, and why the total cash of the company does not change.'],
                    'example' => 'Moving 500,000 from a bank account to a branch safe: the bank falls 500,000 and the safe rises 500,000 on the same day. Company cash is unchanged.',
                ],
                [
                    'heading' => 'The From half',
                    'fields' => [
                        ['label' => 'From Bank', 'text' => 'The bank the money leaves. Empty when the money leaves a safe.'],
                        ['label' => 'From Account Type / From Account Number', 'text' => 'Which account exactly. Two accounts at one bank are different sources.'],
                        ['label' => 'From Branch', 'text' => 'The branch safe, when the money leaves cash rather than a bank.'],
                        ['label' => 'Balance', 'text' => 'What that source holds now, shown so you can see the transfer is affordable before saving.'],
                    ],
                ],
                [
                    'heading' => 'The To half',
                    'fields' => [
                        ['label' => 'To Bank / To Account Type / To Account Number', 'text' => 'Where the money lands.'],
                        ['label' => 'To Branch', 'text' => 'The receiving safe, for a transfer into cash.'],
                    ],
                ],
                [
                    'heading' => 'Dates and amount',
                    'fields' => [
                        ['label' => 'Transfer Date', 'text' => 'The day the money left the source.'],
                        ['label' => 'Transfer Days', 'text' => 'How long the money takes to arrive. For a transfer that does not land the same day, this is what keeps the destination\'s balance honest until it does.'],
                        ['label' => 'Transfer Amount', 'text' => 'How much is moving.'],
                        ['label' => 'Currency', 'text' => 'Both sides must be the same currency. Moving between two currencies is a purchase or sale of currency — use Buy Or Sell Currency instead.'],
                        ['label' => 'Cheque Number / Cash Withdrawal', 'text' => 'The reference for the movement, so it can be matched against the bank statement.'],
                    ],
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        'Transferring more than the source holds drives that balance negative from the transfer date onwards. The Balance shown is there to prevent it.',
                        'This is not a payment. Money going to anyone outside the company belongs in Money Payment or Cash Expense.',
                    ],
                ],
            ],
        ];
    }

    private static function currencyExchangeForm(): array
    {
        return [
            'title' => 'Buy Or Sell Currency — the form',
            'summary' => 'Exchanging one currency for another between your own accounts. One side loses the currency sold, the other gains the currency bought, at the rate you agreed with the bank.',
            'sections' => [
                [
                    'heading' => 'Reading the two sides',
                    'body' => ['Like an internal transfer, this writes two movements — but the currency changes between them, so the two amounts are different numbers of the same value.'],
                    'example' => 'Selling 500,000 EGP to buy USD at 50: the EGP account falls 500,000 and the USD account rises 10,000. Currency To Sell Amount 500,000, Exchange Rate 50, Currency To Buy Amount 10,000.',
                ],
                [
                    'heading' => 'The exchange',
                    'fields' => [
                        ['label' => 'Type', 'text' => 'Whether you are buying or selling the currency, from the company\'s point of view.'],
                        ['label' => 'Transaction Date', 'text' => 'The day the exchange happened. Both accounts move on this date.'],
                        ['label' => 'Currency To Sell', 'text' => 'What leaves you.'],
                        ['label' => 'Currency To Sell Amount', 'text' => 'How much of it leaves.'],
                        ['label' => 'Exchange Rate', 'text' => 'The rate the bank actually gave you — not the official rate. Everything downstream is valued at this figure.'],
                        ['label' => 'Currency To Buy', 'text' => 'What you receive.'],
                        ['label' => 'Currency To Buy Amount', 'text' => 'How much you receive. It follows from the amount sold and the rate.'],
                    ],
                ],
                [
                    'heading' => 'The accounts',
                    'fields' => [
                        ['label' => 'From Bank / Account Type / Account Number / Branch', 'text' => 'Where the sold currency leaves. It must be an account in the currency being sold.'],
                        ['label' => 'To Bank / Account Type / Account Number / Branch', 'text' => 'Where the bought currency lands. It must be an account in the currency being bought.'],
                        ['label' => 'Balance', 'text' => 'What the source account holds now, so you can see the sale is affordable.'],
                    ],
                    'notes' => ['If the account you need does not appear, it is because no account exists in that currency at that bank. Create it first — an EGP and a USD account are two separate accounts.'],
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        'Enter the rate the bank actually applied, including its margin. Using the published rate makes the two sides disagree with the bank statement.',
                        'A wrong rate does not just misstate this record: every report that values this currency against the main currency uses it.',
                    ],
                ],
            ],
        ];
    }

    private static function lcSettlementForm(): array
    {
        return [
            'title' => 'LC Settlement Transfer — the form',
            'summary' => 'Paying down a letter of credit the bank has already settled with the supplier. The money moves from your account to the outstanding LC, and any interest the bank charged is posted at the same time.',
            'sections' => [
                [
                    'heading' => 'When this is used',
                    'body' => ['The bank paid your supplier under the letter of credit, so you now owe the bank. This screen records you repaying that amount, in whole or in part.'],
                ],
                [
                    'heading' => 'The fields',
                    'fields' => [
                        ['label' => 'Date', 'text' => 'The day the money left your account.'],
                        ['label' => 'From Bank / From Account Type / From Account Number', 'text' => 'The account paying the settlement.'],
                        ['label' => 'Currency', 'text' => 'The currency of the settlement, which must match the letter of credit.'],
                        ['label' => 'To Letter Of Credit Issuance', 'text' => 'Which letter of credit is being paid down.'],
                        ['label' => 'Remaining Balance', 'text' => 'What is still outstanding on that LC before this payment — shown so you can see how much is left to settle.'],
                        ['label' => 'Amount', 'text' => 'How much of it you are paying now. It may be a partial settlement.'],
                        ['label' => 'Interest Amount', 'text' => 'What the bank charged for the period between paying your supplier and you repaying it.'],
                        ['label' => 'Post Interest To', 'text' => 'Which account the interest is charged to. Keeping it separate from the settlement itself is what lets you see the true cost of the letter of credit later.'],
                        ['label' => 'Comment', 'text' => 'A note kept with the record.'],
                    ],
                    'example' => 'A letter of credit with 300,000 outstanding: you pay 200,000 and the bank charges 4,500 interest. The LC falls to 100,000, your account falls 204,500, and the 4,500 is recorded as a cost rather than a reduction of the debt.',
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        'Interest is not part of the settlement. Adding it to the Amount would understate what you still owe on the letter of credit.',
                        'Settling more than the Remaining Balance is not possible — check you picked the right letter of credit.',
                    ],
                ],
            ],
        ];
    }

    private static function lgIssuanceForm(): array
    {
        return [
            'title' => 'Letter Of Guarantee Issuance — the form',
            'summary' => 'Issuing one guarantee to a beneficiary. The form is the same whichever way the guarantee is funded; only the collateral section changes.',
            'sections' => [
                [
                    'heading' => 'First choose how it is funded — Issuance Type',
                    'body' => ['This choice comes first because it decides what the rest of the form asks for, and what the guarantee costs you.'],
                    'fields' => [
                        ['label' => 'LG Facility', 'text' => 'Issued against the limit the bank granted you. Consumes facility room; only the cash cover percentage is frozen.'],
                        ['label' => 'Against TD', 'text' => 'Backed by a time deposit you pledge. Asks for the TD Account.'],
                        ['label' => 'Against CD', 'text' => 'Backed by a certificate you pledge. Asks for the CD Account.'],
                        ['label' => '100% Cash Cover', 'text' => 'The whole value is frozen from your account. No facility is used, but the money is fully locked for the guarantee\'s life.'],
                    ],
                ],
                [
                    'heading' => 'The facility figures',
                    'fields' => [
                        ['label' => 'LG Limit', 'text' => 'The total the facility allows.'],
                        ['label' => 'Total LGs Outstanding Balance', 'text' => 'What is already committed under it.'],
                        ['label' => 'Total LGs Room', 'text' => 'What remains available. If it is smaller than the guarantee you are issuing, the facility cannot carry it.'],
                        ['label' => 'LG Type / LG Type Outstanding Balance', 'text' => 'The kind of guarantee and how much of the facility that type already uses. The commission terms come from this type — a type with no terms on the facility cannot be issued.'],
                    ],
                ],
                [
                    'heading' => 'Identifying the guarantee',
                    'fields' => [
                        ['label' => 'LG Code', 'text' => 'The bank\'s reference for it.'],
                        ['label' => 'Transaction Name', 'text' => 'Your own name, so you can find it later.'],
                        ['label' => 'Customer / Beneficiary', 'text' => 'Who the guarantee is issued in favour of.'],
                        ['label' => 'Contract / SO / Sales Order Date', 'text' => 'The contract or sales order the guarantee supports.'],
                        ['label' => 'Transaction Reference', 'text' => 'Any further reference you need to match it to your own paperwork.'],
                    ],
                ],
                [
                    'heading' => 'Dates and amount',
                    'fields' => [
                        ['label' => 'Issuance Date', 'text' => 'When the bank issued it. Commission runs from this date.'],
                        ['label' => 'LG Duration (Months)', 'text' => 'How long it stands.'],
                        ['label' => 'Renewal Date', 'text' => 'When it expires and must be extended or released.'],
                        ['label' => 'LG Amount / LG Currency', 'text' => 'The value guaranteed, and in which currency.'],
                    ],
                ],
                [
                    'heading' => 'What it costs — and where the money comes from',
                    'fields' => [
                        ['label' => 'Cash Cover Rate (%) / Cash Cover Amount', 'text' => 'The share of the value the bank freezes from your account, and the resulting figure. This money is unavailable until the guarantee is released.'],
                        ['label' => 'LG Commission Rate (%) / LG Commission Amount', 'text' => 'What the bank charges to hold it open, and the amount for the period.'],
                        ['label' => 'Min LG Commission Fees', 'text' => 'A floor charged instead of the percentage when the guarantee is small or short.'],
                        ['label' => 'Issuance Fees', 'text' => 'The one-off charge for issuing it.'],
                        ['label' => 'Commission Interval', 'text' => 'How often the commission recurs.'],
                        ['label' => 'Cash Cover From Account Type / Cash Cover Deducted From Account #', 'text' => 'Which account the frozen cover comes out of.'],
                        ['label' => 'Fees & Commission Account Type / Deducted From Account #', 'text' => 'Which account pays the fees and commission. It is often a different account from the cover.'],
                    ],
                    'example' => 'A 1,000,000 EGP performance bond at 10% cover and 2% annual commission: 100,000 is frozen, 20,000 commission is charged for the year, plus the issuance fee. The remaining 900,000 of exposure rests on the facility, not on your cash.',
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        'The cash cover is real money leaving your available balance. A run of guarantees at high cover rates can freeze more cash than expected.',
                        'If the LG Type you need is missing, its terms have not been added to the facility. Add them there first.',
                    ],
                ],
            ],
        ];
    }

    private static function lcIssuanceForm(): array
    {
        return [
            'title' => 'Letter Of Credit Issuance — the form',
            'summary' => 'Opening one letter of credit for an import. The bank undertakes to pay your supplier against shipping documents, and this form records the terms and what it costs you.',
            'sections' => [
                [
                    'heading' => 'How it is funded — Issuance Type',
                    'fields' => [
                        ['label' => 'LC Facility', 'text' => 'Opened against the limit the bank granted. Consumes facility room.'],
                        ['label' => '100% Cash Cover', 'text' => 'The whole value is frozen from your account. No facility used, but the cash is locked.'],
                    ],
                ],
                [
                    'heading' => 'The facility figures',
                    'fields' => [
                        ['label' => 'LC Limit / Total LCs Outstanding Balance / Total LCs Room', 'text' => 'What the facility allows, what is already committed, and what remains. The credit you are opening must fit inside the room.'],
                        ['label' => 'LC Type / LC Type Outstanding Balance', 'text' => 'The kind of credit and how much of the facility it already uses.'],
                    ],
                ],
                [
                    'heading' => 'The trade behind it',
                    'fields' => [
                        ['label' => 'LC Code', 'text' => 'The bank\'s reference.'],
                        ['label' => 'Beneficiary Name', 'text' => 'The supplier who will be paid.'],
                        ['label' => 'Contract Reference', 'text' => 'The purchase contract it covers.'],
                        ['label' => 'Purchase Order / New PO / Purchase Order Date', 'text' => 'The order being imported. Choose an existing order, or add a new one here.'],
                    ],
                ],
                [
                    'heading' => 'Dates, amount and currency',
                    'fields' => [
                        ['label' => 'Issuance Date', 'text' => 'When the credit was opened.'],
                        ['label' => 'LC Duration (Days) / Due Date', 'text' => 'How long it runs and when it falls due.'],
                        ['label' => 'LC Amount / LC Currency', 'text' => 'The value of the credit and its currency — usually the supplier\'s currency, not yours.'],
                        ['label' => 'Exchange Rate / Amount In Payment Currency', 'text' => 'What the credit is worth in the currency you will actually pay from.'],
                    ],
                ],
                [
                    'heading' => 'Cost and cover',
                    'fields' => [
                        ['label' => 'Cash Cover Rate (%) / Cash Cover Amount / LC Cash Cover Currency', 'text' => 'How much is frozen, the resulting amount, and the currency it is frozen in.'],
                        ['label' => 'LC Commission Rate (%) / LC Commission Amount', 'text' => 'What the bank charges for the undertaking.'],
                        ['label' => 'Min LC Commission Fees', 'text' => 'The floor charged on a small or short credit.'],
                        ['label' => 'Issuance Fees', 'text' => 'The one-off opening charge.'],
                        ['label' => 'Cash Cover From Account Type / Cash Cover Account #', 'text' => 'Where the frozen cover comes from.'],
                        ['label' => 'Fees & Commission Account Type / Deducted From Account #', 'text' => 'Which account pays the fees.'],
                    ],
                ],
                [
                    'heading' => 'Financing after the bank pays',
                    'fields' => [
                        ['label' => 'Self Financed Or By Bank', 'text' => 'Whether you repay the bank immediately from your own money, or the bank finances the amount for a period and charges interest.'],
                        ['label' => 'Financing Duration (Days)', 'text' => 'How long the bank carries it. Interest accrues over this period and is settled on the LC Settlement screen.'],
                    ],
                    'example' => 'A 500,000 USD credit financed by the bank for 90 days: the bank pays your supplier on presentation of documents, and you repay it 90 days later with interest — recorded through LC Settlement.',
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        'A letter of credit is expected to be paid — unlike a guarantee, which usually is not. Plan the cash for its due date.',
                        'The cover currency and the credit currency can differ; check which one the bank actually froze.',
                    ],
                ],
            ],
        ];
    }

    private static function factoringForm(): array
    {
        return [
            'title' => 'Factoring Transaction — the form',
            'summary' => 'Selling one customer invoice to a factoring company to be paid now instead of at its due date. The form works out what you receive after the factor\'s interest and charges.',
            'sections' => [
                [
                    'heading' => 'What you are recording',
                    'body' => ['You hand over an invoice due in the future. The factor advances you a percentage of it today and charges interest for the waiting time. What arrives in your account is less than the invoice — the difference is the cost of getting paid early.'],
                    'example' => 'An invoice of 1,000,000 due in 90 days, factored at 80% with 15% annual interest: the factor advances 800,000, charges roughly 29,500 interest for 90 days, and you receive about 770,500 today.',
                ],
                [
                    'heading' => 'The invoice being factored',
                    'fields' => [
                        ['label' => 'Factoring Date', 'text' => 'The day the invoice was handed over. Interest runs from here.'],
                        ['label' => 'Factoring Company / Factoring Contract', 'text' => 'Who is buying it, under which agreement. The contract supplies the rate and the limit.'],
                        ['label' => 'Customer', 'text' => 'Whose invoice it is.'],
                        ['label' => 'Invoice Currency / Invoice Number / Invoice Amount', 'text' => 'Which invoice, and for how much.'],
                        ['label' => 'Invoice Due Date', 'text' => 'When the customer was originally due to pay. The gap between this and the factoring date is what you are paying interest on.'],
                    ],
                ],
                [
                    'heading' => 'What you get and what it costs',
                    'fields' => [
                        ['label' => 'Factoring Percentage', 'text' => 'The share of the invoice the factor advances. The rest is held back until the customer pays.'],
                        ['label' => 'Factoring Amount', 'text' => 'The advance itself, before charges.'],
                        ['label' => 'Remaining Limit', 'text' => 'How much of the contract\'s limit is still available. The transaction cannot exceed it.'],
                        ['label' => 'Contract Interest Rate (%)', 'text' => 'The rate from the factoring contract.'],
                        ['label' => 'Diff In Days', 'text' => 'Days between the factoring date and the invoice due date — the period interest is charged for.'],
                        ['label' => 'Factoring Interest Amount', 'text' => 'The interest for those days.'],
                        ['label' => 'Other Charges', 'text' => 'Any further fee the factor applies.'],
                        ['label' => 'Received Amount', 'text' => 'What actually lands in your account: the advance less interest and charges. Check this against what the factor really paid.'],
                    ],
                ],
                [
                    'heading' => 'Where the money lands',
                    'fields' => [
                        ['label' => 'Bank / Account Type / Account Number', 'text' => 'The account the factor pays into. That balance rises by the Received Amount on the factoring date.'],
                    ],
                ],
                [
                    'heading' => 'With recourse or without — it matters',
                    'body' => ['Under a with-recourse contract, if the customer never pays, the factor comes back to you and you must repay. Without recourse, the factor carries that loss. The contract decides which, and it changes what happens when a customer defaults — not what you enter here.'],
                ],
            ],
        ];
    }

    private static function factoringContractForm(): array
    {
        return [
            'title' => 'Factoring Contract — the form',
            'summary' => 'The agreement with a factoring company: how much they will advance in total, at what rates, and who carries the loss if a customer does not pay. Individual invoices are factored against this contract.',
            'sections' => [
                [
                    'heading' => 'The choice that matters most',
                    'fields' => [
                        ['label' => 'Recourse Type', 'text' => 'With recourse means you repay the factor if the customer defaults — the risk stays with you, and the rate is lower. Without recourse means the factor carries the loss, and charges more for it.'],
                    ],
                    'example' => 'A customer never pays a factored invoice of 1,000,000. Under a with-recourse contract you must return the advance to the factor. Without recourse, the factor absorbs it and you keep the money.',
                ],
                [
                    'heading' => 'The agreement',
                    'fields' => [
                        ['label' => 'Factoring Company Name', 'text' => 'Who you are contracting with.'],
                        ['label' => 'Contract Start Date / Contract End Date', 'text' => 'The life of the agreement.'],
                        ['label' => 'Select Currency', 'text' => 'The currency it operates in. Only invoices in this currency can be factored under it.'],
                        ['label' => 'Limit', 'text' => 'The most that may be outstanding with this factor at once.'],
                        ['label' => 'Outstanding Balance / Balance Date', 'text' => 'What is already outstanding, and the date that figure was true. For a contract that predates your use of the system.'],
                    ],
                ],
                [
                    'heading' => 'The rates',
                    'body' => ['These behave exactly like a bank facility\'s rates, and each is charged on a different basis.'],
                    'fields' => [
                        ['label' => 'Borrowing Rate (%)', 'text' => 'The main rate on what is advanced.'],
                        ['label' => 'Bank Margin Rate (%)', 'text' => 'The margin added on top.'],
                        ['label' => 'Min Interest Rate (%)', 'text' => 'A floor charged when the calculated rate falls below it.'],
                        ['label' => 'Highest Debt Balance Rate (%)', 'text' => 'Charged on the highest balance reached in the period, not the closing one — it can cost more than the final figure suggests.'],
                        ['label' => 'Admin Fees Rate (%)', 'text' => 'The administration fee, usually on the whole limit.'],
                        ['label' => 'Settled Max Within (Days)', 'text' => 'How long the factor allows before an advance must be settled.'],
                    ],
                    'notes' => ['The rates can only be set when the contract is created. Correcting them later means re-creating the contract, so check them against the signed agreement before saving.'],
                ],
                [
                    'heading' => 'Outstanding Breakdown',
                    'body' => ['For a contract carried over from before you began using the system: break the outstanding balance down by the date each part is due to settle, so the cash-flow reporting knows when each piece falls due.'],
                    'notes' => [
                        '"Repeater Outstanding Balance Must Be Equal To Total Outstanding Balance" — the lines must add up to the Outstanding Balance exactly.',
                        '"Settlement Dates Must Be Greater Than Or Equal Contract Start Date" — no line may settle before the contract began.',
                    ],
                ],
            ],
        ];
    }

    private static function factoringContracts(): array
    {
        return [
            'title' => 'Factoring Contracts — the list',
            'summary' => 'Every agreement you hold with factoring companies, with its limit, its rates and how much of it is currently used.',
            'sections' => [
                [
                    'heading' => 'What a row tells you',
                    'fields' => [
                        ['label' => 'Limit / Outstanding Balance', 'text' => 'What the factor allows in total, and what is committed now. The difference is what you can still factor.'],
                        ['label' => 'Recourse Type', 'text' => 'Whether you carry the risk of a customer defaulting, or the factor does.'],
                        ['label' => 'Contract End Date', 'text' => 'When the agreement lapses. After it, no new invoice can be factored under it.'],
                    ],
                ],
                [
                    'heading' => 'Renewal and archived contracts',
                    'body' => ['Renewing extends a contract with new terms while keeping the previous ones, so an invoice factored last year is still costed at the rate that applied then. Archived contracts are those earlier periods — nothing is overwritten.'],
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        'A contract that has expired still shows its history, but cannot take new transactions. Renew it before factoring further invoices.',
                        'Rates cannot be edited after creation. A contract entered with the wrong rate must be re-created.',
                    ],
                ],
            ],
        ];
    }

    /* ══════════════ Account forms ══════════════ */

    private static function financialInstitutionForm(): array
    {
        return [
            'title' => 'Add or Edit a Financial Institution',
            'summary' => 'Registering a bank, leasing company or factoring company you deal with. This is the parent record — accounts and facilities are added under it afterwards.',
            'sections' => [
                [
                    'heading' => 'The fields',
                    'fields' => [
                        ['label' => 'Select Bank', 'text' => 'The institution itself, chosen from the list maintained in Settings. If the bank is missing, add it there first.'],
                        ['label' => 'Branch Name', 'text' => 'The branch you actually deal with. Two branches of one bank are two separate records here, because their accounts and facilities are separate.'],
                        ['label' => 'Company Account Number', 'text' => 'Your reference number with that institution, used on statements and reports.'],
                    ],
                ],
                [
                    'heading' => 'What comes next',
                    'body' => ['Registering the bank creates nothing financial on its own. Add a Current Account first — the other accounts settle into it and take their fees from it — and then any deposits and facilities.'],
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        'Editing a branch name is safe. Changing which bank a record points at is not, once transactions exist under it.',
                        'An institution with accounts or transactions under it cannot be deleted until those are removed.',
                    ],
                ],
            ],
        ];
    }

    private static function currentAccountForm(): array
    {
        return [
            'title' => 'Add or Edit a Bank Account',
            'summary' => 'Registering one account at a bank. The two fields that matter most are the opening balance and the date it was true — everything the system calculates afterwards is built on them.',
            'sections' => [
                [
                    'heading' => 'Identifying the account',
                    'fields' => [
                        ['label' => 'Account Number', 'text' => 'Exactly as the bank writes it. It appears on every statement and transfer.'],
                        ['label' => 'IBAN', 'text' => 'The international number, for incoming and outgoing transfers.'],
                        ['label' => 'Currency', 'text' => 'One currency per account. An EGP and a USD account at the same bank are two separate accounts, and their balances are never added.'],
                        ['label' => 'Odoo Code', 'text' => 'The matching account in Odoo, so entries reach the right place when sent across.'],
                    ],
                ],
                [
                    'heading' => 'The opening balance — get this right',
                    'fields' => [
                        ['label' => 'Balance Amount', 'text' => 'What the account held on the day you started using the system.'],
                        ['label' => 'Balance Date', 'text' => 'The day that figure was true. Every later movement is applied from this date forward; anything dated before it is already inside the opening figure and will not move the balance.'],
                    ],
                    'example' => 'Opening 2,500,000 dated 1 January. A 100,000 receipt on 5 January takes it to 2,600,000. A 100,000 receipt dated 20 December does not — that money was already counted in the 2,500,000.',
                    'notes' => ['A wrong Balance Date is the usual reason a balance will not agree with the bank, even when the amount is right.'],
                ],
                [
                    'heading' => 'The rest',
                    'fields' => [
                        ['label' => 'Exchange Rate', 'text' => 'For an account not in the company\'s main currency: the rate used to show it alongside the others.'],
                        ['label' => 'Interest Rate', 'text' => 'What the bank pays on the balance, if anything.'],
                        ['label' => 'Min Balance', 'text' => 'The minimum the bank requires you to keep, so you can see how much is genuinely free to spend.'],
                    ],
                ],
                [
                    'heading' => 'Locking an account',
                    'body' => ['From the accounts list, an account can be locked once a period is closed and agreed with the bank. A locked account refuses further changes, so nobody edits settled history by accident. It can be unlocked again.'],
                ],
            ],
        ];
    }

    /**
     * Time deposits and certificates share one entry form, so they share
     * one piece of writing parameterised by the account type — rather than
     * two near-identical copies that would drift.
     */
    private static function depositForm(string $account, string $noun): array
    {
        return [
            'title' => 'Add or Edit a '.$account,
            'summary' => 'Placing money with the bank for a fixed period. Saving this form moves real money: the current account you choose falls by the amount on the start date.',
            'sections' => [
                [
                    'heading' => 'Where the money comes from',
                    'fields' => [
                        ['label' => 'Deducted From Account #', 'text' => 'The current account the money leaves. That account drops by the full amount on the start date — this is a real movement, not just a record, so the money must actually be there.'],
                        ['label' => 'Account Number', 'text' => 'The bank\'s number for the '.$noun.' itself.'],
                        ['label' => 'Currency', 'text' => 'The currency it is placed in. It must match the account it is funded from.'],
                    ],
                ],
                [
                    'heading' => 'The period and the amount',
                    'fields' => [
                        ['label' => 'Start Date', 'text' => 'When the money is locked away. The funding account moves on this date.'],
                        ['label' => 'End Date', 'text' => 'Maturity — when it becomes available again.'],
                        ['label' => 'Amount', 'text' => 'How much is placed.'],
                    ],
                ],
                [
                    'heading' => 'The return',
                    'fields' => [
                        ['label' => 'Interest Rate (%)', 'text' => 'The rate agreed with the bank.'],
                        ['label' => 'Interest Amount [At Maturity]', 'text' => 'The total interest over the whole period.'],
                        ['label' => 'Interest Amount Interval', 'text' => 'When it is actually paid — at maturity, or periodically along the way. This changes WHEN the cash arrives, not how much. Choosing a periodic interval is what makes the Period Interest screen meaningful.'],
                        ['label' => 'Add Maturity Amount To Account', 'text' => 'Whether the amount plus its interest returns to the current account at the end. Leave it off if the bank rolls it over instead.'],
                    ],
                    'example' => 'A 1,000,000 '.$noun.' for one year at 20% paying at maturity: the current account falls 1,000,000 on the start date and rises 1,200,000 a year later. Paying monthly instead, it would rise about 16,700 each month and 1,000,000 at the end.',
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        'If the funding account does not hold the amount on the start date, its balance goes negative from that day onward.',
                        'This money is not spendable cash and is shown separately from your current accounts for that reason.',
                        'If you intend to borrow against it later, record it here first — a secured overdraft cannot select a '.$noun.' that does not exist yet.',
                    ],
                ],
            ],
        ];
    }

    /**
     * The four overdraft entry forms are the same form apart from one
     * distinguishing section, so the common part is written once here.
     *
     * @param  array<int, array<string, mixed>>  $extraSections
     */
    private static function overdraftForm(string $title, string $summary, array $extraSections = []): array
    {
        $common = [
            [
                'heading' => 'Identifying the facility',
                'fields' => [
                    ['label' => 'Bank Name', 'text' => 'Which bank granted it.'],
                    ['label' => 'Account Number', 'text' => 'The bank\'s number for the facility.'],
                    ['label' => 'Currency', 'text' => 'The currency it operates in.'],
                    ['label' => 'Odoo Code', 'text' => 'The matching account in Odoo.'],
                    ['label' => 'Contract End Date', 'text' => 'When the facility expires. After it, the facility must be renewed to stay available.'],
                ],
            ],
            [
                'heading' => 'The limit and what is drawn',
                'fields' => [
                    ['label' => 'Limit', 'text' => 'The most the bank allows you to owe on this facility.'],
                    ['label' => 'Outstanding Balance', 'text' => 'How much of it is drawn as at the balance date. This is money you OWE — the opposite of a bank account balance.'],
                    ['label' => 'Balance Date', 'text' => 'The date that outstanding figure was true. Movements are applied from this date onward, so it must match the bank\'s statement.'],
                    ['label' => 'Current Chapter Start Date', 'text' => 'When the terms currently in force began. A renewal starts a new chapter, and the old one is kept.'],
                ],
            ],
            [
                'heading' => 'Terms & Conditions — where the cost comes from',
                'body' => ['Several rates apply at once, each charged on a different basis. One wrong figure quietly misstates every interest calculation afterwards.'],
                'fields' => [
                    ['label' => 'Borrowing Rate (%)', 'text' => 'The main rate on the amount drawn.'],
                    ['label' => 'Bank Margin Rate (%)', 'text' => 'The bank\'s margin on top of the base rate.'],
                    ['label' => 'Min Interest Rate (%)', 'text' => 'A floor: charged even when the calculated rate comes out lower.'],
                    ['label' => 'Highest Debt Balance Rate (%)', 'text' => 'Charged on the highest balance reached during the period, not the closing balance. This is the one that surprises people.'],
                    ['label' => 'Admin Fees Rate (%)', 'text' => 'The administration fee, usually charged on the whole limit rather than what you drew.'],
                    ['label' => 'Settled Max Within (Days)', 'text' => 'How many days the bank allows before a drawn amount must be repaid.'],
                ],
                'example' => 'You draw 3,000,000 for most of a month but touch 8,000,000 for two days. The borrowing rate applies to what you owed day by day; the highest-debt rate applies to the 8,000,000 peak — which is why the bank\'s charge can exceed what the closing balance suggests.',
            ],
            [
                'heading' => 'Outstanding Breakdown',
                'body' => ['For a facility carried over from before you began using the system: split the outstanding balance by the date each part is due to settle, so cash-flow reporting knows when each piece falls due.'],
                'notes' => ['The lines must add up to the Outstanding Balance exactly, and no settlement date may fall before the contract start date.'],
            ],
            [
                'heading' => 'Editing a facility that has been renewed',
                'notes' => [
                    'Once a renewal exists, this form edits the CURRENT chapter\'s terms only. Account details and onboarding figures — Outstanding Balance, Balance Date, Outstanding Breakdown — belong to the original setup and cannot be changed here.',
                    'To change a renewal\'s own start date, delete the renewal from the Archived Facilities tab and record it again.',
                ],
            ],
        ];

        return [
            'title' => $title,
            'summary' => $summary,
            'sections' => array_merge(array_slice($common, 0, 2), $extraSections, array_slice($common, 2)),
        ];
    }

    private static function cleanOverdraftForm(): array
    {
        return self::overdraftForm(
            'Add or Edit a Clean Overdraft',
            'An overdraft limit granted against nothing but your standing — no deposit, no cheques, no contracts pledged. You pay interest only on what you actually draw.'
        );
    }

    private static function fullySecuredOverdraftForm(): array
    {
        return self::overdraftForm(
            'Add or Edit a Fully Secured Overdraft',
            'An overdraft backed by a deposit or certificate you pledge. Because the bank holds your own money as security, the rate is far lower than an unsecured facility.',
            [[
                'heading' => 'The collateral — what makes this facility cheap',
                'fields' => [
                    ['label' => 'Account Type', 'text' => 'Whether the security is a Time Of Deposit or a Certificate Of Deposit.'],
                    ['label' => 'Account Number', 'text' => 'Which deposit or certificate is pledged. It must already exist on this bank — record it first if it does not.'],
                    ['label' => 'Amount', 'text' => 'How much of that deposit is pledged. It can be part of it rather than all.'],
                    ['label' => 'CD Or TD Interest Rate', 'text' => 'What the pledged deposit itself earns. Recorded so the true net cost of borrowing is visible: the rate you pay less the rate you earn.'],
                ],
                'example' => 'A pledged certificate earning 18% against an overdraft at 20%: the real cost of the money you draw is about 2%, not 20%.',
                'notes' => ['The limit is normally a percentage of the pledged amount, not all of it — the bank keeps a cushion.'],
            ]]
        );
    }

    private static function overdraftCommercialPaperForm(): array
    {
        return self::overdraftForm(
            'Add or Edit an Overdraft Against Commercial Paper',
            'An overdraft secured by customer cheques you deposit with the bank. The bank lends against paper your customers have signed, and comes back to you if a cheque bounces.',
            [[
                'heading' => 'The field that makes this facility different',
                'fields' => [
                    ['label' => 'Max Lending Limit Per Customer', 'text' => 'The most the bank will lend against ONE customer\'s cheques, whatever the overall limit. It stops the whole facility resting on a single customer who might default.'],
                ],
                'example' => 'A 10,000,000 facility with a 2,000,000 per-customer limit: even if one customer gives you 6,000,000 of cheques, only 2,000,000 can be borrowed against them. The rest of the facility needs cheques from other customers.',
                'notes' => ['Set this even when the bank states it only verbally — without it the concentration risk is invisible.'],
            ]]
        );
    }

    private static function overdraftAssignmentForm(): array
    {
        return self::overdraftForm(
            'Add or Edit an Overdraft Against Assignment Of Contracts',
            'An overdraft secured by customer contracts you assign to the bank. The customer pays the bank directly, and the bank advances you against the money still to come.',
            [[
                'heading' => 'The field that makes this facility different',
                'fields' => [
                    ['label' => 'Max Lending Limit Per Contract', 'text' => 'The most the bank will lend against a SINGLE contract, whatever the overall limit. It prevents the whole facility depending on one contract that might be cancelled or delayed.'],
                ],
                'example' => 'A 30,000,000 facility with a 12,000,000 per-contract limit: a 25,000,000 contract can still only carry 12,000,000 of borrowing. The remaining facility needs other contracts assigned to it.',
                'notes' => ['A contract that finishes or is cancelled stops being security, and the facility must be reviewed when that happens.'],
            ]]
        );
    }

    private static function lgFacilityForm(): array
    {
        return [
            'title' => 'Add or Edit a Letter Of Guarantee Facility',
            'summary' => 'The agreement with the bank for issuing guarantees: how much you may guarantee in total, and what each type of guarantee costs. Individual guarantees are issued elsewhere.',
            'sections' => [
                [
                    'heading' => 'The agreement itself',
                    'fields' => [
                        ['label' => 'Bank Name', 'text' => 'Which bank granted the facility.'],
                        ['label' => 'LG Contract Name', 'text' => 'Your own name for it, so two facilities at one bank can be told apart.'],
                        ['label' => 'Contract Start Date / Contract End Date', 'text' => 'The life of the agreement. It must be renewed before the end date to keep issuing.'],
                        ['label' => 'Limit', 'text' => 'The total value of guarantees that may be outstanding at once.'],
                        ['label' => 'Currency', 'text' => 'The currency the facility operates in.'],
                    ],
                ],
                [
                    'heading' => 'Terms & Conditions — by LG Type',
                    'body' => ['The bank prices each kind of guarantee differently, so terms are entered per type. Add a row for every type you actually use: a type with no row here cannot be issued against this facility, which is the usual reason only one type appears when issuing.'],
                    'fields' => [
                        ['label' => 'LG Type', 'text' => 'Which kind of guarantee this row prices — bid bond, performance bond, advance payment guarantee, and so on.'],
                        ['label' => 'Cash Cover Rate (%)', 'text' => 'The share of each guarantee\'s value the bank freezes from your account. At 100% the whole amount is locked for the guarantee\'s life.'],
                        ['label' => 'Commission Rate (%)', 'text' => 'What the bank charges for holding a guarantee of this type open.'],
                        ['label' => 'Commission Interval', 'text' => 'How often that commission recurs — monthly, quarterly, semi-annually or annually.'],
                        ['label' => 'Min Commission Fees', 'text' => 'A floor charged instead of the percentage on a small or short guarantee.'],
                        ['label' => 'Issuance Fees', 'text' => 'The one-off charge when a guarantee of this type is issued.'],
                    ],
                    'example' => 'A bid bond row at 10% cover and 2% annual commission: a 1,000,000 guarantee under it freezes 100,000 and costs 20,000 a year — unless the minimum fee is higher, in which case the minimum applies.',
                ],
                [
                    'heading' => 'Editing a facility that has been renewed',
                    'notes' => ['Once a renewal exists, this form edits the current chapter\'s terms only. Earlier terms are preserved so a guarantee issued under them is still costed correctly.'],
                ],
            ],
        ];
    }

    private static function lcFacilityForm(): array
    {
        return [
            'title' => 'Add or Edit a Letter Of Credit Facility',
            'summary' => 'The agreement with the bank for opening letters of credit: how much may be open at once, and whether it is backed by a pledged deposit.',
            'sections' => [
                [
                    'heading' => 'The agreement',
                    'fields' => [
                        ['label' => 'Bank Name', 'text' => 'Which bank granted it.'],
                        ['label' => 'Name', 'text' => 'Your own name for the agreement.'],
                        ['label' => 'Contract Start Date / Contract End Date', 'text' => 'Its life. It must be renewed before expiry to keep opening credits.'],
                        ['label' => 'Limit', 'text' => 'The total value of credits that may be open at once.'],
                        ['label' => 'Select Currency', 'text' => 'The currency the facility operates in.'],
                    ],
                ],
                [
                    'heading' => 'Type — and what it costs you',
                    'fields' => [
                        ['label' => 'Unsecured', 'text' => 'The bank opens credits on your standing alone. More expensive, but your money stays available.'],
                        ['label' => 'Fully Secured', 'text' => 'Backed by a deposit or certificate you pledge. Cheaper, but the pledged amount is locked for the facility\'s life.'],
                    ],
                ],
                [
                    'heading' => 'The collateral, for a fully secured facility',
                    'fields' => [
                        ['label' => 'Account Type / Account Number', 'text' => 'Which deposit or certificate is pledged. It must already exist on this bank.'],
                        ['label' => 'Amount', 'text' => 'How much of it is pledged.'],
                        ['label' => 'CD Or TD Interest Rate', 'text' => 'What the pledged deposit earns, so the true net cost of the facility is visible.'],
                        ['label' => 'Limit', 'text' => 'The limit the pledge supports — normally a percentage of the pledged amount, not all of it.'],
                    ],
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => ['Record the deposit or certificate before creating the facility; it cannot be selected here until it exists.'],
                ],
            ],
        ];
    }

    private static function mediumTermLoanForm(): array
    {
        return [
            'title' => 'Add or Edit a Medium Term Loan',
            'summary' => 'A loan drawn once and repaid on a fixed schedule. One field on this form — New or Existing — changes what the system will let you do with the loan afterwards, so choose it carefully.',
            'sections' => [
                [
                    'heading' => 'New or Existing — the field that matters most',
                    'fields' => [
                        ['label' => 'New (not consumed yet — can pay suppliers)', 'text' => 'The money has not been drawn. The loan can be used to pay suppliers, and the system tracks how much remains unused.'],
                        ['label' => 'Existing (already drawn — repayment only)', 'text' => 'The money was taken already, usually before you began using the system. Only the repayment schedule is tracked; the loan cannot pay anyone.'],
                    ],
                    'notes' => ['Recording a loan you already drew as "New" makes the system believe money is available that you have in fact spent.'],
                ],
                [
                    'heading' => 'The loan',
                    'fields' => [
                        ['label' => 'Name', 'text' => 'Your own name for it.'],
                        ['label' => 'Limit', 'text' => 'The full amount of the loan.'],
                        ['label' => 'Currency', 'text' => 'The currency it is drawn and repaid in.'],
                        ['label' => 'Start Date / End Date', 'text' => 'From drawing to the final instalment.'],
                        ['label' => 'Account Number', 'text' => 'The current account the money lands in and the instalments are taken from.'],
                        ['label' => 'Odoo Code', 'text' => 'The matching account in Odoo.'],
                    ],
                ],
                [
                    'heading' => 'The repayment schedule',
                    'fields' => [
                        ['label' => 'Installment Payment Interval', 'text' => 'How often you repay: monthly, quarterly, semi-annually or annually.'],
                        ['label' => 'First Installment Date', 'text' => 'When repayment begins. Banks often allow a grace period, so this can be well after the start date.'],
                        ['label' => 'Remaining Installment Count', 'text' => 'How many instalments are still to pay.'],
                        ['label' => 'Already Paid Amount', 'text' => 'What has been repaid so far. For a loan taken before you began using the system, this is what you had already paid at that point.'],
                        ['label' => 'Net Balance', 'text' => 'What is still owed.'],
                        ['label' => 'Available Room', 'text' => 'For a New loan: how much has not been drawn yet.'],
                    ],
                    'example' => 'A 12,000,000 loan taken two years ago with 5,000,000 repaid: Limit 12,000,000, Already Paid Amount 5,000,000, Net Balance 7,000,000 — and the type is Existing, because the money is long since spent.',
                ],
                [
                    'heading' => 'Watch out',
                    'notes' => [
                        'A loan is not an overdraft: what you repay cannot be drawn again.',
                        'Without a schedule, the statement has nothing to track repayment against.',
                    ],
                ],
            ],
        ];
    }

    private static function otherDues(): array
    {
        return [
            'title' => 'Other Dues',
            'summary' => 'Money owed either way with a partner that is not an invoice — a deposit you left with a customer, a retention held against you, a balance carried over from before you began using CashVero.',
            'sections' => [
                [
                    'heading' => 'When this screen is the right place',
                    'body' => [
                        'Use it for an amount that has no invoice behind it. If there IS an invoice, it belongs in the opening invoices repeater instead, so it can be settled and aged like any other invoice.',
                        'Everything entered here is dated on the company\'s opening balance date, because it describes the position you started from rather than something that happened on a particular day.',
                    ],
                    'example' => 'You left a 50,000 EGP deposit with a customer years ago as security. It is not an invoice and will never be collected against one, but it is money of yours that they hold. Record it as a Due From that customer.',
                ],
                [
                    'heading' => 'Which way the money goes',
                    'fields' => [
                        ['label' => 'Due From (they owe us)', 'text' => 'The partner holds money of yours. It increases what you are owed — a debit on their statement.'],
                        ['label' => 'Due To (we owe them)', 'text' => 'You hold money of theirs. It increases what you owe — a credit on their statement.'],
                    ],
                ],
                [
                    'heading' => 'The rest of the row',
                    'fields' => [
                        ['label' => 'Partner Type', 'text' => 'Which kind of partner this is. It decides which list the name select offers, and which statement the movement appears in.'],
                        ['label' => 'Name', 'text' => 'The partner. The list holds only partners of the type you chose, sorted by name, and you can type to search it.'],
                        ['label' => 'Amount', 'text' => 'How much is owed. It must be greater than zero.'],
                        ['label' => 'Currency', 'text' => 'The currency the due is in.'],
                        ['label' => 'Exchange Rate', 'text' => 'Required only when the currency is not the company\'s main currency — it is what lets the due be shown alongside everything else.'],
                        ['label' => 'Comment', 'text' => 'Why this due exists. It is written onto the partner\'s statement, so it is what someone reading that statement later will see to explain the row.'],
                    ],
                ],
                [
                    'heading' => 'The same partner can appear more than once',
                    'body' => ['Two dues from the same partner stay two separate rows and are deliberately NOT added together. Each has its own reason and its own comment, and merging them would lose exactly that.'],
                    'example' => 'A 50,000 deposit and a 12,000 retention with the same customer are two rows, each with its own comment — not one row of 62,000.'],
                [
                    'heading' => 'Where the movement shows up',
                    'body' => [
                        'For a shareholder, employee, subsidiary company, other partner or tax authority, the due becomes a real row on their Partner Statement, with its comment, and the running balance after it follows on.',
                        'For a customer or a supplier there is no partner ledger — their statement is built from invoices — so the due is added to that invoice statement instead. It appears with the document type "Other Due" and the same comment.',
                    ],
                    'notes' => [
                        'A due always appears as its own row, whatever date range the statement is filtered to. It carries the opening balance date, so an ordinary range starts after it — folding it into the Beginning Balance instead would hide the comment, which is the only thing explaining what the amount is.',
                    ],
                ],
                [
                    'heading' => 'Saving',
                    'notes' => [
                        'The repeater is saved whole: a row you remove and then save is gone, along with the statement movement it created.',
                        'A partner must genuinely be of the type the row claims. Picking a name that is not of that type is refused, because the due would otherwise land in a statement the partner does not belong to.',
                    ],
                ],
            ],
        ];
    }
}
