// @vitest-environment jsdom
// Bank Interest, Commissions & Charges Analysis — the filter form and the result page (run: npm test)
// Location: resources/js/Pages/Statements/BankChargesAnalysis/{Index,Result}.vue
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', async () => (await import('./helpers.js')).inertiaMock())
vi.mock('@/Layouts/AppLayout.vue', () => ({ default: { render() { return this.$slots.default?.() } } }))

const { button, click, lastCall, mount, reset, tick, type } = await import('./helpers.js')
const Index = (await import('@/Pages/Statements/BankChargesAnalysis/Index.vue')).default
const Result = (await import('@/Pages/Statements/BankChargesAnalysis/Result.vue')).default

const indexProps = {
  company: { id: 7 },
  banks: [{ value: 1, label: 'Bank A' }, { value: 2, label: 'Bank B' }],
  facilityTypes: [{ value: 'clean-overdraft', label: 'Clean Overdraft' }],
  currencies: [{ value: 'EGP', label: 'EGP' }, { value: 'USD', label: 'USD' }],
  mainCurrency: 'EGP',
  urls: { result: '/7/bank-charges-analysis-report/result' },
}

beforeEach(() => {
  reset()
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date('2026-11-08T10:00:00+02:00'))
})
afterEach(() => vi.useRealTimers())

describe('the filter form', () => {
  it('opens on this year so far and sends exactly the chosen filters', async () => {
    const root = await mount(Index, indexProps)
    const [start, end] = root.querySelectorAll('input[type=date]')
    expect(start.value).toBe('2026-01-01')
    expect(end.value).toBe('2026-11-08')

    await click(button(root, 'View Report'))

    expect(lastCall(indexProps.urls.result).data).toEqual({
      start_date: '2026-01-01',
      end_date: '2026-11-08',
      financial_institution_ids: [],
      facility_types: [],
      currencies: [],
    })
  })

  it('never sends a date after today, even one typed by hand', async () => {
    const root = await mount(Index, indexProps)
    await type(root.querySelectorAll('input[type=date]')[1], '2027-03-01')
    await click(button(root, 'View Report'))

    expect(lastCall(indexProps.urls.result).data.end_date).toBe('2026-11-08')
  })

  it('refuses to send a range that ends before it starts, and says why', async () => {
    const root = await mount(Index, indexProps)
    await type(root.querySelectorAll('input[type=date]')[0], '2026-06-01')
    await type(root.querySelectorAll('input[type=date]')[1], '2026-05-01')
    await click(button(root, 'View Report'))

    expect(lastCall(indexProps.urls.result)).toBeUndefined()
    expect(root.textContent).toContain('Start Date is after End Date')
  })

  it('tells the person which currency the amounts will be in', async () => {
    const root = await mount(Index, indexProps)
    expect(root.textContent).toContain('More than one currency: every amount is shown in the main currency')
    expect(root.textContent).toContain('(EGP)')
  })

  it('shows the errors the server sent back', async () => {
    reset({ errors: { 'financial_institution_ids.0': 'The selected bank is invalid.' } })
    const root = await mount(Index, indexProps)
    expect(root.textContent).toContain('The selected bank is invalid.')
  })
})

function resultProps({ converted = true } = {}) {
  const facility = (label, interest, commission, charges) => ({
    key: label, facility_type: 'clean-overdraft', facility_type_label: 'Clean Overdraft', facility_label: label,
    currencies: converted ? ['EGP', 'USD'] : ['USD'], line_count: 1, interest, commission, charges, total: interest + commission + charges,
  })
  const bankA = { bank_id: 1, bank_name: 'Bank A', interest: 300, commission: 100, charges: 0, total: 400, facilities: [facility('OD-1', 300, 100, 0)] }
  const bankB = { bank_id: 2, bank_name: 'Bank B', interest: 0, commission: 0, charges: 100, total: 100, facilities: [facility('ACC-9', 0, 0, 100)] }

  return {
    company: { id: 7 },
    startDate: '2026-01-01',
    endDate: '2026-11-08',
    filterSummary: { banks: 'All Banks', facilityTypes: 'All Facility Types', currencies: converted ? 'All Currencies' : 'USD' },
    displayCurrency: converted ? 'EGP' : 'USD',
    isConverted: converted,
    mainCurrency: 'EGP',
    missingRates: converted ? ['GBP'] : [],
    summary: { banks: [bankA, bankB], grand: { interest: 300, commission: 100, charges: 100, total: 500 } },
    kpis: { interest: 300, commission: 100, charges: 100, total: 500, interestShare: 60, commissionShare: 20, chargesShare: 20, lineCount: 2, bankCount: 2, facilityCount: 2 },
    paginator: {
      current_page: 1, last_page: 1, links: [],
      data: [{ date: '2026-02-28', bank_name: 'Bank A', facility_label: 'OD-1', facility_type_label: 'Clean Overdraft', category: 'interest', category_label: 'Interest', item: 'End Of Month Interest', currency: 'USD', amount: 6, exchange_rate: 50, amount_main: 300, comment: '' }],
    },
    perPage: 50,
    urls: { backUrl: '/back', exportUrl: '/export?x=1', printUrl: '/print?x=1' },
  }
}

describe('the result page', () => {
  it('shows each bank with its facilities under it, and the grand total', async () => {
    const root = await mount(Result, resultProps())
    const text = root.textContent
    expect(text).toContain('Bank A')
    expect(text).toContain('OD-1')
    expect(text).toContain('Bank B')
    expect(text).toContain('500.00')
    expect(text).toContain('80.0%') // Bank A's share: 400 of 500
  })

  it('folds a bank away on a click, and opens it again', async () => {
    const root = await mount(Result, resultProps())
    const bankRow = [...root.querySelectorAll('tr')].find((tr) => tr.textContent.includes('Bank A') && tr.textContent.includes('▾'))

    await click(bankRow)
    expect([...root.querySelectorAll('tbody tr')].some((tr) => tr.textContent.includes('OD-1') && !tr.textContent.includes('Interest'))).toBe(false)

    await click(bankRow)
    expect(root.textContent).toContain('OD-1')
  })

  it('with several currencies: shows the rate and the converted amount, and warns about a missing rate', async () => {
    const root = await mount(Result, resultProps({ converted: true }))
    const headings = [...root.querySelectorAll('th')].map((th) => th.textContent.trim())
    expect(headings).toContain('Exchange Rate')
    expect(headings).toContain('Amount In Main Currency')
    expect(root.textContent).toContain('No exchange rate found for')
    expect(root.textContent).toContain('GBP')
  })

  it('with one currency: no conversion columns and no rate warning', async () => {
    const root = await mount(Result, resultProps({ converted: false }))
    const headings = [...root.querySelectorAll('th')].map((th) => th.textContent.trim())
    expect(headings).not.toContain('Exchange Rate')
    expect(headings).not.toContain('Amount In Main Currency')
    expect(root.textContent).not.toContain('No exchange rate found for')
  })

  it('offers the export and the print of the same filters', async () => {
    const root = await mount(Result, resultProps())
    expect(root.querySelector('a[href="/export?x=1"]')).not.toBeNull()
    const print = root.querySelector('a[href="/print?x=1"]')
    expect(print).not.toBeNull()
    expect(print.getAttribute('target')).toBe('_blank')
  })
})
