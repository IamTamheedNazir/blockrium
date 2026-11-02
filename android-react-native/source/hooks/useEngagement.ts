import {
  QueryClient,
  useMutation,
  useQuery,
  useQueryClient,
} from 'react-query';
import {getApi, postApi} from '../storage/server';
import {
  BadgesData,
  DailyMission,
  DailyMissionsData,
  GamificationBadge,
  GamificationSeason,
  GamificationSummary,
  MissionAction,
  MissionStatus,
  ReferralJourneyData,
  ReferralMilestone,
  ReferralMilestoneClaimResult,
} from '../types/engagement';

export const engagementQueryKeys = {
  dailyMissions: ['engagement', 'daily-missions'] as const,
  gamificationSummary: ['engagement', 'gamification-summary'] as const,
  badges: ['engagement', 'badges'] as const,
  referralJourney: ['engagement', 'referral-journey'] as const,
};

const ENGAGEMENT_REQUEST_TIMEOUT_MS = 8000;

const missionStatuses = new Set<MissionStatus>([
  'ready',
  'completed',
  'active',
  'locked',
  'unavailable',
  'error',
]);
const missionActions = new Set<MissionAction>([
  'mining',
  'streak',
  'tasks',
  'referral',
  'profile',
  'wallet',
  'learn',
]);

const isObject = (value: unknown): value is Record<string, any> =>
  Boolean(value && typeof value === 'object' && !Array.isArray(value));

const asArray = (value: unknown): any[] => (Array.isArray(value) ? value : []);

const asString = (value: unknown, fallback = '') => {
  if (typeof value === 'string') {
    return value.trim() || fallback;
  }
  if (typeof value === 'number' && Number.isFinite(value)) {
    return String(value);
  }
  return fallback;
};

const asNumber = (value: unknown, fallback = 0) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
};

const asBoolean = (value: unknown) =>
  value === true || value === 1 || value === '1' || value === 'true';

const asPercent = (value: unknown) =>
  Math.min(100, Math.max(0, asNumber(value)));

const getMessage = (value: any, fallback: string) =>
  asString(value?.message ?? value?.error ?? value?.msg, fallback);

const unwrapResponse = (response: any, fallbackMessage: string) => {
  if (!response?.success) {
    throw new Error(getMessage(response, fallbackMessage));
  }

  const body = response?.data;
  if (!isObject(body)) {
    throw new Error(fallbackMessage);
  }

  if (body.success === false || Number(body.status ?? 1) === 0) {
    throw new Error(getMessage(body, fallbackMessage));
  }

  const payload = isObject(body.data) ? body.data : body;
  if (!isObject(payload)) {
    throw new Error(fallbackMessage);
  }

  return payload;
};

const normalizeBadge = (value: any, earnedFallback: boolean): GamificationBadge => {
  const id = asString(value?.id ?? value?.key ?? value?.slug ?? value?.name, 'badge');
  return {
    id,
    key: asString(value?.key ?? value?.slug, id),
    title: asString(value?.title ?? value?.name, 'Blockrium badge'),
    description: asString(value?.description ?? value?.subtitle),
    earned: asBoolean(value?.earned ?? value?.is_earned ?? value?.unlocked ?? earnedFallback),
    earnedAt: asString(value?.earned_at ?? value?.unlocked_at) || undefined,
    icon: asString(value?.icon ?? value?.icon_name) || undefined,
  };
};

const normalizeMission = (value: any, index: number): DailyMission => {
  const rawStatus = asString(value?.status, 'unavailable').toLowerCase();
  const rawAction = asString(value?.action).toLowerCase();
  const status = missionStatuses.has(rawStatus as MissionStatus)
    ? (rawStatus as MissionStatus)
    : 'unavailable';
  const action = missionActions.has(rawAction as MissionAction)
    ? (rawAction as MissionAction)
    : undefined;

  return {
    key: asString(value?.key ?? value?.id, `mission-${index}`),
    title: asString(value?.title ?? value?.name, 'Daily mission'),
    subtitle: asString(value?.subtitle ?? value?.description),
    status,
    rewardLabel: asString(value?.reward_label ?? value?.reward) || undefined,
    action,
    progressPercent: asPercent(value?.progress_percent),
    meta: isObject(value?.meta) ? value.meta : {},
  };
};

