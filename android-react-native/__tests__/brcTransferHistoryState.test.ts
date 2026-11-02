import {getBrcTransferHistoryState} from '../source/utils/brcTransferHistoryState';

describe('BRC transfer history state', () => {
  it('shows loading while the first history request is pending', () => {
    expect(getBrcTransferHistoryState(true, false, 0)).toBe('loading');
  });

  it('distinguishes an unavailable history from a genuinely empty one', () => {
    expect(getBrcTransferHistoryState(false, true, 0)).toBe('unavailable');
    expect(getBrcTransferHistoryState(false, false, 0)).toBe('empty');
  });

  it('preserves existing rows while reporting a failed refresh', () => {
    expect(getBrcTransferHistoryState(false, true, 2)).toBe('stale');
    expect(getBrcTransferHistoryState(false, false, 2)).toBe('ready');
  });
});
