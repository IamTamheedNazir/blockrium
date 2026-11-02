import {describe, expect, it} from '@jest/globals';
import {loadWalletNetworksIndependently} from '../source/utils/walletNetworkRefresh';

describe('loadWalletNetworksIndependently', () => {
  it('publishes a fast network before a slow network completes', async () => {
    let resolveSlow!: (assets: string[]) => void;
    let resolveFastCommit!: () => void;
    const fastCommitted = new Promise<void>(resolve => {
      resolveFastCommit = resolve;
    });
    const committed: Array<[string, string[]]> = [];
    const refresh = loadWalletNetworksIndependently(
      ['ethereum', 'polygon'] as const,
      network =>
        network === 'ethereum'
          ? Promise.resolve(['ETH'])
          : new Promise<string[]>(resolve => {
              resolveSlow = resolve;
            }),
      (network, assets) => {
        committed.push([network, assets]);
        if (network === 'ethereum') {
          resolveFastCommit();
        }
      },
      () => undefined,
    );

    await fastCommitted;
    expect(committed).toEqual([['ethereum', ['ETH']]]);

    resolveSlow(['MATIC']);
    await refresh;
    expect(committed).toEqual([
      ['ethereum', ['ETH']],
      ['polygon', ['MATIC']],
    ]);
  });

  it('continues loading other networks if one network fails', async () => {
    const committed: string[] = [];
    const failed: string[] = [];

    await loadWalletNetworksIndependently(
      ['ethereum', 'polygon'] as const,
      async network => {
        if (network === 'ethereum') {
          throw new Error('RPC unavailable');
        }
        return ['MATIC'];
      },
      network => committed.push(network),
      network => failed.push(network),
    );

    expect(committed).toEqual(['polygon']);
    expect(failed).toEqual(['ethereum']);
  });
});
