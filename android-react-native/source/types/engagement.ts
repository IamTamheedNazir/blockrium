export type MissionStatus =
  | 'ready'
  | 'completed'
  | 'active'
  | 'locked'
  | 'unavailable'
  | 'error';

export type MissionAction =
  | 'mining'
  | 'streak'
  | 'tasks'
  | 'referral'
  | 'profile'
  | 'wallet'
  | 'learn';

export type DailyMission = {
  key: string;
  title: string;
  subtitle: string;
  status: MissionStatus;
  rewardLabel?: string;
  action?: MissionAction;
  progressPercent: number;
  meta: Record<string, unknown>;
};

export type DailyMissionSummary = {
  completedCount: number;
  totalCount: number;
  progressPercent: number;
};

export type DailyMissionsData = {
  summary: DailyMissionSummary;
  missions: DailyMission[];
};

export type GamificationBadge = {
  id: string;
  key: string;
  title: string;
  description: string;
  earned: boolean;
  earnedAt?: string;
  icon?: string;
};

export type GamificationSeason = {
  name: string;
  label: string;
  progressPercent: number;
  endsAt?: string;
};

export type GamificationSummary = {
  xp: number;
  level: number;
  levelName: string;
  currentLevelXp: number;
  nextLevelXp: number;
  progressPercent: number;
  badges: GamificationBadge[];
  season?: GamificationSeason;
};

export type BadgesData = {
  earned: GamificationBadge[];
  available: GamificationBadge[];
};

export type ReferralJourneyStats = {
  totalJoined: number;
  activeReferrals: number;
  pendingReferrals: number;
  verifiedReferrals: number;
  threeDayMiners: number;
  taskReferrals: number;
  streakReferrals: number;
  qualityScore: number;
  qualityLevel: string;
  qualitySummary: string;
  claimableRewards: number;
  claimableRewardLabel: string;
  nextBestAction?: {
    title: string;
    description: string;
    action: string;
  };
};

export type ReferralJourneyStep = {
  key: string;
  title: string;
  description: string;
  current: number;
  target: number;
  progressPercent: number;
  status: string;
};

export type ReferralMilestone = {
  id: string;
  key: string;
  title: string;
  description: string;
  rewardCategory: string;
  requiredActiveReferrals: number;
  requiredVerifiedReferrals: number;
  requiredTaskReferrals: number;
  requiredStreakReferrals: number;
  requiredQualityScore: number;
  currentActiveReferrals: number;
  currentVerifiedReferrals: number;
  currentTaskReferrals: number;
  currentStreakReferrals: number;
  currentQualityScore: number;
  rewardAmount: number;
  rewardLabel: string;
  status: string;
  canClaim: boolean;
  claimed: boolean;
  missingRequirements: Array<{
    metric: string;
    required: number;
    current: number;
  }>;
};

export type ReferralJourneyData = {
  referralCode: string;
  shareMessage: string;
  stats: ReferralJourneyStats;
  journeySteps: ReferralJourneyStep[];
  milestones: ReferralMilestone[];
};

export type ReferralMilestoneClaimResult = {
  message: string;
  alreadyClaimed: boolean;
};
