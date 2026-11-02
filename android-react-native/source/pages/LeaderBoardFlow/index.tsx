import React, {useEffect, useState} from 'react';
import LeaderBoardOrg from '../../organisms/LeaderBoardOrg';
import {callQuery} from '../../hooks';
import {getStorageId} from '../../utils/mmkvStorage';
import {getArrayOrEmpty} from '../../utils/arrayPreview';
import {ScreenLoader} from '../../design/primitives';
import {fetchCommunityLeaderboard} from '../../services/communityLeaderboardService';

const LeaderBoardFlow = () => {
  const [userId, setUserId] = useState<number | undefined>();
  const {
    data,
    error,
    isLoading,
    refetch,
  } = callQuery(`top-miners?userId=${userId ?? ''}`, {
    enabled: userId !== undefined,
    queryFn: ({signal}: {signal?: AbortSignal}) =>
      fetchCommunityLeaderboard('miners', userId ?? 0, signal),
    retry: 0,
    staleTime: 60000,
  });
  const [topMiners, setTopMiners] = useState<any[]>([]);
  const [authMiner, setAuthMiner] = useState<any>(null);

  useEffect(() => {
    const hydrateUserId = async () => {
      const storedUserId = await getStorageId('userId');
      setUserId(storedUserId);
    };

    hydrateUserId();
  }, []);

  useEffect(() => {
    if (data?.success) {
      const payload = data?.data?.data ?? data?.data ?? {};
      setTopMiners(getArrayOrEmpty<any>(payload?.top50Miners));
      setAuthMiner(payload?.authUserMiner ?? null);
    }
  }, [data]);

  if (isLoading) {
    return (
      <ScreenLoader
        title="Loading leaderboard"
        subtitle="Pulling top miners and your current position."
      />
    );
  }

  return (
    <LeaderBoardOrg
      authMiner={authMiner}
      topMiners={topMiners}
      errorMessage={
        error
          ? error instanceof Error
            ? error.message
            : 'Rankings are temporarily unavailable.'
          : undefined
      }
      onRetry={() => refetch()}
    />
  );
};

export default LeaderBoardFlow;
