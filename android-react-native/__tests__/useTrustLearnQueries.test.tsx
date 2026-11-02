import React from 'react';
import renderer, {act} from 'react-test-renderer';
import {QueryClient, QueryClientProvider} from 'react-query';
import {
  trustLearnQueryKeys,
  useLearnModuleDetail,
  useLearnModules,
  useTrustCenter,
} from '../source/hooks/useTrustLearn';

const mockGetApi = jest.fn();

jest.mock('../source/storage/server', () => ({
  getApi: (...args: any[]) => mockGetApi(...args),
  postApi: jest.fn(),
}));

describe('Trust Center and Learn queries', () => {
  it('bounds requests and exposes service failures without retrying', async () => {
    mockGetApi.mockResolvedValue({
      success: false,
      error: 'Service unavailable',
    });
    const queryClient = new QueryClient();
    let latest: any;
    const Probe = () => {
      latest = {
        trust: useTrustCenter(),
        modules: useLearnModules(),
        detail: useLearnModuleDetail(7),
      };
      return null;
    };
    let tree!: renderer.ReactTestRenderer;

    await act(async () => {
      tree = renderer.create(
        <QueryClientProvider client={queryClient}>
          <Probe />
        </QueryClientProvider>,
      );
      await new Promise(resolve => setTimeout(resolve, 0));
    });

    try {
      expect(
        queryClient.getQueryCache().find(trustLearnQueryKeys.trustCenter)
          ?.options.retry,
      ).toBe(0);
      expect(
        queryClient.getQueryCache().find(trustLearnQueryKeys.learnModules)
          ?.options.retry,
      ).toBe(0);
      expect(
        queryClient
          .getQueryCache()
          .find(trustLearnQueryKeys.learnModuleDetail(7))?.options.retry,
      ).toBe(0);
      expect(latest.trust.isError).toBe(true);
      expect(latest.modules.isError).toBe(true);
      expect(latest.detail.isError).toBe(true);
      expect(mockGetApi).toHaveBeenCalledTimes(3);
      expect(mockGetApi).toHaveBeenCalledWith('trust-center', {
        signal: expect.anything(),
        timeout: 8000,
      });
      expect(mockGetApi).toHaveBeenCalledWith('auth/learn/modules', {
        signal: expect.anything(),
        timeout: 8000,
      });
      expect(mockGetApi).toHaveBeenCalledWith('auth/learn/modules/7', {
        signal: expect.anything(),
        timeout: 8000,
      });
    } finally {
      act(() => tree.unmount());
      queryClient.clear();
    }
  });
});
