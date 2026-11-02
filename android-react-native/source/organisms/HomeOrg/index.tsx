import React, {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  InteractionManager,
  Linking,
  Pressable,
  StyleSheet,
  Text,
  useWindowDimensions,
  View,
} from 'react-native';
import FastImage from 'react-native-fast-image';
import Ionicons from 'react-native-vector-icons/Ionicons';
import MaterialCommunityIcons from 'react-native-vector-icons/MaterialCommunityIcons';
import {useQueryClient} from 'react-query';
import {images} from '../../../assets/images';
import {callQuery} from '../../hooks';
import {useKycPaymentReminder} from '../../hooks/useKycPaymentReminder';
import {
  invalidateEngagementQueries,
  useDailyMissions,
  useGamificationSummary,
} from '../../hooks/useEngagement';
import {
  useJoinSquad,
  useMySquad,
  useSquadDiscovery,
  useSquadLeaderboard,
} from '../../hooks/useSquads';
import {useThisAlert} from '../../hooks/AlertContext';
import AnimatedPopup from '../../molecules/AnimatedPopup';
import AppodealBannerSlot from '../../molecules/AppodealBannerSlot';
import KycWaitlistPrompt from '../../molecules/KycWaitlistPrompt';
import KycPaymentReminder from '../../molecules/KycPaymentReminder';
import PartnerCampaignsSection from '../../molecules/PartnerCampaignsSection';
import {
  DailyMissionHub,
  LevelProgressCard,
} from '../../molecules/EngagementCards';
import {
  AppScreen,
  CardEntrance,
  GlassCard,
  PrimaryButton,
  SecondaryButton,
  ScreenLoader,
  SectionEyebrow,
} from '../../design/primitives';
import theme from '../../design/theme';
import {showOptionalActionAd} from '../../services/adService';
import {logAppIssue} from '../../services/appIssueLogger';
import {partnerCampaignQueryKey} from '../../services/partnerCampaignService';
import {fetchTaskList} from '../../services/taskListService';
import {
  VerificationStatus,
  VerificationWaitlist,
} from '../../types/verification';
import {
  getBlockriumPushToken,
  identifyBlockriumNotificationUser,
  requestBlockriumNotificationPermission,
} from '../../services/blockriumNotificationService';
import {getFirebaseMessagingTokenSafely} from '../../services/firebaseMessagingService';
import {navigate} from '../../utils/helper';
import {postApi} from '../../storage/server';
import {
  cacheUserProfile,
  getCachedUserProfile,
  hasProfileIdentity,
  normalizeUserProfile,
  NormalizedUserProfile,
  shouldWaitForHomeProfile,
} from '../../utils/profile';
import {normalizeNotificationCount} from '../../utils/notifications';
import {DailyMission} from '../../types/engagement';
import {Squad, SquadLeaderboardEntry} from '../../types/squads';
import {getSquadMemberTier} from '../../utils/squadMemberTier';
import {hasUnpaidApprovedVerification} from '../../utils/verification';
import {describeArrayPayload, getArrayPreview} from '../../utils/arrayPreview';
import {
  normalizeOnboardingQuest,
  OnboardingQuest,
  OnboardingQuestStep,
} from '../../utils/onboardingQuest';
import {scheduleHomeRequestStages} from '../../utils/homeRequestSchedule';

type Feature = {
  key: string;
  title: string;
  subtitle: string;
  icon: string;
  iconLibrary?: 'ion' | 'material';
  onPress: () => void;
};

type AppStage = {
  title?: string;
  stage?: string;
  miningRate?: string;
};

type TodayChecklistItem = {
  key: string;
  title: string;
  subtitle: string;
  icon: string;
  done: boolean;
  actionLabel: string;
  onPress: () => void;
};

type HomeSquadSuggestion = {
  key: string;
  squadId: number;
  name: string;
  subtitle: string;
  badge: string;
  members: number;
  weeklyPoints: number;
  boostPercent: number;
  rankLabel?: string;
};

const KYC_WAITLIST_PROMPT_SEEN_KEY = 'blockrium.kyc.waitlist.prompt.v1';
const HOME_PROFILE_MAX_WAIT_MS = 2000;

const formatTokenValue = (value: unknown) => {
  const amount = Number(value ?? 0);
  if (!Number.isFinite(amount)) {
    return '0 BRC';
  }

  return `${Math.round(amount).toLocaleString()} BRC`;
};

const formatCompactNumber = (value: unknown) => {
  const amount = Number(value ?? 0);
  if (!Number.isFinite(amount)) {
    return '0';
  }
  if (amount >= 1000000) {
    return `${(amount / 1000000).toFixed(1)}M`;
  }
  if (amount >= 1000) {
    return `${(amount / 1000).toFixed(1)}K`;
  }
  return Math.round(amount).toLocaleString();
};

const squadInitial = (name: string) =>
  name.trim().charAt(0).toUpperCase() || 'B';

const buildSquadSubtitle = (squad: Squad) =>
  [
    squad.countryName || squad.countryCode || 'Global',
    squad.isPublic ? 'Public' : 'Private',
    squad.capacityLabel || (squad.isUnlimited ? 'Unlimited' : ''),
  ]
    .filter(Boolean)
    .join(' / ');

const suggestionFromSquad = (
  squad: Squad,
  badge: string,
): HomeSquadSuggestion => ({
  key: `squad-${squad.id}`,
  squadId: squad.id,
  name: squad.name,
  subtitle: buildSquadSubtitle(squad),
  badge,
  members: squad.memberCount,
  weeklyPoints: squad.weeklyPoints,
  boostPercent: squad.communityBoostPercent || squad.boostPercent || 0,
  rankLabel: squad.countryRank ? `#${squad.countryRank}` : undefined,
});

const suggestionFromLeaderboard = (
  squad: SquadLeaderboardEntry,
  index: number,
  badge: string,
): HomeSquadSuggestion => ({
  key: `squad-${squad.squadId}`,
  squadId: squad.squadId,
  name: squad.name,
  subtitle: [
    squad.countryName || squad.countryCode || 'Global',
    `${squad.memberCount} members`,
  ]
    .filter(Boolean)
    .join(' / '),
  badge,
  members: squad.memberCount,
  weeklyPoints: squad.weeklyPoints,
  boostPercent: squad.communityBoostPercent,
  rankLabel: squad.rankLabel || `#${index + 1}`,
});

const cleanText = (value: unknown) => String(value ?? '').trim();

const normalizeAppStage = (payload: any): AppStage | undefined => {
  const list =
    payload?.data?.appStage ??
    payload?.data?.app_stage ??
    payload?.data?.data ??
    payload?.appStage ??
    payload?.app_stage ??
    payload;
  const firstRecord = Array.isArray(list) ? list.find(Boolean) : list;

  if (!firstRecord || typeof firstRecord !== 'object') {
    return undefined;
  }

  const title = cleanText(firstRecord.title);
  const stage = cleanText(firstRecord.stage);
  const miningRate = cleanText(
    firstRecord.miningrate ?? firstRecord.mining_rate ?? firstRecord.miningRate,
  );

  if (!title && !stage && !miningRate) {
    return undefined;
  }

  return {
    title: title || undefined,
    stage: stage || undefined,
    miningRate: miningRate || undefined,
  };
};

const formatMiningRate = (value?: string) => {
  if (!value) {
    return '';
  }

  return /brc|\/h|per hour|p\/h/i.test(value) ? value : `${value} BRC/h`;
};

