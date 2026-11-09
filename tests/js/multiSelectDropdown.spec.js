// @vitest-environment jsdom
// The checkbox dropdown used for banks, currencies and facility types (run: npm test)
// Location: resources/js/Components/MultiSelectDropdown.vue
import { describe, expect, it } from 'vitest'
import { h, ref } from 'vue'
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue'
import { button, click, mount, tick } from './helpers.js'

const options = [
  { value: 1, label: 'Bank A' },
  { value: 2, label: 'Bank B' },
  { value: 3, label: 'Bank C' },
]

/** Mount it bound to a real v-model, the way the forms use it. */
async function dropdown(initial = []) {
  const selected = ref(initial)
  const root = await mount({
    render: () => h(MultiSelectDropdown, {
      modelValue: selected.value,
      'onUpdate:modelValue': (value) => { selected.value = value },
      options,
      placeholder: 'All Banks',
    }),
  })
  return { root, selected, summary: () => root.querySelector('button span').textContent.trim() }
}

const checkbox = (root, label) => [...root.querySelectorAll('label')].find((l) => l.textContent.includes(label)).querySelector('input')

describe('MultiSelectDropdown', () => {
  it('is closed until clicked and shows the placeholder while nothing is picked', async () => {
    const { root, summary } = await dropdown()
    expect(root.querySelectorAll('input[type=checkbox]')).toHaveLength(0)
    expect(summary()).toBe('All Banks')

    await click(root.querySelector('button'))
    expect(root.querySelectorAll('input[type=checkbox]')).toHaveLength(3)
  })

  it('ticking boxes picks those options, and the summary names one or counts several', async () => {
    const { root, selected, summary } = await dropdown()
    await click(root.querySelector('button'))

    checkbox(root, 'Bank B').dispatchEvent(new Event('change'))
    await tick()
    expect(selected.value).toEqual([2])
    expect(summary()).toBe('Bank B')

    checkbox(root, 'Bank A').dispatchEvent(new Event('change'))
    await tick()
    expect(selected.value).toEqual([2, 1])
    expect(summary()).toBe('2 selected')
  })

  it('unticking removes only that option', async () => {
    const { root, selected } = await dropdown([1, 2])
    await click(root.querySelector('button'))

    checkbox(root, 'Bank A').dispatchEvent(new Event('change'))
    await tick()
    expect(selected.value).toEqual([2])
  })

  it('Select All picks every option and Deselect All clears them', async () => {
    const { root, selected, summary } = await dropdown()
    await click(root.querySelector('button'))

    await click(button(root, 'Select All'))
    expect(selected.value).toEqual([1, 2, 3])
    expect(summary()).toBe('All selected')

    await click(button(root, 'Deselect All'))
    expect(selected.value).toEqual([])
    expect(summary()).toBe('All Banks')
  })

  it('stays open while ticking, and closes on a click elsewhere on the page', async () => {
    const { root } = await dropdown()
    await click(root.querySelector('button'))
    checkbox(root, 'Bank C').dispatchEvent(new Event('change'))
    await tick()
    expect(root.querySelectorAll('input[type=checkbox]')).toHaveLength(3)

    document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await tick()
    expect(root.querySelectorAll('input[type=checkbox]')).toHaveLength(0)
  })
})
