import React from 'react';
import renderer, {act} from 'react-test-renderer';
import {QueryClient, QueryClientProvider} from 'react-query';
import {
  useMySquad,
  useMySquadProfile,
  useSquadDiscovery,
  useSquadLeaderboard,
  useSquadMembers,
} from '../source/hooks/useSquads';

const mockGetApi = jest.fn();
const mockPostApi = jest.fn();

jest.mock('../source/storage/server', () => ({
  getApi: (...args: any[]) => mockGetApi(...args),
  postApi: (...args: any[]) => mockPostApi(...args),
  removeLeadsTokenFromHeader: jest.fn(),
}));

jest.mock('../source/services/appIssueLogger', () => ({
  logAppIssue: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('../source/hooks/useEngagement', () => ({
  invalidateEngagementQueries: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('../source/utils/helper', () => ({resetNavigation: jest.fn()}));
jest.mock('../source/utils/mmkvStorage', () => ({
  setStorageToken: jest.fn().mockResolvedValue(undefined),
}));

describe('Squad app-home requests', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('can defer the My Squad summary request until Home is ready', async () => {
    mockGetApi.mockResolvedValue({
      success: true,
      status: 200,
      data: {success: true, data: {has_squad: false}},
    });

    const queryClient = new QueryClient({
      defaultOptions: {queries: {retry: false}},
    });
    let latest: any;
    let tree!: renderer.ReactTestRenderer;
    const Probe = ({enabled}: {enabled: boolean}) => {
      latest = useMySquad(enabled);
      return null;
    };

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
    expect(mockGetApi).toHaveBeenCalledWith(
      'auth/squads/app-home',
      expect.any(Object),
    );
    expect(latest.data.hasSquad).toBe(false);

    act(() => tree.unmount());
    queryClient.clear();
  });

  it('loads the selected member filter from the dedicated members endpoint', async () => {
    mockGetApi.mockResolvedValue({
      success: true,
      status: 200,
      data: {
        success: true,
        data: {members: [{user_id: 81, name: 'Amina', role: 'moderator', score: 12}]},
      },
    });

    const queryClient = new QueryClient({
      defaultOptions: {queries: {retry: false}},
    });
    let latest: any;
    const Probe = () => {
      latest = useSquadMembers('top_miners', true);
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

    expect(mockGetApi).toHaveBeenCalledWith(
      'auth/squads/members?tab=top_miners&limit=25',
      expect.any(Object),
    );
    expect(latest.data[0]).toMatchObject({userId: 81, name: 'Amina', score: 12});

    act(() => tree.unmount());
    queryClient.clear();
  });

  it('shares the app-home response across My Squad, Discover, and Ranks', async () => {
    mockGetApi.mockResolvedValue({
      success: true,
      status: 200,
      data: {
        success: true,
        data: {
          has_squad: false,
          discover: [{id: 7, name: 'North Stars', member_count: 520}],
          recommended: [],
          leaderboard: [{squad_id: 7, name: 'North Stars', members: 520}],
          country_leaderboard: [],
        },
      },
    });

    const queryClient = new QueryClient({
      defaultOptions: {queries: {retry: false}},
    });
    let latest: any;
    const Probe = () => {
      latest = {
        mine: useMySquad(),
        discover: useSquadDiscovery(),
        leaderboard: useSquadLeaderboard(),
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

    expect(mockGetApi).toHaveBeenCalledTimes(1);
    expect(mockGetApi).toHaveBeenCalledWith('auth/squads/app-home', expect.any(Object));
    expect(latest.mine.data.hasSquad).toBe(false);
    expect(latest.discover.data[0].name).toBe('North Stars');
    expect(latest.leaderboard.data[0].squadId).toBe(7);

    act(() => tree.unmount());
    queryClient.clear();
  });

  it('defers discovery and rankings until secondary Home content is enabled', async () => {
    mockGetApi.mockResolvedValue({
      success: true,
      status: 200,
      data: {
        success: true,
        data: {
          has_squad: false,
          discover: [{id: 7, name: 'North Stars', member_count: 520}],
          recommended: [],
          leaderboard: [{squad_id: 7, name: 'North Stars', members: 520}],
          country_leaderboard: [],
        },
      },
    });

    const queryClient = new QueryClient({
      defaultOptions: {queries: {retry: false}},
    });
    let latest: any;
    const Probe = ({enabled}: {enabled: boolean}) => {
      latest = {
        discover: useSquadDiscovery(enabled),
        leaderboard: useSquadLeaderboard(enabled),
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

    expect(mockGetApi).toHaveBeenCalledTimes(1);
    expect(mockGetApi).toHaveBeenCalledWith('auth/squads/app-home', expect.any(Object));
    expect(latest.discover.data[0].name).toBe('North Stars');
    expect(latest.leaderboard.data[0].name).toBe('North Stars');

    act(() => tree.unmount());
    queryClient.clear();
  });

  it('falls back to the legacy squad home endpoint when app-home is not deployed', async () => {
    mockGetApi
      .mockResolvedValueOnce({
        success: false,
        status: 404,
        data: {message: 'Route not found.'},
      })
      .mockResolvedValueOnce({
        success: true,
        status: 200,
        data: {
          success: true,
          data: {
            has_squad: true,
            squad: {id: 42, name: 'North Stars', member_count: 18},
            membership: {role: 'member'},
          },
        },
      });

    const queryClient = new QueryClient({
      defaultOptions: {queries: {retry: false}},
    });
    let latest: any;
    const Probe = () => {
      latest = useMySquad();
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

    expect(mockGetApi).toHaveBeenNthCalledWith(1, 'auth/squads/app-home', expect.any(Object));
    expect(mockGetApi).toHaveBeenNthCalledWith(2, 'auth/squads/home', expect.any(Object));
    expect(latest.isError).toBe(false);
    expect(latest.data).toMatchObject({
      hasSquad: true,
      squad: {id: 42, name: 'North Stars'},
    });

    act(() => tree.unmount());
    queryClient.clear();
  });

  it('normalizes a joined squad profile with deferred weekly stats', async () => {
    mockGetApi.mockResolvedValue({
      success: true,
      status: 200,
      data: {
        success: true,
        data: {
          has_squad: true,
          membership: {role: 'member'},
          squad: {
            id: 19,
            name: 'North Stars',
            member_count: 28,
            stats_deferred: true,
            weekly_points: 0,
            members: [{id: 7, name: 'Test member', role: 'owner'}],
          },
        },
      },
    });

    const queryClient = new QueryClient({
      defaultOptions: {queries: {retry: false}},
    });
    let latest: any;
    const Probe = () => {
      latest = useMySquad();
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

    expect(latest.data).toMatchObject({
      hasSquad: true,
      squad: {name: 'North Stars', memberCount: 28, statsDeferred: true},
    });

    act(() => tree.unmount());
    queryClient.clear();
  });

  it('loads the detailed squad profile and weekly score separately from the fast summary', async () => {
    mockGetApi.mockResolvedValue({
      success: true,
      status: 200,
      data: {
        success: true,
        data: {
          has_squad: true,
          membership: {role: 'owner'},
          squad: {
            id: 19,
            name: 'North Stars',
            member_count: 28,
            weekly_points: 125,
            weekly_target_points: 500,
            stats_deferred: false,
          },
        },
      },
    });

    const queryClient = new QueryClient({
      defaultOptions: {queries: {retry: false}},
    });
    let latest: any;
    const Probe = () => {
      latest = useMySquadProfile(true);
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

    expect(mockGetApi).toHaveBeenCalledWith(
      'auth/squads/me?profile=1',
      expect.any(Object),
    );
    expect(latest.data).toMatchObject({
      hasSquad: true,
      squad: {
        name: 'North Stars',
        weeklyPoints: 125,
        weeklyTargetPoints: 500,
        statsDeferred: false,
      },
    });

    act(() => tree.unmount());
    queryClient.clear();
  });

  it('turns a network failure into a retryable squad-service error', async () => {
    mockGetApi.mockResolvedValue({
      success: false,
      status: 0,
      error: 'Network request failed',
    });

    const queryClient = new QueryClient({
      defaultOptions: {queries: {retry: false}},
    });
    let latest: any;
    const Probe = () => {
      latest = useMySquad();
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

    expect(latest.isError).toBe(true);
    expect(latest.error.message).toContain('Could not reach the Blockrium squads service');

    act(() => tree.unmount());
    queryClient.clear();
  });

  it('ends the squad loading state when the app-home request never resolves', async () => {
    let requestSignal: AbortSignal | undefined;
    mockGetApi.mockImplementation((_endpoint: string, options: {signal?: AbortSignal}) => {
      requestSignal = options.signal;
      return new Promise(() => {});
    });

    const queryClient = new QueryClient({
      defaultOptions: {queries: {retry: false}},
    });
    let latest: any;
    const Probe = () => {
      latest = useMySquad();
      return null;
    };
    let tree!: renderer.ReactTestRenderer;

    jest.useFakeTimers();
    try {
      await act(async () => {
        tree = renderer.create(
          <QueryClientProvider client={queryClient}>
            <Probe />
          </QueryClientProvider>,
        );
        await Promise.resolve();
      });
      expect(latest.isLoading).toBe(true);
      expect(requestSignal?.aborted).toBe(false);

      await act(async () => {
        jest.advanceTimersByTime(8000);
        await Promise.resolve();
        await Promise.resolve();
      });

      expect(requestSignal?.aborted).toBe(true);
      expect(latest.isLoading).toBe(false);
      expect(latest.isError).toBe(true);
      expect(latest.error.message).toBe('Squad service timed out. Please retry.');
    } finally {
      act(() => tree?.unmount());
      queryClient.clear();
      jest.useRealTimers();
    }
  });

  it('falls back to the lighter squad endpoint when app-home is not deployed', async () => {
    mockGetApi.mockImplementation(async (endpoint: string) => {
      if (endpoint === 'auth/squads/app-home') {
        return {success: false, status: 404, error: 'Route not found'};
      }
      return {
        success: true,
        status: 200,
        data: {
          success: true,
          data: {
            has_squad: false,
            squad: null,
            discover: [{id: 12, name: 'Fallback Squad', member_count: 1400}],
            recommended: [],
            leaderboard: [],
            country_leaderboard: [],
          },
        },
      };
    });

    const queryClient = new QueryClient({
      defaultOptions: {queries: {retry: false}},
    });
    let latest: any;
    const Probe = () => {
      latest = useMySquad();
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

    expect(mockGetApi).toHaveBeenCalledTimes(2);
    expect(mockGetApi).toHaveBeenLastCalledWith('auth/squads/home', expect.any(Object));
    expect(latest.data.discover[0].name).toBe('Fallback Squad');

    act(() => tree.unmount());
    queryClient.clear();
  });

  it('does not fan out to legacy endpoints when app-home is unavailable', async () => {
    mockGetApi.mockResolvedValue({
      success: false,
      status: 500,
      error: 'Squad app home is temporarily unavailable.',
    });

    const queryClient = new QueryClient({
      defaultOptions: {queries: {retry: false}},
    });
    let latest: any;
    let tree!: renderer.ReactTestRenderer;

    await act(async () => {
      tree = renderer.create(
        <QueryClientProvider client={queryClient}>
          <ProbeMySquad value={value => { latest = value; }} />
        </QueryClientProvider>,
      );
      await new Promise(resolve => setTimeout(resolve, 0));
    });

    expect(mockGetApi).toHaveBeenCalledTimes(1);
    expect(mockGetApi).toHaveBeenNthCalledWith(1, 'auth/squads/app-home', expect.any(Object));
    expect(latest.isError).toBe(true);
    expect(latest.error.message).toContain('temporarily unavailable');

    act(() => tree.unmount());
    queryClient.clear();
  });

  it('loads Discover directly when the app-home summary has no squad lists', async () => {
    mockGetApi.mockImplementation(async (endpoint: string) => ({
      success: true,
      status: 200,
      data: {
        success: true,
        data: endpoint === 'auth/squads/app-home'
          ? {has_squad: false, discover: [], recommended: [], leaderboard: []}
          : {squads: [{id: 24, name: 'Builders United', member_count: 640}]},
      },
    }));

    const queryClient = new QueryClient({
      defaultOptions: {queries: {retry: false}},
    });
    let latest: any;
    const Probe = () => {
      latest = useSquadDiscovery();
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

    expect(mockGetApi).toHaveBeenCalledWith('auth/squads/discover', expect.any(Object));
    expect(latest.data[0]).toMatchObject({name: 'Builders United', memberCount: 640});

    act(() => tree.unmount());
    queryClient.clear();
  });
});

const ProbeMySquad = ({value}: {value: (query: any) => void}) => {
  value(useMySquad());
  return null;
};
