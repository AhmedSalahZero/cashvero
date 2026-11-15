// @vitest-environment jsdom
// LG Issuance list — the Actions buttons sit 3 per row (run: npm test)
// Location: resources/js/Pages/LetterOfGuaranteeIssuance/Index.vue
//
// jsdom does not lay the page out, so this checks the grid the buttons are placed in: three
// columns, one button per cell. The phone rule in resources/css/app.css turns `grid-cols-3`
// into a single column below 720px, so the cell must not use that class.
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', async () => (await import('./helpers.js')).inertiaMock())
vi.mock('@/Layouts/AppLayout.vue', () => ({ default: { props: ['navUrls'], render() { return this.$slots.default?.() } } }))

const { mount, reset } = await import('./helpers.js')
const Index = (await import('@/Pages/LetterOfGuaranteeIssuance/Index.vue')).default

const ADVANCE_PAYMENT = 'advanced-payment-lgs'

/** A running, activated advance-payment LG, not reviewed yet: every action applies to it. */
const row = {
  id: 1123,
  serial: 'LG-1',
  transaction_name: 'Supply contract',
  beneficiary_name: 'Customer A',
  source_formatted: 'LG Facility',
  status_formatted: 'Running',
  bank_name: 'Bank A',
  lg_code: '000059LLG446335',
  lg_amount_formatted: '54,750',
  lg_current_amount_formatted: '54,750',
  lg_currency: 'USD',
  issuance_date_formatted: '01-07-2025',
  renewal_date_formatted: '23-07-2026',
  activation_date_formatted: '01-07-2025',
  is_running: true,
  is_expired: false,
  is_cancelled: false,
  is_activated: true,
  is_advanced_payment: true,
  has_comment: true,
  fully_integrated_with_odoo: true,
  has_odoo_error: true,
  review: { is_reviewed: false },
  renewal_date_url: '/92/lg/1123/renewal',
  edit_url: '/92/lg/1123/edit',
}

const props = {
  company: { id: 92 },
  canReview: true,
  activeLgType: ADVANCE_PAYMENT,
  filterDates: {},
  lgTypes: { [ADVANCE_PAYMENT]: 'Advance Payment LGs' },
  createUrls: {},
  permissions: { canCreate: false, canUpdate: true, canDelete: true, canCancel: true, canRenew: true },
  tabDataUrl: '/92/letter-of-guarantee-issuance-tab-data',
  tabs: { [ADVANCE_PAYMENT]: { loaded: true, rows: [row], current_page: 1, last_page: 1, total: 1 } },
  navUrls: {},
}

/** The element holding the Actions buttons of the first row. */
function actionsCell(root) {
  return root.querySelector('tbody tr td:last-child > div')
}

beforeEach(() => reset())

describe('the Actions column', () => {
  it('lays the buttons out in a 3-column grid that phones do not stack', async () => {
    const root = await mount(Index, props)
    const classes = actionsCell(root).classList

    expect(classes.contains('grid')).toBe(true)
    expect(classes.contains('grid-cols-[repeat(3,max-content)]')).toBe(true)
    expect(classes.contains('grid-cols-3')).toBe(false)
    expect(classes.contains('flex-wrap')).toBe(false)
    root.unmount()
  })

  it('gives every button a cell of its own', async () => {
    const root = await mount(Index, props)
    const cells = [...actionsCell(root).children]

    // History, Review, Comment, Odoo, Odoo Error, Renewal, Cancel, Amount To Be Decreased, Edit, Delete
    expect(cells).toHaveLength(10)
    // Each cell is one button or link (Review wraps its button in a <span>).
    expect(cells.filter((cell) => !cell.matches('button, a, span'))).toEqual([])
    expect(cells.at(-2).textContent.trim()).toBe('Edit')
    expect(cells.at(-1).textContent.trim()).toBe('Delete')
    root.unmount()
  })
})
