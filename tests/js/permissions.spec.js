// What a screen lets the person do (run: npm test)
// Location: resources/js/composables/usePermissions.js
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', async () => (await import('./helpers.js')).inertiaMock())

const { reset } = await import('./helpers.js')
const { checkPermission, usePermissions } = await import('@/composables/usePermissions.js')

const asUser = (permissions, isSuperAdmin = false) => reset({ auth: { permissions, isSuperAdmin, role: isSuperAdmin ? 'super-admin' : 'user' } })

describe('usePermissions', () => {
  beforeEach(() => reset())

  it('allows exactly the keys the person holds', () => {
    asUser(['cash_expense.view'])
    const { can, cannot } = usePermissions()
    expect(can('cash_expense.view')).toBe(true)
    expect(can('cash_expense.delete')).toBe(false)
    expect(cannot('cash_expense.delete')).toBe(true)
  })

  it('a list means "any of these"', () => {
    asUser(['cheques_report_payable.view'])
    const { can, canAny, canAll } = usePermissions()
    expect(can(['cheques_report_received.view', 'cheques_report_payable.view'])).toBe(true)
    expect(canAny(['cheques_report_received.view', 'cheques_report_payable.view'])).toBe(true)
    expect(canAll(['cheques_report_received.view', 'cheques_report_payable.view'])).toBe(false)
  })

  it('an empty or missing key is never allowed — not even to a super admin', () => {
    asUser([], true)
    const { can, canAny, canAll } = usePermissions()
    expect(can('')).toBe(false)
    expect(can(null)).toBe(false)
    expect(canAny([])).toBe(false)
    expect(canAll([])).toBe(false)
  })

  it('a super admin may do anything that is named', () => {
    asUser([], true)
    const { can, canAll } = usePermissions()
    expect(can('anything.at_all')).toBe(true)
    expect(canAll(['a.view', 'b.delete'])).toBe(true)
  })

  it('someone with no permissions at all may do nothing', () => {
    reset({})
    const { can, canAny } = usePermissions()
    expect(can('cash_expense.view')).toBe(false)
    expect(canAny(['cash_expense.view'])).toBe(false)
  })
})

describe('checkPermission (outside a component)', () => {
  it('reads the same rules from plain page props', () => {
    const props = { auth: { permissions: ['report_bank_statement.view'] } }
    expect(checkPermission(props, 'report_bank_statement.view')).toBe(true)
    expect(checkPermission(props, 'report_bank_statement.export')).toBe(false)
    expect(checkPermission(props, ['x.view', 'report_bank_statement.view'])).toBe(true)
    expect(checkPermission({ auth: { isSuperAdmin: true } }, 'x.view')).toBe(true)
    expect(checkPermission({}, 'x.view')).toBe(false)
    expect(checkPermission(props, '')).toBe(false)
  })
})