const fetchDailyMissions = async (signal?: AbortSignal): Promise<DailyMissionsData> => {
  const payload = unwrapResponse(
    await getApi('auth/daily-missions', {signal, timeout: ENGAGEMENT_REQUEST_TIMEOUT_MS}),
    "Today's plan is unavailable right now.",
  );
  const rawMissions = payload.missions ?? payload.daily_missions;
  if (!Array.isArray(rawMissions)) {
    throw new Error("Today's plan returned an invalid response.");
  }

  const missions = rawMissions
    .filter(isObject)
    .map((mission, index) => normalizeMission(mission, index));
  const summary = isObject(payload.summary) ? payload.summary : {};
  const completedCount = asNumber(
    summary.completed_count,
    missions.filter(mission => mission.status === 'completed').length,
  );
  const totalCount = asNumber(summary.total_count, missions.length);

  return {
    summary: {
      completedCount,
      totalCount,
      progressPercent: asPercent(
        summary.progress_percent ??
          (totalCount > 0 ? (completedCount / totalCount) * 100 : 0),
      ),
    },
    missions,
  };
};

const normalizeSeason = (value: any): GamificationSeason | undefined => {
  if (!isObject(value) && typeof value !== 'string') {
    return undefined;
  }
  if (typeof value === 'string') {
    return {name: value, label: value, progressPercent: 0};
  }

  const name = asString(value.name ?? value.title ?? value.label, 'Genesis Season');
  return {
    name,
    label: asString(value.label, name),
    progressPercent: asPercent(value.progress_percent),
    endsAt: asString(value.ends_at ?? value.end_date) || undefined,
  };
};

const fetchGamificationSummary = async (signal?: AbortSignal): Promise<GamificationSummary> => {
  const payload = unwrapResponse(
    await getApi('auth/gamification/summary', {signal, timeout: ENGAGEMENT_REQUEST_TIMEOUT_MS}),
    'Level progress is unavailable right now.',
  );

  return {
    xp: asNumber(payload.xp ?? payload.total_xp),
    level: Math.max(1, asNumber(payload.level, 1)),
    levelName: asString(payload.level_name ?? payload.level_title, 'Explorer'),
    currentLevelXp: asNumber(payload.current_level_xp ?? payload.level_xp),
    nextLevelXp: asNumber(payload.next_level_xp ?? payload.xp_to_next_level),
    progressPercent: asPercent(payload.progress_percent),
    badges: asArray(payload.badges)
      .filter(isObject)
      .map(badge => normalizeBadge(badge, true)),
    season: normalizeSeason(payload.season),
  };
};

const fetchBadges = async (signal?: AbortSignal): Promise<BadgesData> => {
  const payload = unwrapResponse(
    await getApi('auth/gamification/badges', {signal, timeout: ENGAGEMENT_REQUEST_TIMEOUT_MS}),
    'Badges are unavailable right now.',
  );

  return {
    earned: asArray(payload.earned)
      .filter(isObject)
      .map(badge => normalizeBadge(badge, true)),
    available: asArray(payload.available)
      .filter(isObject)
      .map(badge => normalizeBadge(badge, false)),
  };
};

