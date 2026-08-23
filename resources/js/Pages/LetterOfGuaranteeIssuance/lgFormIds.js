/**
 * Normalize LG issuance form FK ids for Vue <select> v-model.
 * Model getters used to return 0 for empty FKs, which does not match
 * option value="" ("None") on edit.
 */
export function normalizeFkId(value) {
    const n = Number(value);
    return Number.isFinite(n) && n > 0 ? n : '';
}

/**
 * Contracts for the selected beneficiary, always including the saved
 * contract on edit even if the partner filter would exclude it.
 */
export function buildContractsForCustomer(contracts, partnerId, selectedContractId) {
    const partner = Number(partnerId);
    const selectedId = Number(selectedContractId);
    let list = contracts.filter((c) => Number(c.partner_id) === partner);
    if (selectedId && !list.some((c) => Number(c.id) === selectedId)) {
        const saved = contracts.find((c) => Number(c.id) === selectedId);
        if (saved) {
            list = [...list, saved];
        }
    }
    return list.sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base', numeric: true }));
}
