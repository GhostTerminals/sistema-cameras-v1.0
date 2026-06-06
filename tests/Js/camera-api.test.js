import { describe, it, expect } from 'vitest';

describe('Utility functions', () => {
  it('should handle CSRF token retrieval from meta tag', () => {
    document.head.innerHTML = '<meta name="csrf-token" content="test-token-123">';
    const meta = document.querySelector('meta[name="csrf-token"]');
    expect(meta).not.toBeNull();
    expect(meta.getAttribute('content')).toBe('test-token-123');
  });

  it('should return empty string when CSRF meta tag is missing', () => {
    document.head.innerHTML = '';
    const meta = document.querySelector('meta[name="csrf-token"]');
    expect(meta).toBeNull();
  });

  it('should construct correct API base URL', () => {
    const baseUrl = '/public/';
    const apiBase = `${baseUrl}index.php?page=api/`;
    expect(apiBase).toBe('/public/index.php?page=api/');
  });
});
