import React, {useEffect, useMemo, useRef, useState} from 'react';
import {
  ActivityIndicator,
  Animated,
  Easing,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import MaterialCommunityIcons from 'react-native-vector-icons/MaterialCommunityIcons';
import {useQueryClient} from 'react-query';
import {callMutation, callQuery} from '../../hooks';
import {invalidateEngagementQueries} from '../../hooks/useEngagement';
import {useMySquad} from '../../hooks/useSquads';
import {useThisAlert} from '../../hooks/AlertContext';
import AnimatedPopup from '../../molecules/AnimatedPopup';
import AppodealBannerSlot from '../../molecules/AppodealBannerSlot';
import SponsoredBanner from '../../molecules/SponsoredBanner';
import {
  AppScreen,
  AppTopNav,
  CardEntrance,
  GlassCard,
  PrimaryButton,
  ScreenLoader,
  ScreenState,
  SectionEyebrow,
} from '../../design/primitives';
import theme from '../../design/theme';
import {showOptionalActionAd} from '../../services/adService';
import {logAppIssue} from '../../services/appIssueLogger';
import {queueEngagementEvent} from '../../services/engagementAnalyticsService';
import {fetchTaskList} from '../../services/taskListService';
import {
  cancelMiningCompletionReminder,
  scheduleMiningCompletionReminder,
  showRewardNotification,
} from '../../services/miningReminderService';
import {postApi} from '../../storage/server';
import {navigate} from '../../utils/helper';
import {normalizeNotificationCount} from '../../utils/notifications';
import {getMiningTaskPreview, readWalletBalance} from '../../utils/miningData';

const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const MINING_WINDOW_HOURS = 6;

type RewardCalendarDay = {
  day: number;
  title: string;
  reward_label?: string;
  is_chest_day?: boolean;
  state: 'claimed' | 'ready' | 'today' | 'locked' | string;
};

type StreakDay = {
  key: string;
  label: string;
  title: string;
  state: string;
  isChestDay?: boolean;
};

const formatAmount = (value: unknown) => {
  const numericValue = Number(value ?? 0);

  if (!Number.isFinite(numericValue)) {
    return '0';
  }

  return Math.round(numericValue).toLocaleString();
};

const formatTime = (seconds: number) => {
  const hrs = Math.floor(seconds / 3600)
    .toString()
    .padStart(2, '0');
  const mins = Math.floor((seconds % 3600) / 60)
    .toString()
    .padStart(2, '0');
  const secs = (seconds % 60).toString().padStart(2, '0');

  return `${hrs}:${mins}:${secs}`;
};

const delay = (timeout: number) =>
  new Promise(resolve => {
    setTimeout(resolve, timeout);
  });

const reportMiningIssue = (payload: Parameters<typeof logAppIssue>[0]) => {
  logAppIssue(payload).catch(error => {
    console.log('Unable to queue mining issue log', error);
  });
};

const findFirstValue = (payload: any, keys: string[]) => {
  if (!payload || typeof payload !== 'object') {
    return undefined;
  }

  const queue = [payload];
  const visited = new Set<any>();

  while (queue.length > 0) {
    const current = queue.shift();

    if (!current || typeof current !== 'object' || visited.has(current)) {
      continue;
    }

    visited.add(current);

    for (const key of keys) {
      const value = current?.[key];

      if (value !== undefined && value !== null && value !== '') {
        return value;
      }
    }

    Object.values(current).forEach(value => {
      if (value && typeof value === 'object') {
        queue.push(value);
      }
    });
  }

  return undefined;
};

const extractNextMining = (payload: any) =>
  findFirstValue(payload, [
    'nextMining',
    'next_mining',
    'next_mining_at',
    'nextMiningAt',
    'nextMiningTimestamp',
    'next_mining_timestamp',
    'next_mining_epoch',
    'next_mining_ms',
    'mining_end_time',
    'harvest_at',
    'end_time',
  ]);

const extractMessage = (payload: any) =>
  findFirstValue(payload, ['message', 'msg', 'status_message']);

const getBackendStatus = (payload: any) => {
  const rawStatus = findFirstValue(payload, ['status']);

  if (rawStatus === undefined) {
    return 1;
  }

  const numericStatus = Number(rawStatus);
  return Number.isFinite(numericStatus) ? numericStatus : 1;
};

const parseTimestamp = (value: unknown) => {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value > 9999999999 ? value : value * 1000;
  }

  if (typeof value !== 'string') {
    return NaN;
  }

  const trimmedValue = value.trim();

  if (!trimmedValue) {
    return NaN;
  }

  if (/^\d+$/.test(trimmedValue)) {
    const numericValue = Number(trimmedValue);
    return numericValue > 9999999999 ? numericValue : numericValue * 1000;
  }

  return new Date(trimmedValue.replace(' ', 'T')).getTime();
};

const isSameCalendarDay = (value: unknown) => {
  const timestamp = parseTimestamp(value);

  if (Number.isNaN(timestamp)) {
    return false;
  }

  const comparedDate = new Date(timestamp);
  const now = new Date();

  return (
    comparedDate.getFullYear() === now.getFullYear() &&
    comparedDate.getMonth() === now.getMonth() &&
    comparedDate.getDate() === now.getDate()
  );
};

const deriveMiningDeadline = (payload: any) => {
  const explicitNextMining = extractNextMining(payload);

  if (explicitNextMining) {
    return explicitNextMining;
  }

  const baseTimestamp = findFirstValue(payload, [
    'created_at',
    'rewards_start_time',
    'mining_start_time',
  ]);

  const parsedBaseTimestamp = parseTimestamp(baseTimestamp);

  if (Number.isNaN(parsedBaseTimestamp)) {
    return undefined;
  }

  return parsedBaseTimestamp + MINING_WINDOW_HOURS * 60 * 60 * 1000;
};

const buildStreak = (status: number) => {
  const claimedCount = Math.max(0, Math.min(status, weekdays.length));
  const todayIndex = Math.min(claimedCount, weekdays.length - 1);

  return weekdays.map(
    (label, index): StreakDay => ({
      key: label,
      label,
      title: label,
      state:
        index < claimedCount
          ? 'done'
          : index === todayIndex
          ? 'today'
          : 'future',
    }),
  );
};

const cleanActionMessage = (value: any, fallback: string): string => {
  if (Array.isArray(value)) {
    return value.filter(Boolean).join('\n') || fallback;
  }

  if (typeof value === 'string') {
    const message = value.trim();

    if (!message) {
      return fallback;
    }

    if (
      /SQLSTATE|Column not found|Unknown column|Integrity constraint violation|SQL: /i.test(
        message,
      )
    ) {
      return 'Server reward sync is being updated. Please try again shortly.';
    }

    if (
      /<!doctype html|<html|route \[login\] not defined|whoops|laravel/i.test(
        message,
      )
    ) {
      return 'Session needs a refresh. Please log in again and try once more.';
    }

    if (/unauthenticated|session expired/i.test(message)) {
      return 'Session expired. Please log in again.';
    }

    return message;
  }

  if (value && typeof value === 'object') {
    return cleanActionMessage(
      value.message || value.error || value.msg || value.status_message,
      fallback,
    );
  }

  return fallback;
};

const formatApiMessage = cleanActionMessage;

const isAlreadyClaimedMessage = (message: string) =>
  /already claimed|claimed already|already received|today.*claimed/i.test(
    message,
  );

const isLockedMessage = (message: string) =>
  /locked|not eligible|not unlocked|complete.*streak|cannot claim|can't claim/i.test(
    message,
  );

