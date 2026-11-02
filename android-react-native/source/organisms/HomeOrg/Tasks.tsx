import React, {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import {
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import {useQueryClient} from 'react-query';
import Ionicons from 'react-native-vector-icons/Ionicons';
import {
  AppScreen,
  GlassCard,
  PrimaryButton,
  ScreenHeader,
  SectionEyebrow,
} from '../../design/primitives';
import theme from '../../design/theme';
import {callQuery} from '../../hooks';
import {invalidateEngagementQueries} from '../../hooks/useEngagement';
import {useThisAlert} from '../../hooks/AlertContext';
import AppodealBannerSlot from '../../molecules/AppodealBannerSlot';
import SponsoredBanner from '../../molecules/SponsoredBanner';
import TaskPopup from '../../molecules/TaskPopup';
import {logAppIssue} from '../../services/appIssueLogger';
import {showOptionalActionAd} from '../../services/adService';
import {showRewardNotification} from '../../services/miningReminderService';
import {queueEngagementEvent} from '../../services/engagementAnalyticsService';
import {fetchTaskList} from '../../services/taskListService';
import {postApi} from '../../storage/server';
import {navigate as navigateRoot} from '../../utils/helper';
import {getStorageId} from '../../utils/mmkvStorage';

type OnboardingQuestStep = {
  key: string;
  title: string;
  description?: string;
  action?: string;
  completed: boolean;
};

type OnboardingQuest = {
  title: string;
  subtitle?: string;
  reward_amount?: number | string;
  reward_label?: string;
  steps?: OnboardingQuestStep[];
  completed_count?: number;
  total_steps?: number;
  progress_percent?: number;
  is_completed?: boolean;
  claimed?: boolean;
  can_claim?: boolean;
};

type TaskCategoryLabel =
  | 'Daily'
  | 'Social'
  | 'Referral'
  | 'Mining'
  | 'Partner'
  | 'Learn';
type TaskCategoryFilter = 'All' | TaskCategoryLabel;
type TaskStatusLabel = 'Start' | 'Verify' | 'Claim' | 'Claimed' | 'Failed';

type TaskRow =
  | {kind: 'task'; item: any}
  | {kind: 'ad'; id: string}
  | {kind: 'completedHeader'; id: string}
  | {kind: 'completedTask'; item: any}
  | {kind: 'empty'; id: string; title: string; subtitle: string; icon: string}
  | {kind: 'skeleton'; id: string};

const CATEGORY_FILTERS: TaskCategoryFilter[] = [
  'All',
  'Daily',
  'Social',
  'Referral',
  'Mining',
  'Partner',
  'Learn',
];

const isObject = (value: unknown): value is Record<string, any> =>
  Boolean(value && typeof value === 'object' && !Array.isArray(value));

const asArray = (value: unknown): any[] => (Array.isArray(value) ? value : []);

const asNumber = (value: unknown, fallback = 0) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
};

const asString = (value: unknown, fallback = '') => {
  if (typeof value === 'string') {
    return value.trim() || fallback;
  }

  if (typeof value === 'number' && Number.isFinite(value)) {
    return String(value);
  }

  return fallback;
};

const asBoolean = (value: unknown) =>
  value === true || value === 1 || value === '1' || value === 'true';

const isOnboardingQuestPayload = (value: any): value is OnboardingQuest =>
  Boolean(
    value &&
      typeof value === 'object' &&
      typeof value.title === 'string' &&
      Array.isArray(value.steps),
  );

const getNestedPayload = (response: any) =>
  response?.data?.data ??
  response?.data?.userData ??
  response?.data ??
  response;

const getTasksFromResponse = (response: any) => {
  if (!response?.success) {
    return [];
  }

  const body = response?.data;
  const candidates = [
    body?.data,
    body?.tasks,
    body?.userData,
    body?.data?.data,
    body?.data?.tasks,
    body,
  ];

  return candidates.find(Array.isArray) ?? [];
};

const dedupeTasks = (tasks: any[]) => {
  const seen = new Set<string>();

  return tasks.filter(task => {
    if (!isObject(task)) {
      return false;
    }

    const key = asString(
      task.id ?? task.task_id ?? task.taskId ?? task.task_name ?? task.title,
      `task-${seen.size}`,
    );

    if (seen.has(key)) {
      return false;
    }

    seen.add(key);
    return true;
  });
};

const isTaskClaimed = (task: any) => {
  const status = asString(
    task?.status ?? task?.claim_status ?? task?.task_status,
  ).toLowerCase();

  return (
    asBoolean(task?.is_claimed ?? task?.isClaimed ?? task?.claimed) ||
    status === 'claimed' ||
    status === 'completed'
  );
};

const isTaskFailed = (task: any) => {
  const status = asString(
    task?.status ?? task?.claim_status ?? task?.task_status,
  ).toLowerCase();
  return status === 'failed' || status === 'rejected';
};

const canTaskClaim = (task: any) =>
  asBoolean(task?.can_claim ?? task?.canClaim ?? task?.eligible_to_claim) ||
  asString(task?.status).toLowerCase() === 'ready_to_claim';

const getTaskStatus = (task: any): TaskStatusLabel => {
  if (isTaskClaimed(task)) {
    return 'Claimed';
  }

  if (isTaskFailed(task)) {
    return 'Failed';
  }

  if (canTaskClaim(task)) {
    return 'Claim';
  }

  if (asBoolean(task?.started ?? task?.opened ?? task?.verification_pending)) {
    return 'Verify';
  }

  return 'Start';
};

const getTaskRewardAmount = (task: any) =>
  asNumber(
    task?.coin_reward ??
      task?.reward_amount ??
      task?.reward ??
      task?.brc_reward ??
      task?.amount,
  );

const getTaskRewardLabel = (task: any) => {
  const rewardLabel = asString(task?.reward_label ?? task?.rewardLabel);
  if (rewardLabel) {
    return rewardLabel;
  }

  const reward = getTaskRewardAmount(task);
  return reward > 0 ? `${reward} BRC` : 'Reward';
};

const getTaskTitle = (task: any) =>
  asString(task?.task_name ?? task?.title ?? task?.name, 'Blockrium task');

const getTaskDescription = (task: any) =>
  asString(
    task?.description ?? task?.task_description ?? task?.subtitle,
    'Open the activity, complete it, then return to claim.',
  );

const getTaskCategory = (task: any) => {
  const value = `${task?.category ?? ''} ${task?.task_name ?? ''} ${
    task?.title ?? ''
  } ${task?.task_type ?? ''} ${task?.type ?? ''} ${
    task?.task_link ?? ''
  }`.toLowerCase();

  if (
    value.includes('partner') ||
    value.includes('mexc') ||
    value.includes('sponsor') ||
    value.includes('offer') ||
    value.includes('exchange')
  ) {
    return {label: 'Partner' as TaskCategoryLabel, icon: 'sparkles-outline'};
  }

  if (value.includes('refer') || value.includes('invite')) {
    return {label: 'Referral' as TaskCategoryLabel, icon: 'person-add-outline'};
  }

  if (
    value.includes('telegram') ||
    value.includes('social') ||
    value.includes('follow') ||
    value.includes('join') ||
    value.includes('x.com') ||
    value.includes('twitter') ||
    value.includes('youtube') ||
    value.includes('instagram')
  ) {
    return {label: 'Social' as TaskCategoryLabel, icon: 'share-social-outline'};
  }

  if (
    value.includes('mine') ||
    value.includes('mining') ||
    value.includes('streak')
  ) {
    return {label: 'Mining' as TaskCategoryLabel, icon: 'flash-outline'};
  }

  if (
    value.includes('learn') ||
    value.includes('quiz') ||
    value.includes('read')
  ) {
    return {label: 'Learn' as TaskCategoryLabel, icon: 'school-outline'};
  }

  return {label: 'Daily' as TaskCategoryLabel, icon: 'calendar-clear-outline'};
};

const getFriendlyApiMessage = (value: unknown, fallback: string) => {
  const message = asString(value);

  if (!message) {
    return fallback;
  }

  if (
    /sqlstate|unknown column|column not found|laravel|whoops|<!doctype|<html/i.test(
      message,
    )
  ) {
    return fallback;
  }

  return message;
};

const Tasks = () => {
  const navigation = useNavigation<any>();
  const queryClient = useQueryClient();
  const {showAlert} = useThisAlert();
  const [userId, setUserId] = useState<number | undefined>();
  const [isClaimingQuest, setIsClaimingQuest] = useState(false);
  const [isStarterQuestResolved, setIsStarterQuestResolved] = useState(false);
  const [tab, setTab] = useState<'tasks' | 'friends'>('tasks');
  const [selectedCategory, setSelectedCategory] =
    useState<TaskCategoryFilter>('All');
  const [showPopUp, setShowPopUp] = useState(false);
  const [popUpData, setPopUpData] = useState<any>({});
  const taskScreenTrackedRef = useRef(false);
  const taskFailureLoggedRef = useRef(false);

  const {
    data: tasksData,
    error: tasksError,
    isLoading: isTasksLoading,
    isFetching: isTasksFetching,
    refetch: refetchTasks,
  }: any = callQuery('auth/get-task', {
    queryFn: ({signal}: {signal?: AbortSignal}) => fetchTaskList(signal),
    retry: 0,
    staleTime: 60000,
    cacheTime: 5 * 60 * 1000,
    keepPreviousData: true,
  });
  const {
    data: onboardingQuestData,
    isFetching: isQuestFetching,
    refetch: refetchOnboardingQuest,
  }: any = callQuery('auth/onboarding-quest', {
    retry: 0,
    staleTime: 60000,
    cacheTime: 5 * 60 * 1000,
    keepPreviousData: true,
  });
  const {data: bannerData}: any = callQuery('banners', {
    retry: 0,
    staleTime: 120000,
  });
  const {
    data: referralData,
    isLoading: isReferralLoading,
    isFetching: isReferralFetching,
    refetch: refetchReferrals,
  }: any = callQuery(`top-refferals?userId=${userId ?? ''}`, {
    enabled: tab === 'friends' && userId !== undefined,
    retry: 0,
    staleTime: 60000,
    keepPreviousData: true,
  });

  useEffect(() => {
    const hydrateUserId = async () => {
      const storedUserId = await getStorageId('userId');
      setUserId(storedUserId);
    };

    hydrateUserId();
  }, []);

  const taskList = useMemo(
    () => dedupeTasks(getTasksFromResponse(tasksData)),
    [tasksData],
  );
  const friends = useMemo(() => {
    if (!referralData?.success) {
      return [];
    }

    return asArray(
      referralData?.data?.data?.top50Miners ??
        referralData?.data?.top50Miners ??
        referralData?.data?.data,
    );
  }, [referralData]);

  const rawOnboardingQuest = getNestedPayload(onboardingQuestData);
  const onboardingQuest: OnboardingQuest | undefined = isOnboardingQuestPayload(
    rawOnboardingQuest,
  )
    ? rawOnboardingQuest
    : undefined;
  const questSteps = asArray(onboardingQuest?.steps) as OnboardingQuestStep[];
  const firstIncompleteQuestStep = questSteps.find(step => !step.completed);
  const questRewardLabel =
    onboardingQuest?.reward_label ??
    `${asNumber(onboardingQuest?.reward_amount, 5).toFixed(2)} BRC`;
  const questProgressPercent = Math.min(
    100,
    Math.max(0, asNumber(onboardingQuest?.progress_percent)),
  );
  const completedQuestCount = asNumber(onboardingQuest?.completed_count);
  const totalQuestSteps = asNumber(
    onboardingQuest?.total_steps,
    questSteps.length,
  );
  const shouldShowOnboardingQuest = Boolean(
    onboardingQuest && !onboardingQuest.claimed && !isStarterQuestResolved,
  );

  const pendingTasks = useMemo(
    () => taskList.filter(item => !isTaskClaimed(item)),
    [taskList],
  );
  const completedTasks = useMemo(
    () => taskList.filter(item => isTaskClaimed(item)),
    [taskList],
  );
  const visiblePendingTasks = useMemo(
    () =>
      selectedCategory === 'All'
        ? pendingTasks
        : pendingTasks.filter(
            task => getTaskCategory(task).label === selectedCategory,
          ),
    [pendingTasks, selectedCategory],
  );
  const totalRewardAvailable = useMemo(
    () =>
      pendingTasks.reduce((sum, task) => sum + getTaskRewardAmount(task), 0),
    [pendingTasks],
  );
  const taskProgressPercent = taskList.length
    ? Math.round((completedTasks.length / taskList.length) * 100)
    : 0;
  const taskCategoryFilters = useMemo(
    () =>
      CATEGORY_FILTERS.map(category => ({
        label: category,
        count:
          category === 'All'
            ? pendingTasks.length
            : pendingTasks.filter(
                task => getTaskCategory(task).label === category,
              ).length,
      })),
    [pendingTasks],
  );

  const listData = useMemo<TaskRow[]>(() => {
    if (tab === 'friends') {
      if (isReferralLoading && friends.length === 0) {
        return Array.from({length: 5}, (_, index) => ({
          kind: 'skeleton',
          id: `friend-skeleton-${index}`,
        }));
      }

      if (friends.length === 0) {
        return [
          {
            kind: 'empty',
            id: 'friends-empty',
            icon: 'people-outline',
            title: 'No referral activity yet',
            subtitle:
              'Invite more people and your referral network will appear here.',
          },
        ];
      }

      return friends.map(item => ({kind: 'completedTask', item}));
    }

    if (isTasksLoading && taskList.length === 0) {
      return Array.from({length: 6}, (_, index) => ({
        kind: 'skeleton',
        id: `task-skeleton-${index}`,
      }));
    }

    if (taskList.length === 0) {
      return [
        {
          kind: 'empty',
          id: 'tasks-empty',
          icon: 'checkmark-done-outline',
          title: tasksError ? 'Tasks unavailable' : 'No tasks yet',
          subtitle: tasksError
            ? 'We could not sync missions right now. Pull to refresh or retry.'
            : 'New tasks will appear here once the backend sends them.',
        },
      ];
    }

    const rows: TaskRow[] =
      visiblePendingTasks.length > 0
        ? visiblePendingTasks.map(item => ({kind: 'task', item}))
        : [
            {
              kind: 'empty',
              id: 'category-empty',
              icon: 'albums-outline',
              title: `No ${selectedCategory.toLowerCase()} tasks ready`,
              subtitle:
                'Try another category or come back when new tasks are published.',
            },
          ];

    if (visiblePendingTasks.length >= 4) {
      rows.splice(4, 0, {kind: 'ad', id: 'task-mid-ad'});
    }

    if (completedTasks.length > 0) {
      rows.push({kind: 'completedHeader', id: 'completed-header'});
      completedTasks
        .slice(0, 8)
        .forEach(item => rows.push({kind: 'completedTask', item}));
    }

    return rows;
  }, [
    completedTasks,
    friends,
    isReferralLoading,
    isTasksLoading,
    selectedCategory,
    tab,
    taskList.length,
    tasksError,
    visiblePendingTasks,
  ]);

  const bannerImage =
    bannerData?.data?.userData?.[0]?.image ??
    bannerData?.data?.banners?.image_url ??
    bannerData?.data?.banners?.image ??
    bannerData?.data?.data?.[0]?.image;

  useEffect(() => {
    if (onboardingQuest?.claimed) {
      setIsStarterQuestResolved(true);
    }
  }, [onboardingQuest?.claimed]);

  useEffect(() => {
    if (taskScreenTrackedRef.current || isTasksLoading) {
      return;
    }

    taskScreenTrackedRef.current = true;
    queueEngagementEvent({
      eventName: 'task_screen_open',
      area: 'task',
      status: 'viewed',
      route: 'Tasks',
      metadata: {
        taskCount: taskList.length,
        pendingCount: pendingTasks.length,
        claimedCount: completedTasks.length,
      },
    });
  }, [
    completedTasks.length,
    isTasksLoading,
    pendingTasks.length,
    taskList.length,
  ]);

  useEffect(() => {
    if (!tasksError || taskFailureLoggedRef.current) {
      return;
    }

    taskFailureLoggedRef.current = true;
    logAppIssue({
      severity: 'warning',
      source: 'task',
      action: 'task_page_load_failed',
      endpoint: 'auth/get-task',
      method: 'GET',
      message:
        tasksError instanceof Error
          ? tasksError.message
          : 'Tasks query failed before rendering.',
    }).catch(error => {
      console.log('Unable to queue task page load issue', error);
    });
  }, [tasksError]);

  const handleBackPress = () => {
    if (navigation.canGoBack()) {
      navigation.goBack();
      return;
    }

    navigation.navigate('HomeFlow');
  };

  const refreshAll = useCallback(async () => {
    taskFailureLoggedRef.current = false;
    await Promise.all([
      refetchTasks?.(),
      refetchOnboardingQuest?.(),
      tab === 'friends' ? refetchReferrals?.() : Promise.resolve(),
    ]);
  }, [refetchOnboardingQuest, refetchReferrals, refetchTasks, tab]);

  const openQuestStep = (step?: OnboardingQuestStep) => {
    switch (step?.action) {
      case 'tasks':
        setTab('tasks');
        break;
      case 'referral':
        navigation.navigate('Referral');
        break;
      case 'profile':
        navigateRoot('ProfileFlow');
        break;
      case 'streak':
      case 'mining':
      default:
        navigateRoot('MiningFlow');
        break;
    }
  };

  const claimOnboardingQuest = async () => {
    if (isClaimingQuest) {
      return;
    }

    setIsClaimingQuest(true);
    const startedAt = Date.now();
    queueEngagementEvent({
      eventName: 'starter_quest_claim_attempt',
      area: 'task',
      status: 'attempted',
      route: 'Tasks',
      endpoint: 'auth/onboarding-quest/claim',
      metadata: {completedQuestCount, totalQuestSteps},
    });

    try {
      await showOptionalActionAd(18000);

      const response = await postApi('auth/onboarding-quest/claim', {});
      const payload = response?.data;
      const backendStatus = Number(payload?.status ?? 1);

      if (
        !response.success ||
        backendStatus === 0 ||
        payload?.success === false
      ) {
        const message = getFriendlyApiMessage(
          response.error || payload?.message,
          'Starter quest reward could not be claimed right now.',
        );

        if (message.toLowerCase().includes('already claimed')) {
          setIsStarterQuestResolved(true);
          await Promise.all([refetchOnboardingQuest?.(), refetchTasks?.()]);
          await invalidateEngagementQueries(queryClient, {
            wallet: true,
            tasks: true,
            onboardingQuest: true,
          });
          queueEngagementEvent({
            eventName: 'starter_quest_claim_already_claimed',
            area: 'task',
            status: 'info',
            route: 'Tasks',
            endpoint: 'auth/onboarding-quest/claim',
            durationMs: Date.now() - startedAt,
          });
          showAlert('Starter quest reward was already claimed.', 'INFO');
          return;
        }

        logAppIssue({
          severity: 'warning',
          source: 'task',
          action: 'starter_quest_claim_failed',
          endpoint: 'auth/onboarding-quest/claim',
          method: 'POST',
          message,
        }).catch(error => {
          console.log('Unable to queue starter quest claim issue', error);
        });
        queueEngagementEvent({
          eventName: 'starter_quest_claim_failed',
          area: 'task',
          status: 'failed',
          route: 'Tasks',
          endpoint: 'auth/onboarding-quest/claim',
          durationMs: Date.now() - startedAt,
          metadata: {message},
        });
        showAlert(message, 'ERROR');
        return;
      }

      setIsStarterQuestResolved(true);
      await Promise.all([refetchOnboardingQuest?.(), refetchTasks?.()]);
      await invalidateEngagementQueries(queryClient, {
        wallet: true,
        tasks: true,
        onboardingQuest: true,
      });
      queueEngagementEvent({
        eventName: 'starter_quest_claim_success',
        area: 'task',
        status: 'succeeded',
        route: 'Tasks',
        endpoint: 'auth/onboarding-quest/claim',
        durationMs: Date.now() - startedAt,
      });
      showAlert(payload?.message || 'Starter quest reward claimed.', 'SUCCESS');
      showRewardNotification(
        'Starter quest reward received',
        payload?.message ||
          'Your starter quest reward was added to your wallet.',
        'Tasks',
      );
    } catch (error) {
      console.error('Error claiming starter quest from tasks', error);
      logAppIssue({
        severity: 'warning',
        source: 'task',
        action: 'starter_quest_claim_exception',
        endpoint: 'auth/onboarding-quest/claim',
        method: 'POST',
        message:
          error instanceof Error
            ? error.message
            : 'Starter quest claim exception.',
      }).catch(logError => {
        console.log('Unable to queue starter quest exception issue', logError);
      });
      showAlert(
        'Starter quest reward could not be claimed right now.',
        'ERROR',
      );
    } finally {
      setIsClaimingQuest(false);
    }
  };

  const selectCategory = (category: TaskCategoryFilter, count: number) => {
    setSelectedCategory(category);
    queueEngagementEvent({
      eventName: 'task_category_selected',
      area: 'task',
      status: 'viewed',
      route: 'Tasks',
      metadata: {category, count},
    });
  };

  const openTask = (task: any) => {
    setPopUpData(task);
    setShowPopUp(true);
  };

  const renderSummary = () => (
    <GlassCard style={styles.summaryCard}>
      <View style={styles.summaryTop}>
        <View style={styles.summaryCopy}>
          <SectionEyebrow text="Today" />
          <Text style={styles.summaryTitle}>Mission center</Text>
          <Text style={styles.summarySubtitle}>
            Finish simple actions, keep your rewards synced, and track claimed
            BRC.
          </Text>
        </View>
        <View style={styles.progressBadge}>
          <Text style={styles.progressValue}>{taskProgressPercent}%</Text>
          <Text style={styles.progressLabel}>done</Text>
        </View>
      </View>
      <View style={styles.progressTrack}>
        <View
          style={[styles.progressFill, {width: `${taskProgressPercent}%`}]}
        />
      </View>
      <View style={styles.summaryStats}>
        <View style={styles.statPill}>
          <Text style={styles.statValue}>{pendingTasks.length}</Text>
          <Text style={styles.statLabel}>Pending</Text>
        </View>
        <View style={styles.statPill}>
          <Text style={styles.statValue}>{completedTasks.length}</Text>
          <Text style={styles.statLabel}>Claimed</Text>
        </View>
        <View style={styles.statPill}>
          <Text style={styles.statValue}>{totalRewardAvailable}</Text>
          <Text style={styles.statLabel}>BRC ready</Text>
        </View>
      </View>
    </GlassCard>
  );

  const renderQuest = () => {
    if (!shouldShowOnboardingQuest || !onboardingQuest) {
      return null;
    }

    return (
      <GlassCard style={styles.questCard}>
        <View style={styles.questHeader}>
          <View style={styles.questCopy}>
            <SectionEyebrow text="Starter Quest" />
            <Text style={styles.questTitle}>{onboardingQuest.title}</Text>
            <Text style={styles.questSubtitle}>
              {onboardingQuest.subtitle ||
                'Complete the basics and claim your launch reward.'}
            </Text>
          </View>
          <View style={styles.questReward}>
            <Text style={styles.questRewardValue}>{questRewardLabel}</Text>
            <Text style={styles.questRewardLabel}>reward</Text>
          </View>
        </View>

        <View style={styles.progressTrack}>
          <View
            style={[styles.progressFill, {width: `${questProgressPercent}%`}]}
          />
        </View>
        <Text style={styles.questProgressText}>
          {completedQuestCount}/{totalQuestSteps} completed
        </Text>

        {questSteps.slice(0, 3).map(step => (
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
                size={15}
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
                <Text style={styles.questStepSubtitle}>{step.description}</Text>
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

        <PrimaryButton
          label={
            onboardingQuest.can_claim
              ? `Claim ${questRewardLabel}`
              : 'Continue starter quest'
          }
          icon={onboardingQuest.can_claim ? 'gift-outline' : 'arrow-right'}
          loading={isClaimingQuest}
          disabled={isClaimingQuest}
          onPress={
            onboardingQuest.can_claim
              ? claimOnboardingQuest
              : () => openQuestStep(firstIncompleteQuestStep)
          }
        />
      </GlassCard>
    );
  };

  const renderChips = () => (
    <View style={styles.chipScroller}>
      {taskCategoryFilters.map(category => (
        <Pressable
          key={category.label}
          onPress={() => selectCategory(category.label, category.count)}
          style={[
            styles.categoryChip,
            selectedCategory === category.label && styles.categoryChipActive,
          ]}>
          <Text
            style={[
              styles.categoryChipText,
              selectedCategory === category.label &&
                styles.categoryChipTextActive,
            ]}>
            {category.label}
          </Text>
          <Text style={styles.categoryChipCount}>{category.count}</Text>
        </Pressable>
      ))}
    </View>
  );

  const renderHeader = () => (
    <View>
      <ScreenHeader
        title="Rewards"
        subtitle="Tasks, starter progress, and referral actions."
        showBack
        onBackPress={handleBackPress}
      />

      <View style={styles.segmentedControl}>
        <Pressable
          onPress={() => setTab('tasks')}
          style={[styles.segment, tab === 'tasks' && styles.segmentActive]}>
          <Ionicons
            name="checkmark-done-outline"
            size={16}
            color={
              tab === 'tasks' ? theme.colors.primary : theme.colors.textSoft
            }
          />
          <Text
            style={[
              styles.segmentText,
              tab === 'tasks' && styles.segmentTextActive,
            ]}>
            Tasks
          </Text>
        </Pressable>
        <Pressable
          onPress={() => setTab('friends')}
          style={[styles.segment, tab === 'friends' && styles.segmentActive]}>
          <Ionicons
            name="people-outline"
            size={16}
            color={
              tab === 'friends' ? theme.colors.primary : theme.colors.textSoft
            }
          />
          <Text
            style={[
              styles.segmentText,
              tab === 'friends' && styles.segmentTextActive,
            ]}>
            Friends
          </Text>
        </Pressable>
      </View>

      {tab === 'tasks' ? (
        <>
          {renderSummary()}
          <View style={styles.nativeAdWrap}>
            <AppodealBannerSlot placement="tasks" />
          </View>
          {renderQuest()}
          <GlassCard style={styles.filterCard}>
            <View style={styles.sectionHeader}>
              <View>
                <SectionEyebrow text="Categories" />
                <Text style={styles.sectionTitle}>Find a task fast</Text>
              </View>
              <Text style={styles.sectionCount}>
                {visiblePendingTasks.length}
              </Text>
            </View>
            {renderChips()}
          </GlassCard>
        </>
      ) : (
        <GlassCard style={styles.summaryCard}>
          <View style={styles.summaryTop}>
            <View style={styles.summaryCopy}>
              <SectionEyebrow text="Referral Circle" />
              <Text style={styles.summaryTitle}>Network activity</Text>
              <Text style={styles.summarySubtitle}>
                Top referral activity appears here after your network starts
                moving.
              </Text>
            </View>
            <View style={styles.progressBadge}>
              <Text style={styles.progressValue}>{friends.length}</Text>
              <Text style={styles.progressLabel}>users</Text>
            </View>
          </View>
        </GlassCard>
      )}
    </View>
  );

  const renderStatus = (status: TaskStatusLabel) => {
    const isClaimed = status === 'Claimed';
    const isFailed = status === 'Failed';

    return (
      <View
        style={[
          styles.statusPill,
          isClaimed && styles.statusPillClaimed,
          isFailed && styles.statusPillFailed,
        ]}>
        <Text
          style={[
            styles.statusText,
            isClaimed && styles.statusTextClaimed,
            isFailed && styles.statusTextFailed,
          ]}>
          {status}
        </Text>
      </View>
    );
  };

  const renderTaskCard = (task: any) => {
    const category = getTaskCategory(task);
    const status = getTaskStatus(task);

    return (
      <Pressable
        onPress={() => openTask(task)}
        android_ripple={{color: 'rgba(0, 214, 176, 0.08)'}}
        style={styles.pressableRow}>
        <GlassCard style={styles.taskCard}>
          <View style={styles.taskIcon}>
            <Ionicons
              name={category.icon}
              size={20}
              color={theme.colors.primary}
            />
          </View>
          <View style={styles.taskCopy}>
            <View style={styles.taskTitleRow}>
              <Text style={styles.taskTitle} numberOfLines={2}>
                {getTaskTitle(task)}
              </Text>
              {renderStatus(status)}
            </View>
            <Text style={styles.taskDescription} numberOfLines={2}>
              {getTaskDescription(task)}
            </Text>
            <View style={styles.taskMetaRow}>
              <View style={styles.categoryPill}>
                <Text style={styles.categoryPillText}>{category.label}</Text>
              </View>
              <View style={styles.rewardPill}>
                <Ionicons
                  name="diamond-outline"
                  size={13}
                  color={theme.colors.warning}
                />
                <Text style={styles.rewardText}>
                  {getTaskRewardLabel(task)}
                </Text>
              </View>
            </View>
          </View>
          <View style={styles.taskCta}>
            <Ionicons
              name="chevron-forward"
              size={18}
              color={theme.colors.textSoft}
            />
          </View>
        </GlassCard>
      </Pressable>
    );
  };

  const renderCompletedTask = (item: any, index: number) => {
    if (tab === 'friends') {
      return (
        <GlassCard style={styles.friendCard}>
          <View style={styles.rankAvatar}>
            <Text style={styles.rankAvatarText}>#{index + 1}</Text>
          </View>
          <View style={styles.taskCopy}>
            <Text style={styles.taskTitle} numberOfLines={1}>
              {item?.name ?? 'User'}
            </Text>
            <Text style={styles.taskDescription} numberOfLines={1}>
              Referral leaderboard participant
            </Text>
          </View>
          <Text style={styles.friendBadge}>Network</Text>
        </GlassCard>
      );
    }

    return (
      <GlassCard style={[styles.taskCard, styles.claimedCard]}>
        <View style={styles.taskIconClaimed}>
          <Ionicons
            name="checkmark-circle-outline"
            size={20}
            color={theme.colors.success}
          />
        </View>
        <View style={styles.taskCopy}>
          <Text style={styles.taskTitle} numberOfLines={1}>
            {getTaskTitle(item)}
          </Text>
          <Text style={styles.taskDescription}>Reward already claimed</Text>
        </View>
        {renderStatus('Claimed')}
      </GlassCard>
    );
  };

  const renderEmptyState = (row: Extract<TaskRow, {kind: 'empty'}>) => (
    <GlassCard style={styles.emptyCard}>
      <View style={styles.emptyIcon}>
        <Ionicons name={row.icon} size={24} color={theme.colors.primary} />
      </View>
      <Text style={styles.emptyTitle}>{row.title}</Text>
      <Text style={styles.emptySubtitle}>{row.subtitle}</Text>
      {tasksError && tab === 'tasks' ? (
        <PrimaryButton
          label="Retry"
          icon="refresh-outline"
          onPress={refreshAll}
        />
      ) : null}
    </GlassCard>
  );

  const renderSkeleton = () => (
    <GlassCard style={styles.skeletonCard}>
      <View style={styles.skeletonIcon} />
      <View style={styles.skeletonCopy}>
        <View style={styles.skeletonLineWide} />
        <View style={styles.skeletonLine} />
        <View style={styles.skeletonPills}>
          <View style={styles.skeletonPill} />
          <View style={styles.skeletonPill} />
        </View>
      </View>
    </GlassCard>
  );

  const renderItem = ({item, index}: {item: TaskRow; index: number}) => {
    switch (item.kind) {
      case 'task':
        return renderTaskCard(item.item);
      case 'ad':
        return (
          <GlassCard style={styles.adCard}>
            <View style={styles.adCardHeader}>
              <View>
                <SectionEyebrow text="Sponsored" />
                <Text style={styles.adTitle}>Featured partner</Text>
              </View>
              <Text style={styles.adBadge}>Ad</Text>
            </View>
            <SponsoredBanner
              imageUri={bannerImage}
              fallbackUrl="https://www.mexc.com/en-IN/landings/Blockrium?inviteCode=mexc-2ffmJ"
              placement="tasks"
            />
          </GlassCard>
        );
      case 'completedHeader':
        return (
          <View style={styles.completedHeader}>
            <SectionEyebrow text="Completed" />
            <Text style={styles.sectionTitle}>Claimed rewards</Text>
          </View>
        );
      case 'completedTask':
        return renderCompletedTask(item.item, index);
      case 'empty':
        return renderEmptyState(item);
      case 'skeleton':
      default:
        return renderSkeleton();
    }
  };

  return (
    <AppScreen contentContainerStyle={styles.screenContent}>
      <FlatList
        data={listData}
        keyExtractor={(item, index) =>
          item.kind === 'task' || item.kind === 'completedTask'
            ? `${item.kind}-${item.item?.id ?? item.item?.task_id ?? index}`
            : item.id
        }
        renderItem={renderItem}
        ListHeaderComponent={renderHeader}
        ListFooterComponent={<View style={styles.footerSpacer} />}
        contentContainerStyle={styles.listContent}
        showsVerticalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
        removeClippedSubviews
        initialNumToRender={8}
        maxToRenderPerBatch={8}
        windowSize={7}
        refreshControl={
          <RefreshControl
            refreshing={Boolean(
              isTasksFetching || isQuestFetching || isReferralFetching,
            )}
            onRefresh={refreshAll}
            tintColor={theme.colors.primary}
            colors={[theme.colors.primary]}
            progressBackgroundColor={theme.colors.surface}
          />
        }
      />

      <TaskPopup
        popUpData={popUpData}
        visible={showPopUp}
        setVisible={setShowPopUp}
        onClaimed={async () => {
          await refetchTasks?.();
          await refetchOnboardingQuest?.();
          await invalidateEngagementQueries(queryClient, {
            wallet: true,
            tasks: true,
            onboardingQuest: true,
          });
        }}
      />
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  screenContent: {
    flex: 1,
  },
  listContent: {
    paddingTop: 4,
    paddingBottom: 132,
  },
  footerSpacer: {
    height: 12,
  },
  segmentedControl: {
    flexDirection: 'row',
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(2, 6, 11, 0.42)',
    padding: 4,
    marginBottom: 12,
  },
  segment: {
    flex: 1,
    minHeight: 42,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 7,
  },
  segmentActive: {
    backgroundColor: 'rgba(0, 214, 176, 0.14)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.24)',
  },
  segmentText: {
    color: theme.colors.textSoft,
    fontSize: 14,
    fontFamily: theme.typography.bodyBold,
  },
  segmentTextActive: {
    color: theme.colors.primary,
  },
  summaryCard: {
    marginBottom: 12,
    borderColor: 'rgba(0, 214, 176, 0.18)',
  },
  summaryTop: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 14,
  },
  summaryCopy: {
    flex: 1,
  },
  summaryTitle: {
    color: theme.colors.text,
    fontSize: 24,
    lineHeight: 30,
    fontFamily: theme.typography.heading,
  },
  summarySubtitle: {
    marginTop: 6,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  progressBadge: {
    width: 74,
    height: 74,
    borderRadius: 24,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.26)',
  },
  progressValue: {
    color: theme.colors.primary,
    fontSize: 18,
    fontFamily: theme.typography.heading,
  },
  progressLabel: {
    marginTop: 2,
    color: theme.colors.textSoft,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  progressTrack: {
    height: 7,
    borderRadius: 99,
    overflow: 'hidden',
    backgroundColor: 'rgba(151, 166, 195, 0.16)',
    marginTop: 14,
  },
  progressFill: {
    height: '100%',
    borderRadius: 99,
    backgroundColor: theme.colors.primary,
  },
  summaryStats: {
    marginTop: 14,
    flexDirection: 'row',
    gap: 8,
  },
  statPill: {
    flex: 1,
    minHeight: 58,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(8, 20, 34, 0.78)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  statValue: {
    color: theme.colors.text,
    fontSize: 17,
    fontFamily: theme.typography.heading,
  },
  statLabel: {
    marginTop: 3,
    color: theme.colors.textSoft,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  nativeAdWrap: {
    marginBottom: 4,
  },
  filterCard: {
    marginBottom: 10,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  sectionTitle: {
    color: theme.colors.text,
    fontSize: 19,
    fontFamily: theme.typography.heading,
  },
  sectionCount: {
    overflow: 'hidden',
    minWidth: 34,
    textAlign: 'center',
    borderRadius: 17,
    paddingHorizontal: 10,
    paddingVertical: 6,
    color: theme.colors.primary,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
  },
  chipScroller: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 14,
  },
  categoryChip: {
    minHeight: 38,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(8, 20, 34, 0.68)',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 12,
  },
  categoryChipActive: {
    borderColor: 'rgba(0, 214, 176, 0.38)',
    backgroundColor: 'rgba(0, 214, 176, 0.14)',
  },
  categoryChipText: {
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  categoryChipTextActive: {
    color: theme.colors.primary,
  },
  categoryChipCount: {
    overflow: 'hidden',
    minWidth: 21,
    textAlign: 'center',
    borderRadius: 999,
    paddingHorizontal: 6,
    paddingVertical: 2,
    color: theme.colors.text,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
    backgroundColor: 'rgba(151, 166, 195, 0.14)',
  },
  questCard: {
    marginBottom: 12,
    borderColor: 'rgba(0, 214, 176, 0.22)',
  },
  questHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 10,
  },
  questCopy: {
    flex: 1,
  },
  questTitle: {
    color: theme.colors.text,
    fontSize: 20,
    fontFamily: theme.typography.heading,
  },
  questSubtitle: {
    marginTop: 6,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  questReward: {
    minWidth: 78,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
    paddingHorizontal: 12,
    paddingVertical: 8,
    alignItems: 'center',
  },
  questRewardValue: {
    color: theme.colors.primary,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  questRewardLabel: {
    marginTop: 2,
    color: theme.colors.textMuted,
    fontSize: 11,
    fontFamily: theme.typography.body,
  },
  questProgressText: {
    marginTop: 8,
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  questStepRow: {
    minHeight: 52,
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(8, 20, 34, 0.66)',
    padding: 10,
    marginTop: 8,
  },
  questStepIcon: {
    width: 28,
    height: 28,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0, 214, 176, 0.10)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.28)',
    marginRight: 10,
  },
  questStepIconDone: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  questStepCopy: {
    flex: 1,
  },
  questStepTitle: {
    color: theme.colors.text,
    fontSize: 14,
    fontFamily: theme.typography.bodyBold,
  },
  questStepSubtitle: {
    marginTop: 3,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 16,
    fontFamily: theme.typography.body,
  },
  pressableRow: {
    marginBottom: 10,
  },
  taskCard: {
    minHeight: 88,
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
  },
  claimedCard: {
    opacity: 0.78,
    marginBottom: 10,
  },
  taskIcon: {
    width: 46,
    height: 46,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.18)',
    marginRight: 12,
  },
  taskIconClaimed: {
    width: 46,
    height: 46,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(24, 198, 131, 0.12)',
    marginRight: 12,
  },
  taskCopy: {
    flex: 1,
  },
  taskTitleRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 8,
  },
  taskTitle: {
    flex: 1,
    color: theme.colors.text,
    fontSize: 16,
    lineHeight: 21,
    fontFamily: theme.typography.heading,
  },
  taskDescription: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
  taskMetaRow: {
    marginTop: 10,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  categoryPill: {
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
    backgroundColor: 'rgba(151, 166, 195, 0.12)',
  },
  categoryPillText: {
    color: theme.colors.textSoft,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  rewardPill: {
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
    backgroundColor: 'rgba(240, 185, 11, 0.12)',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  rewardText: {
    color: theme.colors.warning,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  taskCta: {
    marginLeft: 8,
  },
  statusPill: {
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.18)',
  },
  statusPillClaimed: {
    backgroundColor: 'rgba(24, 198, 131, 0.12)',
    borderColor: 'rgba(24, 198, 131, 0.2)',
  },
  statusPillFailed: {
    backgroundColor: 'rgba(255, 90, 110, 0.12)',
    borderColor: 'rgba(255, 90, 110, 0.2)',
  },
  statusText: {
    color: theme.colors.primary,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  statusTextClaimed: {
    color: theme.colors.success,
  },
  statusTextFailed: {
    color: theme.colors.danger,
  },
  adCard: {
    marginBottom: 10,
    paddingBottom: 14,
  },
  adCardHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  adTitle: {
    color: theme.colors.text,
    fontSize: 18,
    fontFamily: theme.typography.heading,
  },
  adBadge: {
    overflow: 'hidden',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
    color: theme.colors.primary,
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  completedHeader: {
    marginTop: 8,
    marginBottom: 10,
  },
  friendCard: {
    minHeight: 74,
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 10,
  },
  rankAvatar: {
    width: 44,
    height: 44,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(47, 124, 255, 0.14)',
    marginRight: 12,
  },
  rankAvatarText: {
    color: theme.colors.accent,
    fontSize: 12,
    fontFamily: theme.typography.heading,
  },
  friendBadge: {
    overflow: 'hidden',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
    color: theme.colors.textMuted,
    backgroundColor: 'rgba(151, 166, 195, 0.12)',
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  emptyCard: {
    alignItems: 'center',
    paddingVertical: 24,
    marginBottom: 10,
  },
  emptyIcon: {
    width: 54,
    height: 54,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
    marginBottom: 12,
  },
  emptyTitle: {
    color: theme.colors.text,
    fontSize: 19,
    textAlign: 'center',
    fontFamily: theme.typography.heading,
  },
  emptySubtitle: {
    marginTop: 6,
    marginBottom: 14,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    textAlign: 'center',
    fontFamily: theme.typography.body,
  },
  skeletonCard: {
    minHeight: 88,
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 10,
  },
  skeletonIcon: {
    width: 46,
    height: 46,
    borderRadius: 16,
    backgroundColor: 'rgba(151, 166, 195, 0.12)',
    marginRight: 12,
  },
  skeletonCopy: {
    flex: 1,
  },
  skeletonLineWide: {
    width: '76%',
    height: 14,
    borderRadius: 999,
    backgroundColor: 'rgba(151, 166, 195, 0.14)',
  },
  skeletonLine: {
    width: '54%',
    height: 11,
    borderRadius: 999,
    backgroundColor: 'rgba(151, 166, 195, 0.10)',
    marginTop: 9,
  },
  skeletonPills: {
    marginTop: 12,
    flexDirection: 'row',
    gap: 8,
  },
  skeletonPill: {
    width: 70,
    height: 22,
    borderRadius: 999,
    backgroundColor: 'rgba(151, 166, 195, 0.10)',
  },
});

export default Tasks;
