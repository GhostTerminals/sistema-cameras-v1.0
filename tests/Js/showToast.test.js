import { beforeEach, describe, expect, it, vi } from 'vitest'

describe('showToast', () => {
  beforeEach(() => {
    vi.resetModules()
    globalThis.window = {}
    globalThis.document = {
      addEventListener: vi.fn(),
      querySelector: vi.fn(),
      createElement: vi.fn(() => ({
        className: '',
        style: {},
        appendChild: vi.fn(),
        insertAdjacentHTML: vi.fn(),
        getElementById: vi.fn(),
      })),
      body: {
        appendChild: vi.fn(),
      },
    }
  })

  it('should be defined as a function in ui-utils', async () => {
    await import('../../public/assets/js/utils/ui-utils.js')

    expect(typeof window.showToast).toBe('function')
  })
})
