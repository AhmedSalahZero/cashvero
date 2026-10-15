/**
 * Account-number dropdown options.
 *
 * The account-number endpoints return a map of
 *   { <stored account number> : <label to display> }
 *
 * Those two used to be identical, so every caller could get away with
 * Object.values() and use one string as both the option's value and its
 * text. They are no longer identical: a shareholder-owned account is
 * labelled "12345 — Ahmed" while the value stored on the record is still
 * the plain "12345" (decision D7, docs/shareholder-accounts.md).
 *
 * Using the label as the value would write the owner's name into
 * account_number columns, so every dropdown goes through here instead.
 *
 * @param {Object<string,string>|null|undefined} map
 * @returns {{value: string, label: string}[]}
 */
export function mapAccountNumberOptions(map) {
    return Object.entries(map || {}).map(([value, label]) => ({
        value: String(value),
        label: String(label ?? value),
    }));
}

/**
 * Seed a dropdown with the value a record already holds, before the
 * lookup returns. The label is the bare number — the decorated one
 * arrives with the response.
 *
 * @param {string|number|null|undefined} accountNumber
 * @returns {{value: string, label: string}[]}
 */
export function accountNumberOption(accountNumber) {
    return accountNumber ? [{ value: String(accountNumber), label: String(accountNumber) }] : [];
}

/**
 * Does this option list contain a given stored account number?
 *
 * @param {{value: string}[]} options
 * @param {string|number|null|undefined} accountNumber
 */
export function hasAccountNumber(options, accountNumber) {
    return options.some(option => option.value === String(accountNumber ?? ''));
}
