export type SquadMember = {
  userId: number;
  name: string;
  username?: string;
  role: string;
  joinedAt?: string;
  score?: number;
};

export type SquadMemberTab = {
  key: string;
  label: string;
  count: number;
};

export type SquadFeedItem = {
  type: string;
  title: string;
  body?: string;
  status?: 'live' | 'coming_soon' | string;
};

export type SquadSocialSummary = {
  followersCount: number;
  following?: boolean;
  feedEnabled: boolean;
  postFeedStatus?: string;
  postFeedNote?: string;
};

export type Squad = {
  id: number;
  name: string;
  slug: string;
  inviteCode?: string;
  shareUrl?: string;
  shareText?: string;
  countryCode?: string;
  languageCode?: string;
  shareCount: number;
  description: string;
  memberCount: number;
  maxMembers?: number;
  isUnlimited: boolean;
  capacityLabel: string;
  isPublic: boolean;
  featured: boolean;
  promoted: boolean;
  weeklyTargetPoints: number;
  weeklyPoints: number;
  weeklyProgressPercent: number;
  statsDeferred?: boolean;
  communityBoostPercent: number;
  chestUnlocked: boolean;
  chestClaimed: boolean;
  boostPercent?: number;
  posterUrl?: string;
  qrUrl?: string;
  countryRank?: number;
  countryName?: string;
  weekStart?: string;
  weekEnd?: string;
  members: SquadMember[];
  memberGroups: {
    leaders: SquadMember[];
    activeToday: SquadMember[];
    newJoiners: SquadMember[];
    topMiners: SquadMember[];
    topReferrers: SquadMember[];
  };
  social?: SquadSocialSummary;
  activityBreakdown: Record<string, number>;
  weeklyMissions: SquadMission[];
  inviteStreak?: SquadInviteStreak;
  announcements: SquadAnnouncement[];
};

export type SquadMembership = {
  role: string;
  joinedAt?: string;
};

export type SquadLeaderboardEntry = {
  squadId: number;
  name: string;
  memberCount: number;
  weeklyPoints: number;
  communityBoostPercent: number;
  chestUnlocked: boolean;
  countryCode?: string;
  countryName?: string;
  rankLabel?: string;
};

export type MySquadData = {
  hasSquad: boolean;
  squad?: Squad;
  membership?: SquadMembership;
  discover: Squad[];
  leaderboard: SquadLeaderboardEntry[];
  countryLeaderboard: SquadLeaderboardEntry[];
  weeklyMissions: SquadMission[];
  announcements: SquadAnnouncement[];
  memberTabs: SquadMemberTab[];
  followers?: {
    count: number;
    following: boolean;
  };
  socialFeedPreview: SquadFeedItem[];
  recommended: Squad[];
  fastLoaded?: boolean;
  cacheTtlSeconds?: number;
};

export type SquadChestClaimResult = {
  message: string;
  claimed: boolean;
  alreadyClaimed: boolean;
  rewardXp: number;
  rewardBrc?: number;
};

export type SquadActionResult = {
  message: string;
  claimed?: boolean;
  alreadyClaimed?: boolean;
  rewardXp?: number;
  rewardBrc?: number;
};

export type SquadShareChannel =
  | 'copy_link'
  | 'whatsapp'
  | 'telegram'
  | 'instagram'
  | 'facebook'
  | 'x'
  | 'sms'
  | 'email'
  | 'other';

export type SquadShareCard = {
  squad: {
    inviteCode?: string;
    shareUrl?: string;
    shareText: string;
    countryCode?: string;
    languageCode?: string;
    memberCount: number;
    maxMembers?: number;
    isUnlimited: boolean;
    capacityLabel: string;
    weeklyPoints: number;
    boostPercent: number;
  };
  poster: {
    url?: string;
    template?: string;
    title?: string;
    qrUrl?: string;
    backgroundColor?: string;
    textColor?: string;
    accentColor?: string;
    qrText?: string;
    safeNote?: string;
    templates: SquadPosterTemplate[];
  };
  shareOptions: {
    channels: SquadShareChannel[];
  };
};

export type SquadPublicInvite = {
  squad: Squad;
  shareCard?: SquadShareCard;
};

export type SquadMission = {
  id?: number;
  key: string;
  title: string;
  description?: string;
  metric?: string;
  progress: number;
  target: number;
  rewardLabel?: string;
  rewardXp?: number;
  rewardBrc?: number;
  completed: boolean;
  claimed: boolean;
  canClaim: boolean;
};

export type SquadInviteStreak = {
  current: number;
  target: number;
  activeInvitesToday?: number;
  streakDays?: number;
  rewardLabel?: string;
  rewardXp?: number;
  rewardBrc?: number;
  canClaim: boolean;
  claimed: boolean;
};

export type SquadAnnouncement = {
  id?: string | number;
  title: string;
  body: string;
  template?: string;
  ctaLabel?: string;
};

export type SquadPosterTemplate = {
  key: string;
  title: string;
  url?: string;
};
