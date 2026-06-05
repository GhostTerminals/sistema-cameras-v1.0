import { beforeEach, describe, expect, it, vi } from 'vitest'

describe('showToast', () => {
  beforeEach(() => {
    vi.resetModules()
    globalThis.window = {}
    globalThis.document = {
      addEventListener: vi.fn(),
      querySelector: vi.fn(),
    }
  })

  it('should be defined as a function', async () => {
    await import('../../public/assets/js/main.js')

    expect(typeof window.showToast).toBe('function')
  })

  it('should call showToast successfully', async () => {
    window.showToast = vi.fn()
    await import('../../public/assets/js/main.js')

    window.showToast('test message', 'success')

    expect(window.showToast).toHaveBeenCalledWith('test message', 'success')
  })
})
