type CommunitySquadShortcutInput = {
  isLoading: boolean;
  isError: boolean;
  hasSquad?: boolean;
  squad?: {name?: string; memberCount?: number} | null;
};

export const getCommunitySquadShortcutCopy = ({isLoading, isError, hasSquad, squad}: CommunitySquadShortcutInput) => {
  if (squad) {
    return {
      title: squad.name || 'My squad',
      meta: `${Math.max(0, Number(squad.memberCount) || 0).toLocaleString()} members`,
    };
  }

  if (isLoading) {
    return {title: 'Loading your squad', meta: ''};
  }

  if (isError) {
    return {title: 'Squad unavailable', meta: ''};
  }

  if (hasSquad) {
    return {title: 'Squad unavailable', meta: ''};
  }

  return {title: 'Find a squad', meta: 'Create or join a squad'};
};
