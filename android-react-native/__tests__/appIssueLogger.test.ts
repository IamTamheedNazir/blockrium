import {
  logAppIssue,
  redactAppIssueText,
  sanitizeAppIssueMetadata,
} from '../source/services/appIssueLogger';

jest.mock('../source/utils/mmkvStorage', () => ({
  getStorageId: jest.fn().mockResolvedValue(42),
  getStorageToken: jest.fn().mockResolvedValue(undefined),
}));

describe('app issue log redaction', () => {
  it('redacts secret fields regardless of key casing or separators', () => {
    const metadata = {
      access_token: 'access-secret',
      refreshToken: 'refresh-secret',
      nested: {
        Authorization: 'bearer-secret',
      },
      endpoint: '/profile?api_key=query-secret',
    };

    const safe = sanitizeAppIssueMetadata(metadata);
    expect(safe.access_token).toBe('[redacted]');
    expect(safe.refreshToken).toBe('[redacted]');
    expect((safe.nested as Record<string, unknown>).Authorization).toBe(
      '[redacted]',
    );
    expect(safe.endpoint).toBe('/profile?api_key=[redacted]');
  });

  it('redacts bearer credentials in error text', () => {
    expect(
      redactAppIssueText('Request failed: Bearer abc.def-123'),
    ).toBe('Request failed: Bearer [redacted]');
  });

  it('preserves bounded React crash stacks while still redacting secrets', () => {
    const stack = `TypeError: failure Bearer abc.def-secret\n${'frame\n'.repeat(2000)}`;
    const componentStack = `\n${' at HomeOrg\n'.repeat(1200)}`;
    const safe = sanitizeAppIssueMetadata({stack, componentStack, details: stack});

    expect(safe.stack).toHaveLength(8000);
    expect(safe.componentStack).toHaveLength(8000);
    expect(safe.details).toHaveLength(1000);
    expect(safe.stack).not.toContain('abc.def-secret');
  });

  it('aborts a stalled issue log request after its timeout', async () => {
    jest.useFakeTimers();
    const logSpy = jest.spyOn(console, 'log').mockImplementation(() => {});
    const fetchMock = jest.spyOn(global, 'fetch').mockImplementation(
      (_input, init) =>
        new Promise((_resolve, reject) => {
          init?.signal?.addEventListener('abort', () => {
            reject(Object.assign(new Error('Aborted'), {name: 'AbortError'}));
          });
        }) as Promise<Response>,
    );

    try {
      const pending = logAppIssue({message: 'diagnostic'});
      await Promise.resolve();
      await Promise.resolve();
      await Promise.resolve();
      expect(fetchMock).toHaveBeenCalledTimes(1);

      jest.advanceTimersByTime(4000);
      await expect(pending).resolves.toBe(false);
      expect(fetchMock.mock.calls[0][1]?.signal?.aborted).toBe(true);
    } finally {
      fetchMock.mockRestore();
      logSpy.mockRestore();
      jest.useRealTimers();
    }
  });
});
