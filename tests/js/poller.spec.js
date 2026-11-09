// @vitest-environment jsdom
// Background polling for messages, imports and notifications (run: npm test)
// Location: resources/js/composables/usePoller.js
//
// The clock is fake: each test moves time forward by hand and checks exactly when the server was
// asked again.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPoller } from '@/composables/usePoller.js'

let poller
beforeEach(() => vi.useFakeTimers())
afterEach(() => { poller?.stop(); vi.useRealTimers() })

function makePoller(options) {
  poller = createPoller({ interval: 1000, maxInterval: 4000, idleGrowth: 2, errorBackoff: [3000, 6000], ...options })
  return poller
}

describe('createPoller', () => {
  it('asks again after the interval, and waits longer while nothing changes', async () => {
    const request = vi.fn().mockResolvedValue({})
    makePoller({ request, onResult: () => false }).start()

    await vi.advanceTimersByTimeAsync(999)
    expect(request).toHaveBeenCalledTimes(0)
    await vi.advanceTimersByTimeAsync(1)
    expect(request).toHaveBeenCalledTimes(1) // at 1s

    await vi.advanceTimersByTimeAsync(2000)
    expect(request).toHaveBeenCalledTimes(2) // nothing changed: next one 2s later

    await vi.advanceTimersByTimeAsync(4000)
    expect(request).toHaveBeenCalledTimes(3) // then 4s — the ceiling

    await vi.advanceTimersByTimeAsync(4000)
    expect(request).toHaveBeenCalledTimes(4) // and never more than 4s
  })

  it('goes back to the short interval as soon as something changed', async () => {
    const answers = [false, false, true]
    const request = vi.fn().mockResolvedValue({})
    makePoller({ request, onResult: () => answers.shift() ?? false }).start()

    await vi.advanceTimersByTimeAsync(1000 + 2000 + 4000) // 3 calls, the third reports a change
    expect(request).toHaveBeenCalledTimes(3)
    await vi.advanceTimersByTimeAsync(1000)
    expect(request).toHaveBeenCalledTimes(4)
  })

  it('backs off after errors, says it is failing from the second one, and recovers', async () => {
    let fail = true
    const request = vi.fn(() => (fail ? Promise.reject(new Error('down')) : Promise.resolve({})))
    const onError = vi.fn()
    const p = makePoller({ request, onResult: () => true, onError })
    p.start()

    await vi.advanceTimersByTimeAsync(1000)
    expect(onError).toHaveBeenCalledTimes(1)
    expect(p.failing.value).toBe(false) // one blip is not "failing"

    await vi.advanceTimersByTimeAsync(3000)
    expect(onError).toHaveBeenCalledTimes(2)
    expect(p.failing.value).toBe(true)

    fail = false
    await vi.advanceTimersByTimeAsync(6000)
    expect(p.failing.value).toBe(false)
  })

  it('stopping cancels the request in flight and nothing runs afterwards', async () => {
    let signal
    const request = vi.fn((s) => { signal = s; return new Promise(() => {}) })
    const onResult = vi.fn()
    const p = makePoller({ request, onResult })
    p.start()

    await vi.advanceTimersByTimeAsync(1000)
    expect(request).toHaveBeenCalledTimes(1)
    p.stop()

    expect(signal.aborted).toBe(true)
    await vi.advanceTimersByTimeAsync(60000)
    expect(request).toHaveBeenCalledTimes(1)
    expect(onResult).not.toHaveBeenCalled()
  })

  it('an answer that arrives after a newer one is thrown away', async () => {
    const resolvers = []
    const request = vi.fn(() => new Promise((resolve) => resolvers.push(resolve)))
    const seen = []
    const p = makePoller({ request, onResult: (r) => { seen.push(r); return true } })
    p.start()

    await vi.advanceTimersByTimeAsync(1000) // first request in flight
    p.poke() // the person asks for fresh data: a second request
    await vi.advanceTimersByTimeAsync(0)
    expect(request).toHaveBeenCalledTimes(2)

    resolvers[1]('new')
    await vi.advanceTimersByTimeAsync(0)
    resolvers[0]('old')
    await vi.advanceTimersByTimeAsync(0)

    expect(seen).toEqual(['new'])
  })

  it('does not ask while the tab is hidden, and asks at once when it is shown again', async () => {
    const request = vi.fn().mockResolvedValue({})
    makePoller({ request, onResult: () => true }).start()

    Object.defineProperty(document, 'hidden', { configurable: true, get: () => true })
    document.dispatchEvent(new Event('visibilitychange'))
    await vi.advanceTimersByTimeAsync(10000)
    expect(request).toHaveBeenCalledTimes(0)

    Object.defineProperty(document, 'hidden', { configurable: true, get: () => false })
    document.dispatchEvent(new Event('visibilitychange'))
    await vi.advanceTimersByTimeAsync(0)
    expect(request).toHaveBeenCalledTimes(1)
  })
})
