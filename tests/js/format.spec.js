// Money formatting on screen (run: npm test)
// Location: resources/js/i18n.js formatNumber
//
// The same answer sheet (tests/Fixtures/number_format_cases.json) is checked on the server by
// tests/Unit/NumberFormatSheetTest.php, so the screen and the printouts write money the same way.
import { describe, expect, it } from 'vitest'
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { formatNumber } from '@/i18n.js'

const sheet = JSON.parse(readFileSync('tests/Fixtures/number_format_cases.json', 'utf8'))

describe('formatNumber', () => {
  it.each(sheet.cases)('$name: $value → $expect', ({ value, expect: expected }) => {
    expect(formatNumber(value)).toBe(expected)
  })

  it('never writes Arabic-Indic digits, whatever the language', () => {
    for (const { value } of sheet.cases) {
      expect(/[٠-٩]/.test(formatNumber(value))).toBe(false)
    }
  })

  it('can show a different number of decimals when asked', () => {
    expect(formatNumber(1234.5678, { fractionDigits: 0 })).toBe('1,235')
    expect(formatNumber(1234.5678, { fractionDigits: 4 })).toBe('1,234.5678')
  })
})

/**
 * Screens that format money themselves instead of using formatNumber. Each one is a place where
 * "-0.00" or a different rounding can creep back in. The number may only go down.
 */
const FILES_FORMATTING_MONEY_THEMSELVES = 59

function vueFiles(dir) {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    return statSync(path).isDirectory() ? vueFiles(path) : path.endsWith('.vue') ? [path] : []
  })
}

describe('money formatting stays in one place', () => {
  it('no new screen formats money on its own', () => {
    const offenders = vueFiles('resources/js').filter((file) => /toLocaleString\(\s*['"]en-EG['"]/.test(readFileSync(file, 'utf8')))
    expect(offenders.length, `Use formatNumber from @/i18n.js instead. Files: \n${offenders.join('\n')}`).toBeLessThanOrEqual(FILES_FORMATTING_MONEY_THEMSELVES)
  })
})
