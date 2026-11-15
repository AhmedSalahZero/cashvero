// @vitest-environment jsdom
// Contract Dashboard — every amount and percentage is a whole number (run: npm test)
// Location: resources/js/Pages/Dashboard/ContractStatus.vue
//
// Same rule as the Cash / LG-LC / Forecast dashboards: no decimals on screen. The server still
// sends full amounts; only the display is rounded.
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', async () => (await import('./helpers.js')).inertiaMock())
vi.mock('@/Layouts/AppLayout.vue', () => ({ default: { render() { return this.$slots.default?.() } } }))

const { click, mount, reset } = await import('./helpers.js')
const ContractStatus = (await import('@/Pages/Dashboard/ContractStatus.vue')).default

const contract = {
  id: 1,
  customer_name: 'Customer A',
  code: 'C-001',
  name: 'Supply contract',
  currency: 'EGP',
  amount: 1234567.89,
  invoiced: 1000.5,
  remaining: -0.4,
  end_date_formatted: '31-12-2026',
  status_label: 'Running',
}

const props = {
  company: { id: 92 },
  counts: { all: 1, running: 1 },
  currencies: ['EGP'],
  byCurrency: {
    EGP: {
      contract_count: 1,
      value: 1234567.89,
      invoiced: 1000.5,
      remaining: -0.4,
      utilization: 66.67,
      billed: 2500.25,
      collected: 1999.49,
      withheld: 12.5,
      deductions: 0.3,
      uncollected: 500.76,
      collection_rate: 99.6,
      reconciliation_gap: -0.4,
    },
  },
  mainCurrency: 'EGP',
  mainCurrencyTotals: { value: 1234567.89, invoiced: 1000.5, collected: 1999.49, uncollected: 500.76, value_unconvertible_count: 0 },
  details: { all: [contract], by_currency: { EGP: [contract] } },
  dashboardTabUrls: { cash: '/92/cash', contracts: '/92/contracts', lglc: '/92/lglc', forecast: '/92/forecast' },
  filterUrl: '/92/contracts',
  exportUrl: '/92/contracts/export',
}

/** The value shown on every KPI card with this label, in page order. */
function kpi(root, label) {
  return [...root.querySelectorAll('.cvr-kpi-card')]
    .filter((card) => card.querySelector('.cvr-kpi-label')?.textContent.trim() === label)
    .map((card) => card.querySelector('.cvr-kpi-value').textContent.trim())
}

beforeEach(() => reset())

describe('the contract dashboard shows whole numbers', () => {
  it('rounds amounts to the nearest whole number', async () => {
    const root = await mount(ContractStatus, props)

    // Company total card, then the EGP card.
    expect(kpi(root, 'Contract Value')).toEqual(['1,234,568', '1,234,568'])
    expect(kpi(root, 'Invoiced')).toEqual(['1,001', '1,001'])
    expect(kpi(root, 'Collected')[0]).toBe('1,999')
    root.unmount()
  })

  it('rounds percentages to the nearest whole number', async () => {
    const root = await mount(ContractStatus, props)

    expect(kpi(root, 'Utilization')).toEqual(['67%'])
    expect(kpi(root, 'Collection Rate')).toEqual(['100%'])
    root.unmount()
  })

  it('shows a tiny negative as 0, not -0', async () => {
    const root = await mount(ContractStatus, props)

    expect(kpi(root, 'Remaining to Invoice')).toEqual(['0'])
    root.unmount()
  })

  it('has no decimal point in any KPI card', async () => {
    const root = await mount(ContractStatus, props)
    const values = [...root.querySelectorAll('.cvr-kpi-value')].map((el) => el.textContent.trim())

    expect(values.length).toBeGreaterThan(0)
    expect(values.filter((text) => /\d\.\d/.test(text))).toEqual([])
    root.unmount()
  })

  it('rounds the amounts in the contract table that opens under a card', async () => {
    const root = await mount(ContractStatus, props)
    const valueCard = [...root.querySelectorAll('.cvr-kpi-card.cursor-pointer')]
      .find((card) => card.querySelector('.cvr-kpi-label')?.textContent.trim() === 'Contract Value')

    await click(valueCard)

    const row = [...root.querySelectorAll('tbody tr')].find((tr) => tr.textContent.includes('C-001'))
    const amounts = [...row.querySelectorAll('td.cvr-num')].map((td) => td.textContent.trim())
    expect(amounts).toEqual(['1,234,568', '1,001', '0'])
    root.unmount()
  })
})
