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

    public static function keys(): array
    {
        return [self::MONEY_RECEIVED_INDEX, self::MONEY_RECEIVED_FORM, self::MONEY_RECEIVED_DOWN_PAYMENT];
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
            default => null,
        };
    }

    private static function moneyReceivedIndex(): array
    {
        return [
            'title' => 'Money Received — the list screen',
            'summary' => 'Every amount your customers have paid you: cash into a safe, cash into a bank, an incoming transfer, or a cheque. Each row is one payment you received, and shows which invoices it settled.',
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
            'summary' => 'Use this form to record money a customer paid you, and to say which of their invoices it settles. The form changes depending on how the money arrived, so only the fields relevant to that method are shown.',
            'sections' => [
                [
                    'heading' => 'Fill it in this order',
                    'body' => ['Each step below decides what the next one offers you, so working top to bottom saves re-doing it.'],
                    'fields' => [
                        ['label' => '1. Receiving Date', 'text' => 'The day the money actually reached you. It cannot be before the opening balance date of the account or safe receiving it, and it cannot be in the future.'],
                        ['label' => '2. Partner Type', 'text' => 'Usually Customer. Choosing Customer is what makes the invoice table at the bottom appear — other partner types have no invoices to settle.'],
                        ['label' => '3. Name', 'text' => 'Which customer paid. Changing it reloads their open invoices, and clears anything you had already allocated.'],
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
                    'body' => ['The bottom table lists that customer\'s unpaid invoices. Type into Settlement Amount on each invoice this payment is settling.'],
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
                        ['label' => '"At Least One Settlement Is Required"', 'text' => 'For a customer, the payment must be put against at least one invoice. If it is genuinely an advance, use the Down Payment form instead.'],
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