const normalizeMilestone = (
  value: any,
  index: number,
  activeReferralFallback: number,
): ReferralMilestone => {
  const rewardAmount = asNumber(value?.reward_amount ?? value?.reward);
  return {
    id: asString(value?.id ?? value?.milestone_id, `milestone-${index}`),
    key: asString(value?.key ?? value?.milestone_key, `milestone-${index}`),
    title: asString(value?.title ?? value?.name, 'Referral milestone'),
    description: asString(value?.description ?? value?.subtitle),
    rewardCategory: asString(value?.reward_category, 'active_referral'),
    requiredActiveReferrals: asNumber(
      value?.required_active_referrals ?? value?.required_referrals ?? value?.target,
    ),
    requiredVerifiedReferrals: asNumber(value?.required_verified_referrals),
    requiredTaskReferrals: asNumber(value?.required_task_referrals),
    requiredStreakReferrals: asNumber(value?.required_streak_referrals),
    requiredQualityScore: asNumber(value?.required_quality_score),
    currentActiveReferrals: asNumber(
      value?.current_active_referrals ??
        value?.active_referrals ??
        value?.progress ??
        activeReferralFallback,
    ),
    currentVerifiedReferrals: asNumber(value?.current_verified_referrals),
    currentTaskReferrals: asNumber(value?.current_task_referrals),
    currentStreakReferrals: asNumber(value?.current_streak_referrals),
    currentQualityScore: asNumber(value?.current_quality_score),
    rewardAmount,
    rewardLabel: asString(
      value?.reward_label,
      `${rewardAmount.toFixed(2)} BRC`,
    ),
    status: asString(value?.status, 'locked').toLowerCase(),
    canClaim: asBoolean(value?.can_claim),
    claimed: asBoolean(value?.claimed ?? value?.is_claimed) ||
      asString(value?.status).toLowerCase() === 'claimed',
    missingRequirements: asArray(value?.missing_requirements)
      .filter(isObject)
      .map(item => ({
        metric: asString(item.metric, 'requirement'),
        required: asNumber(item.required),
        current: asNumber(item.current),
      })),
  };
};

const normalizeJourneyStep = (value: any, index: number) => ({
  key: asString(value?.key, `step-${index}`),
  title: asString(value?.title, 'Referral step'),
  description: asString(value?.description),
  current: asNumber(value?.current),
  target: Math.max(1, asNumber(value?.target, 1)),
  progressPercent: asPercent(value?.progress_percent),
  status: asString(value?.status, 'locked').toLowerCase(),
});

const fetchReferralJourney = async (signal?: AbortSignal): Promise<ReferralJourneyData> => {
  const payload = unwrapResponse(
    await getApi('auth/referrals/journey', {signal, timeout: ENGAGEMENT_REQUEST_TIMEOUT_MS}),
    'Referral journey is unavailable right now.',
  );
  const stats = isObject(payload.stats) ? payload.stats : {};
  const claimableRewards = asNumber(
    stats.claimable_rewards ?? stats.claimable_reward ?? stats.claimable_amount,
  );
  const activeReferrals = asNumber(
    stats.active_referrals ?? stats.active_count ?? stats.active_today_count,
  );
  const quality = isObject(payload.quality) ? payload.quality : {};
  const nextBestAction = isObject(stats.next_best_action)
    ? stats.next_best_action
    : isObject(quality.next_action)
    ? quality.next_action
    : undefined;
  const rawMilestones = payload.milestones;

  return {
    referralCode: asString(payload.referral_code ?? payload.code),
    shareMessage: asString(payload.share_message),
    stats: {
      totalJoined: asNumber(
        stats.total_joined ?? stats.joined ?? stats.total_referrals ?? stats.direct_count,
      ),
      activeReferrals,
      pendingReferrals: asNumber(
        stats.pending_referrals ?? stats.pending_count ?? stats.inactive_count,
      ),
      verifiedReferrals: asNumber(stats.verified_referrals),
      threeDayMiners: asNumber(stats.three_day_miners),
      taskReferrals: asNumber(stats.task_referrals),
      streakReferrals: asNumber(stats.streak_referrals),
      qualityScore: asPercent(stats.quality_score ?? quality.score),
      qualityLevel: asString(stats.quality_level ?? quality.level, 'Starter'),
      qualitySummary: asString(stats.quality_summary ?? quality.summary),
      claimableRewards,
      claimableRewardLabel: asString(
        stats.claimable_reward_label,
        `${claimableRewards.toFixed(2)} BRC`,
      ),
      nextBestAction: nextBestAction
        ? {
            title: asString(nextBestAction.title, 'Next referral action'),
            description: asString(nextBestAction.description),
            action: asString(nextBestAction.action),
          }
        : undefined,
    },
    journeySteps: asArray(payload.journey_steps)
      .filter(isObject)
      .map(normalizeJourneyStep),
    milestones: asArray(rawMilestones)
      .filter(isObject)
      .map((milestone, index) =>
        normalizeMilestone(milestone, index, activeReferrals),
      ),
  };
};

