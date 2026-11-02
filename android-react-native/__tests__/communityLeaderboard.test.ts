import {
  getCommunityLeaderboardPosition,
  getCommunityLeaderboardValue,
} from '../source/utils/communityLeaderboard';
import {getApi} from '../source/storage/server';
import {fetchCommunityLeaderboard} from '../source/services/communityLeaderboardService';

jest.mock('../source/storage/server', () => ({
  getApi: jest.fn(),
}));

describe('community leaderboard rendering', () => {
  it('uses the backend mining total instead of rendering a false zero', () => {
    expect(getCommunityLeaderboardValue({total: 37644}, 'miners')).toBe('37,644 BRC');
  });

  it('uses the backend referral count as the invite total', () => {
    expect(getCommunityLeaderboardValue({total: '485'}, 'referrals')).toBe('485 invites');
  });

  it('prefers the backend global position and falls back for older payloads', () => {
    expect(getCommunityLeaderboardPosition({position: '12'}, 1)).toBe(12);
    expect(getCommunityLeaderboardPosition({position: 0}, 4)).toBe(4);
  });
});

describe('community leaderboard requests', () => {
  const mockedGetApi = getApi as jest.MockedFunction<typeof getApi>;

  beforeEach(() => mockedGetApi.mockReset());

  it('preserves successful API envelopes for the existing response parser', async () => {
    const response = {
      success: true,
      data: {message: 'Authenticated', data: {top50Miners: []}},
    };
    mockedGetApi.mockResolvedValue(response);

    await expect(fetchCommunityLeaderboard('miners', 12)).resolves.toBe(response);
    expect(mockedGetApi).toHaveBeenCalledWith('top-miners?userId=12', {signal: undefined});
  });

  it('rejects resolved HTTP failures so the screen renders its retry state', async () => {
    mockedGetApi.mockResolvedValue({success: false, error: 'Service unavailable'});

    await expect(fetchCommunityLeaderboard('referrals', 12)).rejects.toThrow('Service unavailable');
    expect(mockedGetApi).toHaveBeenCalledWith('top-refferals?userId=12', {signal: undefined});
  });

  it('rejects an unsuccessful application payload even when HTTP returned successfully', async () => {
    mockedGetApi.mockResolvedValue({success: true, data: {success: false, message: 'Ranking is paused'}});

    await expect(fetchCommunityLeaderboard('miners', 12)).rejects.toThrow('Ranking is paused');
  });
});