const MiningOrg = () => {
  const [timeLeft, setTimeLeft] = useState(0);
  const [miningDeadline, setMiningDeadline] = useState<number | null>(null);
  const [isButtonEnabled, setIsButtonEnabled] = useState(true);
  const [showPopup, setShowPopup] = useState(false);
  const [isStartingMining, setIsStartingMining] = useState(false);
  const [isClaimingStreak, setIsClaimingStreak] = useState(false);
  const [isClaimingChest, setIsClaimingChest] = useState(false);
  const [isClaimingAdReward, setIsClaimingAdReward] = useState(false);
  const [claimedStreakToday, setClaimedStreakToday] = useState(false);
  const pulseProgress = useRef(new Animated.Value(0)).current;
  const {showAlert} = useThisAlert();
  const queryClient = useQueryClient();
  const {
    data: miningData,
    error: miningQueryError,
    isLoading: isMiningLoading,
    refetch: refetchMining,
  } = callQuery('auth/get_mining', {requestTimeoutMs: 8000, retry: 0});
  const {
    data: walletData,
    error: walletQueryError,
    isLoading: isWalletLoading,
    refetch: refetchWallet,
  } = callQuery('auth/wallet', {requestTimeoutMs: 8000, retry: 0});
  const {
    data: boostData,
    isLoading: isBoostLoading,
    refetch: refetchBoost,
  } = callQuery('auth/my-boost', {retry: 0, staleTime: 60000});
  const {
    data: tasksData,
    error: tasksQueryError,
    isLoading: isTasksLoading,
    refetch: refetchTasks,
  } = callQuery('auth/get-task', {
    queryFn: ({signal}: {signal?: AbortSignal}) => fetchTaskList(signal),
    retry: 0,
    staleTime: 30000,
  });
  const {
    data: rewardStatusData,
    isLoading: isRewardStatusLoading,
    refetch: refetchRewardStatus,
  } = callQuery('auth/reward-status', {retry: 0, staleTime: 30000});
  const {
    data: rewardCalendarData,
    isFetching: isRewardCalendarFetching,
    refetch: refetchRewardCalendar,
  }: any = callQuery('auth/reward-calendar', {
    retry: 0,
    staleTime: 30000,
  });
  const {data: bannerData} = callQuery('banners', {
    retry: 0,
    staleTime: 120000,
  });
  const {data: notificationCountData, refetch: refetchNotificationCount}: any =
    callQuery('auth/count-notification', {
      retry: 0,
      staleTime: 30000,
    });
  const {data: squadData, refetch: refetchSquad} = useMySquad();
  const {mutateAsync: startMiningMutation} = callMutation(
    'auth/click-button',
    {},
  );

  const coreApiError =
    miningData?.success === false
      ? miningData?.error
      : walletData?.success === false
      ? walletData?.error
      : miningQueryError instanceof Error
      ? miningQueryError.message
      : walletQueryError instanceof Error
      ? walletQueryError.message
      : undefined;

  const wallet = walletData?.data?.data ?? walletData?.data ?? {};
  const walletBalance = readWalletBalance(wallet, 'wallet_balance');
  const verifiedBalance = readWalletBalance(wallet, 'verififiedBalance');
  const lockedBalance = readWalletBalance(wallet, 'unVerifiedBalance');
  const boostRate = boostData?.data?.data ?? boostData?.data ?? {};
  const miningReward = Number(
    boostRate?.mining_reward ?? boostRate?.reward ?? 1.5,
  );
  const paidBoostPercent =
    readWalletBalance(boostRate, 'paid_boost_percent') ?? 0;
  const teamBoostPercent = readWalletBalance(boostRate, 'team_boost_percent');
  const miningSquad = squadData?.squad;
  const squadMission =
    miningSquad?.weeklyMissions?.find(mission => !mission.claimed) ??
    squadData?.weeklyMissions?.find(mission => !mission.claimed) ??
    miningSquad?.weeklyMissions?.[0] ??
    squadData?.weeklyMissions?.[0];
  const squadProgressPercent = Math.min(
    100,
    Math.max(0, Number(miningSquad?.weeklyProgressPercent ?? 0)),
  );

  const applyMiningCountdown = (nextMiningValue: unknown) => {
    const nextTimestamp = parseTimestamp(nextMiningValue);

    if (Number.isNaN(nextTimestamp)) {
      setMiningDeadline(null);
      setIsButtonEnabled(true);
      setTimeLeft(0);
      cancelMiningCompletionReminder();
      return false;
    }

    const remainingSeconds = Math.max(
      0,
      Math.ceil((nextTimestamp - Date.now()) / 1000),
    );

    if (remainingSeconds <= 0) {
      setMiningDeadline(null);
      setIsButtonEnabled(true);
      setTimeLeft(0);
      cancelMiningCompletionReminder();
      return false;
    }

    setMiningDeadline(nextTimestamp);
    setIsButtonEnabled(false);
    setTimeLeft(remainingSeconds);
    scheduleMiningCompletionReminder(nextTimestamp, 'mining');
    return true;
  };

  const syncMiningCountdown = async (...payloads: any[]) => {
    const immediateMatch = payloads.find(payload =>
      deriveMiningDeadline(payload),
    );
    const initialNextMining = deriveMiningDeadline(immediateMatch);

    if (initialNextMining) {
      return initialNextMining;
    }

    for (let attempt = 0; attempt < 4; attempt += 1) {
      await delay(attempt === 0 ? 700 : 1200);
      const refreshed = await refetchMining?.();
      const refreshedNextMining = deriveMiningDeadline(refreshed?.data?.data);

      if (refreshedNextMining) {
        return refreshedNextMining;
      }
    }

    return undefined;
  };

  useEffect(() => {
    const nextMining = deriveMiningDeadline(miningData?.data);
    applyMiningCountdown(nextMining);
  }, [miningData]);

  useEffect(() => {
    if (isButtonEnabled) {
      pulseProgress.stopAnimation();
      pulseProgress.setValue(0);
      return;
    }

    const animation = Animated.loop(
      Animated.sequence([
        Animated.timing(pulseProgress, {
          toValue: 1,
          duration: 1200,
          easing: Easing.out(Easing.cubic),
          useNativeDriver: true,
        }),
        Animated.timing(pulseProgress, {
          toValue: 0,
          duration: 1200,
          easing: Easing.in(Easing.cubic),
          useNativeDriver: true,
        }),
      ]),
    );

    animation.start();

    return () => animation.stop();
  }, [isButtonEnabled, pulseProgress]);

  useEffect(() => {
    if (!miningDeadline) {
      return;
    }

    const updateCountdown = () => {
      const remainingSeconds = Math.max(
        0,
        Math.ceil((miningDeadline - Date.now()) / 1000),
      );

      if (remainingSeconds <= 0) {
        setMiningDeadline(null);
        setIsButtonEnabled(true);
        setTimeLeft(0);
        cancelMiningCompletionReminder();
        return;
      }

      setIsButtonEnabled(false);
      setTimeLeft(remainingSeconds);
    };

    updateCountdown();
    const intervalId = setInterval(updateCountdown, 1000);

    return () => clearInterval(intervalId);
  }, [miningDeadline]);

  const miningTasks = getMiningTaskPreview(tasksData);

  const rewardRecord =
    rewardStatusData?.data?.data ?? rewardStatusData?.data ?? {};
  const rewardStatus = Number(
    rewardRecord?.status ?? rewardRecord?.streak_day ?? 0,
  );
  const rewardCalendar =
    rewardCalendarData?.data?.data ?? rewardCalendarData?.data;
  const calendarDays: RewardCalendarDay[] = useMemo(
    () =>
      Array.isArray(rewardCalendar?.calendar_days)
        ? rewardCalendar.calendar_days
        : [],
    [rewardCalendar?.calendar_days],
  );
  const chest = rewardCalendar?.chest ?? {};
  const maxStreakDays = Number(
    rewardCalendar?.max_streak_days ??
      rewardRecord?.max_streak_days ??
      weekdays.length,
  );
  const currentStreakDay = Number(
    rewardCalendar?.current_streak_day ??
      rewardCalendar?.status?.streak_day ??
      rewardRecord?.streak_day ??
      rewardStatus,
  );
  const calendarStatus = rewardCalendar?.status ?? {};
  const claimedTodayFromBackend = Boolean(
    rewardRecord?.claimed_today ?? isSameCalendarDay(rewardRecord?.updated_at),
  );
  const canClaimFromStatus =
    rewardRecord?.can_claim !== undefined
      ? Boolean(rewardRecord.can_claim)
      : !claimedTodayFromBackend;
  const hasStreakResponse = Boolean(
    (rewardStatusData && rewardStatusData.success !== false) ||
      (rewardCalendarData && rewardCalendarData.success !== false),
  );
  const normalizedCurrentStreak = Number.isFinite(currentStreakDay)
    ? Math.max(0, Math.min(currentStreakDay, maxStreakDays))
    : 0;
  const fallbackCalendarDays = useMemo<RewardCalendarDay[]>(
    () =>
      Array.from({length: 30}, (_, index) => {
        const day = index + 1;
        const isCycleDay = day <= maxStreakDays;
        const isChestDay = day % maxStreakDays === 0 || day === 30;
        let state: RewardCalendarDay['state'] = 'locked';

        if (isCycleDay && day < normalizedCurrentStreak) {
          state = 'claimed';
        } else if (
          isCycleDay &&
          day === normalizedCurrentStreak &&
          claimedTodayFromBackend &&
          normalizedCurrentStreak > 0
        ) {
          state = 'today';
        } else if (isCycleDay && day <= normalizedCurrentStreak) {
          state = 'claimed';
        } else if (
          isCycleDay &&
          day === normalizedCurrentStreak + 1 &&
          canClaimFromStatus
        ) {
          state = 'ready';
        }

        return {
          day,
          title: `Day ${day}`,
          reward_label: isChestDay ? '5 BRC' : '1 BRC',
          is_chest_day: isChestDay,
          state,
        };
      }),
    [
      canClaimFromStatus,
      claimedTodayFromBackend,
      maxStreakDays,
      normalizedCurrentStreak,
    ],
  );
  const visibleCalendarDays = hasStreakResponse
    ? calendarDays.length > 0
      ? calendarDays
      : fallbackCalendarDays
    : [];
  const bannerImage =
    bannerData?.data?.userData?.[0]?.image ??
    bannerData?.data?.banners?.image_url ??
    bannerData?.data?.banners?.image ??
    bannerData?.data?.data?.[0]?.image;
  const notificationCount = normalizeNotificationCount(notificationCountData);
  useEffect(() => {
    setClaimedStreakToday(claimedTodayFromBackend);
  }, [claimedTodayFromBackend]);
  const streak = useMemo<StreakDay[]>(() => {
    const backendStreakDays = visibleCalendarDays
      .filter(day => Number(day?.day) <= Math.max(1, maxStreakDays))
      .slice(0, Math.max(1, maxStreakDays))
      .map(day => ({
        key: String(day.day),
        label: String(day.day),
        title: day.title ?? `Day ${day.day}`,
        state: day.state,
        isChestDay: Boolean(day.is_chest_day),
      }));

    if (!hasStreakResponse) {
      return [];
    }

    return backendStreakDays.length > 0
      ? backendStreakDays
      : buildStreak(rewardStatus);
  }, [hasStreakResponse, maxStreakDays, rewardStatus, visibleCalendarDays]);
  const completedDays =
    normalizedCurrentStreak ||
    streak.filter(day => day.state === 'claimed' || day.state === 'done')
      .length;
  const hasRemainingStreakReward =
    hasStreakResponse &&
    (calendarStatus?.can_claim !== undefined
      ? Boolean(calendarStatus.can_claim)
      : canClaimFromStatus && !claimedStreakToday);
  const completedMiningTasks = miningTasks.filter(
    (task: any) => task.done,
  ).length;
  const miningLoopItems = [
    {
      key: 'cycle',
      label: 'Cycle',
      value: isButtonEnabled ? 'Ready' : 'Running',
      icon: 'pickaxe',
      active: isButtonEnabled,
      done: !isButtonEnabled,
    },
    {
      key: 'streak',
      label: 'Streak',
      value: isRewardStatusLoading
        ? '...'
        : !hasStreakResponse
        ? '--'
        : hasRemainingStreakReward
        ? 'Claim'
        : 'Done',
      icon: 'calendar-check-outline',
      active: hasRemainingStreakReward,
      done: !hasRemainingStreakReward,
    },
    {
      key: 'tasks',
      label: 'Tasks',
      value: isTasksLoading
        ? '...'
        : miningTasks.length > 0
        ? `${completedMiningTasks}/${miningTasks.length}`
        : '--',
      icon: 'check-decagram-outline',
      active: completedMiningTasks < miningTasks.length,
      done:
        miningTasks.length > 0 && completedMiningTasks >= miningTasks.length,
    },
    {
      key: 'squad',
      label: 'Squad',
      value: miningSquad ? `${Math.round(squadProgressPercent)}%` : 'Join',
      icon: 'account-group-outline',
      active: !miningSquad || squadProgressPercent < 100,
      done: Boolean(miningSquad && squadProgressPercent >= 100),
    },
  ];

  const countdownLabel = isButtonEnabled
    ? 'Mining ready'
    : `Next cycle in ${Math.floor(timeLeft / 3600)}h ${Math.floor(
        (timeLeft % 3600) / 60,
      )}m`;
  const pulseScale = pulseProgress.interpolate({
    inputRange: [0, 1],
    outputRange: [1, 1.035],
  });
  const pulseOpacity = pulseProgress.interpolate({
    inputRange: [0, 1],
    outputRange: [0.08, 0.2],
  });
  const refreshMiningScreen = () => {
    refetchMining?.();
    refetchWallet?.();
    refetchRewardStatus?.();
    refetchRewardCalendar?.();
    refetchBoost?.();
    refetchNotificationCount?.();
    refetchTasks?.();
    refetchSquad?.();
  };

  const handleStartMining = async () => {
    setIsStartingMining(true);
    const startedAt = Date.now();
    queueEngagementEvent({
      eventName: 'mining_start_attempt',
      area: 'mining',
      status: 'attempted',
      route: 'MiningFlow',
      endpoint: 'auth/click-button',
      metadata: {buttonEnabled: isButtonEnabled},
    });

    try {
      const adResult = await showOptionalActionAd(18000);

      const response: any = await startMiningMutation?.();

      if (!response?.success) {
        queueEngagementEvent({
          eventName: 'mining_start_failed',
          area: 'mining',
          status: 'failed',
          route: 'MiningFlow',
          endpoint: 'auth/click-button',
          durationMs: Date.now() - startedAt,
          metadata: {
            message: response?.error || extractMessage(response?.data),
          },
        });
        reportMiningIssue({
          action: 'mining_start_failed',
          endpoint: 'auth/click-button',
          method: 'POST',
          message:
            response?.error ||
            extractMessage(response?.data) ||
            'Mining start API returned failure.',
        });
        showAlert(
          cleanActionMessage(
            response?.error || extractMessage(response?.data),
            'We could not start mining right now.',
          ),
          'ERROR',
        );
        return;
      }

      const backendStatus = getBackendStatus(response?.data);
      const nextMining = await syncMiningCountdown(
        response,
        response?.data,
        response?.data?.data,
        miningData?.data,
      );

      if (backendStatus === 0) {
        if (nextMining) {
          queueEngagementEvent({
            eventName: 'mining_start_already_active',
            area: 'mining',
            status: 'info',
            route: 'MiningFlow',
            endpoint: 'auth/click-button',
            durationMs: Date.now() - startedAt,
            metadata: {nextMining},
          });
          applyMiningCountdown(nextMining);
          await invalidateEngagementQueries(queryClient, {wallet: true});
          showAlert(
            cleanActionMessage(
              extractMessage(response?.data),
              'Mining is already active. Your timer has been synced.',
            ),
            'INFO',
          );
        } else {
          queueEngagementEvent({
            eventName: 'mining_start_failed_no_timer',
            area: 'mining',
            status: 'failed',
            route: 'MiningFlow',
            endpoint: 'auth/click-button',
            durationMs: Date.now() - startedAt,
            metadata: {backendStatus},
          });
          reportMiningIssue({
            action: 'mining_start_no_timer',
            endpoint: 'auth/click-button',
            method: 'POST',
            message:
              extractMessage(response?.data) ||
              'Mining start returned no sync timer.',
            metadata: {backendStatus},
          });
          showAlert(
            cleanActionMessage(
              extractMessage(response?.data),
              'We could not start mining right now.',
            ),
            'ERROR',
          );
        }
        return;
      }

      await Promise.all([
        refetchWallet?.(),
        refetchRewardStatus?.(),
        refetchMining?.(),
        refetchBoost?.(),
      ]);
      await invalidateEngagementQueries(queryClient, {wallet: true});

      if (!nextMining) {
        queueEngagementEvent({
          eventName: 'mining_start_success_timer_missing',
          area: 'mining',
          status: 'succeeded',
          route: 'MiningFlow',
          endpoint: 'auth/click-button',
          durationMs: Date.now() - startedAt,
        });
        showAlert(
          cleanActionMessage(
            extractMessage(response?.data),
            'Mining started, but the timer is still syncing with the server.',
          ),
          'INFO',
        );
        return;
      }

      applyMiningCountdown(nextMining);
      queueEngagementEvent({
        eventName: 'mining_start_success',
        area: 'mining',
        status: 'succeeded',
        route: 'MiningFlow',
        endpoint: 'auth/click-button',
        durationMs: Date.now() - startedAt,
        metadata: {adShown: adResult.shown, adType: adResult.type, nextMining},
      });

      showAlert(
        adResult.shown
          ? cleanActionMessage(
              extractMessage(response?.data),
              `Mining started after ${adResult.type} ad confirmation.`,
            )
          : cleanActionMessage(
              extractMessage(response?.data),
              'Mining started. Ad did not load in time, so the cycle began without it.',
            ),
        'SUCCESS',
      );
      showRewardNotification(
        'Daily streak reward received',
        'Your daily streak reward was added to your Blockrium wallet.',
        'MiningFlow',
      );
    } catch (error) {
      console.error('Error starting mining', error);
      queueEngagementEvent({
        eventName: 'mining_start_exception',
        area: 'mining',
        status: 'failed',
        route: 'MiningFlow',
        endpoint: 'auth/click-button',
        durationMs: Date.now() - startedAt,
        metadata: {
          error:
            error instanceof Error ? error.message : 'Mining start exception.',
        },
      });
      reportMiningIssue({
        action: 'mining_start_exception',
        endpoint: 'auth/click-button',
        method: 'POST',
        message:
          error instanceof Error ? error.message : 'Mining start exception.',
        errorCode: error instanceof Error ? error.name : undefined,
      });
      showAlert('We could not start mining right now.', 'ERROR');
    } finally {
      setIsStartingMining(false);
    }
  };

  const handleClaimStreak = async () => {
    setIsClaimingStreak(true);

    try {
      const adResult = await showOptionalActionAd(18000);

      const response: any = await postApi('auth/random-dailyreward', {});

      if (!response?.success) {
        const message = formatApiMessage(
          response?.error || response?.data?.message,
          'Daily streak could not be claimed.',
        );
        reportMiningIssue({
          action: 'streak_claim_failed',
          endpoint: 'auth/random-dailyreward',
          method: 'POST',
          message,
        });

        if (message.toLowerCase().includes('already claimed')) {
          setClaimedStreakToday(true);
          await Promise.all([
            refetchRewardStatus?.(),
            refetchRewardCalendar?.(),
            refetchWallet?.(),
          ]);
          await invalidateEngagementQueries(queryClient, {wallet: true});
          showAlert('Daily streak was already claimed today.', 'INFO');
          return;
        }

        showAlert(message, 'ERROR');
        return;
      }

      const backendStatus = getBackendStatus(response?.data);
      await Promise.all([
        refetchRewardStatus?.(),
        refetchRewardCalendar?.(),
        refetchWallet?.(),
      ]);

      if (backendStatus === 0) {
        const message = formatApiMessage(
          extractMessage(response?.data),
          'Daily streak could not be claimed right now.',
        );
        reportMiningIssue({
          action: 'streak_claim_backend_rejected',
          endpoint: 'auth/random-dailyreward',
          method: 'POST',
          message,
          metadata: {backendStatus},
        });
        const alreadyClaimed = isAlreadyClaimedMessage(message);
        setClaimedStreakToday(alreadyClaimed || claimedStreakToday);
        if (alreadyClaimed) {
          await invalidateEngagementQueries(queryClient, {wallet: true});
        }
        showAlert(
          alreadyClaimed ? 'Daily streak was already claimed today.' : message,
          alreadyClaimed ? 'INFO' : 'ERROR',
        );
        return;
      }

      setClaimedStreakToday(true);
      await invalidateEngagementQueries(queryClient, {wallet: true});
      showAlert(
        adResult.shown
          ? cleanActionMessage(
              extractMessage(response?.data),
              'Daily streak claimed successfully.',
            )
          : cleanActionMessage(
              extractMessage(response?.data),
              'Daily streak claimed. Ad did not load in time, so the reward was recorded directly.',
            ),
        'SUCCESS',
      );
    } catch (error) {
      console.error('Error claiming daily streak', error);
      reportMiningIssue({
        action: 'streak_claim_exception',
        endpoint: 'auth/random-dailyreward',
        method: 'POST',
        message:
          error instanceof Error ? error.message : 'Daily streak exception.',
        errorCode: error instanceof Error ? error.name : undefined,
      });
      showAlert('Daily streak could not be claimed.', 'ERROR');
    } finally {
      setIsClaimingStreak(false);
    }
  };

  const handleClaimChest = async () => {
    setIsClaimingChest(true);

    try {
      await showOptionalActionAd(18000);

      const response = await postApi('auth/reward-chest/claim', {});
      const backendStatus = getBackendStatus(response?.data);

      if (!response?.success || backendStatus === 0) {
        const message = formatApiMessage(
          response?.error || extractMessage(response?.data),
          '7-day chest could not be claimed.',
        );
        reportMiningIssue({
          action: 'reward_chest_claim_failed',
          endpoint: 'auth/reward-chest/claim',
          method: 'POST',
          message,
          metadata: {backendStatus},
        });
        const isFriendlyState =
          isAlreadyClaimedMessage(message) || isLockedMessage(message);
        showAlert(
          isAlreadyClaimedMessage(message)
            ? '7-day chest was already claimed.'
            : message,
          isFriendlyState ? 'INFO' : 'ERROR',
        );
        return;
      }

      await Promise.all([refetchRewardCalendar?.(), refetchWallet?.()]);
      showAlert(
        cleanActionMessage(
          extractMessage(response?.data),
          '7-day chest claimed successfully.',
        ),
        'SUCCESS',
      );
      showRewardNotification(
        '7-day chest reward received',
        'Your chest reward was added to your Blockrium wallet.',
        'MiningFlow',
      );
    } catch (error) {
      console.error('Error claiming streak chest', error);
      reportMiningIssue({
        action: 'reward_chest_claim_exception',
        endpoint: 'auth/reward-chest/claim',
        method: 'POST',
        message:
          error instanceof Error ? error.message : 'Reward chest exception.',
        errorCode: error instanceof Error ? error.name : undefined,
      });
      showAlert('7-day chest could not be claimed.', 'ERROR');
    } finally {
      setIsClaimingChest(false);
    }
  };

  const handleClaimAdReward = async () => {
    setIsClaimingAdReward(true);

    try {
      const adResult = await showOptionalActionAd(18000);

      const response = await postApi('auth/ads-videos-add-coin', {});

      if (!response?.success) {
        reportMiningIssue({
          action: 'ad_reward_claim_failed',
          endpoint: 'auth/ads-videos-add-coin',
          method: 'POST',
          message:
            response?.error ||
            response?.data?.message ||
            'Ad reward API returned failure.',
        });
        showAlert(
          cleanActionMessage(
            response?.error || response?.data?.message,
            'Ad reward could not be credited.',
          ),
          'ERROR',
        );
        return;
      }

      const backendStatus = Number(response?.data?.status ?? 1);
      if (backendStatus === 0) {
        reportMiningIssue({
          action: 'ad_reward_backend_rejected',
          endpoint: 'auth/ads-videos-add-coin',
          method: 'POST',
          message:
            response?.data?.message || 'Ad reward backend rejected claim.',
          metadata: {backendStatus},
        });
        showAlert(
          cleanActionMessage(
            response?.data?.message,
            'Ad reward could not be credited.',
          ),
          'ERROR',
        );
        return;
      }

      await Promise.all([
        refetchWallet?.(),
        refetchMining?.(),
        refetchBoost?.(),
      ]);
      showAlert(
        adResult.shown
          ? `${
              response?.data?.added_coin ?? 1
            } BRC ad reward credited to your wallet.`
          : `${
              response?.data?.added_coin ?? 1
            } BRC credited. Ad did not load in time, so the reward continued.`,
        'SUCCESS',
      );
      showRewardNotification(
        'Ad reward received',
        `${
          response?.data?.added_coin ?? 1
        } BRC was added to your Blockrium wallet.`,
        'MiningFlow',
      );
    } catch (error) {
      console.error('Error claiming ad reward', error);
      reportMiningIssue({
        action: 'ad_reward_claim_exception',
        endpoint: 'auth/ads-videos-add-coin',
        method: 'POST',
        message:
          error instanceof Error ? error.message : 'Ad reward exception.',
        errorCode: error instanceof Error ? error.name : undefined,
      });
      showAlert('Ad reward could not be credited.', 'ERROR');
    } finally {
      setIsClaimingAdReward(false);
    }
  };

  if (isMiningLoading || isWalletLoading) {
    return (
      <ScreenLoader
        title="Loading mining"
        subtitle="Syncing your mining timer and wallet balance."
      />
    );
  }

  if (
    coreApiError &&
    (miningData?.success === false ||
      walletData?.success === false ||
      Boolean(miningQueryError) ||
      Boolean(walletQueryError))
  ) {
    return (
      <AppScreen
        scrollable
        refreshing={Boolean(isMiningLoading || isWalletLoading)}
        onRefresh={refreshMiningScreen}
        contentContainerStyle={styles.contentContainer}>
        <AppTopNav
          title="Mining"
          subtitle="Cycle, streak, rewards, and mining tasks."
          notificationCount={notificationCount}
          onNotificationsPress={() => navigate('Notifications')}
          onSearchPress={() => navigate('AppSearch')}
          onSettingsPress={() => navigate('AppSettings')}
        />
        <ScreenState
          icon="hammer-outline"
          title="Mining is unavailable"
          message={formatApiMessage(
            coreApiError,
            'Please retry once your connection is stable.',
          )}
          actionLabel="Retry"
          onActionPress={refreshMiningScreen}
        />
      </AppScreen>
    );
  }

  return (
    <AppScreen
      scrollable
      refreshing={Boolean(
        isMiningLoading || isWalletLoading || isRewardCalendarFetching,
      )}
      onRefresh={refreshMiningScreen}
      contentContainerStyle={styles.contentContainer}>
      <AppTopNav
        title="Mining"
        subtitle="Cycle, streak, rewards, and mining tasks."
        notificationCount={notificationCount}
        onNotificationsPress={() => navigate('Notifications')}
        onSearchPress={() => navigate('AppSearch')}
        onSettingsPress={() => navigate('AppSettings')}
      />

      <CardEntrance>
        <GlassCard style={styles.loopCard}>
          <View style={styles.loopHeader}>
            <View>
              <SectionEyebrow text="Today Mining Loop" />
              <Text style={styles.loopTitle}>
                {isButtonEnabled ? 'Start your cycle' : 'Cycle is running'}
              </Text>
              <Text style={styles.loopSubtitle}>
                {isButtonEnabled
                  ? 'Start mining, claim streak, finish tasks, and help your squad.'
                  : `${formatTime(
                      timeLeft,
                    )} left before the next cycle is ready.`}
              </Text>
            </View>
            <View
              style={[
                styles.loopStatusPill,
                isButtonEnabled && styles.loopStatusPillReady,
              ]}>
              <Text style={styles.loopStatusText}>
                {isButtonEnabled ? 'Ready' : 'Active'}
              </Text>
            </View>
          </View>
          <View style={styles.loopItemGrid}>
            {miningLoopItems.map(item => (
              <Pressable
                key={item.key}
                onPress={() => {
                  if (item.key === 'tasks') {
                    navigate('Tasks');
                  } else if (item.key === 'squad') {
                    navigate('Squads');
                  }
                }}
                style={[styles.loopItem, item.done && styles.loopItemDone]}>
                <View
                  style={[
                    styles.loopItemIcon,
                    item.done && styles.loopItemIconDone,
                  ]}>
                  <MaterialCommunityIcons
                    name={item.done ? 'check' : item.icon}
                    size={18}
                    color={
                      item.done ? theme.colors.background : theme.colors.primary
                    }
                  />
                </View>
                <Text style={styles.loopItemLabel}>{item.label}</Text>
                <Text
                  style={[
                    styles.loopItemValue,
                    item.active && styles.loopItemValueActive,
                  ]}>
                  {item.value}
                </Text>
              </Pressable>
            ))}
          </View>
        </GlassCard>
      </CardEntrance>

      <CardEntrance>
        <GlassCard style={styles.heroCard}>
          <SectionEyebrow text="Mining Session" />
          <Text style={styles.heroBalanceLabel}>Available balance</Text>
          <Text style={styles.heroBalanceValue}>
            {walletBalance === null ? '--' : formatAmount(walletBalance)}
            <Text style={styles.heroBalanceUnit}> BRC</Text>
          </Text>
          <Text style={styles.heroBalanceMeta}>
            Verified{' '}
            {verifiedBalance === null ? '--' : formatAmount(verifiedBalance)}{' '}
            BRC and locked{' '}
            {lockedBalance === null ? '--' : formatAmount(lockedBalance)} BRC
          </Text>

          <View style={styles.timerShell}>
            <Animated.View
              style={[
                styles.timerPulse,
                {
                  opacity: pulseOpacity,
                  transform: [{scale: pulseScale}],
                },
              ]}
            />
            <Animated.View
              style={[
                styles.timerRing,
                {
                  transform: [{scale: isButtonEnabled ? 1 : pulseScale}],
                },
              ]}>
              <View style={styles.timerCore}>
                <Text style={styles.timerLabel}>Next cycle</Text>
                <Text style={styles.timerValue}>
                  {isButtonEnabled ? '00:00:00' : formatTime(timeLeft)}
                </Text>
                <Text style={styles.timerSubLabel}>Base rate 1.0 BRC/hr</Text>
                <Text style={styles.timerRewardLabel}>
                  +
                  {Number.isFinite(miningReward)
                    ? miningReward.toFixed(4)
                    : '1.5000'}{' '}
                  BRC/cycle
                </Text>
              </View>
            </Animated.View>
          </View>

          <View style={styles.metricsRow}>
            <View style={styles.metricCard}>
              <Text style={styles.metricLabel}>Reward</Text>
              <Text style={styles.metricValue}>
                {Number.isFinite(miningReward)
                  ? miningReward.toFixed(2)
                  : '1.50'}{' '}
                BRC
              </Text>
            </View>
            <View style={styles.metricCard}>
              <Text style={styles.metricLabel}>Paid boost</Text>
              <Text style={styles.metricValue}>
                {paidBoostPercent === null ? '--' : paidBoostPercent.toFixed(0)}
                %
              </Text>
            </View>
            <View style={styles.metricCard}>
              <Text style={styles.metricLabel}>Network</Text>
              <Text style={styles.metricValue}>
                {teamBoostPercent === null ? '--' : teamBoostPercent.toFixed(0)}
                %
              </Text>
            </View>
          </View>

          <Pressable
            accessibilityRole="button"
            accessibilityLabel={
              isStartingMining
                ? 'Starting mining'
                : isButtonEnabled
                ? 'Start mining now'
                : `Mining ready in ${countdownLabel}`
            }
            onPress={
              isButtonEnabled && !isStartingMining
                ? handleStartMining
                : undefined
            }
            disabled={!isButtonEnabled || isStartingMining}
            style={({pressed}) => [
              styles.miningButton,
              (!isButtonEnabled || isStartingMining) &&
                styles.miningButtonDisabled,
              pressed && isButtonEnabled && styles.miningButtonPressed,
            ]}>
            {isStartingMining ? (
              <ActivityIndicator size="small" color="#7D8897" />
            ) : (
              <MaterialCommunityIcons
                name="pickaxe"
                size={18}
                color={
                  isButtonEnabled && !isStartingMining ? '#04111D' : '#7D8897'
                }
              />
            )}
            <Text
              style={[
                styles.miningButtonText,
                (!isButtonEnabled || isStartingMining) &&
                  styles.miningButtonTextDisabled,
              ]}>
              {isStartingMining
                ? 'Starting mining...'
                : isButtonEnabled
                ? 'Start mining now'
                : countdownLabel}
            </Text>
          </Pressable>

          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Open Blockrium Boost plans"
            onPress={() => navigate('BoostStore')}
            style={styles.heroBoostCta}>
            <View style={styles.heroBoostIcon}>
              <MaterialCommunityIcons
                name="rocket-launch-outline"
                size={20}
                color={theme.colors.primary}
              />
            </View>
            <View style={styles.heroBoostCopy}>
              <Text style={styles.heroBoostTitle}>
                {paidBoostPercent > 0
                  ? 'Paid boost active'
                  : 'Boost your mining rate'}
              </Text>
              <Text style={styles.heroBoostSubtitle} numberOfLines={2}>
                {paidBoostPercent > 0
                  ? `${paidBoostPercent}% extra mining rate is active.`
                  : 'Compare plans and see how each one increases your cycle rate.'}
              </Text>
            </View>
            <Ionicons name="chevron-forward" size={18} color="#607188" />
          </Pressable>
        </GlassCard>
      </CardEntrance>

      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Open mining history"
        style={({pressed}) => [
          styles.historyPill,
          pressed && styles.historyPillPressed,
        ]}
        onPress={() => setShowPopup(true)}>
        <Ionicons name="time-outline" size={16} color={theme.colors.text} />
        <Text style={styles.historyPillText}>Mining history</Text>
      </Pressable>

      <Pressable onPress={() => navigate('Squads')}>
        <GlassCard style={styles.squadMiningCard}>
          <View style={styles.squadMiningHeader}>
            <View style={styles.squadMiningIcon}>
              <MaterialCommunityIcons
                name="account-group-outline"
                size={20}
                color={theme.colors.background}
              />
            </View>
            <View style={styles.squadMiningCopy}>
              <SectionEyebrow text="Squad contribution" />
              <Text style={styles.squadMiningTitle} numberOfLines={1}>
                {miningSquad
                  ? miningSquad.name
                  : 'Mining works better with a squad'}
              </Text>
              <Text style={styles.squadMiningText} numberOfLines={2}>
                {miningSquad
                  ? squadMission?.title ||
                    'Your validated activity helps unlock the weekly squad chest.'
                  : 'Join a squad to turn mining, streaks, lessons, and invites into weekly team progress.'}
              </Text>
            </View>
            <Ionicons
              name="chevron-forward"
              size={18}
              color={theme.colors.textSoft}
            />
          </View>
          {miningSquad ? (
            <>
              <View style={styles.squadMiningProgressTrack}>
                <View
                  style={[
                    styles.squadMiningProgressFill,
                    {width: `${squadProgressPercent}%`},
                  ]}
                />
              </View>
              <View style={styles.squadMiningStats}>
                <Text style={styles.squadMiningStatText}>
                  {Math.round(miningSquad.weeklyPoints).toLocaleString()} /{' '}
                  {Math.round(miningSquad.weeklyTargetPoints).toLocaleString()}{' '}
                  pts
                </Text>
                <Text style={styles.squadMiningStatText}>
                  {miningSquad.communityBoostPercent}% boost
                </Text>
              </View>
            </>
          ) : null}
        </GlassCard>
      </Pressable>

      <AppodealBannerSlot />

      <GlassCard style={styles.rewardAdCard}>
        <View style={styles.sectionHeader}>
          <View>
            <Text style={styles.sectionTitle}>Reward sponsor</Text>
            <Text style={styles.sectionCaption}>
              Native placement for streak and rewards
            </Text>
          </View>
          <View style={styles.sectionBadge}>
            <Text style={styles.sectionBadgeText}>Ad</Text>
          </View>
        </View>
        <SponsoredBanner
          imageUri={bannerImage}
          fallbackUrl="https://www.mexc.com/en-IN/landings/Blockrium?inviteCode=mexc-2ffmJ"
          placement="default"
          style={styles.banner}
        />
        <AppodealBannerSlot placement="mining_reward" />
        <PrimaryButton
          label={
            isClaimingAdReward ? 'Crediting reward...' : 'Watch ad for +1 BRC'
          }
          icon="play-circle-outline"
          loading={isClaimingAdReward}
          disabled={isClaimingAdReward}
          onPress={handleClaimAdReward}
          style={styles.adRewardButton}
        />
      </GlassCard>

      <GlassCard style={styles.sectionCard}>
        <View style={styles.sectionHeader}>
          <View>
            <Text style={styles.sectionTitle}>Reward calendar</Text>
            <Text style={styles.sectionCaption}>
              {completedDays}/{maxStreakDays} day streak, 7-day chest{' '}
              {chest?.claimed
                ? 'claimed'
                : chest?.unlocked
                ? 'unlocked'
                : 'waiting'}
            </Text>
          </View>
          <View style={styles.sectionBadge}>
            <Text style={styles.sectionBadgeText}>
              {chest?.can_claim ? 'Chest ready' : 'Active'}
            </Text>
          </View>
        </View>
        <View style={styles.streakRow}>
          {streak.map(day => (
            <View key={day.key} style={styles.streakItem}>
              <View
                style={[
                  styles.streakDot,
                  (day.state === 'claimed' || day.state === 'done') &&
                    styles.streakDotDone,
                  day.state === 'ready' && styles.streakDotReady,
                  day.state === 'today' && styles.streakDotToday,
                  day.state === 'locked' && styles.streakDotLocked,
                  day.isChestDay && styles.streakDotChest,
                ]}>
                {day.isChestDay &&
                day.state !== 'claimed' &&
                day.state !== 'done' ? (
                  <MaterialCommunityIcons
                    name="treasure-chest-outline"
                    size={14}
                    color={
                      day.state === 'locked'
                        ? theme.colors.textSoft
                        : theme.colors.accentWarm
                    }
                  />
                ) : day.state === 'claimed' || day.state === 'done' ? (
                  <Ionicons name="checkmark" size={12} color="#04111D" />
                ) : (
                  <Text
                    style={[
                      styles.streakDotText,
                      (day.state === 'future' || day.state === 'locked') &&
                        styles.streakDotTextFuture,
                      day.state === 'ready' && styles.streakDotTextReady,
                    ]}>
                    {day.label}
                  </Text>
                )}
              </View>
              <Text style={styles.streakLabel}>{day.title}</Text>
            </View>
          ))}
        </View>
        <PrimaryButton
          label={
            isClaimingStreak
              ? 'Claiming streak...'
              : !hasStreakResponse
              ? isRewardStatusLoading
                ? 'Loading streak...'
                : 'Streak unavailable'
              : hasRemainingStreakReward
              ? 'Claim today'
              : 'Claimed today'
          }
          icon="calendar-check-outline"
          loading={isClaimingStreak}
          disabled={isClaimingStreak || !hasRemainingStreakReward}
          onPress={handleClaimStreak}
          style={styles.streakButton}
        />
        <View style={styles.chestPanel}>
          <View style={styles.chestIconShell}>
            <MaterialCommunityIcons
              name={
                chest?.claimed ? 'treasure-chest' : 'treasure-chest-outline'
              }
              size={24}
              color={
                chest?.can_claim
                  ? theme.colors.accentWarm
                  : theme.colors.primary
              }
            />
          </View>
          <View style={styles.chestCopy}>
            <Text style={styles.chestTitle}>7-day streak chest</Text>
            <Text style={styles.chestMeta}>
              {chest?.claimed
                ? 'Chest reward has been claimed.'
                : chest?.can_claim
                ? `Unlock ${chest?.reward_label ?? '5.00 BRC'} now.`
                : 'Claim 7 daily streaks to unlock the chest.'}
            </Text>
          </View>
          <Pressable
            disabled={!chest?.can_claim || isClaimingChest}
            onPress={handleClaimChest}
            style={[
              styles.chestButton,
              (!chest?.can_claim || isClaimingChest) &&
                styles.chestButtonDisabled,
            ]}>
            {isClaimingChest ? (
              <ActivityIndicator size="small" color={theme.colors.text} />
            ) : (
              <Text
                style={[
                  styles.chestButtonText,
                  (!chest?.can_claim || isClaimingChest) &&
                    styles.chestButtonTextDisabled,
                ]}>
                {chest?.claimed ? 'Claimed' : 'Claim'}
              </Text>
            )}
          </Pressable>
        </View>
        {visibleCalendarDays.length > 0 ? (
          <View style={styles.calendarGrid}>
            {visibleCalendarDays.slice(0, 30).map(day => (
              <View
                key={day.day}
                style={[
                  styles.calendarDay,
                  day.state === 'claimed' && styles.calendarDayClaimed,
                  day.state === 'ready' && styles.calendarDayReady,
                  day.state === 'today' && styles.calendarDayToday,
                  day.state === 'locked' && styles.calendarDayLocked,
                  day.is_chest_day && styles.calendarDayChest,
                ]}>
                <Text
                  style={[
                    styles.calendarDayNumber,
                    day.state === 'locked' && styles.calendarDayNumberMuted,
                    day.state === 'ready' && styles.calendarDayNumberReady,
                    day.state === 'today' && styles.calendarDayNumberToday,
                  ]}>
                  {day.day}
                </Text>
                {day.is_chest_day ? (
                  <MaterialCommunityIcons
                    name="treasure-chest-outline"
                    size={13}
                    color={theme.colors.accentWarm}
                  />
                ) : (
                  <Text
                    style={[
                      styles.calendarRewardText,
                      day.state === 'ready' && styles.calendarRewardTextReady,
                      day.state === 'today' && styles.calendarRewardTextToday,
                    ]}>
                    {day.reward_label?.replace('.00 BRC', '') ?? '1 BRC'}
                  </Text>
                )}
              </View>
            ))}
          </View>
        ) : null}
      </GlassCard>

      <GlassCard style={styles.sectionCard}>
        <View style={styles.sectionHeader}>
          <View>
            <Text style={styles.sectionTitle}>Today's tasks</Text>
            <Text style={styles.sectionCaption}>
              Backend tasks and reward actions
            </Text>
          </View>
          <Pressable onPress={() => navigate('Tasks')}>
            <Text style={styles.sectionLink}>See all</Text>
          </Pressable>
        </View>

        {miningTasks.length === 0 ? (
          <Text style={styles.sectionCaption}>
            {isTasksLoading
              ? 'Loading tasks...'
              : tasksQueryError ||
                tasksData?.success === false ||
                tasksData?.data?.success === false
              ? 'Tasks are temporarily unavailable.'
              : 'No tasks available right now.'}
          </Text>
        ) : null}

        {miningTasks.map((task: any) => (
          <Pressable
            key={task.id}
            onPress={() => navigate('Tasks')}
            style={styles.taskRow}>
            <View
              style={[styles.taskStatus, task.done && styles.taskStatusDone]}>
              {task.done ? (
                <Ionicons name="checkmark" size={12} color="#04111D" />
              ) : null}
            </View>
            <View style={styles.taskCopy}>
              <Text style={styles.taskTitle}>{task.title}</Text>
              <Text style={styles.taskReward}>{task.reward}</Text>
            </View>
            <View style={[styles.taskChip, task.done && styles.taskChipDone]}>
              <Text
                style={[
                  styles.taskChipText,
                  task.done && styles.taskChipTextDone,
                ]}>
                {task.done ? 'Done' : 'Open'}
              </Text>
            </View>
          </Pressable>
        ))}
      </GlassCard>

      <GlassCard style={styles.sectionCard}>
        <View style={styles.sectionHeader}>
          <View>
            <Text style={styles.sectionTitle}>Growth</Text>
            <Text style={styles.sectionCaption}>
              Referral and task shortcuts
            </Text>
          </View>
          <Pressable onPress={() => setShowPopup(true)}>
            <Text style={styles.sectionLink}>Open</Text>
          </Pressable>
        </View>
        <View style={styles.boostRow}>
          <Pressable
            style={styles.boostCard}
            onPress={() => navigate('Referral')}>
            <MaterialCommunityIcons
              name="account-multiple-outline"
              size={18}
              color={theme.colors.primary}
            />
            <Text style={styles.boostTitle}>Referral</Text>
            <Text style={styles.boostValue}>Invite network</Text>
            <Text style={styles.boostMeta}>
              Share your code and grow your mining network.
            </Text>
          </Pressable>
          <Pressable
            style={styles.boostCard}
            onPress={() => navigate('BoostStore')}>
            <MaterialCommunityIcons
              name="rocket-launch-outline"
              size={18}
              color={theme.colors.accentWarm}
            />
            <Text style={styles.boostTitle}>Paid boosts</Text>
            <Text style={styles.boostValue}>
              {paidBoostPercent !== null && paidBoostPercent > 0
                ? `+${paidBoostPercent}% active`
                : 'Shop plans'}
            </Text>
            <Text style={styles.boostMeta}>
              {paidBoostPercent !== null && paidBoostPercent > 0
                ? 'Your server-confirmed mining boost is active.'
                : 'Explore plans and check which options are currently available.'}
            </Text>
          </Pressable>
          <Pressable style={styles.boostCard} onPress={() => navigate('Tasks')}>
            <MaterialCommunityIcons
              name="clipboard-check-outline"
              size={18}
              color={theme.colors.accent}
            />
            <Text style={styles.boostTitle}>Tasks</Text>
            <Text style={styles.boostValue}>Claim rewards</Text>
            <Text style={styles.boostMeta}>
              Open tasks, complete missions, and claim rewards.
            </Text>
          </Pressable>
        </View>
      </GlassCard>

      <GlassCard style={styles.bannerCard}>
        <Text style={styles.bannerTitle}>Sponsored</Text>
        <SponsoredBanner
          imageUri={bannerImage}
          fallbackUrl="https://www.mexc.com/en-IN/landings/Blockrium?inviteCode=mexc-2ffmJ"
          placement="default"
          style={styles.banner}
        />
      </GlassCard>

      <AnimatedPopup
        showPopup={showPopup}
        onClose={() => setShowPopup(false)}
      />
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {
    paddingTop: 6,
    paddingBottom: 126,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 16,
  },
  headerCopy: {
    flex: 1,
    paddingRight: 12,
  },
  screenTitle: {
    color: theme.colors.text,
    fontSize: 34,
    lineHeight: 36,
    fontFamily: theme.typography.display,
  },
  screenSubtitle: {
    marginTop: 6,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 20,
    fontFamily: theme.typography.body,
  },
  historyPill: {
    minHeight: 40,
    borderRadius: theme.radii.pill,
    borderWidth: 1,
    borderColor: theme.colors.borderStrong,
    paddingHorizontal: 16,
    justifyContent: 'center',
    alignItems: 'center',
    alignSelf: 'flex-start',
    flexDirection: 'row',
    gap: 8,
    marginTop: 4,
    marginBottom: 10,
    backgroundColor: 'rgba(25, 36, 53, 0.72)',
  },
  historyPillPressed: {
    opacity: 0.78,
    transform: [{scale: 0.98}],
  },
  historyPillText: {
    color: theme.colors.text,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  loopCard: {
    marginBottom: 10,
    borderColor: 'rgba(120, 167, 255, 0.18)',
  },
  loopHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: 12,
  },
  loopTitle: {
    color: theme.colors.text,
    fontSize: 21,
    lineHeight: 25,
    fontFamily: theme.typography.heading,
  },
  loopSubtitle: {
    marginTop: 5,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  loopStatusPill: {
    minHeight: 32,
    borderRadius: theme.radii.pill,
    paddingHorizontal: 12,
    justifyContent: 'center',
    backgroundColor: 'rgba(120, 167, 255, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(120, 167, 255, 0.24)',
  },
  loopStatusPillReady: {
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
    borderColor: 'rgba(0, 214, 176, 0.24)',
  },
  loopStatusText: {
    color: theme.colors.primary,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  loopItemGrid: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 14,
  },
  loopItem: {
    flex: 1,
    minHeight: 92,
    borderRadius: theme.radii.lg,
    padding: 9,
    justifyContent: 'space-between',
    backgroundColor: 'rgba(14, 21, 31, 0.76)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  loopItemDone: {
    backgroundColor: 'rgba(93, 211, 158, 0.08)',
    borderColor: 'rgba(93, 211, 158, 0.22)',
  },
  loopItemIcon: {
    width: 30,
    height: 30,
    borderRadius: 15,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0, 214, 176, 0.11)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.2)',
  },
  loopItemIconDone: {
    backgroundColor: theme.colors.success,
    borderColor: theme.colors.success,
  },
  loopItemLabel: {
    marginTop: 8,
    color: theme.colors.textSoft,
    fontSize: 10,
    fontFamily: theme.typography.bodyBold,
  },
  loopItemValue: {
    color: theme.colors.text,
    fontSize: 13,
    fontFamily: theme.typography.heading,
  },
  loopItemValueActive: {
    color: theme.colors.primary,
  },
  squadMiningCard: {
    marginTop: 10,
    marginBottom: 10,
    borderColor: 'rgba(0, 214, 176, 0.15)',
  },
  squadMiningHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  squadMiningIcon: {
    width: 42,
    height: 42,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.primary,
  },
  squadMiningCopy: {
    flex: 1,
    minWidth: 0,
  },
  squadMiningTitle: {
    color: theme.colors.text,
    fontSize: 17,
    fontFamily: theme.typography.heading,
  },
  squadMiningText: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
  squadMiningProgressTrack: {
    height: 7,
    marginTop: 13,
    overflow: 'hidden',
    borderRadius: theme.radii.pill,
    backgroundColor: 'rgba(255, 255, 255, 0.08)',
  },
  squadMiningProgressFill: {
    height: '100%',
    borderRadius: theme.radii.pill,
    backgroundColor: theme.colors.primary,
  },
  squadMiningStats: {
    marginTop: 9,
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 10,
  },
  squadMiningStatText: {
    color: theme.colors.textSoft,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  heroCard: {
    borderColor: 'rgba(0, 214, 176, 0.16)',
  },
  heroBalanceLabel: {
    color: theme.colors.textSoft,
    fontSize: 12,
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    fontFamily: theme.typography.bodyMedium,
  },
  heroBalanceValue: {
    marginTop: 8,
    color: theme.colors.text,
    fontSize: 31,
    lineHeight: 35,
    fontFamily: theme.typography.display,
  },
  heroBalanceUnit: {
    color: theme.colors.textMuted,
    fontSize: 16,
    fontFamily: theme.typography.bodyBold,
  },
  heroBalanceMeta: {
    marginTop: 6,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  timerShell: {
    alignItems: 'center',
    marginTop: 16,
    marginBottom: 14,
    justifyContent: 'center',
  },
  timerPulse: {
    position: 'absolute',
    width: 188,
    height: 188,
    borderRadius: 94,
    backgroundColor: theme.colors.primary,
  },
  timerRing: {
    width: 170,
    height: 170,
    borderRadius: 85,
    padding: 12,
    backgroundColor: 'rgba(0, 214, 176, 0.11)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.25)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  timerCore: {
    width: '100%',
    height: '100%',
    borderRadius: 73,
    backgroundColor: 'rgba(5, 7, 10, 0.94)',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: 'rgba(155, 174, 205, 0.16)',
    paddingHorizontal: 16,
  },
  timerLabel: {
    color: theme.colors.textSoft,
    fontSize: 12,
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    fontFamily: theme.typography.bodyMedium,
  },
  timerValue: {
    marginTop: 8,
    color: theme.colors.text,
    fontSize: 25,
    fontFamily: theme.typography.display,
  },
  timerSubLabel: {
    marginTop: 6,
    color: theme.colors.accent,
    fontSize: 12,
    textAlign: 'center',
    fontFamily: theme.typography.bodyBold,
  },
  timerRewardLabel: {
    marginTop: 4,
    color: theme.colors.primary,
    fontSize: 12,
    textAlign: 'center',
    fontFamily: theme.typography.bodyBold,
  },
  metricsRow: {
    flexDirection: 'row',
    gap: 8,
  },
  metricCard: {
    flex: 1,
    borderRadius: theme.radii.md,
    backgroundColor: 'rgba(14, 21, 31, 0.74)',
    borderWidth: 1,
    borderColor: theme.colors.border,
    paddingVertical: 12,
    paddingHorizontal: 10,
  },
  metricLabel: {
    color: theme.colors.textSoft,
    fontSize: 11,
    fontFamily: theme.typography.body,
  },
  metricValue: {
    marginTop: 6,
    color: theme.colors.text,
    fontSize: 14,
    fontFamily: theme.typography.bodyBold,
  },
  miningButton: {
    marginTop: 16,
    minHeight: 52,
    borderRadius: theme.radii.pill,
    backgroundColor: theme.colors.primary,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  miningButtonDisabled: {
    backgroundColor: 'rgba(155, 174, 205, 0.18)',
  },
  miningButtonPressed: {
    opacity: 0.9,
    transform: [{scale: 0.985}],
  },
  miningButtonText: {
    color: '#04111D',
    fontSize: 15,
    fontFamily: theme.typography.bodyBold,
  },
  miningButtonTextDisabled: {
    color: '#6F7B88',
  },
  heroBoostCta: {
    marginTop: 12,
    minHeight: 60,
    borderRadius: theme.radii.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(14, 21, 31, 0.78)',
    paddingHorizontal: 14,
    paddingVertical: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  heroBoostIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0, 214, 176, 0.14)',
  },
  heroBoostCopy: {
    flex: 1,
    minWidth: 0,
  },
  heroBoostTitle: {
    color: theme.colors.text,
    fontSize: 14,
    fontFamily: theme.typography.bodyBold,
  },
  heroBoostSubtitle: {
    marginTop: 3,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 16,
    fontFamily: theme.typography.body,
  },
  sectionCard: {
    marginTop: 10,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  sectionTitle: {
    color: theme.colors.text,
    fontSize: 19,
    fontFamily: theme.typography.heading,
  },
  sectionCaption: {
    marginTop: 4,
    color: theme.colors.textSoft,
    fontSize: 13,
    fontFamily: theme.typography.body,
  },
  sectionBadge: {
    minHeight: 28,
    borderRadius: 14,
    paddingHorizontal: 12,
    backgroundColor: 'rgba(47, 224, 196, 0.14)',
    justifyContent: 'center',
  },
  sectionBadgeText: {
    color: theme.colors.primary,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  sectionLink: {
    color: theme.colors.primary,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
  },
  streakRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  streakItem: {
    alignItems: 'center',
    width: '13%',
  },
  streakDot: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(120, 167, 255, 0.12)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  streakDotDone: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  streakDotReady: {
    backgroundColor: 'rgba(244, 183, 116, 0.18)',
    borderColor: 'rgba(244, 183, 116, 0.42)',
  },
  streakDotToday: {
    backgroundColor: '#152A46',
    borderColor: theme.colors.primary,
  },
  streakDotLocked: {
    backgroundColor: 'rgba(120, 167, 255, 0.07)',
    borderColor: 'rgba(120, 167, 255, 0.14)',
  },
  streakDotChest: {
    borderColor: 'rgba(244, 183, 116, 0.38)',
  },
  streakDotText: {
    color: theme.colors.text,
    fontSize: 9,
    fontFamily: theme.typography.bodyBold,
  },
  streakDotTextFuture: {
    color: theme.colors.textSoft,
  },
  streakDotTextReady: {
    color: theme.colors.accentWarm,
  },
  streakLabel: {
    marginTop: 6,
    color: theme.colors.textMuted,
    fontSize: 10,
    fontFamily: theme.typography.body,
  },
  streakButton: {
    marginTop: 14,
  },
  chestPanel: {
    marginTop: 12,
    borderRadius: theme.radii.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(18, 39, 66, 0.58)',
    padding: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  chestIconShell: {
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(244, 183, 116, 0.13)',
    borderWidth: 1,
    borderColor: 'rgba(244, 183, 116, 0.25)',
  },
  chestCopy: {
    flex: 1,
    minWidth: 0,
  },
  chestTitle: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.bodyBold,
  },
  chestMeta: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 16,
    fontFamily: theme.typography.body,
  },
  chestButton: {
    minHeight: 36,
    minWidth: 72,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 12,
    backgroundColor: theme.colors.primary,
  },
  chestButtonDisabled: {
    backgroundColor: 'rgba(120, 167, 255, 0.13)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  chestButtonText: {
    color: theme.colors.background,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  chestButtonTextDisabled: {
    color: theme.colors.textSoft,
  },
  calendarGrid: {
    marginTop: 12,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
  },
  calendarDay: {
    width: '12%',
    minWidth: 34,
    aspectRatio: 0.95,
    borderRadius: 11,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(120, 167, 255, 0.08)',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 5,
  },
  calendarDayClaimed: {
    backgroundColor: 'rgba(47, 224, 196, 0.16)',
    borderColor: 'rgba(47, 224, 196, 0.28)',
  },
  calendarDayReady: {
    backgroundColor: 'rgba(244, 183, 116, 0.14)',
    borderColor: 'rgba(244, 183, 116, 0.35)',
  },
  calendarDayToday: {
    backgroundColor: 'rgba(47, 224, 196, 0.10)',
    borderColor: 'rgba(47, 224, 196, 0.42)',
  },
  calendarDayLocked: {
    backgroundColor: 'rgba(120, 167, 255, 0.04)',
    opacity: 0.72,
  },
  calendarDayChest: {
    borderColor: 'rgba(244, 183, 116, 0.35)',
  },
  calendarDayNumber: {
    color: theme.colors.text,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  calendarDayNumberMuted: {
    color: theme.colors.textSoft,
  },
  calendarDayNumberReady: {
    color: theme.colors.accentWarm,
  },
  calendarDayNumberToday: {
    color: theme.colors.primary,
  },
  calendarRewardText: {
    marginTop: 3,
    color: theme.colors.textMuted,
    fontSize: 8,
    fontFamily: theme.typography.body,
  },
  calendarRewardTextReady: {
    color: theme.colors.accentWarm,
  },
  calendarRewardTextToday: {
    color: theme.colors.primary,
  },
  taskRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  taskStatus: {
    width: 24,
    height: 24,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: theme.colors.borderStrong,
    marginRight: 12,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(120, 167, 255, 0.08)',
  },
  taskStatusDone: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  taskCopy: {
    flex: 1,
  },
  taskTitle: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.bodyBold,
  },
  taskReward: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  taskChip: {
    minHeight: 30,
    borderRadius: 15,
    paddingHorizontal: 12,
    justifyContent: 'center',
    backgroundColor: 'rgba(120, 167, 255, 0.12)',
  },
  taskChipDone: {
    backgroundColor: 'rgba(93, 211, 158, 0.14)',
  },
  taskChipText: {
    color: theme.colors.accent,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  taskChipTextDone: {
    color: theme.colors.success,
  },
  boostRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  boostCard: {
    flex: 1,
    minWidth: '30%',
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(18, 39, 66, 0.42)',
    padding: 14,
  },
  boostTitle: {
    marginTop: 12,
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.bodyBold,
  },
  boostValue: {
    marginTop: 6,
    color: theme.colors.primary,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
  },
  boostMeta: {
    marginTop: 6,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  bannerCard: {
    marginTop: 10,
  },
  rewardAdCard: {
    marginTop: 10,
    gap: 12,
  },
  adRewardButton: {
    alignSelf: 'stretch',
  },
  bannerTitle: {
    color: theme.colors.text,
    fontSize: 20,
    fontFamily: theme.typography.heading,
  },
  bannerText: {
    marginTop: 8,
    marginBottom: 14,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  banner: {
    width: '100%',
    minHeight: 132,
    alignSelf: 'stretch',
  },
});

export default MiningOrg;
