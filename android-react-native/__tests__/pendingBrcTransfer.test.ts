import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  clearPendingBrcTransfer,
  getPendingBrcTransfer,
  isDefinitiveTransferRejection,
  savePendingBrcTransfer,
} from '../source/services/pendingBrcTransfer';

describe('durable pending BRC transfer', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('persists the exact request under the signed-in user and restores it', async () => {
    const intent = {
      recipient_username: 'recipient_user',
      amount: '10.00000000',
      idempotency_key: 'brc-retry-1',
      note: 'Thank you',
      user_id: 42,
    };

    await savePendingBrcTransfer(intent);
    expect(AsyncStorage.setItem).toHaveBeenCalledWith(
      'blockrium.brc-transfer.pending.v1.42',
      JSON.stringify(intent),
    );
    (AsyncStorage.getItem as jest.Mock).mockResolvedValue(JSON.stringify(intent));
    await expect(getPendingBrcTransfer(42)).resolves.toEqual(intent);
  });

  it('does not restore one account’s transfer for another account', async () => {
    const intent = {
      recipient_username: 'recipient_user',
      amount: '10.00000000',
      idempotency_key: 'brc-retry-1',
      user_id: 42,
    };
    (AsyncStorage.getItem as jest.Mock).mockResolvedValue(JSON.stringify(intent));

    await expect(getPendingBrcTransfer(43)).resolves.toBeNull();
    expect(AsyncStorage.getItem).toHaveBeenCalledWith(
      'blockrium.brc-transfer.pending.v1.43',
    );
  });

  it('clears the durable request only for the matching idempotency key', async () => {
    const intent = {
      recipient_username: 'recipient_user',
      amount: '10.00000000',
      idempotency_key: 'brc-retry-1',
      user_id: 42,
    };
    (AsyncStorage.getItem as jest.Mock).mockResolvedValue(JSON.stringify(intent));

    await clearPendingBrcTransfer(42, 'another-request');
    expect(AsyncStorage.removeItem).not.toHaveBeenCalled();
    await clearPendingBrcTransfer(42, 'brc-retry-1');
    expect(AsyncStorage.removeItem).toHaveBeenCalledWith(
      'blockrium.brc-transfer.pending.v1.42',
    );
  });

  it('treats ordinary client errors as final but keeps timeout and throttling ambiguous', () => {
    expect(isDefinitiveTransferRejection(422)).toBe(true);
    expect(isDefinitiveTransferRejection(401)).toBe(true);
    expect(isDefinitiveTransferRejection(408)).toBe(false);
    expect(isDefinitiveTransferRejection(409)).toBe(false);
    expect(isDefinitiveTransferRejection(429)).toBe(false);
    expect(isDefinitiveTransferRejection(500)).toBe(false);
    expect(isDefinitiveTransferRejection(undefined)).toBe(false);
  });
});
