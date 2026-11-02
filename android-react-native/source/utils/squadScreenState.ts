type SquadTab = 'mine' | 'discover' | 'leaderboard';

export const shouldShowSquadJoinOptions = (state: {
  tab: SquadTab;
  isLoading: boolean;
  isError: boolean;
  hasSquad: boolean;
}) => state.tab === 'mine' && !state.isLoading && !state.isError && !state.hasSquad;
