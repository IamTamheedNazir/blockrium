type CommunityLeaderboardTab = 'miners' | 'referrals';

const readFiniteNumber = (...values: unknown[]) => {
  for (const value of values) {
    if (value === null || value === undefined || value === '') {
      continue;
    }

    const number = Number(value);
    if (Number.isFinite(number)) {
      return number;
    }
  }

  return 0;
};

export const getCommunityLeaderboardValue = (
  item: Record<string, unknown>,
  tab: CommunityLeaderboardTab,
) => {
  const value =
    tab === 'miners'
      ? readFiniteNumber(item.total_mining, item.totalMining, item.mined, item.score, item.total)
      : readFiniteNumber(item.referrals, item.total_referrals, item.count, item.score, item.total);

  return tab === 'miners'
    ? `${value.toLocaleString()} BRC`
    : `${value.toLocaleString()} invites`;
};

export const getCommunityLeaderboardPosition = (item: Record<string, unknown>, fallback: number) => {
  const position = Number(item.position);
  return Number.isInteger(position) && position > 0 ? position : fallback;
};
