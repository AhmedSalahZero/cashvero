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

    public static function keys(): array
    {
        return [
            self::MONEY_RECEIVED_INDEX, self::MONEY_RECEIVED_FORM, self::MONEY_RECEIVED_DOWN_PAYMENT,
            self::MONEY_PAYMENT, self::CASH_EXPENSE, self::INTERNAL_TRANSFER, self::CURRENCY_EXCHANGE,
            self::LG_ISSUANCE, self::LC_ISSUANCE, self::LC_SETTLEMENT, self::FACTORING, self::SETTINGS,
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
}