export const invalidateEngagementQueries = async (
  queryClient: QueryClient,
  options: {
    wallet?: boolean;
    tasks?: boolean;
    onboardingQuest?: boolean;
    referrals?: boolean;
  } = {},
) => {
  const invalidations = [
    queryClient.invalidateQueries(engagementQueryKeys.dailyMissions),
    queryClient.invalidateQueries(engagementQueryKeys.gamificationSummary),
    queryClient.invalidateQueries(engagementQueryKeys.badges),
  ];

  if (options.wallet) {
    invalidations.push(queryClient.invalidateQueries(['auth/wallet', 'GET']));
  }
  if (options.tasks) {
    invalidations.push(queryClient.invalidateQueries(['auth/get-task', 'GET']));
  }
  if (options.onboardingQuest) {
    invalidations.push(
      queryClient.invalidateQueries(['auth/onboarding-quest', 'GET']),
    );
  }
  if (options.referrals) {
    invalidations.push(
      queryClient.invalidateQueries(engagementQueryKeys.referralJourney),
      queryClient.invalidateQueries(['auth/referral-missions', 'GET']),
      queryClient.invalidateQueries(['auth/my-network', 'GET'], {exact: false}),
    );
  }

  await Promise.all(invalidations);
};

export const useDailyMissions = (enabled = true) =>
  useQuery(engagementQueryKeys.dailyMissions, ({signal}) => fetchDailyMissions(signal), {
    enabled,
    staleTime: 30000,
    retry: false,
    refetchOnReconnect: true,
  });

export const useGamificationSummary = (enabled = true) =>
  useQuery(engagementQueryKeys.gamificationSummary, ({signal}) => fetchGamificationSummary(signal), {
    enabled,
    staleTime: 45000,
    retry: false,
    refetchOnReconnect: true,
  });

export const useBadges = () =>
  useQuery(engagementQueryKeys.badges, ({signal}) => fetchBadges(signal), {
    staleTime: 60000,
    retry: false,
    refetchOnReconnect: true,
  });

export const useReferralJourney = () =>
  useQuery(engagementQueryKeys.referralJourney, ({signal}) => fetchReferralJourney(signal), {
    staleTime: 30000,
    retry: false,
    refetchOnReconnect: true,
  });

export const useClaimReferralMilestone = () => {
  const queryClient = useQueryClient();

  return useMutation<ReferralMilestoneClaimResult, Error, string>(
    async milestoneId => {
      if (!milestoneId) {
        throw new Error('This referral milestone is unavailable.');
      }

      const response = await postApi(
        `auth/referrals/milestone/${encodeURIComponent(milestoneId)}/claim`,
        {},
      );
      const body = response?.data;
      const message = getMessage(
        response?.success ? body : response,
        'Referral milestone could not be claimed right now.',
      );
      const alreadyClaimed = message.toLowerCase().includes('already claimed');

      if (!response?.success || body?.success === false || Number(body?.status ?? 1) === 0) {
        if (alreadyClaimed) {
          return {message: 'This referral milestone was already claimed.', alreadyClaimed: true};
        }
        throw new Error(message);
      }

      return {message, alreadyClaimed: false};
    },
    {
      onSuccess: async () => {
        await invalidateEngagementQueries(queryClient, {
          wallet: true,
          referrals: true,
        });
      },
    },
  );
};
