import {getApi} from '../storage/server';

type LeaderboardKind = 'miners' | 'referrals';

export const fetchCommunityLeaderboard = async (
  kind: LeaderboardKind,
  userId: number,
  signal?: AbortSignal,
) => {
  const endpoint = kind === 'miners' ? 'top-miners' : 'top-refferals';
  const response = await getApi(`${endpoint}?userId=${userId}`, {signal});
  const payload = response.data as {success?: boolean; message?: string} | undefined;

  if (!response.success || payload?.success === false) {
    const message =
      (typeof response.error === 'string' && response.error) ||
      (typeof payload?.message === 'string' && payload.message) ||
      'Unable to load the community ranking.';
    throw new Error(message);
  }

  return response;
};
