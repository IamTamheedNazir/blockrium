import React, {useEffect, useMemo, useState} from 'react';
import {ActivityIndicator, Pressable, StyleSheet, Text, View} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import {useQueryClient} from 'react-query';
import Ionicons from 'react-native-vector-icons/Ionicons';
import Share from 'react-native-share';
import {
  AppScreen,
  AppTextField,
  GlassCard,
  PrimaryButton,
  ScreenHeader,
  ScreenLoader,
  SectionEyebrow,
} from '../../design/primitives';
import theme from '../../design/theme';
import {callQuery} from '../../hooks';
import {
  invalidateEngagementQueries,
  useClaimReferralMilestone,
  useReferralJourney,
} from '../../hooks/useEngagement';
import {useThisAlert} from '../../hooks/AlertContext';
import {postApi} from '../../storage/server';
import {copyToClipboard} from '../../utils/helper';
import {getStorageId} from '../../utils/mmkvStorage';

const formatPercent = (value: unknown) => {
  const numericValue = Number(value ?? 0);
  return Number.isFinite(numericValue) ? `${numericValue.toFixed(0)}%` : '0%';
};

const formatToken = (value: unknown) => {
  const numericValue = Number(value ?? 0);
  return Number.isFinite(numericValue) ? numericValue.toFixed(4) : '0.0000';
};

const formatCount = (value: unknown) => {
  const numericValue = Number(value ?? 0);
  return Number.isFinite(numericValue) ? Math.round(numericValue).toLocaleString() : '0';
};

const formatDate = (value: unknown) => {
  if (!value) {
    return 'No mining yet';
  }

  const date = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) {
    return 'No mining yet';
  }

  return date.toLocaleDateString(undefined, {
    day: '2-digit',
    month: 'short',
  });
};

const filterLabels: Record<string, string> = {
  all: 'All',
  active: 'Active',
  inactive: 'Inactive',
  new: 'New',
  needs_ping: 'Needs Ping',
  top_earners: 'Top Earners',
};

const normalizeTierList = (value: any) => {
  return Array.isArray(value) ? value : [];
};

const formatRequirementLabel = (metric: string) => {
  const labels: Record<string, string> = {
    active_referrals: 'Active',
    verified_referrals: 'Verified',
    task_referrals: 'Tasks',
    streak_referrals: 'Streaks',
    quality_score: 'Quality',
  };

  return labels[metric] ?? metric.replace(/_/g, ' ');
};

