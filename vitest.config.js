import vue from '@vitejs/plugin-vue'
import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'

/**
 * Frontend tests (run: npm test). Only tests/js/**\/*.spec.js are run.
 *
 * Specs run in Node by default; a spec that mounts a screen opts into a browser-like DOM with
 * the comment `// @vitest-environment jsdom` on its first line. The server's timezone
 * (Africa/Cairo) is used, so date helpers behave as they do for the people using the app.
 */
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
  test: {
    include: ['tests/js/**/*.spec.js'],
    environment: 'node',
    env: { TZ: 'Africa/Cairo' },
    restoreMocks: true,
  },
})