const HomeOrg = () => {
  const [showPopup, setShowPopup] = useState(false);
  const [showKycWaitlistPrompt, setShowKycWaitlistPrompt] = useState(false);
  const [hasCheckedKycPrompt, setHasCheckedKycPrompt] = useState(false);
  const [isSecondaryContentReady, setIsSecondaryContentReady] = useState(false);
  const [isHomeActivityReady, setIsHomeActivityReady] = useState(false);
  const [isHomeSquadReady, setIsHomeSquadReady] = useState(false);
  const [isClaimingQuest, setIsClaimingQuest] = useState(false);
  const [isStarterQuestResolved, setIsStarterQuestResolved] = useState(false);
  const [cachedProfile, setCachedProfile] = useState<NormalizedUserProfile>();
  const [profileWaitExpired, setProfileWaitExpired] = useState(false);
  const reportedListPayloadIssues = useRef(new Set<string>());
  const {width} = useWindowDimensions();
  const {showAlert} = useThisAlert();
  const joinSquad = useJoinSquad();
  const queryClient = useQueryClient();
  const {
    data: dailyMissions,
    isLoading: isDailyMissionsLoading,
    isError: isDailyMissionsError,
    isFetching: isDailyMissionsFetching,
    refetch: refetchDailyMissions,
  } = useDailyMissions(isHomeActivityReady);
  const {
    data: gamificationSummary,
    isLoading: isGamificationLoading,
    isError: isGamificationError,
    refetch: refetchGamification,
  } = useGamificationSummary(isHomeActivityReady);
  const {data: bannerData, refetch: refetchBanners}: any = callQuery(
    'banners',
    {retry: 0, staleTime: 120000, enabled: isSecondaryContentReady},
  );
  const {
    data: walletData,
    isLoading: isWalletLoading,
    isFetching: isWalletFetching,
    refetch: refetchWallet,
  }: any = callQuery('auth/wallet', {requestTimeoutMs: 8000, retry: 0});
  const {
    data: profileData,
    isLoading: isProfileLoading,
    isFetching: isProfileFetching,
    refetch: refetchProfile,
  }: any = callQuery('auth/profile', {
    requestTimeoutMs: 8000,
    retry: 0,
    staleTime: 30000,
  });
  const {
    data: verificationData,
    isLoading: isVerificationLoading,
    isFetching: isVerificationFetching,
    refetch: refetchVerification,
  }: any = callQuery('auth/verification/status', {
    requestTimeoutMs: 8000,
    retry: 0,
    staleTime: 30000,
  });
  const {data: tasksData, refetch: refetchTasks}: any = callQuery(
    'auth/get-task',
    {
      queryFn: ({signal}: {signal?: AbortSignal}) => fetchTaskList(signal),
      retry: 0,
      staleTime: 60000,
      enabled: isHomeActivityReady,
    },
  );
  const {data: boostData, refetch: refetchBoost}: any = callQuery(
    'auth/my-boost',
    {
      retry: 0,
      staleTime: 60000,
      enabled: isHomeActivityReady,
    },
  );
  const {data: appStageData, refetch: refetchAppStage}: any = callQuery(
    'app-stage',
    {
      retry: 0,
      staleTime: 120000,
      enabled: isHomeActivityReady,
    },
  );
  const {data: onboardingQuestData, refetch: refetchOnboardingQuest}: any =
    callQuery('auth/onboarding-quest', {
      retry: 0,
      staleTime: 30000,
      enabled: isHomeActivityReady,
    });
  const {data: newsData, refetch: refetchNews}: any = callQuery('get-news', {
    retry: 0,
    staleTime: 60000,
    enabled: isSecondaryContentReady,
  });
  const {data: notificationCountData, refetch: refetchNotificationCount}: any =
    callQuery('auth/count-notification', {
      retry: 0,
      staleTime: 30000,
      enabled: isSecondaryContentReady,
    });
  const {
    data: squadData,
    refetch: refetchSquad,
    isLoading: isSquadLoading,
    isFetching: isSquadFetching,
  } = useMySquad(isHomeSquadReady);
  const {data: discoverSquads = []} = useSquadDiscovery(isHomeSquadReady);
  const {data: rankedSquads = []} = useSquadLeaderboard(isHomeSquadReady);

  useEffect(() => {
    getCachedUserProfile()
      .then(profile => {
        if (profile) {
          setCachedProfile(profile);
        }
      })
      .catch(error => {
        console.log('Unable to load cached profile', error);
      });
  }, []);

  useEffect(() => {
    if (!isProfileLoading || hasProfileIdentity(cachedProfile)) {
      setProfileWaitExpired(false);
      return;
    }

    const timeout = setTimeout(
      () => setProfileWaitExpired(true),
      HOME_PROFILE_MAX_WAIT_MS,
    );

    return () => clearTimeout(timeout);
  }, [cachedProfile, isProfileLoading]);

  useEffect(() => {
    return scheduleHomeRequestStages(
      callback => InteractionManager.runAfterInteractions(callback),
      stage => {
        if (stage === 'secondary') {
          setIsSecondaryContentReady(true);
        } else if (stage === 'activity') {
          setIsHomeActivityReady(true);
        } else {
          setIsHomeSquadReady(true);
        }
      }
    );
  }, []);

  useEffect(() => {
    const syncMessagingToken = async () => {
      try {
        const oneSignalToken = await getBlockriumPushToken();
        let firebaseToken = '';

        if (!oneSignalToken) {
          firebaseToken = await getFirebaseMessagingTokenSafely();
        }

        const deviceToken = oneSignalToken || firebaseToken;
        if (!deviceToken) {
          return;
        }

        await postApi('auth/update-device-id', {device_id: deviceToken});
      } catch (error) {
        console.error('Unable to sync messaging token', error);
      }
    };

    syncMessagingToken();
  }, []);

  const featureCards = useMemo<Feature[]>(
    () => [
      {
        key: 'learn',
        title: 'Learn & Earn',
        subtitle: 'Short lessons, XP, and safer Web3 habits.',
        icon: 'school-outline',
        onPress: () => navigate('LearnEarn'),
      },
      {
        key: 'tasks',
        title: 'Rewards',
        subtitle: 'Tasks, streaks, and starter quests.',
        icon: 'checkmark-done-outline',
        onPress: () => navigate('Tasks'),
      },
      {
        key: 'promo-code',
        title: 'Promo code',
        subtitle: 'Enter a code to claim a reward.',
        icon: 'ticket-outline',
        onPress: () => navigate('PromoCodeRedeem'),
      },
      {
        key: 'squads',
        title: 'Squads',
        subtitle: 'Invite friends and unlock team chests.',
        icon: 'account-group-outline',
        iconLibrary: 'material',
        onPress: () => navigate('Squads'),
      },
      {
        key: 'trust',
        title: 'Trust Center',
        subtitle: 'Reward, privacy, and wallet safety.',
        icon: 'shield-checkmark-outline',
        onPress: () => navigate('TrustCenter'),
      },
      {
        key: 'leaderboard',
        title: 'Leaderboard',
        subtitle: 'Top miners and your rank.',
        icon: 'trophy-outline',
        onPress: () => navigate('LeaderBoard'),
      },
      {
        key: 'referral',
        title: 'My Network',
        subtitle: 'Ping referrals and grow boosts.',
        icon: 'account-multiple-outline',
        iconLibrary: 'material',
        onPress: () => navigate('Referral'),
      },
      {
        key: 'verified-member',
        title: 'Verified Member',
        subtitle: 'Complete KYC and unlock BRC member benefits.',
        icon: 'shield-checkmark-outline',
        onPress: () => navigate('VerifiedMember'),
      },
      {
        key: 'boosts',
        title: 'Boosts',
        subtitle: 'Choose a paid mining boost.',
        icon: 'rocket-launch-outline',
        iconLibrary: 'material',
        onPress: () => navigate('BoostStore'),
      },
      {
        key: 'community',
        title: 'News',
        subtitle: 'Launches, app updates, and ecosystem notes.',
        icon: 'people-outline',
        onPress: () => navigate('Community'),
      },
      {
        key: 'alerts',
        title: 'Alerts',
        subtitle: 'Important account and app notices.',
        icon: 'notifications-outline',
        onPress: () => navigate('Notifications'),
      },
      {
        key: 'telegram',
        title: 'Telegram',
        subtitle: 'Open the Blockrium community.',
        icon: 'paper-plane-outline',
        onPress: async () => {
          const link = 'tg://resolve?domain=blockrium';
          const canOpen = await Linking.canOpenURL(link);
          await Linking.openURL(canOpen ? link : 'https://t.me/blockrium');
        },
      },
    ],
    [],
  );

  const primaryActions = useMemo<Feature[]>(
    () => [
      {
        key: 'mine',
        title: 'Mine',
        subtitle: 'Start cycle',
        icon: 'pickaxe',
        iconLibrary: 'material',
        onPress: () => navigate('Mining'),
      },
      {
        key: 'wallet',
        title: 'Wallet',
        subtitle: 'Assets',
        icon: 'wallet-outline',
        onPress: () => navigate('WalletFlow'),
      },
      {
        key: 'rewards',
        title: 'Rewards',
        subtitle: 'Tasks',
        icon: 'gift-outline',
        onPress: () => navigate('Tasks'),
      },
      {
        key: 'squad',
        title: 'Squads',
        subtitle: 'Team',
        icon: 'account-group-outline',
        iconLibrary: 'material',
        onPress: () => navigate('Squads'),
      },
    ],
    [],
  );

  const wallet = walletData?.data?.data ?? walletData?.data ?? {};
  const walletUnavailable =
    !isWalletLoading && (!walletData || walletData?.success === false);
  const boostRate = boostData?.data?.data ?? boostData?.data ?? {};
  const miningReward = Number(boostRate?.mining_reward ?? 1.5);
  const boostPercent = Number(boostRate?.boost_percent ?? 0);
  const paidBoostPercent = Number(boostRate?.paid_boost_percent ?? 0);
  const teamBoostPercent = Number(boostRate?.team_boost_percent ?? 0);
  const appStage = normalizeAppStage(appStageData);
  const appStageLabel = appStage?.title || appStage?.stage;
  const appStageDetail = formatMiningRate(appStage?.miningRate);
  const homeSquad = squadData?.squad;
  const isSquadPending = !isHomeSquadReady || isSquadLoading;
  const squadSuggestions = useMemo<HomeSquadSuggestion[]>(() => {
    const suggestions: HomeSquadSuggestion[] = [];
    const seen = new Set<string>();
    const currentSquadId = homeSquad?.id ? `squad-${homeSquad.id}` : '';
    const addSuggestion = (suggestion: HomeSquadSuggestion) => {
      if (
        !suggestion.name ||
        seen.has(suggestion.key) ||
        suggestion.key === currentSquadId
      ) {
        return;
      }
      seen.add(suggestion.key);
      suggestions.push(suggestion);
    };

    (rankedSquads.length ? rankedSquads : squadData?.leaderboard ?? []).forEach(
      (squad, index) =>
        addSuggestion(suggestionFromLeaderboard(squad, index, 'Top ranked')),
    );
    (squadData?.recommended ?? []).forEach(squad =>
      addSuggestion(suggestionFromSquad(squad, 'Suggested')),
    );
    (discoverSquads.length
      ? discoverSquads
      : squadData?.discover ?? []
    ).forEach(squad =>
      addSuggestion(
        suggestionFromSquad(squad, squad.featured ? 'Featured' : 'Discover'),
      ),
    );
    (squadData?.countryLeaderboard ?? []).forEach((squad, index) =>
      addSuggestion(suggestionFromLeaderboard(squad, index, 'Country rank')),
    );

    return suggestions.slice(0, 3);
  }, [
    homeSquad?.id,
    discoverSquads,
    rankedSquads,
    squadData?.countryLeaderboard,
    squadData?.discover,
    squadData?.leaderboard,
    squadData?.recommended,
  ]);
  const joinHomeSquad = async (squadId: number) => {
    try {
      const result = await joinSquad.mutateAsync({squad_id: squadId});
      showAlert(result.message || 'You joined the squad.', 'SUCCESS');
      await refetchSquad();
    } catch (error) {
      showAlert(
        (error as Error)?.message || 'Unable to join this squad.',
        'ERROR',
      );
    }
  };
  const squadMissions = homeSquad?.weeklyMissions?.length
    ? homeSquad.weeklyMissions
    : squadData?.weeklyMissions ?? [];
  const nextSquadMission =
    squadMissions.find(mission => !mission.claimed) ?? squadMissions[0];
  const squadProgressPercent = Math.min(
    100,
    Math.max(0, Number(homeSquad?.weeklyProgressPercent ?? 0)),
  );
  const user = useMemo(
    () => normalizeUserProfile(profileData, cachedProfile),
    [cachedProfile, profileData],
  );
  const firstName = user?.name?.split(' ')?.[0] ?? 'Miner';
  const verification: VerificationStatus =
    verificationData?.data?.data ?? verificationData?.data ?? {};
  const waitlist: VerificationWaitlist | undefined = verification.waitlist;
  const hasKycPaymentPending = hasUnpaidApprovedVerification(verification);
  const refreshKycStatusForReminder = useCallback(async () => {
    const result = await refetchVerification?.();
    const payload = result?.data?.data?.data ?? result?.data?.data;

    return Boolean(
      !result?.error &&
        result?.data?.success === true &&
        payload &&
        typeof payload === 'object' &&
        payload.success !== false &&
        Number(payload.status) !== 0,
    );
  }, [refetchVerification]);
  const kycPaymentReminder = useKycPaymentReminder(
    !isVerificationLoading && hasKycPaymentPending,
    refreshKycStatusForReminder,
  );
  const waitlistStatus = waitlist?.status ?? null;
  const hasJoinedKycWaitlist = [
    'waiting',
    'invited',
    'started',
    'converted',
  ].includes(waitlistStatus ?? '');
  const shouldShowKycWaitlist = Boolean(
    waitlist?.enabled && !verification.is_verified,
  );
  const miningBoostPercent = Number(
    verification.benefits?.mining_boost_percent ?? 10,
  );
  const isCompact = width < 380;
  const rawTasks = tasksData?.data?.data ?? tasksData?.data;
  const rawUpdates =
    newsData?.data?.userData ?? newsData?.data?.data ?? newsData?.data;
  const tasks = getArrayPreview<any>(rawTasks, 3);
  const updates = getArrayPreview<any>(rawUpdates, 3);
  const bannerImage =
    bannerData?.data?.userData?.[0]?.image ??
    bannerData?.data?.banners?.image_url ??
    bannerData?.data?.banners?.image ??
    bannerData?.data?.data?.[0]?.image;
  const isRefreshing = Boolean(
    isWalletFetching ||
      isProfileFetching ||
      isVerificationFetching ||
      isDailyMissionsFetching ||
      isSquadFetching,
  );
  const notificationCount = normalizeNotificationCount(notificationCountData);
  const notificationBadgeText =
    notificationCount > 99 ? '99+' : String(notificationCount);
  const rawOnboardingQuest =
    onboardingQuestData?.data?.data ?? onboardingQuestData?.data;
  const onboardingQuest: OnboardingQuest | undefined =
    normalizeOnboardingQuest(rawOnboardingQuest);

  useEffect(() => {
    [
      {endpoint: 'auth/get-task', response: tasksData, payload: rawTasks},
      {endpoint: 'get-news', response: newsData, payload: rawUpdates},
    ].forEach(({endpoint, response, payload}) => {
      if (response === undefined || Array.isArray(payload)) {
        return;
      }

      const payloadShape = describeArrayPayload(payload);
      const signature = `${endpoint}:${JSON.stringify(payloadShape)}`;
      if (reportedListPayloadIssues.current.has(signature)) {
        return;
      }

      reportedListPayloadIssues.current.add(signature);
      logAppIssue({
        severity: 'warning',
        source: 'app',
        action: 'home_list_payload_invalid',
        endpoint,
        method: 'GET',
        message: 'Home list response was not an array; items were skipped.',
        metadata: {payload_shape: payloadShape},
      }).catch(error => {
        console.log('Unable to report an invalid Home list response', error);
      });
    });
  }, [newsData, rawTasks, rawUpdates, tasksData]);
  const questSteps = Array.isArray(onboardingQuest?.steps)
    ? onboardingQuest?.steps ?? []
    : [];
  const firstIncompleteQuestStep = questSteps.find(step => !step.completed);
  const questRewardLabel =
    onboardingQuest?.reward_label ??
    `${Number(onboardingQuest?.reward_amount ?? 5).toFixed(2)} BRC`;
  const questProgressPercent = Number(onboardingQuest?.progress_percent ?? 0);
  const safeQuestProgressPercent = Number.isFinite(questProgressPercent)
    ? Math.min(100, Math.max(0, questProgressPercent))
    : 0;
  const shouldShowOnboardingQuest = Boolean(
    onboardingQuest && !onboardingQuest.claimed && !isStarterQuestResolved,
  );
  const missionByAction = useMemo(() => {
    const map = new Map<string, DailyMission>();
    dailyMissions?.missions?.forEach(mission => {
      if (mission.action && !map.has(mission.action)) {
        map.set(mission.action, mission);
      }
    });
    return map;
  }, [dailyMissions?.missions]);
  const taskCount = Array.isArray(tasks) ? tasks.length : 0;
  const completedTodayCount = Number(
    dailyMissions?.summary?.completedCount ?? 0,
  );
  const totalTodayCount = Number(dailyMissions?.summary?.totalCount ?? 5);
  const todayProgressPercent = Math.min(
    100,
    Math.max(0, Number(dailyMissions?.summary?.progressPercent ?? 0)),
  );
  const todayChecklist = useMemo<TodayChecklistItem[]>(
    () => [
      {
        key: 'mine',
        title: 'Start mining',
        subtitle:
          missionByAction.get('mining')?.subtitle ||
          `${
            Number.isFinite(miningReward) ? miningReward.toFixed(2) : '1.50'
          } BRC cycle reward`,
        icon: 'pickaxe',
        done: missionByAction.get('mining')?.status === 'completed',
        actionLabel: 'Mine',
        onPress: () => navigate('Mining'),
      },
      {
        key: 'streak',
        title: 'Claim streak',
        subtitle:
          missionByAction.get('streak')?.subtitle ||
          'Keep the daily reward calendar moving.',
        icon: 'calendar-check-outline',
        done: missionByAction.get('streak')?.status === 'completed',
        actionLabel: 'Claim',
        onPress: () => navigate('Mining'),
      },
      {
        key: 'tasks',
        title: 'Finish reward task',
        subtitle:
          taskCount > 0
            ? `${taskCount} tasks loaded today`
            : 'Open tasks and claim verified rewards.',
        icon: 'check-decagram-outline',
        done: missionByAction.get('tasks')?.status === 'completed',
        actionLabel: 'Tasks',
        onPress: () => navigate('Tasks'),
      },
      {
        key: 'squad',
        title: homeSquad ? 'Help squad' : 'Join squad',
        subtitle: homeSquad
          ? nextSquadMission?.title ||
            `${Math.round(squadProgressPercent)}% weekly progress`
          : 'Team activity unlocks weekly chests.',
        icon: 'account-group-outline',
        done: Boolean(homeSquad && squadProgressPercent >= 100),
        actionLabel: 'Squads',
        onPress: () => navigate('Squads'),
      },
      {
        key: 'wallet',
        title: 'Review wallet',
        subtitle: `${formatTokenValue(wallet?.wallet_balance)} available`,
        icon: 'wallet-outline',
        done: missionByAction.get('wallet')?.status === 'completed',
        actionLabel: 'Wallet',
        onPress: () => navigate('WalletFlow'),
      },
    ],
    [
      homeSquad,
      miningReward,
      missionByAction,
      nextSquadMission?.title,
      squadProgressPercent,
      taskCount,
      wallet?.wallet_balance,
    ],
  );

  useEffect(() => {
    if (onboardingQuest?.claimed) {
      setIsStarterQuestResolved(true);
    }
  }, [onboardingQuest?.claimed]);

  useEffect(() => {
    if (
      hasCheckedKycPrompt ||
      isVerificationLoading ||
      !shouldShowKycWaitlist ||
      hasJoinedKycWaitlist
    ) {
      return;
    }

    let isMounted = true;
    AsyncStorage.getItem(KYC_WAITLIST_PROMPT_SEEN_KEY)
      .then(value => {
        if (!isMounted) {
          return;
        }

        setHasCheckedKycPrompt(true);
        if (!value) {
          setShowKycWaitlistPrompt(true);
          AsyncStorage.setItem(KYC_WAITLIST_PROMPT_SEEN_KEY, '1').catch(error =>
            console.warn('Unable to remember KYC prompt state', error),
          );
        }
      })
      .catch(error => {
        console.warn('Unable to read KYC prompt state', error);
        if (isMounted) {
          setHasCheckedKycPrompt(true);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [
    hasCheckedKycPrompt,
    hasJoinedKycWaitlist,
    isVerificationLoading,
    shouldShowKycWaitlist,
  ]);

  const openKycWaitlistPrompt = () => {
    setShowKycWaitlistPrompt(true);
    AsyncStorage.setItem(KYC_WAITLIST_PROMPT_SEEN_KEY, '1').catch(error =>
      console.warn('Unable to remember KYC prompt state', error),
    );
  };

  const handleKycWaitlistJoined = () => {
    refetchVerification?.();
    showAlert(
      'Your place is reserved. We will notify you when your country batch opens.',
      'SUCCESS',
    );
  };

  const refreshHome = () => {
    refetchWallet?.();
    refetchProfile?.();
    refetchVerification?.();
    refetchBanners?.();
    refetchTasks?.();
    refetchBoost?.();
    refetchAppStage?.();
    refetchNews?.();
    refetchNotificationCount?.();
    refetchOnboardingQuest?.();
    refetchDailyMissions?.();
    refetchGamification?.();
    refetchSquad?.();
    queryClient.invalidateQueries(partnerCampaignQueryKey);
  };

  const openMission = (mission: DailyMission) => {
    switch (mission.action) {
      case 'tasks':
        navigate('Tasks');
        break;
      case 'referral':
        navigate('Referral');
        break;
      case 'profile':
        navigate('Profile');
        break;
      case 'wallet':
        navigate('Wallet');
        break;
      case 'learn':
        navigate('LearnEarn');
        break;
      case 'streak':
      case 'mining':
      default:
        navigate('Mining');
        break;
    }
  };

  const openQuestStep = (step?: OnboardingQuestStep) => {
    switch (step?.action) {
      case 'tasks':
        navigate('Tasks');
        break;
      case 'referral':
        navigate('Referral');
        break;
      case 'profile':
        navigate('ProfileFlow');
        break;
      case 'learn':
        navigate('LearnEarn');
        break;
      case 'streak':
      case 'mining':
      default:
        navigate('Mining');
        break;
    }
  };

  const claimOnboardingQuest = async () => {
    if (isClaimingQuest) {
      return;
    }

    setIsClaimingQuest(true);
    try {
      await showOptionalActionAd(18000);

      const response = await postApi('auth/onboarding-quest/claim', {});
      const payload = response?.data;

      if (!response.success || payload?.status === 0) {
        const message =
          response.error ||
          payload?.message ||
          'Unable to claim starter quest reward.';

        if (String(message).toLowerCase().includes('already claimed')) {
          setIsStarterQuestResolved(true);
          refetchOnboardingQuest?.();
          refetchWallet?.();
          await invalidateEngagementQueries(queryClient, {
            wallet: true,
            onboardingQuest: true,
          });
          showAlert('Starter quest reward was already claimed.', 'INFO');
          return;
        }

        showAlert(message, 'ERROR');
        return;
      }

      showAlert(payload?.message || 'Starter quest reward claimed.', 'SUCCESS');
      setIsStarterQuestResolved(true);
      refetchOnboardingQuest?.();
      refetchWallet?.();
      await invalidateEngagementQueries(queryClient, {
        wallet: true,
        onboardingQuest: true,
      });
    } catch (error) {
      console.error('Error claiming starter quest', error);
      showAlert(
        'Starter quest reward could not be claimed right now.',
        'ERROR',
      );
    } finally {
      setIsClaimingQuest(false);
    }
  };

  useEffect(() => {
    if (hasProfileIdentity(user)) {
      cacheUserProfile(user).catch(cacheError => {
        console.log('Unable to cache home profile', cacheError);
      });
      identifyBlockriumNotificationUser(user).catch(notificationError => {
        console.log(
          'Unable to identify OneSignal home user',
          notificationError,
        );
      });
      requestBlockriumNotificationPermission().catch(notificationError => {
        console.log(
          'Unable to request OneSignal notification permission',
          notificationError,
        );
      });
    }
  }, [user]);

  if (
    shouldWaitForHomeProfile(
      isProfileLoading,
      cachedProfile,
      profileWaitExpired,
    )
  ) {
    return (
      <ScreenLoader
        title="Loading overview"
        subtitle="Pulling balances, profile details, and partner placements."
      />
    );
  }

  return (
    <AppScreen
      scrollable
      refreshing={isRefreshing}
      onRefresh={refreshHome}
      contentContainerStyle={styles.contentContainer}>
      <View style={styles.topBar}>
        <View style={styles.brandRow}>
          <FastImage
            source={images.logo}
            style={styles.logo}
            resizeMode="contain"
          />
          <View style={styles.brandText}>
            <Text style={styles.brandName} numberOfLines={1}>
              Blockrium
            </Text>
            <Text style={styles.brandTag} numberOfLines={1}>
              Web3 Network
            </Text>
          </View>
        </View>
        <View style={styles.topBarActions}>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Open notifications"
            onPress={() => navigate('Notifications')}
            style={styles.notificationButton}>
            <Ionicons
              name="notifications-outline"
              size={20}
              color={theme.colors.text}
            />
            {notificationCount > 0 ? (
              <View style={styles.notificationBadge}>
                <Text style={styles.notificationBadgeText}>
                  {notificationBadgeText}
                </Text>
              </View>
            ) : null}
          </Pressable>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Open app search"
            onPress={() => navigate('AppSearch')}
            style={styles.notificationButton}>
            <Ionicons
              name="search-outline"
              size={20}
              color={theme.colors.text}
            />
          </Pressable>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Open app settings"
            onPress={() => navigate('AppSettings')}
            style={styles.notificationButton}>
            <Ionicons
              name="settings-outline"
              size={20}
              color={theme.colors.text}
            />
          </Pressable>
        </View>
      </View>

      <CardEntrance>
        <GlassCard style={styles.dashboardCard}>
          <View style={styles.dashboardHeader}>
            <View style={styles.dashboardCopy}>
              <SectionEyebrow text="Today" />
              <Text style={styles.heroTitle} numberOfLines={2}>
                Hi, {firstName}
              </Text>
              <Text style={styles.heroText} numberOfLines={2}>
                Wallet, mining, missions, and squads in one daily command
                center.
              </Text>
            </View>
            <View style={styles.dashboardBadge}>
              <Ionicons
                name="flash-outline"
                size={18}
                color={theme.colors.primary}
              />
              <Text style={styles.dashboardBadgeText}>Live</Text>
            </View>
          </View>
          <View style={styles.heroStatusRow}>
            <View style={styles.heroStatusChip}>
              <View style={styles.heroStatusDot} />
              <Text style={styles.heroStatusText}>Activity live</Text>
            </View>
            <View style={styles.heroStatusChip}>
              <Ionicons
                name="shield-checkmark-outline"
                size={14}
                color={theme.colors.primary}
              />
              <Text style={styles.heroStatusText}>
                {isWalletLoading
                  ? 'Syncing wallet'
                  : walletUnavailable
                  ? 'Wallet unavailable'
                  : 'Wallet synced'}
              </Text>
            </View>
          </View>
          <View style={styles.balanceStrip}>
            {[
              {
                label: 'Wallet',
                value: isWalletLoading
                  ? '...'
                  : walletUnavailable
                  ? '--'
                  : formatTokenValue(wallet?.wallet_balance),
                icon: 'wallet-outline',
                color: theme.colors.primary,
              },
              {
                label: 'Verified',
                value: isWalletLoading
                  ? '...'
                  : walletUnavailable
                  ? '--'
                  : formatTokenValue(wallet?.verififiedBalance),
                icon: 'shield-checkmark-outline',
                color: theme.colors.accent,
              },
              {
                label: 'Lockup',
                value: isWalletLoading
                  ? '...'
                  : walletUnavailable
                  ? '--'
                  : formatTokenValue(wallet?.unVerifiedBalance),
                icon: 'lock-closed-outline',
                color: theme.colors.accentWarm,
              },
            ].map(item => (
              <View key={item.label} style={styles.balanceCard}>
                <View
                  style={[
                    styles.balanceIcon,
                    {backgroundColor: `${item.color}24`},
                  ]}>
                  <Ionicons
                    name={item.icon as any}
                    size={14}
                    color={item.color}
                  />
                </View>
                <Text style={styles.balanceLabel}>{item.label}</Text>
                <Text style={styles.balanceValue} numberOfLines={1}>
                  {item.value}
                </Text>
                <Text style={styles.balanceUnit}>BRC</Text>
              </View>
            ))}
          </View>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Explore mining boost plans"
            testID="home-boost-store-cta"
            onPress={() => navigate('BoostStore')}
            style={({pressed}) => [
              styles.featuredBoost,
              pressed && styles.featuredBoostPressed,
            ]}>
            <View style={styles.featuredBoostIcon}>
              <MaterialCommunityIcons
                name="rocket-launch-outline"
                size={22}
                color={theme.colors.background}
              />
            </View>
            <View style={styles.featuredBoostCopy}>
              <SectionEyebrow
                text={paidBoostPercent > 0 ? 'BOOST ACTIVE' : 'MINING BOOST'}
              />
              <Text style={styles.featuredBoostTitle}>
                {paidBoostPercent > 0
                  ? `Your +${paidBoostPercent}% boost is active`
                  : 'Increase your mining rate'}
              </Text>
              <Text style={styles.featuredBoostSubtitle} numberOfLines={2}>
                {paidBoostPercent > 0
                  ? 'View your plan and subscription status.'
                  : 'Compare plans and see the boost applied to each cycle.'}
              </Text>
            </View>
            <Ionicons
              name="arrow-forward"
              size={19}
              color={theme.colors.primary}
            />
          </Pressable>
          <View style={styles.quickActionGrid}>
            {primaryActions.map(action => (
              <Pressable
                key={action.key}
                accessibilityRole="button"
                accessibilityLabel={`${action.title}. ${action.subtitle}`}
                onPress={action.onPress}
                style={({pressed}) => [
                  styles.quickAction,
                  pressed ? styles.quickActionPressed : null,
                ]}>
                <View style={styles.quickActionIcon}>
                  {action.iconLibrary === 'material' ? (
                    <MaterialCommunityIcons
                      name={action.icon}
                      size={20}
                      color={theme.colors.primary}
                    />
                  ) : (
                    <Ionicons
                      name={action.icon as any}
                      size={20}
                      color={theme.colors.primary}
                    />
                  )}
                </View>
                <Text style={styles.quickActionTitle} numberOfLines={1}>
                  {action.title}
                </Text>
                <Text style={styles.quickActionSubtitle} numberOfLines={1}>
                  {action.subtitle}
                </Text>
              </Pressable>
            ))}
          </View>
        </GlassCard>
      </CardEntrance>

      {shouldShowKycWaitlist ? (
        <CardEntrance delay={12}>
          <GlassCard style={styles.kycWaitlistCard}>
            <View style={styles.kycWaitlistHeader}>
              <View style={styles.kycWaitlistIcon}>
                <Ionicons
                  name="shield-checkmark-outline"
                  size={22}
                  color={theme.colors.primary}
                />
              </View>
              <View style={styles.kycWaitlistCopy}>
                <View style={styles.kycWaitlistEyebrowRow}>
                  <Text style={styles.kycWaitlistEyebrow}>
                    KYC EARLY ACCESS
                  </Text>
                  <View style={styles.kycWaitlistLivePill}>
                    <View style={styles.kycWaitlistLiveDot} />
                    <Text style={styles.kycWaitlistLiveText}>Limited</Text>
                  </View>
                </View>
                <Text style={styles.kycWaitlistTitle}>
                  Be first in your country batch
                </Text>
                <Text style={styles.kycWaitlistSubtitle} numberOfLines={2}>
                  {waitlist?.message ||
                    'Reserve your place before verification opens in your country.'}
                </Text>
              </View>
            </View>
            <View style={styles.kycWaitlistBenefitRow}>
              <View style={styles.kycWaitlistBenefit}>
                <Ionicons
                  name="flash-outline"
                  size={14}
                  color={theme.colors.primary}
                />
                <Text style={styles.kycWaitlistBenefitText}>
                  {miningBoostPercent}% mining boost
                </Text>
              </View>
              <View style={styles.kycWaitlistBenefit}>
                <Ionicons
                  name="notifications-outline"
                  size={14}
                  color={theme.colors.accent}
                />
                <Text style={styles.kycWaitlistBenefitText}>
                  Priority invite
                </Text>
              </View>
              {waitlist?.country_name ? (
                <View style={styles.kycWaitlistBenefit}>
                  <Ionicons
                    name="globe-outline"
                    size={14}
                    color={theme.colors.accentWarm}
                  />
                  <Text style={styles.kycWaitlistBenefitText} numberOfLines={1}>
                    {waitlist.country_name}
                  </Text>
                </View>
              ) : null}
            </View>
            {hasJoinedKycWaitlist ? (
              <View style={styles.kycQueueRow}>
                <View>
                  <Text style={styles.kycQueueLabel}>YOUR QUEUE POSITION</Text>
                  <Text style={styles.kycQueueValue}>
                    {waitlist?.position ? `#${waitlist.position}` : 'Reserved'}
                  </Text>
                </View>
                <Pressable
                  onPress={openKycWaitlistPrompt}
                  style={styles.kycQueueAction}>
                  <Text style={styles.kycQueueActionText}>View status</Text>
                  <Ionicons
                    name="arrow-forward"
                    size={14}
                    color={theme.colors.primary}
                  />
                </Pressable>
              </View>
            ) : (
              <PrimaryButton
                label="Join the KYC waiting list"
                icon="arrow-right"
                onPress={openKycWaitlistPrompt}
                style={styles.kycWaitlistButton}
              />
            )}
          </GlassCard>
        </CardEntrance>
      ) : null}

      <CardEntrance delay={20}>
        <GlassCard style={styles.todayCard}>
          <View style={styles.todayHeader}>
            <View style={styles.todayHeaderCopy}>
              <SectionEyebrow text="Today Checklist" />
              <Text style={styles.todayTitle}>Complete the daily loop</Text>
              <Text style={styles.todaySubtitle}>
                Mine, streak, tasks, squad, and wallet review in one flow.
              </Text>
            </View>
            <View style={styles.todayScorePill}>
              <Text style={styles.todayScoreValue}>
                {completedTodayCount}/{Math.max(1, totalTodayCount)}
              </Text>
              <Text style={styles.todayScoreLabel}>done</Text>
            </View>
          </View>
          <View style={styles.todayProgressTrack}>
            <View
              style={[
                styles.todayProgressFill,
                {width: `${todayProgressPercent}%`},
              ]}
            />
          </View>
          <View style={styles.todayList}>
            {todayChecklist.map(item => (
              <Pressable
                key={item.key}
                accessibilityRole="button"
                accessibilityLabel={`${item.title}. ${item.actionLabel}`}
                onPress={item.onPress}
                style={({pressed}) => [
                  styles.todayRow,
                  item.done && styles.todayRowDone,
                  pressed && styles.quickActionPressed,
                ]}>
                <View
                  style={[styles.todayIcon, item.done && styles.todayIconDone]}>
                  <MaterialCommunityIcons
                    name={item.done ? 'check' : item.icon}
                    size={18}
                    color={
                      item.done ? theme.colors.background : theme.colors.primary
                    }
                  />
                </View>
                <View style={styles.todayCopy}>
                  <Text style={styles.todayItemTitle} numberOfLines={1}>
                    {item.title}
                  </Text>
                  <Text style={styles.todayItemSubtitle} numberOfLines={1}>
                    {item.subtitle}
                  </Text>
                </View>
                <View style={styles.todayActionPill}>
                  <Text style={styles.todayActionText}>
                    {item.done ? 'Done' : item.actionLabel}
                  </Text>
                  {!item.done ? (
                    <Ionicons
                      name="arrow-forward"
                      size={13}
                      color={theme.colors.primary}
                    />
                  ) : null}
                </View>
              </Pressable>
            ))}
          </View>
        </GlassCard>
      </CardEntrance>

      <CardEntrance delay={30}>
        <LevelProgressCard
          compact
          summary={gamificationSummary}
          loading={isGamificationLoading}
          error={isGamificationError}
          onRetry={() => refetchGamification()}
        />
      </CardEntrance>

      <CardEntrance delay={45}>
        <DailyMissionHub
          data={dailyMissions}
          loading={isDailyMissionsLoading}
          error={isDailyMissionsError}
          fetching={isDailyMissionsFetching}
          maxItems={isCompact ? 5 : 7}
          onRetry={() => refetchDailyMissions()}
          onMissionPress={openMission}
        />
      </CardEntrance>

      <PartnerCampaignsSection
        enabled={isSecondaryContentReady}
        delay={48}
        onWalletRefresh={refetchWallet}
        onNotificationsRefresh={refetchNotificationCount}
        onRedeemPromo={() => navigate('PromoCodeRedeem')}
      />

      <CardEntrance delay={50}>
        <View style={styles.sectionHeader}>
          <View>
            <Text style={styles.sectionTitle}>Discover</Text>
            <Text style={styles.sectionSubtitle}>
              New rewards, squads, and announcements are now easier to reach.
            </Text>
          </View>
        </View>
        <View style={styles.discoverRail}>
          {featureCards.slice(0, 3).map(feature => (
            <Pressable
              key={feature.key}
              onPress={feature.onPress}
              style={styles.discoverCard}>
              <View style={styles.discoverIconShell}>
                {feature.iconLibrary === 'material' ? (
                  <MaterialCommunityIcons
                    name={feature.icon}
                    size={21}
                    color={theme.colors.background}
                  />
                ) : (
                  <Ionicons
                    name={feature.icon as any}
                    size={21}
                    color={theme.colors.background}
                  />
                )}
              </View>
              <Text style={styles.discoverTitle} numberOfLines={1}>
                {feature.title}
              </Text>
              <Text style={styles.discoverSubtitle} numberOfLines={2}>
                {feature.subtitle}
              </Text>
            </Pressable>
          ))}
        </View>
      </CardEntrance>

      <CardEntrance delay={55}>
        <View style={styles.sectionHeader}>
          <View>
            <Text style={styles.sectionTitle}>News & launches</Text>
            <Text style={styles.sectionSubtitle}>
              Latest Blockrium updates, campaigns, and product notes.
            </Text>
          </View>
        </View>

        {updates.length === 0 ? (
          <GlassCard>
            <Text style={styles.emptyTitle}>No announcements yet</Text>
            <Text style={styles.emptyCopy}>
              App announcements will appear here once the feed responds.
            </Text>
          </GlassCard>
        ) : null}

        {updates.slice(0, 2).map((item: any, index: number) => (
          <Pressable
            key={String(item?.id ?? index)}
            onPress={() => {
              if (item?.link) {
                Linking.openURL(item.link);
              }
            }}>
            <GlassCard style={styles.newsCard}>
              <View style={styles.newsIconShell}>
                <Ionicons
                  name="newspaper-outline"
                  size={18}
                  color={theme.colors.primary}
                />
              </View>
              <View style={styles.newsTextWrap}>
                <SectionEyebrow text={item?.categories ?? 'Announcement'} />
                <Text style={styles.newsTitle}>
                  {item?.title ?? 'Blockrium update'}
                </Text>
                <Text style={styles.newsCopy} numberOfLines={2}>
                  {item?.description ??
                    'Latest Blockrium update from the news feed.'}
                </Text>
              </View>
              <Ionicons
                name="chevron-forward"
                size={18}
                color={theme.colors.textSoft}
              />
            </GlassCard>
          </Pressable>
        ))}
      </CardEntrance>

      <CardEntrance delay={60}>
        <GlassCard style={styles.miningStatusCard}>
          <View style={styles.statusHeader}>
            <View>
              <SectionEyebrow text="Mining Status" />
              <Text style={styles.statusTitle}>Daily mining is ready</Text>
            </View>
            <View style={styles.liveBadge}>
              <Text style={styles.liveBadgeText}>Live</Text>
            </View>
          </View>
          <Text style={styles.statusCopy}>
            {appStageLabel
              ? `${appStageLabel}${
                  appStageDetail ? ` · ${appStageDetail}` : ''
                }`
              : 'Open Mining to start your cycle, claim streak rewards, and complete missions.'}
          </Text>
          <View style={styles.compactStatsRow}>
            <View style={styles.compactStat}>
              <Text style={styles.compactStatValue}>
                {Number.isFinite(miningReward)
                  ? miningReward.toFixed(2)
                  : '1.50'}
              </Text>
              <Text style={styles.compactStatLabel}>BRC/cycle</Text>
            </View>
            <View style={styles.compactStat}>
              <Text style={styles.compactStatValue}>
                {Number.isFinite(boostPercent)
                  ? `${boostPercent.toFixed(0)}%`
                  : '0%'}
              </Text>
              <Text style={styles.compactStatLabel}>Boost</Text>
            </View>
            <View style={styles.compactStat}>
              <Text style={styles.compactStatValue}>
                {Number.isFinite(teamBoostPercent)
                  ? `${teamBoostPercent.toFixed(0)}%`
                  : '0%'}
              </Text>
              <Text style={styles.compactStatLabel}>Network</Text>
            </View>
            {appStageLabel ? (
              <View style={[styles.compactStat, styles.compactStatWide]}>
                <Text style={styles.compactStatValue} numberOfLines={1}>
                  {appStage?.stage || 'Active'}
                </Text>
                <Text style={styles.compactStatLabel} numberOfLines={1}>
                  {appStageDetail || 'App stage'}
                </Text>
              </View>
            ) : null}
          </View>
        </GlassCard>
      </CardEntrance>

      <CardEntrance delay={65}>
        <Pressable onPress={() => navigate('Squads')}>
          <GlassCard style={styles.squadCommandCard}>
            <View style={styles.squadCommandHeader}>
              <View style={styles.squadCommandIcon}>
                <MaterialCommunityIcons
                  name="account-group-outline"
                  size={22}
                  color={theme.colors.background}
                />
              </View>
              <View style={styles.squadCommandCopy}>
                <SectionEyebrow text="Squad command" />
                <Text style={styles.squadCommandTitle} numberOfLines={1}>
                  {homeSquad
                    ? homeSquad.name
                    : isSquadPending
                      ? 'Loading your squad'
                      : 'Join a weekly squad'}
                </Text>
                <Text style={styles.squadCommandText} numberOfLines={2}>
                  {homeSquad
                    ? nextSquadMission?.title ||
                      'Build weekly points and unlock your squad chest.'
                    : isSquadPending
                      ? 'Syncing your membership and weekly stats.'
                      : 'Squads are unlimited now. Invite players, earn weekly points, and climb country ranks.'}
                </Text>
              </View>
              <Ionicons
                name="chevron-forward"
                size={18}
                color={theme.colors.textSoft}
              />
            </View>
            {homeSquad ? (
              <>
                {!homeSquad.statsDeferred ? (
                  <View style={styles.squadProgressTrack}>
                    <View
                      style={[
                        styles.squadProgressFill,
                        {width: `${squadProgressPercent}%`},
                      ]}
                    />
                  </View>
                ) : (
                  <Text style={styles.squadCommandText}>
                    Your squad is active. Weekly stats are temporarily
                    unavailable.
                  </Text>
                )}
                <View style={styles.squadCommandStats}>
                  <View style={styles.squadCommandStat}>
                    <Text style={styles.squadCommandStatValue}>
                      {homeSquad.statsDeferred
                        ? homeSquad.memberCount.toLocaleString()
                        : Math.round(homeSquad.weeklyPoints).toLocaleString()}
                    </Text>
                    <Text style={styles.squadCommandStatLabel}>
                      {homeSquad.statsDeferred ? 'members' : 'weekly pts'}
                    </Text>
                  </View>
                  {!homeSquad.statsDeferred ? (
                    <>
                      <View style={styles.squadCommandStat}>
                        <Text style={styles.squadCommandStatValue}>
                          {homeSquad.communityBoostPercent}%
                        </Text>
                        <Text style={styles.squadCommandStatLabel}>
                          squad boost
                        </Text>
                      </View>
                      <View style={styles.squadCommandStat}>
                        <Text style={styles.squadCommandStatValue}>
                          {homeSquad.countryRank
                            ? `#${homeSquad.countryRank}`
                            : homeSquad.capacityLabel}
                        </Text>
                        <Text style={styles.squadCommandStatLabel}>
                          {homeSquad.countryRank ? 'country rank' : 'capacity'}
                        </Text>
                      </View>
                    </>
                  ) : null}
                </View>
              </>
            ) : isSquadPending ? null : (
              <View style={styles.squadEmptyActions}>
                <Text style={styles.squadEmptyActionText}>Open squads</Text>
                <MaterialCommunityIcons
                  name="share-variant-outline"
                  size={16}
                  color={theme.colors.primary}
                />
              </View>
            )}
          </GlassCard>
        </Pressable>
      </CardEntrance>

      {squadSuggestions.length > 0 ? (
        <CardEntrance delay={68}>
          <View style={styles.sectionHeader}>
            <View>
              <Text style={styles.sectionTitle}>Top Squads</Text>
              <Text style={styles.sectionSubtitle}>
                Join a growing community and climb the weekly rankings together.
              </Text>
            </View>
            <Pressable onPress={() => navigate('Squads')}>
              <Text style={styles.sectionLink}>Explore</Text>
            </Pressable>
          </View>
          <GlassCard style={styles.squadSuggestionsCard}>
            {squadSuggestions.map((squad, index) => (
              <Pressable
                key={squad.key}
                onPress={() =>
                  homeSquad ? navigate('Squads') : joinHomeSquad(squad.squadId)
                }
                style={({pressed}) => [
                  styles.squadSuggestionRow,
                  index === squadSuggestions.length - 1 &&
                    styles.squadSuggestionRowLast,
                  joinSquad.isLoading &&
                    !homeSquad &&
                    styles.squadSuggestionRowDisabled,
                  pressed && styles.quickActionPressed,
                ]}>
                <View style={styles.squadSuggestionAvatar}>
                  <Text style={styles.squadSuggestionAvatarText}>
                    {squadInitial(squad.name)}
                  </Text>
                </View>
                <View style={styles.squadSuggestionCopy}>
                  <View style={styles.squadSuggestionTitleRow}>
                    <Text style={styles.squadSuggestionName} numberOfLines={1}>
                      {squad.name}
                    </Text>
                    <View
                      style={[
                        styles.squadSuggestionBadge,
                        {
                          backgroundColor: `${
                            getSquadMemberTier(squad.members).color
                          }18`,
                          borderColor: `${
                            getSquadMemberTier(squad.members).color
                          }44`,
                        },
                      ]}>
                      <MaterialCommunityIcons
                        name={getSquadMemberTier(squad.members).icon}
                        size={12}
                        color={getSquadMemberTier(squad.members).color}
                      />
                      <Text
                        style={[
                          styles.squadSuggestionBadgeText,
                          {color: getSquadMemberTier(squad.members).color},
                        ]}>
                        {getSquadMemberTier(squad.members).label}
                      </Text>
                    </View>
                  </View>
                  <Text
                    style={styles.squadSuggestionSubtitle}
                    numberOfLines={1}>
                    {squad.subtitle || squad.badge}
                  </Text>
                  <View style={styles.squadSuggestionStats}>
                    <Text style={styles.squadSuggestionStat}>
                      {formatCompactNumber(squad.members)} members
                    </Text>
                    <Text style={styles.squadSuggestionDivider}>/</Text>
                    <Text style={styles.squadSuggestionStat}>
                      {formatCompactNumber(squad.weeklyPoints)} pts
                    </Text>
                    <Text style={styles.squadSuggestionDivider}>/</Text>
                    <Text style={styles.squadSuggestionStat}>
                      {Math.round(squad.boostPercent)}% boost
                    </Text>
                  </View>
                </View>
                <View style={styles.squadSuggestionAction}>
                  <Text style={styles.squadSuggestionActionText}>
                    {homeSquad
                      ? 'View'
                      : joinSquad.isLoading
                      ? 'Joining'
                      : 'Join'}
                  </Text>
                </View>
              </Pressable>
            ))}
          </GlassCard>
        </CardEntrance>
      ) : null}

      {shouldShowOnboardingQuest && onboardingQuest ? (
        <CardEntrance delay={70}>
          <GlassCard style={styles.questCard}>
            <View style={styles.questHeader}>
              <View style={styles.questHeaderCopy}>
                <SectionEyebrow text="Starter Quest" />
                <Text style={styles.questTitle}>{onboardingQuest.title}</Text>
                <Text style={styles.questText}>
                  {onboardingQuest.subtitle ||
                    'Finish the first actions and unlock your launch reward.'}
                </Text>
              </View>
              <View style={styles.questRewardBadge}>
                <Text style={styles.questRewardValue}>{questRewardLabel}</Text>
                <Text style={styles.questRewardLabel}>reward</Text>
              </View>
            </View>
            <View style={styles.questProgressTrack}>
              <View
                style={[
                  styles.questProgressFill,
                  {width: `${safeQuestProgressPercent}%`},
                ]}
              />
            </View>
            <Text style={styles.questProgressText}>
              {onboardingQuest.completed_count}/{onboardingQuest.total_steps}{' '}
              completed
            </Text>
            <View style={styles.questStepList}>
              {questSteps.map(step => (
                <Pressable
                  key={step.key}
                  disabled={step.completed}
                  onPress={() => openQuestStep(step)}
                  style={styles.questStepRow}>
                  <View
                    style={[
                      styles.questStepIcon,
                      step.completed && styles.questStepIconDone,
                    ]}>
                    <Ionicons
                      name={step.completed ? 'checkmark' : 'ellipse-outline'}
                      size={16}
                      color={
                        step.completed
                          ? theme.colors.background
                          : theme.colors.primary
                      }
                    />
                  </View>
                  <View style={styles.questStepCopy}>
                    <Text style={styles.questStepTitle}>{step.title}</Text>
                    {step.description ? (
                      <Text style={styles.questStepDescription}>
                        {step.description}
                      </Text>
                    ) : null}
                  </View>
                  {!step.completed ? (
                    <Ionicons
                      name="chevron-forward"
                      size={18}
                      color={theme.colors.textSoft}
                    />
                  ) : null}
                </Pressable>
              ))}
            </View>
            {onboardingQuest.can_claim ? (
              <PrimaryButton
                label={`Claim ${questRewardLabel}`}
                icon="gift-outline"
                loading={isClaimingQuest}
                disabled={isClaimingQuest}
                onPress={claimOnboardingQuest}
              />
            ) : (
              <SecondaryButton
                label="Continue quest"
                icon="arrow-right"
                onPress={() => openQuestStep(firstIncompleteQuestStep)}
              />
            )}
          </GlassCard>
        </CardEntrance>
      ) : null}

      {bannerImage ? (
        <Pressable
          onPress={() =>
            Linking.openURL(
              'https://www.mexc.com/en-IN/landings/Blockrium?inviteCode=mexc-2ffmJ',
            )
          }>
          <GlassCard style={styles.bannerCard}>
            <View style={styles.partnerHeader}>
              <View>
                <SectionEyebrow text="Partner" />
                <Text style={styles.partnerTitle}>Reward sponsor</Text>
              </View>
              <View style={styles.partnerBadge}>
                <Text style={styles.partnerBadgeText}>Ad</Text>
              </View>
            </View>
            <FastImage
              source={{uri: bannerImage}}
              style={styles.remoteBanner}
              resizeMode="cover"
            />
            <AppodealBannerSlot placement="home" />
          </GlassCard>
        </Pressable>
      ) : null}

      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>More actions</Text>
      </View>

      <View style={styles.grid}>
        {featureCards.slice(3, 9).map(feature => (
          <Pressable
            key={feature.key}
            onPress={feature.onPress}
            style={styles.gridItem}>
            <GlassCard style={styles.featureCard}>
              <View style={styles.featureIconShell}>
                {feature.iconLibrary === 'material' ? (
                  <MaterialCommunityIcons
                    name={feature.icon}
                    size={22}
                    color={theme.colors.primary}
                  />
                ) : (
                  <Ionicons
                    name={feature.icon as any}
                    size={22}
                    color={theme.colors.primary}
                  />
                )}
              </View>
              <Text style={styles.featureTitle}>{feature.title}</Text>
              <Text style={styles.featureSubtitle}>{feature.subtitle}</Text>
            </GlassCard>
          </Pressable>
        ))}
      </View>

      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Rewards today</Text>
        <Pressable onPress={() => navigate('Tasks')}>
          <Text style={styles.sectionLink}>See all</Text>
        </Pressable>
      </View>

      {tasks.length === 0 ? (
        <GlassCard>
          <Text style={styles.emptyTitle}>No tasks available</Text>
          <Text style={styles.emptyCopy}>
            New reward tasks will appear here as soon as the backend sends them.
          </Text>
        </GlassCard>
      ) : null}

      {tasks.map((task: any, index: number) => (
        <Pressable
          key={String(task?.id ?? index)}
          onPress={() => navigate('Tasks')}>
          <GlassCard style={styles.activityCard}>
            <View style={styles.activityIcon}>
              <Ionicons
                name="flash-outline"
                size={18}
                color={theme.colors.primary}
              />
            </View>
            <View style={styles.activityCopy}>
              <Text style={styles.activityTitle}>
                {task?.task_name ?? 'Daily reward task'}
              </Text>
              <Text style={styles.activitySubtitle}>
                +{task?.coin_reward ?? 0} BRC reward
              </Text>
            </View>
            <Ionicons
              name="chevron-forward"
              size={18}
              color={theme.colors.textSoft}
            />
          </GlassCard>
        </Pressable>
      ))}

      <AnimatedPopup
        showPopup={showPopup}
        onClose={() => setShowPopup(false)}
      />
      <KycWaitlistPrompt
        visible={showKycWaitlistPrompt && shouldShowKycWaitlist}
        waitlist={waitlist}
        miningBoostPercent={miningBoostPercent}
        onClose={() => setShowKycWaitlistPrompt(false)}
        onJoined={handleKycWaitlistJoined}
        onError={message => showAlert(message, 'ERROR')}
      />
      <KycPaymentReminder
        visible={kycPaymentReminder.visible}
        onClose={kycPaymentReminder.dismiss}
        onPayNow={() => {
          kycPaymentReminder.dismiss();
          navigate('VerifiedMember');
        }}
      />
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {
    paddingBottom: 126,
    paddingTop: 6,
  },
  topBar: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 14,
    gap: 12,
  },
  topBarActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    flexShrink: 0,
  },
  brandRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    flex: 1,
    minWidth: 0,
  },
  logo: {
    width: 42,
    height: 42,
    borderRadius: 14,
  },
  brandText: {
    flex: 1,
    minWidth: 0,
  },
  brandName: {
    color: theme.colors.text,
    fontSize: 23,
    fontFamily: theme.typography.display,
  },
  brandTag: {
    color: theme.colors.textMuted,
    fontSize: 13,
    fontFamily: theme.typography.body,
  },
  notificationButton: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(25, 36, 53, 0.72)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  notificationBadge: {
    position: 'absolute',
    top: -4,
    right: -4,
    minWidth: 17,
    height: 17,
    borderRadius: 9,
    paddingHorizontal: 4,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.danger,
    borderWidth: 1,
    borderColor: theme.colors.background,
  },
  notificationBadgeText: {
    color: theme.colors.text,
    fontSize: 9,
    lineHeight: 11,
    fontFamily: theme.typography.bodyBold,
  },
  dashboardCard: {
    paddingBottom: 16,
    borderColor: 'rgba(0, 214, 176, 0.16)',
  },
  dashboardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: 12,
  },
  dashboardCopy: {
    flex: 1,
    minWidth: 0,
  },
  dashboardBadge: {
    minHeight: 32,
    borderRadius: theme.radii.pill,
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.24)',
  },
  dashboardBadgeText: {
    color: theme.colors.primary,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  heroTitle: {
    color: theme.colors.text,
    fontSize: 29,
    lineHeight: 33,
    fontFamily: theme.typography.display,
  },
  heroText: {
    marginTop: 6,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 20,
    fontFamily: theme.typography.body,
  },
  heroStatusRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
    marginTop: 16,
  },
  heroStatusChip: {
    minHeight: 34,
    borderRadius: theme.radii.pill,
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: 'rgba(14, 26, 41, 0.72)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  heroStatusDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: theme.colors.primary,
  },
  heroStatusText: {
    color: theme.colors.text,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  balanceStrip: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 16,
    marginBottom: 14,
  },
  balanceCard: {
    flex: 1,
    minHeight: 94,
    borderRadius: theme.radii.lg,
    padding: 10,
    backgroundColor: 'rgba(14, 21, 31, 0.82)',
    borderWidth: 1,
    borderColor: theme.colors.border,
    justifyContent: 'space-between',
  },
  balanceIcon: {
    width: 26,
    height: 26,
    borderRadius: 13,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 6,
  },
  balanceLabel: {
    color: theme.colors.textSoft,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  balanceValue: {
    marginTop: 4,
    color: theme.colors.text,
    fontSize: 18,
    lineHeight: 22,
    fontFamily: theme.typography.heading,
  },
  balanceUnit: {
    marginTop: 1,
    color: theme.colors.textMuted,
    fontSize: 10,
    fontFamily: theme.typography.bodyBold,
  },
  featuredBoost: {
    minHeight: 82,
    marginBottom: 12,
    borderRadius: 20,
    paddingHorizontal: 14,
    paddingVertical: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: 'rgba(0, 214, 176, 0.10)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.30)',
  },
  featuredBoostPressed: {
    opacity: 0.88,
    transform: [{scale: 0.99}],
  },
  featuredBoostIcon: {
    width: 46,
    height: 46,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.primary,
  },
  featuredBoostCopy: {
    flex: 1,
    minWidth: 0,
  },
  featuredBoostTitle: {
    marginTop: 3,
    color: theme.colors.text,
    fontSize: 16,
    fontFamily: theme.typography.heading,
  },
  featuredBoostSubtitle: {
    marginTop: 3,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 16,
    fontFamily: theme.typography.body,
  },
  quickActionGrid: {
    flexDirection: 'row',
    gap: 8,
  },
  quickAction: {
    flex: 1,
    minHeight: 82,
    borderRadius: theme.radii.lg,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 6,
    backgroundColor: 'rgba(14, 21, 31, 0.76)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  quickActionPressed: {
    opacity: 0.86,
    transform: [{scale: 0.98}],
  },
  quickActionIcon: {
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 7,
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.18)',
  },
  quickActionTitle: {
    color: theme.colors.text,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
  },
  quickActionSubtitle: {
    marginTop: 2,
    color: theme.colors.textSoft,
    fontSize: 10,
    fontFamily: theme.typography.bodyMedium,
  },
  kycWaitlistCard: {
    borderColor: 'rgba(0, 214, 176, 0.26)',
    backgroundColor: 'rgba(13, 27, 36, 0.96)',
  },
  kycWaitlistHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
  },
  kycWaitlistIcon: {
    width: 46,
    height: 46,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 16,
    backgroundColor: 'rgba(0, 214, 176, 0.13)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.2)',
  },
  kycWaitlistCopy: {
    flex: 1,
    minWidth: 0,
  },
  kycWaitlistEyebrowRow: {
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: 8,
  },
  kycWaitlistEyebrow: {
    color: theme.colors.primarySoft,
    fontSize: 10,
    letterSpacing: 0.8,
    fontFamily: theme.typography.bodyBold,
  },
  kycWaitlistLivePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingHorizontal: 7,
    paddingVertical: 4,
    borderRadius: theme.radii.pill,
    backgroundColor: 'rgba(240, 185, 11, 0.12)',
  },
  kycWaitlistLiveDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: theme.colors.accentWarm,
  },
  kycWaitlistLiveText: {
    color: theme.colors.accentWarm,
    fontSize: 9,
    fontFamily: theme.typography.bodyBold,
  },
  kycWaitlistTitle: {
    marginTop: 5,
    color: theme.colors.text,
    fontSize: 19,
    lineHeight: 23,
    fontFamily: theme.typography.heading,
  },
  kycWaitlistSubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
  kycWaitlistBenefitRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 15,
  },
  kycWaitlistBenefit: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingHorizontal: 9,
    paddingVertical: 7,
    borderRadius: theme.radii.pill,
    backgroundColor: 'rgba(14, 21, 31, 0.76)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  kycWaitlistBenefitText: {
    maxWidth: 150,
    color: theme.colors.textMuted,
    fontSize: 10,
    fontFamily: theme.typography.bodyMedium,
  },
  kycWaitlistButton: {
    marginTop: 15,
  },
  kycQueueRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    marginTop: 15,
    padding: 12,
    borderRadius: theme.radii.lg,
    backgroundColor: 'rgba(0, 214, 176, 0.08)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.18)',
  },
  kycQueueLabel: {
    color: theme.colors.textSoft,
    fontSize: 9,
    letterSpacing: 0.7,
    fontFamily: theme.typography.bodyBold,
  },
  kycQueueValue: {
    marginTop: 3,
    color: theme.colors.primarySoft,
    fontSize: 20,
    fontFamily: theme.typography.heading,
  },
  kycQueueAction: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingHorizontal: 10,
    paddingVertical: 9,
    borderRadius: theme.radii.md,
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
  },
  kycQueueActionText: {
    color: theme.colors.primary,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  todayCard: {
    borderColor: 'rgba(120, 167, 255, 0.18)',
  },
  todayHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 12,
  },
  todayHeaderCopy: {
    flex: 1,
    minWidth: 0,
  },
  todayTitle: {
    color: theme.colors.text,
    fontSize: 21,
    lineHeight: 25,
    fontFamily: theme.typography.heading,
  },
  todaySubtitle: {
    marginTop: 5,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  todayScorePill: {
    minWidth: 64,
    minHeight: 54,
    borderRadius: theme.radii.lg,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(120, 167, 255, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(120, 167, 255, 0.24)',
  },
  todayScoreValue: {
    color: theme.colors.text,
    fontSize: 18,
    fontFamily: theme.typography.heading,
  },
  todayScoreLabel: {
    marginTop: 1,
    color: theme.colors.textSoft,
    fontSize: 10,
    fontFamily: theme.typography.bodyBold,
  },
  todayProgressTrack: {
    height: 8,
    marginTop: 14,
    overflow: 'hidden',
    borderRadius: theme.radii.pill,
    backgroundColor: 'rgba(255, 255, 255, 0.08)',
  },
  todayProgressFill: {
    height: '100%',
    borderRadius: theme.radii.pill,
    backgroundColor: theme.colors.primary,
  },
  todayList: {
    marginTop: 14,
    gap: 8,
  },
  todayRow: {
    minHeight: 62,
    borderRadius: theme.radii.lg,
    paddingHorizontal: 12,
    paddingVertical: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: 'rgba(14, 21, 31, 0.72)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  todayRowDone: {
    borderColor: 'rgba(93, 211, 158, 0.25)',
    backgroundColor: 'rgba(93, 211, 158, 0.08)',
  },
  todayIcon: {
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.2)',
  },
  todayIconDone: {
    backgroundColor: theme.colors.success,
    borderColor: theme.colors.success,
  },
  todayCopy: {
    flex: 1,
    minWidth: 0,
  },
  todayItemTitle: {
    color: theme.colors.text,
    fontSize: 14,
    fontFamily: theme.typography.bodyBold,
  },
  todayItemSubtitle: {
    marginTop: 3,
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  todayActionPill: {
    minHeight: 30,
    minWidth: 58,
    borderRadius: theme.radii.pill,
    paddingHorizontal: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 5,
    backgroundColor: 'rgba(0, 214, 176, 0.1)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.18)',
  },
  todayActionText: {
    color: theme.colors.primary,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  discoverRail: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 14,
  },
  discoverCard: {
    flex: 1,
    minHeight: 118,
    borderRadius: theme.radii.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(14, 21, 31, 0.78)',
    padding: 12,
    overflow: 'hidden',
  },
  discoverIconShell: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 10,
    backgroundColor: theme.colors.primary,
  },
  discoverTitle: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  discoverSubtitle: {
    marginTop: 6,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 16,
    fontFamily: theme.typography.body,
  },
  heroActionRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 2,
  },
  heroActionButton: {
    flex: 1,
  },
  miningStatusCard: {
    marginTop: -4,
  },
  statusHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 12,
  },
  statusTitle: {
    color: theme.colors.text,
    fontSize: 20,
    fontFamily: theme.typography.heading,
  },
  statusCopy: {
    marginTop: 8,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  liveBadge: {
    minHeight: 30,
    borderRadius: 15,
    paddingHorizontal: 12,
    justifyContent: 'center',
    backgroundColor: 'rgba(93, 211, 158, 0.14)',
    borderWidth: 1,
    borderColor: 'rgba(93, 211, 158, 0.24)',
  },
  liveBadgeText: {
    color: theme.colors.success,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  compactStatsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
    marginTop: 16,
  },
  compactStat: {
    flex: 1,
    minWidth: 92,
    borderRadius: theme.radii.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(18, 39, 66, 0.46)',
    padding: 12,
  },
  compactStatWide: {
    flexBasis: '46%',
  },
  compactStatValue: {
    color: theme.colors.text,
    fontSize: 18,
    fontFamily: theme.typography.heading,
  },
  compactStatLabel: {
    marginTop: 4,
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  squadCommandCard: {
    marginTop: -4,
    borderColor: 'rgba(0, 214, 176, 0.15)',
  },
  squadCommandHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  squadCommandIcon: {
    width: 44,
    height: 44,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.primary,
  },
  squadCommandCopy: {
    flex: 1,
    minWidth: 0,
  },
  squadCommandTitle: {
    color: theme.colors.text,
    fontSize: 19,
    fontFamily: theme.typography.heading,
  },
  squadCommandText: {
    marginTop: 5,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  squadProgressTrack: {
    height: 8,
    marginTop: 14,
    overflow: 'hidden',
    borderRadius: theme.radii.pill,
    backgroundColor: 'rgba(255, 255, 255, 0.08)',
  },
  squadProgressFill: {
    height: '100%',
    borderRadius: theme.radii.pill,
    backgroundColor: theme.colors.primary,
  },
  squadCommandStats: {
    marginTop: 12,
    flexDirection: 'row',
    gap: 8,
  },
  squadCommandStat: {
    flex: 1,
    minHeight: 62,
    borderRadius: theme.radii.md,
    padding: 10,
    justifyContent: 'center',
    backgroundColor: 'rgba(18, 39, 66, 0.48)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  squadCommandStatValue: {
    color: theme.colors.text,
    fontSize: 16,
    fontFamily: theme.typography.heading,
  },
  squadCommandStatLabel: {
    marginTop: 4,
    color: theme.colors.textSoft,
    fontSize: 10,
    fontFamily: theme.typography.bodyBold,
  },
  squadEmptyActions: {
    minHeight: 38,
    marginTop: 14,
    borderRadius: theme.radii.pill,
    paddingHorizontal: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.22)',
  },
  squadEmptyActionText: {
    color: theme.colors.primary,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
  },
  squadSuggestionsCard: {
    paddingVertical: 6,
    borderColor: 'rgba(120, 167, 255, 0.14)',
  },
  squadSuggestionRow: {
    minHeight: 76,
    paddingVertical: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(255, 255, 255, 0.07)',
  },
  squadSuggestionRowLast: {
    borderBottomWidth: 0,
  },
  squadSuggestionRowDisabled: {
    opacity: 0.55,
  },
  squadSuggestionAvatar: {
    width: 48,
    height: 48,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0, 214, 176, 0.16)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.26)',
  },
  squadSuggestionAvatarText: {
    color: theme.colors.primary,
    fontSize: 18,
    fontFamily: theme.typography.heading,
  },
  squadSuggestionCopy: {
    flex: 1,
    minWidth: 0,
  },
  squadSuggestionTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  squadSuggestionName: {
    flex: 1,
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  squadSuggestionBadge: {
    minHeight: 22,
    borderRadius: theme.radii.pill,
    paddingHorizontal: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(120, 167, 255, 0.14)',
    borderWidth: 1,
    borderColor: 'rgba(120, 167, 255, 0.22)',
  },
  squadSuggestionBadgeText: {
    color: theme.colors.accent,
    fontSize: 10,
    fontFamily: theme.typography.bodyBold,
  },
  squadSuggestionSubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  squadSuggestionStats: {
    marginTop: 8,
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: 6,
  },
  squadSuggestionStat: {
    color: theme.colors.textSoft,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  squadSuggestionDivider: {
    color: theme.colors.borderStrong,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  squadSuggestionAction: {
    minHeight: 34,
    borderRadius: theme.radii.pill,
    paddingHorizontal: 12,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.primary,
  },
  squadSuggestionActionText: {
    color: theme.colors.background,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  questCard: {
    marginTop: -4,
  },
  questHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 12,
  },
  questHeaderCopy: {
    flex: 1,
    minWidth: 0,
  },
  questTitle: {
    color: theme.colors.text,
    fontSize: 20,
    fontFamily: theme.typography.heading,
  },
  questText: {
    marginTop: 7,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  questRewardBadge: {
    minWidth: 86,
    borderRadius: theme.radii.md,
    paddingVertical: 10,
    paddingHorizontal: 12,
    alignItems: 'center',
    backgroundColor: 'rgba(120, 167, 255, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(120, 167, 255, 0.25)',
  },
  questRewardValue: {
    color: theme.colors.text,
    fontSize: 16,
    fontFamily: theme.typography.heading,
  },
  questRewardLabel: {
    marginTop: 2,
    color: theme.colors.textSoft,
    fontSize: 11,
    fontFamily: theme.typography.body,
  },
  questProgressTrack: {
    height: 8,
    overflow: 'hidden',
    borderRadius: 4,
    marginTop: 16,
    backgroundColor: 'rgba(255, 255, 255, 0.08)',
  },
  questProgressFill: {
    height: '100%',
    borderRadius: 4,
    backgroundColor: theme.colors.primary,
  },
  questProgressText: {
    marginTop: 8,
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  questStepList: {
    marginTop: 14,
    gap: 9,
  },
  questStepRow: {
    minHeight: 58,
    borderRadius: theme.radii.md,
    paddingHorizontal: 12,
    paddingVertical: 10,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(18, 39, 66, 0.46)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  questStepIcon: {
    width: 28,
    height: 28,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 10,
    backgroundColor: 'rgba(47, 224, 196, 0.11)',
    borderWidth: 1,
    borderColor: 'rgba(47, 224, 196, 0.25)',
  },
  questStepIconDone: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  questStepCopy: {
    flex: 1,
    minWidth: 0,
  },
  questStepTitle: {
    color: theme.colors.text,
    fontSize: 14,
    fontFamily: theme.typography.bodyBold,
  },
  questStepDescription: {
    marginTop: 3,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 16,
    fontFamily: theme.typography.body,
  },
  questCompletePill: {
    minHeight: 44,
    borderRadius: theme.radii.pill,
    marginTop: 16,
    paddingHorizontal: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: 'rgba(93, 211, 158, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(93, 211, 158, 0.24)',
  },
  questCompleteText: {
    color: theme.colors.success,
    fontSize: 14,
    fontFamily: theme.typography.bodyBold,
  },
  bannerCard: {
    paddingBottom: 16,
  },
  partnerHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 12,
  },
  partnerTitle: {
    color: theme.colors.text,
    fontSize: 22,
    fontFamily: theme.typography.heading,
  },
  partnerBadge: {
    minHeight: 30,
    paddingHorizontal: 12,
    borderRadius: theme.radii.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0, 214, 176, 0.14)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.24)',
  },
  partnerBadgeText: {
    color: theme.colors.primary,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  partnerText: {
    marginTop: 8,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  remoteBanner: {
    width: '100%',
    height: 150,
    borderRadius: 18,
    marginTop: 14,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 2,
    marginBottom: 10,
  },
  sectionTitle: {
    color: theme.colors.text,
    fontSize: 19,
    fontFamily: theme.typography.display,
  },
  sectionLink: {
    color: theme.colors.primary,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
  },
  sectionSubtitle: {
    marginTop: 4,
    color: theme.colors.textSoft,
    fontSize: 14,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  gridItem: {
    width: '48.5%',
  },
  featureCard: {
    minHeight: 112,
  },
  featureIconShell: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
    marginBottom: 10,
  },
  featureTitle: {
    color: theme.colors.text,
    fontSize: 16,
    fontFamily: theme.typography.heading,
  },
  featureSubtitle: {
    marginTop: 8,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  newsCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 16,
  },
  newsIconShell: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0, 226, 183, 0.11)',
    borderWidth: 1,
    borderColor: 'rgba(0, 226, 183, 0.18)',
  },
  newsTextWrap: {
    flex: 1,
    minWidth: 0,
  },
  listCard: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 16,
  },
  listIconShell: {
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(120, 167, 255, 0.12)',
    marginRight: 14,
  },
  listCopy: {
    flex: 1,
  },
  listTitle: {
    color: theme.colors.text,
    fontSize: 17,
    fontFamily: theme.typography.heading,
  },
  listSubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
  emptyTitle: {
    color: theme.colors.text,
    fontSize: 18,
    fontFamily: theme.typography.heading,
  },
  emptyCopy: {
    marginTop: 8,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  activityCard: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 16,
  },
  activityIcon: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 14,
    backgroundColor: 'rgba(47, 224, 196, 0.12)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  activityCopy: {
    flex: 1,
  },
  activityTitle: {
    color: theme.colors.text,
    fontSize: 16,
    fontFamily: theme.typography.bodyBold,
  },
  activitySubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  trendingRow: {
    flexDirection: 'row',
    gap: 10,
  },
  trendingCard: {
    flex: 1,
    minHeight: 118,
  },
  trendingTitle: {
    marginTop: 12,
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.bodyBold,
  },
  trendingMeta: {
    marginTop: 5,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 16,
    fontFamily: theme.typography.body,
  },
  newsTitle: {
    color: theme.colors.text,
    fontSize: 18,
    fontFamily: theme.typography.heading,
  },
  newsCopy: {
    marginTop: 8,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  supportTitle: {
    color: theme.colors.text,
    fontSize: 22,
    fontFamily: theme.typography.heading,
  },
  supportText: {
    marginTop: 8,
    marginBottom: 16,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  supportBannerWrap: {
    marginTop: 16,
  },
});

export default HomeOrg;
