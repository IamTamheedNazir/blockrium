import React from 'react';
import renderer, {act} from 'react-test-renderer';
import {QueryClient, QueryClientProvider} from 'react-query';
import {
  useDailyMissions,
  useGamificationSummary,
} from '../source/hooks/useEngagement';

const mockGetApi = jest.fn();

jest.mock('../source/storage/server', () => ({
  getApi: (...args: any[]) => mockGetApi(...args),
  postApi: jest.fn(),
}));

describe('Home engagement queries', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockGetApi.mockResolvedValue({
      success: true,
      status: 200,
      data: {
        success: true,
        data: {missions: [], xp: 0},
      },
    });
  });

  it('defers daily missions and gamification until secondary Home content is enabled', async () => {
    const queryClient = new QueryClient({
      defaultOptions: {queries: {retry: false}},
    });
    let latest: any;
    const Probe = ({enabled}: {enabled: boolean}) => {
      latest = {
        missions: useDailyMissions(enabled),
        gamification: useGamificationSummary(enabled),
      };
      return null;
    };
    let tree!: renderer.ReactTestRenderer;

    await act(async () => {
      tree = renderer.create(
        <QueryClientProvider client={queryClient}>
          <Probe enabled={false} />
        </QueryClientProvider>,
      );
      await new Promise(resolve => setTimeout(resolve, 0));
    });

    expect(mockGetApi).not.toHaveBeenCalled();

    await act(async () => {
      tree.update(
        <QueryClientProvider client={queryClient}>
          <Probe enabled />
        </QueryClientProvider>,
      );
      await new Promise(resolve => setTimeout(resolve, 0));
    });

    expect(mockGetApi).toHaveBeenCalledTimes(2);
    expect(mockGetApi).toHaveBeenCalledWith('auth/daily-missions', expect.any(Object));
    expect(mockGetApi).toHaveBeenCalledWith('auth/gamification/summary', expect.any(Object));
    expect(latest.missions.data.missions).toEqual([]);
    expect(latest.gamification.data.level).toBe(1);

    act(() => tree.unmount());
    queryClient.clear();
  });

  it('bounds daily mission requests and exposes failures without a hidden retry', async () => {
    mockGetApi.mockResolvedValue({success: false, error: 'Service unavailable'});
    const queryClient = new QueryClient();
    let latest: any;
    const Probe = () => {
      latest = useDailyMissions();
      return null;
    };
    let tree!: renderer.ReactTestRenderer;

    await act(async () => {
      tree = renderer.create(
        <QueryClientProvider client={queryClient}>
          <Probe />
        </QueryClientProvider>,
      );
      await new Promise(resolve => setTimeout(resolve, 1800));
    });

    expect(latest.isError).toBe(true);
    expect(mockGetApi).toHaveBeenCalledTimes(1);
    expect(mockGetApi).toHaveBeenCalledWith('auth/daily-missions', {
      signal: expect.anything(),
      timeout: 8000,
    });

    act(() => tree.unmount());
    queryClient.clear();
  });
});
