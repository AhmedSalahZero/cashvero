// @vitest-environment jsdom
// LG/LC dashboard — currency pills (run: npm test)
// Location: resources/js/Pages/Dashboard/LGLCStatus.vue
//
// The server sends a pill only for a currency with an LG or LC facility still running on the
// chosen date (selectedCurrencies), and works out the figures for one currency per visit. The
// page must show exactly those pills and stay on the currency the server worked out.
import { reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', async () => (await import('./helpers.js')).inertiaMock())
vi.mock('@/Layouts/AppLayout.vue', () => ({ default: { render() { return this.$slots.default?.() } } }))
vi.mock('@/Components/Charts/DonutChart3D.vue', () => ({ default: { render: () => null } }))

const { click, mount, reset, tick } = await import('./helpers.js')
const LGLCStatus = (await import('@/Pages/Dashboard/LGLCStatus.vue')).default

/** Props as the server sends them when it worked out `currency`. */
function propsFor(currency, { selectedCurrencies, limit }) {
  return {
    company: { id: 92 },
    mainFunctionalCurrency: 'EGP',
    selectedCurrencies,
    allCurrencies: ['EGP', 'USD'],
    reports: currency
      ? { lg: { [currency]: { limit } }, lc: { [currency]: { limit: 0 } } }
      : [],
    canShowDashboardPerCurrency: currency ? { lg: { [currency]: true }, lc: { [currency]: false } } : [],
    charts: {},
    tablesData: {},
    details: {},
    financialInstitutionBanks: [],
    financialInstitutions: [],
    lgTypes: {},
    lcTypes: {},
    date: '2026-11-15',
    filterUrl: '/92/cashvero-dashboard/lglc',
    dashboardTabUrls: { cash: '/c', contracts: '/k', lglc: '/l', forecast: '/f' },
  }
}

const pills = (root) => [...root.querySelectorAll('.cvr-filter-pill')].map((el) => el.textContent.trim())
const activePill = (root) => root.querySelector('.cvr-filter-pill-active')?.textContent.trim()
const headings = (root) => [...root.querySelectorAll('.cvr-section-heading h2')].map((el) => el.textContent.trim())

beforeEach(() => reset())

describe('the currency pills', () => {
  it('shows only the currencies the server sent, not every currency ever used', async () => {
    const root = await mount(LGLCStatus, propsFor('EGP', { selectedCurrencies: ['EGP'], limit: 1000000 }))

    expect(pills(root)).toEqual(['EGP'])
    expect(activePill(root)).toBe('EGP')
    expect(headings(root)).toEqual(['Letters of Guarantee [EGP]'])
    root.unmount()
  })

  it('says so when no currency has a running facility, instead of showing an empty page', async () => {
    const root = await mount(LGLCStatus, propsFor(null, { selectedCurrencies: [] }))

    expect(pills(root)).toEqual([])
    expect(root.textContent).toContain('No active LG or LC facilities on this date.')
    root.unmount()
  })

  it('does not show the message while there are pills', async () => {
    const root = await mount(LGLCStatus, propsFor('EGP', { selectedCurrencies: ['EGP'], limit: 1 }))

    expect(root.textContent).not.toContain('No active LG or LC facilities on this date.')
    root.unmount()
  })

  it('opens on the currency the server worked out, even when it is not the main one', async () => {
    // e.g. a link with ?currencies[0]=USD on a date both currencies are running
    const root = await mount(LGLCStatus, propsFor('USD', { selectedCurrencies: ['EGP', 'USD'], limit: 54750 }))

    expect(activePill(root)).toBe('USD')
    expect(headings(root)).toEqual(['Letters of Guarantee [USD]'])
    expect(root.querySelector('.cvr-kpi-value').textContent.trim()).toBe('54,750')
    root.unmount()
  })

  it('moves to the currency the server sent when the date change removes the one being viewed', async () => {
    // Viewing USD on 2026-06-30 ...
    const props = reactive(propsFor('USD', { selectedCurrencies: ['EGP', 'USD'], limit: 54750 }))
    const root = await mount(LGLCStatus, props)
    expect(activePill(root)).toBe('USD')

    // ... then Apply with 2026-11-15: USD's facility has ended, the server sends EGP.
    Object.assign(props, propsFor('EGP', { selectedCurrencies: ['EGP'], limit: 1000000 }))
    await tick()

    expect(pills(root)).toEqual(['EGP'])
    expect(activePill(root)).toBe('EGP')
    expect(headings(root)).toEqual(['Letters of Guarantee [EGP]'])
    root.unmount()
  })

  it('still asks the server for a pill whose figures are not loaded yet', async () => {
    const { lastCall } = await import('./helpers.js')
    const root = await mount(LGLCStatus, propsFor('EGP', { selectedCurrencies: ['EGP', 'USD'], limit: 1 }))

    await click([...root.querySelectorAll('.cvr-filter-pill')].find((el) => el.textContent.trim() === 'USD'))

    expect(lastCall('/92/cashvero-dashboard/lglc').data).toEqual({ date: '2026-11-15', currencies: ['USD'] })
    root.unmount()
  })
})
