// "Today" and the no-future-dates rule on money forms (run: npm test)
// Location: resources/js/composables/today.js
//
// Same sheet as the server's rule (tests/Fixtures/date_clamp_cases.json). The server runs on
// Africa/Cairo (vitest.config.js sets TZ), which is where "today" must be decided — not UTC.
import { afterEach, describe, expect, it, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import { clampDateToToday, todayDate } from '@/composables/today.js'

const sheet = JSON.parse(readFileSync('tests/Fixtures/date_clamp_cases.json', 'utf8'))

afterEach(() => vi.useRealTimers())

describe('todayDate', () => {
  it('is the date in Cairo', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-11-08T10:00:00+02:00'))
    expect(todayDate()).toBe('2026-11-08')
  })

  it('is already tomorrow in Cairo just after midnight, while UTC is still on yesterday', () => {
    vi.useFakeTimers()
    // 00:30 in Cairo = 22:30 UTC the day before
    vi.setSystemTime(new Date('2026-11-08T22:30:00Z'))
    expect(new Date().toISOString().slice(0, 10)).toBe('2026-11-08')
    expect(todayDate()).toBe('2026-11-09')
  })
})

describe('clampDateToToday', () => {
  it.each(sheet.cases)('$name', ({ date, expect: expected }) => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date(`${sheet.today}T12:00:00+02:00`))
    expect(clampDateToToday(date)).toBe(expected)
  })
})
