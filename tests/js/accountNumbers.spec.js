// The account-number picker on money forms (run: npm test)
// Location: resources/js/composables/useAccountNumberOptions.js
import { describe, expect, it } from 'vitest'
import { accountNumberOption, hasAccountNumber, mapAccountNumberOptions } from '@/composables/useAccountNumberOptions.js'

describe('mapAccountNumberOptions', () => {
  it('turns the server map into options, keeping the order', () => {
    expect(mapAccountNumberOptions({ 1002: '1002 — Shareholder: Ali', 1001: '1001' })).toEqual([
      { value: '1001', label: '1001' },
      { value: '1002', label: '1002 — Shareholder: Ali' },
    ])
  })

  it('a missing label falls back to the number itself', () => {
    expect(mapAccountNumberOptions({ 'ACC-9': null })).toEqual([{ value: 'ACC-9', label: 'ACC-9' }])
  })

  it('nothing in, nothing out', () => {
    expect(mapAccountNumberOptions(null)).toEqual([])
    expect(mapAccountNumberOptions(undefined)).toEqual([])
    expect(mapAccountNumberOptions({})).toEqual([])
  })
})

describe('accountNumberOption / hasAccountNumber', () => {
  it('the account already on a record is offered on its own', () => {
    expect(accountNumberOption(12345)).toEqual([{ value: '12345', label: '12345' }])
    expect(accountNumberOption('')).toEqual([])
    expect(accountNumberOption(null)).toEqual([])
  })

  it('a number matches whether it arrives as text or as a number', () => {
    const options = mapAccountNumberOptions({ 12345: '12345' })
    expect(hasAccountNumber(options, 12345)).toBe(true)
    expect(hasAccountNumber(options, '12345')).toBe(true)
    expect(hasAccountNumber(options, '999')).toBe(false)
    expect(hasAccountNumber(options, null)).toBe(false)
  })
})
