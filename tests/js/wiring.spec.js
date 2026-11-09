// The server and the screens fit together (run: npm test)
//
// Every page the server renders exists as a .vue file, and every "@/..." import in the
// frontend points at a file that is really there. Both are otherwise only found when someone
// opens the screen.
import { describe, expect, it } from 'vitest'
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'

function files(dir, ext) {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    if (statSync(path).isDirectory()) return name === 'dompdf' ? [] : files(path, ext)
    return path.endsWith(ext) ? [path] : []
  })
}

describe('pages', () => {
  it('every Inertia::render() page exists as a .vue file', () => {
    const missing = new Set()
    for (const file of files('app', '.php')) {
      for (const [, page] of readFileSync(file, 'utf8').matchAll(/Inertia::render\(\s*'([^']+)'/g)) {
        if (!existsSync(`resources/js/Pages/${page}.vue`)) missing.add(`${page} (${file})`)
      }
    }
    expect([...missing]).toEqual([])
  })
})

describe('imports', () => {
  it('every "@/..." import resolves to a real file', () => {
    const broken = []
    for (const file of [...files('resources/js', '.vue'), ...files('resources/js', '.js')]) {
      for (const [, target] of readFileSync(file, 'utf8').matchAll(/from\s+['"]@\/([^'"]+)['"]/g)) {
        const base = `resources/js/${target}`
        const candidates = [base, `${base}.js`, `${base}.vue`, `${base}/index.js`]
        if (!candidates.some((c) => existsSync(c) && statSync(c).isFile())) broken.push(`${file} → @/${target}`)
      }
    }
    expect(broken).toEqual([])
  })
})