const Referral = () => {
  const navigation = useNavigation<any>();
  const queryClient = useQueryClient();
  const {showAlert} = useThisAlert();
  const [userId, setUserId] = useState<number | undefined>();
  const [pingingUserId, setPingingUserId] = useState<number | null>(null);
  const [selectedFilter, setSelectedFilter] = useState('all');
  const [selectedPingType, setSelectedPingType] = useState('mine_reminder');
  const [joinCode, setJoinCode] = useState('');
  const [isApplyingReferral, setIsApplyingReferral] = useState(false);
  const [claimingMissionId, setClaimingMissionId] = useState<number | null>(null);
  const [claimingMilestoneId, setClaimingMilestoneId] = useState<string | null>(null);
  const {
    data: referralJourney,
    isLoading: isJourneyLoading,
    isError: isJourneyError,
    isFetching: isJourneyFetching,
    refetch: refetchJourney,
  } = useReferralJourney();
  const claimReferralMilestone = useClaimReferralMilestone();
  const {
    data,
    isLoading: isReferralLoading,
    isFetching: isReferralFetching,
    refetch: refetchTopReferrals,
  }: any = callQuery(
    `top-refferals?userId=${userId ?? ''}`,
    {
      enabled: userId !== undefined,
      retry: 0,
      staleTime: 60000,
    },
  );
  const {
    data: networkData,
    isLoading: isNetworkLoading,
    isFetching: isNetworkFetching,
    refetch: refetchNetwork,
  }: any = callQuery(`auth/my-network?filter=${selectedFilter}`, {
    retry: 0,
    staleTime: 30000,
  });
  const {
    data: profileData,
    isLoading: isProfileLoading,
    isFetching: isProfileFetching,
    refetch: refetchProfile,
  }: any = callQuery('auth/profile');
  const {
    data: missionData,
    isLoading: isMissionLoading,
    isFetching: isMissionFetching,
    refetch: refetchMissions,
  }: any = callQuery('auth/referral-missions', {
    retry: 0,
    staleTime: 30000,
  });
  const [topReferrals, setTopReferrals] = useState<any[]>([]);
  const [authReferral, setAuthReferral] = useState<any>(null);
  const handleBackPress = () => {
    if (navigation.canGoBack()) {
      navigation.goBack();
      return;
    }

    navigation.navigate('HomeFlow');
  };

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
      setTopReferrals(Array.isArray(payload?.top50Miners) ? payload.top50Miners : []);
      setAuthReferral(payload?.authUserMiner ?? null);
    }
  }, [data]);

  const referralCode = useMemo(() => {
    return (
      referralJourney?.referralCode ||
      networkData?.data?.referral_code ||
      profileData?.data?.user?.refferal_code ||
      authReferral?.refferal_code ||
      String(profileData?.data?.user?.uid ?? 'BRC-INVITE')
    );
  }, [authReferral, networkData, profileData, referralJourney?.referralCode]);

  const networkPayload = networkData?.data?.data ?? {};
  const networkStats = networkPayload?.stats ?? {};
  const referralSettings = networkPayload?.settings || profileData?.data?.referral_settings || {};
  const teamBoostTiers = normalizeTierList(
    networkStats?.team_boost_tiers || referralSettings?.team_boost_tiers,
  );
  const activeReferralCount = Number(networkStats?.active_today_count ?? 0);
  const hasAppliedReferral = Boolean(
    profileData?.data?.is_referred_by || profileData?.data?.referral?.referred_by,
  );
  const referralMissions = Array.isArray(missionData?.data?.data)
    ? missionData.data.data
    : [];
  const networkReferrals = Array.isArray(networkPayload?.referrals)
    ? networkPayload.referrals
    : [];
  const networkFilters = Array.isArray(networkPayload?.filters)
    ? networkPayload.filters
    : ['all', 'active', 'inactive', 'new', 'needs_ping', 'top_earners'];
  const pingTypes = Array.isArray(networkPayload?.ping_types)
    ? networkPayload.ping_types
    : [
        {type: 'mine_reminder', label: 'Start mining again'},
        {type: 'streak_reminder', label: 'Claim today streak'},
        {type: 'task_reminder', label: 'Complete a task'},
        {type: 'team_boost', label: 'Join team boost'},
      ];

  const onPressShare = async () => {
    try {
      await Share.open({
        title: 'Blockrium referral',
        message:
          referralJourney?.shareMessage ||
          `Join Blockrium with my referral code: ${referralCode}`,
      });
    } catch (error: any) {
      if (!String(error?.message ?? '').toLowerCase().includes('cancel')) {
        console.log('Referral share unavailable', error);
        showAlert('Share sheet is unavailable right now.', 'INFO');
      }
    }
  };

  const refetchReferralArea = async () => {
    await Promise.all([
      refetchProfile?.(),
      refetchNetwork?.(),
      refetchMissions?.(),
      refetchTopReferrals?.(),
      refetchJourney?.(),
    ]);
  };

  const handleClaimMilestone = async (milestone: {
    id: string;
    canClaim: boolean;
    claimed: boolean;
  }) => {
    if (milestone.claimed) {
      showAlert('This referral milestone was already claimed.', 'INFO');
      return;
    }
    if (!milestone.canClaim) {
      showAlert('Keep growing your active network to unlock this milestone.', 'INFO');
      return;
    }
    if (claimingMilestoneId || claimReferralMilestone.isLoading) {
      return;
    }

    setClaimingMilestoneId(milestone.id);
    try {
      const result = await claimReferralMilestone.mutateAsync(milestone.id);
      await refetchReferralArea();
      showAlert(
        result.alreadyClaimed
          ? 'This referral milestone was already claimed.'
          : result.message || 'Referral milestone claimed.',
        result.alreadyClaimed ? 'INFO' : 'SUCCESS',
      );
    } catch (error: any) {
      showAlert(
        error?.message || 'Referral milestone could not be claimed right now.',
        'ERROR',
      );
    } finally {
      setClaimingMilestoneId(null);
    }
  };

  const handleApplyReferralCode = async () => {
    const code = joinCode.trim().toUpperCase();
    if (!code) {
      showAlert('Enter a referral code first.', 'ERROR');
      return;
    }

    setIsApplyingReferral(true);

    try {
      const validationResponse = await postApi('auth/validate-referral-code', {
        referral_code: code,
      });

      if (
        !validationResponse?.success ||
        Number(validationResponse?.data?.status ?? 0) === 0
      ) {
        showAlert(
          validationResponse?.error ||
            validationResponse?.data?.message ||
            'Referral code not found.',
          'ERROR',
        );
        return;
      }

      const referrer =
        validationResponse?.data?.data?.referrer?.name ||
        validationResponse?.data?.data?.referrer?.username;
      const response = await postApi('auth/update-referred-by', {
        referred_by: code,
      });

      if (!response?.success || Number(response?.data?.status ?? 0) === 0) {
        showAlert(
          response?.error ||
            response?.data?.message ||
            'Could not apply this referral code.',
          'ERROR',
        );
        return;
      }

      setJoinCode('');
      await refetchReferralArea();
      await invalidateEngagementQueries(queryClient, {referrals: true});
      showAlert(
        referrer
          ? `Referral connected to ${referrer}.`
          : response?.data?.message || 'Referral code applied.',
        'SUCCESS',
      );
    } catch (error) {
      console.error('Apply referral error', error);
      showAlert('Could not apply this referral code.', 'ERROR');
    } finally {
      setIsApplyingReferral(false);
    }
  };

  const handleClaimMission = async (mission: any) => {
    const missionId = Number(mission?.id);
    if (!Number.isFinite(missionId)) {
      showAlert('Mission is not available right now.', 'ERROR');
      return;
    }

    setClaimingMissionId(missionId);

    try {
      const response = await postApi('auth/claim-referral-mission', {
        mission_id: missionId,
      });

      if (!response?.success || Number(response?.data?.status ?? 0) === 0) {
        showAlert(
          response?.error ||
            response?.data?.message ||
            'Could not claim this mission right now.',
          'ERROR',
        );
        return;
      }

      await refetchReferralArea();
      await invalidateEngagementQueries(queryClient, {
        wallet: true,
        referrals: true,
      });
      showAlert(response?.data?.message || 'Referral mission claimed.', 'SUCCESS');
    } catch (error) {
      console.error('Claim referral mission error', error);
      showAlert('Could not claim this mission right now.', 'ERROR');
    } finally {
      setClaimingMissionId(null);
    }
  };

  const handlePingReferral = async (referral: any) => {
    const referralUserId = Number(referral?.user_id);
    if (!Number.isFinite(referralUserId)) {
      showAlert('Referral user is not available right now.', 'ERROR');
      return;
    }

    setPingingUserId(referralUserId);

    try {
      const response = await postApi('auth/referral-ping', {
        referral_user_id: referralUserId,
        message_type: selectedPingType,
      });

      if (!response?.success || Number(response?.data?.status ?? 0) === 0) {
        showAlert(
          response?.error ||
            response?.data?.message ||
            'Could not ping this referral right now.',
          'ERROR',
        );
        return;
      }

      await refetchNetwork?.();
      showAlert(response?.data?.message || 'Referral ping sent.', 'SUCCESS');
    } catch (error) {
      console.error('Referral ping error', error);
      showAlert('Could not ping this referral right now.', 'ERROR');
    } finally {
      setPingingUserId(null);
    }
  };

  if (isReferralLoading || isProfileLoading || isNetworkLoading || isMissionLoading) {
    return (
      <ScreenLoader
        title="Loading network"
        subtitle="Preparing your code, referrals, ping status, and boosts."
      />
    );
  }

  return (
    <AppScreen
      scrollable
      refreshing={Boolean(
        isReferralFetching ||
          isProfileFetching ||
          isNetworkFetching ||
          isMissionFetching ||
          isJourneyFetching,
      )}
      onRefresh={refetchReferralArea}
      contentContainerStyle={styles.contentContainer}>
      <ScreenHeader
        title="My Network"
        subtitle="Share your code, ping inactive referrals, and earn network boost."
        showBack
        onBackPress={handleBackPress}
      />

      <GlassCard>
        <SectionEyebrow text="Invite Engine" />
        <Text style={styles.heroTitle}>Share your Blockrium code</Text>
        <Text style={styles.heroCopy}>
          Copy or share your referral code to grow your network.
        </Text>
        <View style={styles.codeShell}>
          <Text style={styles.codeLabel}>Referral code</Text>
          <Text style={styles.codeValue}>{referralCode}</Text>
        </View>
        <View style={styles.buttonRow}>
          <PrimaryButton
            label="Copy code"
            icon="content-copy"
            onPress={() => copyToClipboard(referralCode)}
            style={styles.buttonItem}
          />
          <PrimaryButton
            label="Share invite"
            icon="share-variant"
            onPress={onPressShare}
            style={styles.buttonItem}
          />
        </View>
      </GlassCard>

      {isJourneyLoading ? (
        <GlassCard style={styles.journeyLoadingCard}>
          <ActivityIndicator size="small" color={theme.colors.primary} />
          <Text style={styles.journeyLoadingText}>Loading referral journey...</Text>
        </GlassCard>
      ) : isJourneyError ? (
        <GlassCard>
          <View style={styles.journeyErrorRow}>
            <View style={styles.journeyErrorCopy}>
              <Text style={styles.appliedTitle}>Referral journey unavailable</Text>
              <Text style={styles.appliedText}>Your existing network tools still work.</Text>
            </View>
            <Pressable onPress={() => refetchJourney()} style={styles.journeyRetryButton}>
              <Ionicons name="refresh" size={18} color={theme.colors.primary} />
            </Pressable>
          </View>
        </GlassCard>
      ) : referralJourney ? (
        <GlassCard>
          <SectionEyebrow text="Referral Journey" />
          <Text style={styles.heroTitle}>Network progress</Text>
          <View style={styles.journeyStatGrid}>
            <View style={styles.journeyStatCard}>
              <Text style={styles.journeyStatValue}>
                {formatCount(referralJourney.stats.totalJoined)}
              </Text>
              <Text style={styles.journeyStatLabel}>Joined</Text>
            </View>
            <View style={styles.journeyStatCard}>
              <Text style={styles.journeyStatValue}>
                {formatCount(referralJourney.stats.activeReferrals)}
              </Text>
              <Text style={styles.journeyStatLabel}>Active</Text>
            </View>
            <View style={styles.journeyStatCard}>
              <Text style={styles.journeyStatValue}>
                {formatCount(referralJourney.stats.pendingReferrals)}
              </Text>
              <Text style={styles.journeyStatLabel}>Pending</Text>
            </View>
            <View style={styles.journeyStatCard}>
              <Text style={styles.journeyStatValue} numberOfLines={1}>
                {referralJourney.stats.claimableRewardLabel}
              </Text>
              <Text style={styles.journeyStatLabel}>Claimable</Text>
            </View>
          </View>
        </GlassCard>
      ) : null}

      {referralJourney ? (
        <GlassCard style={styles.qualityCard}>
          <View style={styles.qualityHeader}>
            <View style={styles.qualityScoreRing}>
              <Text style={styles.qualityScoreValue}>
                {formatCount(referralJourney.stats.qualityScore)}
              </Text>
              <Text style={styles.qualityScoreLabel}>Score</Text>
            </View>
            <View style={styles.qualityCopy}>
              <SectionEyebrow text="Network Quality" />
              <Text style={styles.heroTitle}>
                {referralJourney.stats.qualityLevel} referrals
              </Text>
              <Text style={styles.heroCopy}>
                {referralJourney.stats.qualitySummary ||
                  'Quality rewards use real mining, task, streak, and verification signals.'}
              </Text>
            </View>
          </View>
          <View style={styles.qualityMetricGrid}>
            <View style={styles.qualityMetric}>
              <Text style={styles.qualityMetricValue}>
                {formatCount(referralJourney.stats.threeDayMiners)}
              </Text>
              <Text style={styles.qualityMetricLabel}>3-day miners</Text>
            </View>
            <View style={styles.qualityMetric}>
              <Text style={styles.qualityMetricValue}>
                {formatCount(referralJourney.stats.taskReferrals)}
              </Text>
              <Text style={styles.qualityMetricLabel}>Task users</Text>
            </View>
            <View style={styles.qualityMetric}>
              <Text style={styles.qualityMetricValue}>
                {formatCount(referralJourney.stats.streakReferrals)}
              </Text>
              <Text style={styles.qualityMetricLabel}>Streak users</Text>
            </View>
            <View style={styles.qualityMetric}>
              <Text style={styles.qualityMetricValue}>
                {formatCount(referralJourney.stats.verifiedReferrals)}
              </Text>
              <Text style={styles.qualityMetricLabel}>Verified</Text>
            </View>
          </View>
          {referralJourney.stats.nextBestAction ? (
            <View style={styles.nextActionBox}>
              <Ionicons
                name="sparkles-outline"
                size={18}
                color={theme.colors.primary}
              />
              <View style={styles.nextActionCopy}>
                <Text style={styles.nextActionTitle}>
                  {referralJourney.stats.nextBestAction.title}
                </Text>
                <Text style={styles.nextActionText}>
                  {referralJourney.stats.nextBestAction.description}
                </Text>
              </View>
            </View>
          ) : null}
        </GlassCard>
      ) : null}

      {referralJourney?.journeySteps.length ? (
        <GlassCard>
          <SectionEyebrow text="Referral Path" />
          {referralJourney.journeySteps.map((step, index) => (
            <View
              key={step.key}
              style={[
                styles.pathStep,
                index === referralJourney.journeySteps.length - 1 &&
                  styles.pathStepLast,
              ]}>
              <View
                style={[
                  styles.pathStepIcon,
                  step.status === 'complete' && styles.pathStepIconDone,
                ]}>
                <Ionicons
                  name={step.status === 'complete' ? 'checkmark' : 'ellipse-outline'}
                  size={16}
                  color={
                    step.status === 'complete'
                      ? theme.colors.background
                      : theme.colors.primary
                  }
                />
              </View>
              <View style={styles.pathStepCopy}>
                <View style={styles.pathStepHeader}>
                  <Text style={styles.pathStepTitle}>{step.title}</Text>
                  <Text style={styles.pathStepMeta}>
                    {formatCount(step.current)}/{formatCount(step.target)}
                  </Text>
                </View>
                <Text style={styles.pathStepText}>{step.description}</Text>
                <View style={styles.pathProgressTrack}>
                  <View
                    style={[
                      styles.pathProgressFill,
                      {width: `${step.progressPercent}%`},
                    ]}
                  />
                </View>
              </View>
            </View>
          ))}
        </GlassCard>
      ) : null}

      {!hasAppliedReferral ? (
        <GlassCard>
          <SectionEyebrow text="Join A Sponsor" />
          <Text style={styles.heroTitle}>Apply an invite code</Text>
          <Text style={styles.heroCopy}>
            Add a sponsor code once to connect your account to a network and unlock referral rewards.
          </Text>
          <AppTextField
            label="Referral code"
            leadingIcon="gift-outline"
            placeholder="BRC-INVITE"
            value={joinCode}
            autoCapitalize="characters"
            onChangeText={setJoinCode}
          />
          <PrimaryButton
            label={isApplyingReferral ? 'Applying...' : 'Apply code'}
            icon="check-circle-outline"
            onPress={handleApplyReferralCode}
            disabled={isApplyingReferral}
          />
        </GlassCard>
      ) : (
        <GlassCard style={styles.appliedCard}>
          <Ionicons name="checkmark-circle-outline" size={22} color={theme.colors.success} />
          <View style={styles.appliedCopy}>
            <Text style={styles.appliedTitle}>Referral connected</Text>
            <Text style={styles.appliedText}>
              Your account is already attached to a sponsor network.
            </Text>
          </View>
        </GlassCard>
      )}

      <GlassCard>
        <SectionEyebrow text="Network Boost" />
        <Text style={styles.heroTitle}>
          {formatPercent(networkStats?.team_boost_percent)} active boost
        </Text>
        <Text style={styles.heroCopy}>
          Active referrals add mining boost up to {formatPercent(networkStats?.team_boost_cap)}.
        </Text>
        <Text style={styles.rewardHint}>
          Referrer signup reward: {formatToken(referralSettings?.referrer_signup_reward)} BRC
        </Text>
        <View style={styles.statGrid}>
          <View style={styles.statCard}>
            <Text style={styles.statValue}>{formatCount(networkStats?.direct_count)}</Text>
            <Text style={styles.statLabel}>Direct</Text>
          </View>
          <View style={styles.statCard}>
            <Text style={styles.statValue}>{formatCount(networkStats?.active_today_count)}</Text>
            <Text style={styles.statLabel}>Active</Text>
          </View>
          <View style={styles.statCard}>
            <Text style={styles.statValue}>{formatCount(networkStats?.inactive_count)}</Text>
            <Text style={styles.statLabel}>Inactive</Text>
          </View>
          <View style={styles.statCard}>
            <Text style={styles.statValue}>{formatCount(networkStats?.pinged_today_count)}</Text>
            <Text style={styles.statLabel}>Pinged</Text>
          </View>
          <View style={styles.statCard}>
            <Text style={styles.statValue}>{formatToken(networkStats?.referral_rewards)}</Text>
            <Text style={styles.statLabel}>Rewards</Text>
          </View>
          <View style={styles.statCard}>
            <Text style={styles.statValue}>{formatCount(networkStats?.visible_count)}</Text>
            <Text style={styles.statLabel}>Showing</Text>
          </View>
        </View>
        <View style={styles.boostRules}>
          <View style={styles.boostRuleRow}>
            <Text style={styles.boostRuleLabel}>Ping limit</Text>
            <Text style={styles.boostRuleValue}>
              {formatCount(networkStats?.ping_daily_limit)} per referral daily
            </Text>
          </View>
          <View style={styles.boostRuleRow}>
            <Text style={styles.boostRuleLabel}>Filter</Text>
            <Text style={styles.boostRuleValue}>
              {filterLabels[selectedFilter] ?? selectedFilter}
            </Text>
          </View>
        </View>
        {teamBoostTiers.length > 0 ? (
          <View style={styles.tierList}>
            <Text style={styles.tierHeading}>Team boost tiers</Text>
            {teamBoostTiers.map((tier: any, index: number) => {
              const requiredReferrals = Number(tier?.active_referrals ?? 0);
              const isUnlocked = activeReferralCount >= requiredReferrals;
              const progress = Math.max(
                0,
                Math.min(1, activeReferralCount / Math.max(1, requiredReferrals)),
              );

              return (
                <View key={`${requiredReferrals}-${index}`} style={styles.tierRow}>
                  <View style={styles.tierCopy}>
                    <Text style={styles.tierTitle}>
                      {formatPercent(tier?.boost_percent)} boost
                    </Text>
                    <Text style={styles.tierMeta}>
                      {formatCount(activeReferralCount)}/{formatCount(requiredReferrals)} active referrals
                    </Text>
                    <View style={styles.tierProgressTrack}>
                      <View style={[styles.tierProgressFill, {width: `${progress * 100}%`}]} />
                    </View>
                  </View>
                  <View style={[styles.tierBadge, isUnlocked && styles.tierBadgeUnlocked]}>
                    <Text
                      style={[
                        styles.tierBadgeText,
                        isUnlocked && styles.tierBadgeTextUnlocked,
                      ]}>
                      {isUnlocked ? 'Active' : 'Locked'}
                    </Text>
                  </View>
                </View>
              );
            })}
          </View>
        ) : null}
      </GlassCard>

      {referralJourney?.milestones.length ? (
        <>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Referral milestones</Text>
            <Text style={styles.sectionCaption}>
              Rewards are credited by Blockrium after server verification.
            </Text>
          </View>
          {referralJourney.milestones.map(milestone => {
            const target = Math.max(1, milestone.requiredActiveReferrals);
            const current = Math.max(0, milestone.currentActiveReferrals);
            const qualityTarget = Math.max(0, milestone.requiredQualityScore);
            const progress = Math.min(
              100,
              Math.max(
                (current / target) * 100,
                qualityTarget > 0
                  ? (milestone.currentQualityScore / qualityTarget) * 100
                  : 0,
              ),
            );
            const isClaiming = claimingMilestoneId === milestone.id;

            return (
              <GlassCard key={milestone.id} style={styles.milestoneCard}>
                <View style={styles.milestoneHeader}>
                  <View style={styles.milestoneIcon}>
                    <Ionicons
                      name={milestone.claimed ? 'checkmark' : 'flag-outline'}
                      size={18}
                      color={
                        milestone.claimed
                          ? theme.colors.background
                          : theme.colors.primary
                      }
                    />
                  </View>
                  <View style={styles.milestoneCopy}>
                    <Text style={styles.milestoneTitle}>{milestone.title}</Text>
                    <Text style={styles.milestoneSubtitle}>
                      {milestone.description ||
                        `${current}/${target} active referrals`}
                    </Text>
                  </View>
                  <Text style={styles.milestoneReward}>{milestone.rewardLabel}</Text>
                </View>
                <View style={styles.progressTrack}>
                  <View style={[styles.progressFill, {width: `${progress}%`}]} />
                </View>
                <View style={styles.milestoneFooter}>
                  <Text style={styles.missionMeta}>
                    {current}/{target} active / quality {formatCount(milestone.currentQualityScore)}
                    {qualityTarget > 0 ? `/${formatCount(qualityTarget)}` : ''}
                  </Text>
                  <Pressable
                    disabled={isClaiming || milestone.claimed}
                    onPress={() => handleClaimMilestone(milestone)}
                    style={[
                      styles.claimButton,
                      (!milestone.canClaim || milestone.claimed) &&
                        styles.claimButtonDisabled,
                    ]}>
                    {isClaiming ? (
                      <ActivityIndicator size="small" color={theme.colors.textSoft} />
                    ) : (
                      <Text
                        style={[
                          styles.claimText,
                          (!milestone.canClaim || milestone.claimed) &&
                            styles.claimTextDisabled,
                        ]}>
                        {milestone.claimed
                          ? 'Claimed'
                          : milestone.canClaim
                          ? 'Claim'
                          : 'Locked'}
                      </Text>
                    )}
                  </Pressable>
                </View>
                {milestone.missingRequirements.length > 0 ? (
                  <View style={styles.requirementWrap}>
                    {milestone.missingRequirements.map(requirement => (
                      <View
                        key={`${milestone.id}-${requirement.metric}`}
                        style={styles.requirementChip}>
                        <Text style={styles.requirementText}>
                          {formatRequirementLabel(requirement.metric)} {formatCount(requirement.current)}/
                          {formatCount(requirement.required)}
                        </Text>
                      </View>
                    ))}
                  </View>
                ) : null}
              </GlassCard>
            );
          })}
        </>
      ) : null}

      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Referral missions</Text>
      </View>

      {referralMissions.length === 0 ? (
        <GlassCard>
          <Text style={styles.heroTitle}>No missions yet</Text>
          <Text style={styles.heroCopy}>
            Referral missions will appear here after admin enables them.
          </Text>
        </GlassCard>
      ) : null}

      {referralMissions.map((mission: any) => {
        const progress = Number(mission?.progress ?? 0);
        const target = Math.max(1, Number(mission?.target_count ?? 1));
        const ratio = Math.max(0, Math.min(1, progress / target));
        const isClaiming = claimingMissionId === Number(mission?.id);
        const canClaim = Boolean(mission?.can_claim) && !isClaiming;

        return (
          <GlassCard key={`${mission?.id}`} style={styles.missionCard}>
            <View style={styles.missionHeader}>
              <View style={styles.missionIcon}>
                <Ionicons name="sparkles-outline" size={18} color={theme.colors.text} />
              </View>
              <View style={styles.missionCopy}>
                <Text style={styles.missionTitle}>{mission?.title ?? 'Referral mission'}</Text>
                <Text style={styles.missionSubtitle}>
                  {mission?.description || 'Grow your network to earn this reward.'}
                </Text>
              </View>
            </View>
            <View style={styles.progressTrack}>
              <View style={[styles.progressFill, {width: `${ratio * 100}%`}]} />
            </View>
            <View style={styles.missionFooter}>
              <Text style={styles.missionMeta}>
                {progress}/{target} / {formatToken(mission?.reward_amount)} BRC / {mission?.frequency}
              </Text>
              <Pressable
                disabled={!canClaim}
                onPress={() => handleClaimMission(mission)}
                style={[styles.claimButton, !canClaim && styles.claimButtonDisabled]}>
                {isClaiming ? (
                  <ActivityIndicator size="small" color={theme.colors.textSoft} />
                ) : (
                  <Text
                    style={[
                      styles.claimText,
                      !canClaim && styles.claimTextDisabled,
                    ]}>
                    {mission?.claimed ? 'Claimed' : canClaim ? 'Claim' : 'Progress'}
                  </Text>
                )}
              </Pressable>
            </View>
          </GlassCard>
        );
      })}

      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Direct referrals</Text>
        <Text style={styles.sectionCaption}>
          {formatCount(networkStats?.visible_count)} shown from {formatCount(networkStats?.direct_count)} direct referrals
        </Text>
      </View>

      <View style={styles.filterWrap}>
        {networkFilters.map((filter: string) => {
          const isSelected = selectedFilter === filter;
          return (
            <Pressable
              key={filter}
              onPress={() => setSelectedFilter(filter)}
              style={[styles.filterChip, isSelected && styles.filterChipActive]}>
              <Text
                style={[
                  styles.filterChipText,
                  isSelected && styles.filterChipTextActive,
                ]}>
                {filterLabels[filter] ?? filter}
              </Text>
            </Pressable>
          );
        })}
      </View>

      <GlassCard style={styles.pingTypeCard}>
        <SectionEyebrow text="Ping Message" />
        <Text style={styles.pingTypeCopy}>
          Choose one fixed reminder. Custom text stays disabled to prevent spam.
        </Text>
        <View style={styles.filterWrap}>
          {pingTypes.map((item: any) => {
            const isSelected = selectedPingType === item?.type;
            return (
              <Pressable
                key={String(item?.type)}
                onPress={() => setSelectedPingType(item?.type)}
                style={[styles.pingTypeChip, isSelected && styles.pingTypeChipActive]}>
                <Text
                  style={[
                    styles.pingTypeChipText,
                    isSelected && styles.pingTypeChipTextActive,
                  ]}>
                  {item?.label ?? 'Ping'}
                </Text>
              </Pressable>
            );
          })}
        </View>
      </GlassCard>

      {networkReferrals.length === 0 ? (
        <GlassCard>
          <Text style={styles.heroTitle}>No direct referrals yet</Text>
          <Text style={styles.heroCopy}>
            Share your code to start building your mining network.
          </Text>
        </GlassCard>
      ) : null}

      {networkReferrals.map((item: any) => {
        const isPinging = pingingUserId === Number(item?.user_id);
        return (
          <GlassCard key={`${item?.referral_id ?? item?.user_id}`} style={styles.listCard}>
            <View style={[styles.rankBubble, item?.active_today && styles.rankBubbleActive]}>
              <Text style={styles.rankBubbleText}>
                {String(item?.username || item?.name || 'U').slice(0, 1).toUpperCase()}
              </Text>
            </View>
            <View style={styles.listCopy}>
              <Text style={styles.listName} numberOfLines={1}>
                {item?.username || item?.name || 'User'}
              </Text>
              <Text style={styles.listSubtitle}>
                {item?.active_today ? 'Active today' : `Last mined ${formatDate(item?.last_mined_at)}`}
              </Text>
              <Text style={styles.listHint}>
                {item?.ping_count_today ?? 0}/{networkStats?.ping_daily_limit ?? 3} pings today
              </Text>
              <Text style={styles.listHint}>
                {formatToken(item?.referral_rewards)} BRC rewards
              </Text>
            </View>
            <Pressable
              disabled={!item?.can_ping || isPinging}
              onPress={() => handlePingReferral(item)}
              style={[
                styles.pingButton,
                (!item?.can_ping || isPinging) && styles.pingButtonDisabled,
              ]}>
              {isPinging ? (
                <ActivityIndicator size="small" color={theme.colors.textSoft} />
              ) : (
                <Ionicons
                  name="paper-plane-outline"
                  size={16}
                  color={item?.can_ping ? theme.colors.text : theme.colors.textSoft}
                />
              )}
              <Text
                style={[
                  styles.pingText,
                  (!item?.can_ping || isPinging) && styles.pingTextDisabled,
                ]}>
                {item?.can_ping ? 'Ping' : 'Wait'}
              </Text>
            </Pressable>
          </GlassCard>
        );
      })}

      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Top referrers</Text>
      </View>

      {topReferrals.length === 0 ? (
        <GlassCard>
          <Text style={styles.heroTitle}>No referral leaderboard yet</Text>
          <Text style={styles.heroCopy}>
            Once the backend sends referral records, the top performers will appear here.
          </Text>
        </GlassCard>
      ) : null}

      {(topReferrals ?? []).map((item, index) => (
        <GlassCard key={`${item?.id ?? index}`} style={styles.listCard}>
          <View style={styles.rankBubble}>
            <Text style={styles.rankBubbleText}>#{index + 1}</Text>
          </View>
          <View style={styles.listCopy}>
            <Text style={styles.listName}>{item?.name ?? 'User'}</Text>
            <Text style={styles.listSubtitle}>Referral leaderboard</Text>
          </View>
        </GlassCard>
      ))}
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {
    paddingTop: 4,
    paddingBottom: 126,
  },
  heroTitle: {
    color: theme.colors.text,
    fontSize: 21,
    fontFamily: theme.typography.heading,
  },
  heroCopy: {
    marginTop: 5,
    marginBottom: 12,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  codeShell: {
    borderRadius: 16,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(24, 52, 87, 0.36)',
    padding: 12,
  },
  codeLabel: {
    color: theme.colors.textSoft,
    fontSize: 12,
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    fontFamily: theme.typography.bodyMedium,
  },
  codeValue: {
    marginTop: 6,
    color: theme.colors.text,
    fontSize: 24,
    fontFamily: theme.typography.display,
  },
  buttonRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 10,
  },
  buttonItem: {
    flex: 1,
  },
  appliedCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  appliedCopy: {
    flex: 1,
  },
  appliedTitle: {
    color: theme.colors.text,
    fontSize: 16,
    fontFamily: theme.typography.heading,
  },
  appliedText: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  rewardHint: {
    marginTop: -6,
    marginBottom: 14,
    color: theme.colors.textSoft,
    fontSize: 13,
    fontFamily: theme.typography.bodyMedium,
  },
  journeyLoadingCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  journeyLoadingText: {
    color: theme.colors.textMuted,
    fontSize: 13,
    fontFamily: theme.typography.body,
  },
  journeyErrorRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  journeyErrorCopy: {
    flex: 1,
  },
  journeyRetryButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(24, 52, 87, 0.52)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  journeyStatGrid: {
    marginTop: 12,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 9,
  },
  journeyStatCard: {
    width: '48%',
    minHeight: 64,
    borderRadius: 14,
    padding: 10,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(24, 52, 87, 0.36)',
  },
  journeyStatValue: {
    color: theme.colors.text,
    fontSize: 17,
    fontFamily: theme.typography.heading,
  },
  journeyStatLabel: {
    marginTop: 5,
    color: theme.colors.textMuted,
    fontSize: 11,
    fontFamily: theme.typography.body,
  },
  qualityCard: {
    gap: 14,
  },
  qualityHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
  },
  qualityScoreRing: {
    width: 76,
    height: 76,
    borderRadius: 38,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: theme.colors.primary,
    backgroundColor: 'rgba(47, 224, 196, 0.12)',
  },
  qualityScoreValue: {
    color: theme.colors.text,
    fontSize: 22,
    fontFamily: theme.typography.display,
  },
  qualityScoreLabel: {
    marginTop: 1,
    color: theme.colors.textMuted,
    fontSize: 10,
    fontFamily: theme.typography.bodyBold,
  },
  qualityCopy: {
    flex: 1,
    minWidth: 0,
  },
  qualityMetricGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  qualityMetric: {
    flex: 1,
    minWidth: '45%',
    minHeight: 58,
    borderRadius: 14,
    padding: 10,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(24, 52, 87, 0.34)',
  },
  qualityMetricValue: {
    color: theme.colors.text,
    fontSize: 17,
    fontFamily: theme.typography.heading,
  },
  qualityMetricLabel: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 11,
    fontFamily: theme.typography.body,
  },
  nextActionBox: {
    borderRadius: 15,
    borderWidth: 1,
    borderColor: 'rgba(47, 224, 196, 0.22)',
    backgroundColor: 'rgba(47, 224, 196, 0.08)',
    padding: 12,
    flexDirection: 'row',
    gap: 10,
  },
  nextActionCopy: {
    flex: 1,
  },
  nextActionTitle: {
    color: theme.colors.text,
    fontSize: 14,
    fontFamily: theme.typography.heading,
  },
  nextActionText: {
    marginTop: 3,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 16,
    fontFamily: theme.typography.body,
  },
  pathStep: {
    flexDirection: 'row',
    gap: 12,
    paddingBottom: 14,
    marginBottom: 14,
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(148, 163, 184, 0.1)',
  },
  pathStepLast: {
    marginBottom: 0,
    paddingBottom: 0,
    borderBottomWidth: 0,
  },
  pathStepIcon: {
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: 'rgba(47, 224, 196, 0.28)',
    backgroundColor: 'rgba(47, 224, 196, 0.1)',
  },
  pathStepIconDone: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  pathStepCopy: {
    flex: 1,
    minWidth: 0,
  },
  pathStepHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  pathStepTitle: {
    flex: 1,
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  pathStepMeta: {
    color: theme.colors.primary,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  pathStepText: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 16,
    fontFamily: theme.typography.body,
  },
  pathProgressTrack: {
    marginTop: 9,
    height: 5,
    borderRadius: 3,
    overflow: 'hidden',
    backgroundColor: 'rgba(120, 167, 255, 0.14)',
  },
  pathProgressFill: {
    height: '100%',
    borderRadius: 3,
    backgroundColor: theme.colors.primary,
  },
  statGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  statCard: {
    flex: 1,
    minWidth: '30%',
    borderRadius: 14,
    padding: 10,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(24, 52, 87, 0.36)',
  },
  statValue: {
    color: theme.colors.text,
    fontSize: 18,
    fontFamily: theme.typography.heading,
  },
  statLabel: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  boostRules: {
    marginTop: 10,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(11, 27, 45, 0.42)',
    overflow: 'hidden',
  },
  boostRuleRow: {
    minHeight: 44,
    paddingHorizontal: 12,
    paddingVertical: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(148, 163, 184, 0.1)',
  },
  boostRuleLabel: {
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.bodyMedium,
  },
  boostRuleValue: {
    flex: 1,
    color: theme.colors.text,
    fontSize: 13,
    textAlign: 'right',
    fontFamily: theme.typography.bodyBold,
  },
  tierList: {
    marginTop: 12,
    gap: 8,
  },
  tierHeading: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  tierRow: {
    borderRadius: 14,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(24, 52, 87, 0.32)',
    padding: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  tierCopy: {
    flex: 1,
  },
  tierTitle: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  tierMeta: {
    marginTop: 3,
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  tierProgressTrack: {
    marginTop: 10,
    height: 6,
    borderRadius: 3,
    overflow: 'hidden',
    backgroundColor: 'rgba(120, 167, 255, 0.14)',
  },
  tierProgressFill: {
    height: '100%',
    borderRadius: 3,
    backgroundColor: theme.colors.primary,
  },
  tierBadge: {
    minHeight: 30,
    borderRadius: 15,
    paddingHorizontal: 10,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(148, 163, 184, 0.12)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  tierBadgeUnlocked: {
    backgroundColor: 'rgba(48, 211, 181, 0.18)',
    borderColor: theme.colors.primary,
  },
  tierBadgeText: {
    color: theme.colors.textMuted,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  tierBadgeTextUnlocked: {
    color: theme.colors.primary,
  },
  sectionHeader: {
    marginBottom: 10,
  },
  sectionTitle: {
    color: theme.colors.text,
    fontSize: 20,
    fontFamily: theme.typography.heading,
  },
  sectionCaption: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 13,
    fontFamily: theme.typography.body,
  },
  filterWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginBottom: 10,
  },
  filterChip: {
    minHeight: 32,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: theme.colors.border,
    paddingHorizontal: 10,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(24, 52, 87, 0.38)',
  },
  filterChipActive: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  filterChipText: {
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  filterChipTextActive: {
    color: theme.colors.background,
  },
  pingTypeCard: {
    marginBottom: 10,
  },
  pingTypeCopy: {
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
    marginBottom: 12,
  },
  pingTypeChip: {
    minHeight: 36,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.border,
    paddingHorizontal: 12,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(18, 39, 66, 0.44)',
  },
  pingTypeChipActive: {
    borderColor: theme.colors.accent,
    backgroundColor: 'rgba(120, 167, 255, 0.18)',
  },
  pingTypeChipText: {
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  pingTypeChipTextActive: {
    color: theme.colors.text,
  },
  missionCard: {
    gap: 14,
  },
  milestoneCard: {
    gap: 13,
  },
  milestoneHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 11,
  },
  milestoneIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(47, 224, 196, 0.13)',
    borderWidth: 1,
    borderColor: 'rgba(47, 224, 196, 0.24)',
  },
  milestoneCopy: {
    flex: 1,
    minWidth: 0,
  },
  milestoneTitle: {
    color: theme.colors.text,
    fontSize: 16,
    fontFamily: theme.typography.heading,
  },
  milestoneSubtitle: {
    marginTop: 3,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 16,
    fontFamily: theme.typography.body,
  },
  milestoneReward: {
    maxWidth: 92,
    color: theme.colors.primary,
    fontSize: 12,
    textAlign: 'right',
    fontFamily: theme.typography.bodyBold,
  },
  milestoneFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  requirementWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 7,
  },
  requirementChip: {
    minHeight: 28,
    borderRadius: 14,
    paddingHorizontal: 9,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: 'rgba(255, 198, 109, 0.24)',
    backgroundColor: 'rgba(255, 198, 109, 0.1)',
  },
  requirementText: {
    color: theme.colors.textMuted,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  missionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  missionIcon: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(120, 167, 255, 0.18)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  missionCopy: {
    flex: 1,
  },
  missionTitle: {
    color: theme.colors.text,
    fontSize: 17,
    fontFamily: theme.typography.heading,
  },
  missionSubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  progressTrack: {
    height: 8,
    borderRadius: 4,
    overflow: 'hidden',
    backgroundColor: 'rgba(120, 167, 255, 0.13)',
  },
  progressFill: {
    height: '100%',
    borderRadius: 4,
    backgroundColor: theme.colors.primary,
  },
  missionFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  missionMeta: {
    flex: 1,
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.bodyMedium,
  },
  claimButton: {
    minHeight: 38,
    borderRadius: 19,
    paddingHorizontal: 14,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.primary,
  },
  claimButtonDisabled: {
    backgroundColor: 'rgba(24, 52, 87, 0.62)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  claimText: {
    color: theme.colors.background,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
  },
  claimTextDisabled: {
    color: theme.colors.textSoft,
  },
  listCard: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  rankBubble: {
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(120, 167, 255, 0.14)',
    marginRight: 14,
  },
  rankBubbleActive: {
    backgroundColor: theme.colors.primary,
  },
  rankBubbleText: {
    color: theme.colors.text,
    fontSize: 14,
    fontFamily: theme.typography.bodyBold,
  },
  listCopy: {
    flex: 1,
  },
  listName: {
    color: theme.colors.text,
    fontSize: 17,
    fontFamily: theme.typography.heading,
  },
  listSubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 13,
    fontFamily: theme.typography.body,
  },
  listHint: {
    marginTop: 3,
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  pingButton: {
    minHeight: 40,
    borderRadius: 20,
    paddingHorizontal: 14,
    backgroundColor: 'rgba(24, 52, 87, 0.62)',
    borderWidth: 1,
    borderColor: theme.colors.border,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  pingButtonDisabled: {
    opacity: 0.55,
  },
  pingText: {
    color: theme.colors.text,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
  },
  pingTextDisabled: {
    color: theme.colors.textSoft,
  },
});

export default Referral;
