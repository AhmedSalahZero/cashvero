/**
 * Shared helpers for the screen tests (run: npm test).
 *
 * The screens talk to the server through Inertia. Here Inertia is replaced by a stand-in that
 * records every call, so a test can act on a screen and then check exactly what it would have
 * sent — without a server, a browser or a database:
 *
 *   vi.mock('@inertiajs/vue3', async () => (await import('./helpers.js')).inertiaMock())
 *   const root = await mount(Page, { urls: { result: '/r' } })
 *   await click(button(root, 'View Report'))
 *   expect(lastCall('/r').data).toEqual({ ... })
 */
import { createApp, h, nextTick } from 'vue'
import { readFileSync } from 'node:fs'

/** Every call a screen made: { method, url, data, options }. */
export const ctx = { calls: [], page: { props: { errors: {} } } }

export function reset(pageProps = {}) {
  ctx.calls.length = 0
  ctx.page = { props: { errors: {}, ...pageProps } }
}

const record = (method) => (url, data = {}, options = {}) => {
  ctx.calls.push({ method, url, data, options })
}

export function inertiaMock() {
  const passThrough = (tag) => ({
    props: ['href', 'method', 'as', 'data'],
    setup(props, { slots, attrs }) {
      return () => h(tag, { ...attrs, href: props.href }, slots.default ? slots.default() : [])
    },
  })

  return {
    router: {
      get: record('get'),
      post: record('post'),
      put: record('put'),
      patch: record('patch'),
      delete: record('delete'),
      visit: record('visit'),
      reload: record('reload'),
      on: () => () => {},
    },
    Link: passThrough('a'),
    Head: { render: () => null },
    usePage: () => ctx.page,
    useForm: (initial) => {
      const form = { ...initial, errors: {}, processing: false }
      for (const method of ['get', 'post', 'put', 'patch', 'delete']) {
        form[method] = (url, options = {}) => ctx.calls.push({ method, url, data: { ...form }, options })
      }
      form.reset = () => Object.assign(form, initial)
      form.clearErrors = () => { form.errors = {} }
      return form
    },
  }
}

/** The last call made to this url (string, or RegExp), or undefined. */
export function lastCall(url) {
  const matches = ctx.calls.filter((c) => (url instanceof RegExp ? url.test(c.url) : c.url === url))
  return matches[matches.length - 1]
}

/** The Arabic dictionary the app ships with (English phrase → Arabic). */
export const ar = JSON.parse(readFileSync('resources/lang/ar.json', 'utf8'))

/** A translation stand-in: `$t('Save')` returns the key, like the app does in English. */
const $t = (key) => key

/**
 * Mount a component into a fresh <div> and wait for it to render. Returns the root element;
 * `root.unmount()` tears it down.
 */
export async function mount(component, props = {}, { slots = {} } = {}) {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const app = createApp({ render: () => h(component, props, slots) })
  app.config.globalProperties.$t = $t
  app.mount(host)
  await tick()
  host.unmount = () => { app.unmount(); host.remove() }
  return host
}

export async function tick(times = 2) {
  for (let i = 0; i < times; i++) {
    await nextTick()
    await Promise.resolve()
  }
}

export async function click(el) {
  if (!el) throw new Error('click(): element not found')
  el.dispatchEvent(new MouseEvent('click', { bubbles: true }))
  await tick()
}

export async function type(el, value) {
  if (!el) throw new Error('type(): element not found')
  el.value = value
  el.dispatchEvent(new Event('input', { bubbles: true }))
  el.dispatchEvent(new Event('change', { bubbles: true }))
  await tick()
}

/** The first button (or link) whose text contains `text`. */
export function button(root, text) {
  return [...root.querySelectorAll('button, a')].find((el) => el.textContent.includes(text))
}
