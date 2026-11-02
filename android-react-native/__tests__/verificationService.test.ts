import {getApi, postApi} from '../source/storage/server';
import {getBrcTransfers, transferBrc} from '../source/services/verificationService';

jest.mock('../source/storage/server', () => ({
  getApi: jest.fn(),
  postApi: jest.fn(),
}));

describe('verified-member BRC transfer API paths', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('sends transfers through the authenticated API route', async () => {
    const payload = {
      recipient_username: 'recipient_user',
      amount: '10.00000000',
      idempotency_key: 'test-transfer-1',
    };
    (postApi as jest.Mock).mockResolvedValue({success: true, data: {status: 1, data: {id: 7}}});

    const result = await transferBrc(payload);

    expect(postApi).toHaveBeenCalledWith('auth/brc/transfers', payload);
    expect(result.data).toEqual({id: 7});
  });

  it('loads transfer history from the authenticated API route and clamps its limit', async () => {
    const transfers = [{id: 7, direction: 'sent'}];
    (getApi as jest.Mock).mockResolvedValue({success: true, data: transfers});

    const result = await getBrcTransfers(500);

    expect(getApi).toHaveBeenCalledWith('auth/brc/transfers?limit=100', {
      signal: undefined,
      timeout: 8000,
    });
    expect(result.data).toEqual(transfers);
  });

  it('passes an abort signal and a caller timeout to transfer history requests', async () => {
    const controller = new AbortController();
    (getApi as jest.Mock).mockResolvedValue({success: true, data: []});

    await getBrcTransfers(5, {signal: controller.signal, timeout: 4000});

    expect(getApi).toHaveBeenCalledWith('auth/brc/transfers?limit=5', {
      signal: controller.signal,
      timeout: 4000,
    });
  });
});
