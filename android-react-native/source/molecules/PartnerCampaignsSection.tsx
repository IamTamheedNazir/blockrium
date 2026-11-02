import React, {useEffect, useMemo, useRef, useState} from 'react';
import {
  AppState,
  AppStateStatus,
  Linking,
  StyleSheet,
  Text,
  TextInput,
  useWindowDimensions,
  View,
} from 'react-native';
import FastImage from 'react-native-fast-image';
import Ionicons from 'react-native-vector-icons/Ionicons';
import MaterialCommunityIcons from 'react-native-vector-icons/MaterialCommunityIcons';
import {useQuery, useQueryClient} from 'react-query';
import {
  CardEntrance,
  GlassCard,
  PrimaryButton,
  SecondaryButton,
  SectionEyebrow,
} from '../design/primitives';
import theme from '../design/theme';
import {useThisAlert} from '../hooks/AlertContext';
import {
  claimPartnerCampaignReward,
  claimPartnerCampaignTaskReward,
  fetchPartnerCampaigns,
  formatPartnerCampaignDateRange,
  getPartnerCampaignActionState,
  getPartnerCampaignRewardLabel,
  getPartnerCampaignStatusLabel,
  getPartnerTaskActionState,
  getPartnerTaskRewardLabel,
  getPartnerTaskStatusLabel,
  normalizePartnerCampaignList,
  PartnerCampaign,
  PartnerCampaignTask,
  PartnerTaskProofPayload,
  partnerCampaignQueryKey,
  submitPartnerCampaignTaskProof,
  trackPartnerCampaignClick,
  trackPartnerCampaignTaskClick,
} from '../services/partnerCampaignService';

type RefreshCallback = () => void | Promise<unknown>;

type PartnerCampaignsSectionProps = {
  enabled?: boolean;
  onWalletRefresh?: RefreshCallback;
  onNotificationsRefresh?: RefreshCallback;
  onRedeemPromo?: () => void;
  delay?: number;
};

type PartnerCampaignsSectionContentProps = {
  campaigns: PartnerCampaign[];
  activeRequestKey?: string | null;
  onStartCampaign: (campaign: PartnerCampaign) => void | Promise<void>;
  onClaimCampaign: (campaign: PartnerCampaign) => void | Promise<void>;
  onStartTask: (
    campaign: PartnerCampaign,
    task: PartnerCampaignTask,
  ) => void | Promise<void>;
  onSubmitTaskProof: (
    campaign: PartnerCampaign,
    task: PartnerCampaignTask,
    proof: PartnerTaskProofPayload,
  ) => void | Promise<void>;
  onClaimTask: (
    campaign: PartnerCampaign,
    task: PartnerCampaignTask,
  ) => void | Promise<void>;
  onRedeemPromo?: () => void;
  delay?: number;
};

type ProofDraft = {
  evidenceUrl: string;
  evidenceNote: string;
};

type LocalTaskState = Partial<
  Pick<PartnerCampaignTask, 'userStatus' | 'clicked' | 'claimed' | 'canClaim'>
>;

const emptyProofDraft: ProofDraft = {
  evidenceUrl: '',
  evidenceNote: '',
};

const getRequestKey = (campaign: PartnerCampaign, action: string) =>
  `campaign:${action}:${campaign.id}`;

const getTaskIdentityKey = (
  campaign: PartnerCampaign,
  task: PartnerCampaignTask,
) => `${campaign.id}:${task.id}`;

const getTaskRequestKey = (
  campaign: PartnerCampaign,
  task: PartnerCampaignTask,
  action: string,
) => `task:${action}:${campaign.id}:${task.id}`;

const toneStyles = {
  primary: {
    badge: 'rgba(0, 214, 176, 0.14)',
    border: 'rgba(0, 214, 176, 0.24)',
    text: theme.colors.primary,
  },
  secondary: {
    badge: 'rgba(151, 166, 195, 0.12)',
    border: theme.colors.border,
    text: theme.colors.textSoft,
  },
  success: {
    badge: 'rgba(93, 211, 158, 0.14)',
    border: 'rgba(93, 211, 158, 0.24)',
    text: theme.colors.success,
  },
  warning: {
    badge: 'rgba(240, 185, 11, 0.14)',
    border: 'rgba(240, 185, 11, 0.24)',
    text: theme.colors.warning,
  },
  danger: {
    badge: 'rgba(255, 90, 110, 0.14)',
    border: 'rgba(255, 90, 110, 0.24)',
    text: theme.colors.danger,
  },
};

const getTaskIconName = (task: PartnerCampaignTask) => {
  if (task.type.includes('website') || task.type.includes('web')) {
    return 'web';
  }

  if (task.type.includes('social')) {
    return 'account-group-outline';
  }

  return 'clipboard-check-outline';
};

const applyLocalStatus = (
  campaign: PartnerCampaign,
  localStatus?: string,
): PartnerCampaign => {
  if (!localStatus) {
    return campaign;
  }

  return {
    ...campaign,
    userStatus: localStatus,
    clicked: campaign.clicked || localStatus === 'clicked',
    claimed: campaign.claimed || localStatus === 'claimed',
    canClaim: localStatus === 'clicked' ? campaign.canClaim : campaign.canClaim,
  };
};

const applyLocalTaskState = (
  task: PartnerCampaignTask,
  localState?: LocalTaskState,
): PartnerCampaignTask => {
  if (!localState) {
    return task;
  }

  return {
    ...task,
    userStatus: localState.userStatus ?? task.userStatus,
    clicked: localState.clicked ?? task.clicked,
    claimed: localState.claimed ?? task.claimed,
    canClaim: localState.canClaim ?? task.canClaim,
  };
};

export const PartnerCampaignsSectionContent = ({
  campaigns,
  activeRequestKey,
  onStartCampaign,
  onClaimCampaign,
  onStartTask,
  onSubmitTaskProof,
  onClaimTask,
  onRedeemPromo,
  delay = 52,
}: PartnerCampaignsSectionContentProps) => {
  const {width} = useWindowDimensions();
  const isCompact = width < 380;
  const [openProofTasks, setOpenProofTasks] = useState<Record<string, boolean>>(
    {},
  );
  const [proofDrafts, setProofDrafts] = useState<Record<string, ProofDraft>>(
    {},
  );

  if (campaigns.length === 0 && !onRedeemPromo) {
    return null;
  }

  const updateProofDraft = (
    key: string,
    patch: Partial<ProofDraft>,
  ): void => {
    setProofDrafts(current => ({
      ...current,
      [key]: {
        ...(current[key] ?? emptyProofDraft),
        ...patch,
      },
    }));
  };

  const openProofForm = (key: string): void => {
    setOpenProofTasks(current => ({...current, [key]: true}));
  };

  return (
    <CardEntrance delay={delay}>
      <View testID="partner-campaigns-section">
        {campaigns.length > 0 ? (
          <View style={styles.sectionHeader}>
            <View style={styles.sectionHeaderCopy}>
              <Text style={styles.sectionTitle}>Partner campaigns</Text>
              <Text style={styles.sectionSubtitle}>
                Verified partner tasks with BRC rewards.
              </Text>
            </View>
          </View>
        ) : null}

        {onRedeemPromo && !campaigns.some(campaign => campaign.sharedPromo?.enabled) ? (
          <GlassCard style={styles.emptyCampaignCard}>
            <MaterialCommunityIcons
              name="ticket-confirmation-outline"
              size={28}
              color={theme.colors.primary}
            />
            <Text style={styles.emptyCampaignTitle}>
              Have a promo code?
            </Text>
            <PrimaryButton
              testID="partner-promo-redeem-entry"
              label="Enter promo code"
              icon="ticket-confirmation-outline"
              onPress={onRedeemPromo}
              style={styles.campaignButton}
            />
          </GlassCard>
        ) : null}

        {campaigns.map(campaign => {
          const actionState = getPartnerCampaignActionState(campaign);
          const statusTone =
            toneStyles[actionState.tone] ?? toneStyles.secondary;
          const startRequestKey = getRequestKey(campaign, 'start');
          const claimRequestKey = getRequestKey(campaign, 'claim');
          const isCampaignBusy =
            activeRequestKey === startRequestKey ||
            activeRequestKey === claimRequestKey;
          const hasTasks = campaign.tasks.length > 0;

          return (
            <GlassCard
              key={String(campaign.id)}
              style={[
                styles.campaignCard,
                isCompact && styles.campaignCardCompact,
              ]}>
              <View style={styles.campaignTopRow}>
                <View style={styles.campaignCopy}>
                  <SectionEyebrow text={campaign.partnerName} />
                  <Text style={styles.campaignTitle} numberOfLines={2}>
                    {campaign.title}
                  </Text>
                  <Text style={styles.campaignDescription} numberOfLines={3}>
                    {campaign.description ||
                      'Open partner tasks and claim verified campaign rewards.'}
                  </Text>
                </View>
                <View
                  style={[
                    styles.statusBadge,
                    {
                      backgroundColor: statusTone.badge,
                      borderColor: statusTone.border,
                    },
                  ]}>
                  <Text
                    style={[styles.statusBadgeText, {color: statusTone.text}]}>
                    {getPartnerCampaignStatusLabel(campaign)}
                  </Text>
                </View>
              </View>

              {campaign.imageUrl ? (
                <FastImage
                  source={{uri: campaign.imageUrl}}
                  style={styles.campaignImage}
                  resizeMode="cover"
                />
              ) : (
                <View style={styles.campaignImageFallback}>
                  <MaterialCommunityIcons
                    name="handshake-outline"
                    size={34}
                    color={theme.colors.primary}
                  />
                </View>
              )}

              <View style={styles.campaignMetaRow}>
                <View style={styles.rewardPill}>
                  <Ionicons
                    name="gift-outline"
                    size={15}
                    color={theme.colors.accentWarm}
                  />
                  <Text style={styles.rewardPillText}>
                    {getPartnerCampaignRewardLabel(campaign)}
                  </Text>
                </View>
                <View style={styles.datePill}>
                  <Ionicons
                    name="calendar-outline"
                    size={14}
                    color={theme.colors.textSoft}
                  />
                  <Text style={styles.datePillText}>
                    {formatPartnerCampaignDateRange(campaign)}
                  </Text>
                </View>
                {campaign.sharedPromo?.enabled ? (
                  <View style={styles.promoPill}>
                    <Ionicons name="ticket-outline" size={14} color={theme.colors.primarySoft} />
                    <Text style={styles.promoPillText}>
                      {campaign.sharedPromo.rewardAmount || 0} {campaign.sharedPromo.rewardLabel || 'BRC'} · {Math.max(0, (campaign.sharedPromo.dailyLimit || 0) - (campaign.sharedPromo.dailyClaims || 0))} left today
                    </Text>
                  </View>
                ) : null}
              </View>

              {hasTasks ? (
                <View style={styles.taskList}>
                  <Text style={styles.taskListTitle}>Tasks</Text>
                  {campaign.tasks.map(task => {
                    const taskAction = getPartnerTaskActionState(task);
                    const taskTone =
                      toneStyles[taskAction.tone] ?? toneStyles.secondary;
                    const taskKey = getTaskIdentityKey(campaign, task);
                    const taskStartKey = getTaskRequestKey(
                      campaign,
                      task,
                      'start',
                    );
                    const taskSubmitKey = getTaskRequestKey(
                      campaign,
                      task,
                      'submit',
                    );
                    const taskClaimKey = getTaskRequestKey(
                      campaign,
                      task,
                      'claim',
                    );
                    const isTaskBusy =
                      activeRequestKey === taskStartKey ||
                      activeRequestKey === taskSubmitKey ||
                      activeRequestKey === taskClaimKey;
                    const draft = proofDrafts[taskKey] ?? emptyProofDraft;
                    const proofOpen = Boolean(openProofTasks[taskKey]);

                    return (
                      <View
                        key={String(task.id)}
                        testID={`partner-campaign-task-${campaign.id}-${task.id}`}
                        style={styles.taskCard}>
                        <View style={styles.taskHeader}>
                          <View style={styles.taskIconShell}>
                            <MaterialCommunityIcons
                              name={getTaskIconName(task)}
                              size={18}
                              color={theme.colors.primary}
                            />
                          </View>
                          <View style={styles.taskCopy}>
                            <Text style={styles.taskTitle} numberOfLines={2}>
                              {task.title}
                            </Text>
                            {task.instructions ? (
                              <Text
                                style={styles.taskInstructions}
                                numberOfLines={3}>
                                {task.instructions}
                              </Text>
                            ) : null}
                          </View>
                          <View
                            style={[
                              styles.taskStatusBadge,
                              {
                                backgroundColor: taskTone.badge,
                                borderColor: taskTone.border,
                              },
                            ]}>
                            <Text
                              style={[
                                styles.taskStatusText,
                                {color: taskTone.text},
                              ]}>
                              {getPartnerTaskStatusLabel(task)}
                            </Text>
                          </View>
                        </View>

                        <View style={styles.taskMetaRow}>
                          <View style={styles.taskRewardPill}>
                            <Ionicons
                              name="diamond-outline"
                              size={14}
                              color={theme.colors.accentWarm}
                            />
                            <Text style={styles.taskRewardText}>
                              {getPartnerTaskRewardLabel(task)}
                            </Text>
                          </View>
                          <Text style={styles.taskModeText}>
                            {task.type === 'join_telegram'
                              ? 'Tap to earn'
                              : task.completionMode === 'admin_approval'
                              ? 'Proof required'
                              : 'Click and claim'}
                          </Text>
                        </View>

                        {taskAction.helperText ? (
                          <Text
                            testID={`partner-campaign-task-helper-${campaign.id}-${task.id}`}
                            style={[
                              styles.helperText,
                              taskAction.tone === 'danger' &&
                                styles.helperTextDanger,
                            ]}>
                            {taskAction.helperText}
                          </Text>
                        ) : null}

                        {taskAction.kind === 'claim' ? (
                          <PrimaryButton
                            testID={`partner-campaign-task-action-${campaign.id}-${task.id}`}
                            label={taskAction.label}
                            icon="gift-open-outline"
                            loading={isTaskBusy}
                            disabled={taskAction.disabled || isTaskBusy}
                            onPress={() => onClaimTask(campaign, task)}
                            style={styles.taskButton}
                          />
                        ) : taskAction.kind === 'start' ? (
                          <PrimaryButton
                            testID={`partner-campaign-task-action-${campaign.id}-${task.id}`}
                            label={taskAction.label}
                            icon="open-in-new"
                            loading={isTaskBusy}
                            disabled={taskAction.disabled || isTaskBusy}
                            onPress={() => onStartTask(campaign, task)}
                            style={styles.taskButton}
                          />
                        ) : taskAction.kind === 'submit' ? (
                          <>
                            <PrimaryButton
                              testID={`partner-campaign-task-action-${campaign.id}-${task.id}`}
                              label={taskAction.label}
                              icon="file-document-edit-outline"
                              loading={isTaskBusy}
                              disabled={taskAction.disabled || isTaskBusy}
                              onPress={() => openProofForm(taskKey)}
                              style={styles.taskButton}
                            />
                            {proofOpen ? (
                              <View style={styles.proofBox}>
                                <TextInput
                                  testID={`partner-campaign-task-proof-url-${campaign.id}-${task.id}`}
                                  value={draft.evidenceUrl}
                                  onChangeText={value =>
                                    updateProofDraft(taskKey, {
                                      evidenceUrl: value,
                                    })
                                  }
                                  placeholder="Proof link"
                                  placeholderTextColor={theme.colors.textSoft}
                                  autoCapitalize="none"
                                  autoCorrect={false}
                                  style={styles.proofInput}
                                />
                                <TextInput
                                  testID={`partner-campaign-task-proof-note-${campaign.id}-${task.id}`}
                                  value={draft.evidenceNote}
                                  onChangeText={value =>
                                    updateProofDraft(taskKey, {
                                      evidenceNote: value,
                                    })
                                  }
                                  placeholder="Short note"
                                  placeholderTextColor={theme.colors.textSoft}
                                  multiline
                                  style={[
                                    styles.proofInput,
                                    styles.proofNoteInput,
                                  ]}
                                />
                                <PrimaryButton
                                  testID={`partner-campaign-task-proof-submit-${campaign.id}-${task.id}`}
                                  label="Send Proof"
                                  icon="send-outline"
                                  loading={isTaskBusy}
                                  disabled={isTaskBusy}
                                  onPress={() =>
                                    onSubmitTaskProof(campaign, task, draft)
                                  }
                                  style={styles.taskButton}
                                />
                              </View>
                            ) : null}
                          </>
                        ) : (
                          <SecondaryButton
                            testID={`partner-campaign-task-action-${campaign.id}-${task.id}`}
                            label={taskAction.label}
                            icon="clock-outline"
                            loading={isTaskBusy}
                            disabled={taskAction.disabled || isTaskBusy}
                            style={styles.taskButton}
                          />
                        )}
                      </View>
                    );
                  })}
                </View>
              ) : (
                <>
                  {actionState.helperText ? (
                    <Text
                      testID={`partner-campaign-helper-${campaign.id}`}
                      style={[
                        styles.helperText,
                        actionState.tone === 'danger' &&
                          styles.helperTextDanger,
                      ]}>
                      {actionState.helperText}
                    </Text>
                  ) : null}

                  {actionState.kind === 'claim' ? (
                    <PrimaryButton
                      testID={`partner-campaign-action-${campaign.id}`}
                      label={actionState.label}
                      icon="gift-open-outline"
                      loading={isCampaignBusy}
                      disabled={actionState.disabled || isCampaignBusy}
                      onPress={() => onClaimCampaign(campaign)}
                      style={styles.campaignButton}
                    />
                  ) : actionState.kind === 'start' ? (
                    <PrimaryButton
                      testID={`partner-campaign-action-${campaign.id}`}
                      label={actionState.label}
                      icon="open-in-new"
                      loading={isCampaignBusy}
                      disabled={actionState.disabled || isCampaignBusy}
                      onPress={() => onStartCampaign(campaign)}
                      style={styles.campaignButton}
                    />
                  ) : (
                    <SecondaryButton
                      testID={`partner-campaign-action-${campaign.id}`}
                      label={actionState.label}
                      icon="clock-outline"
                      loading={isCampaignBusy}
                      disabled={actionState.disabled || isCampaignBusy}
                      style={styles.campaignButton}
                    />
                  )}
                </>
              )}
              {campaign.sharedPromo?.enabled ? (
                <PrimaryButton
                  testID={`partner-campaign-promo-${campaign.id}`}
                  label="Redeem partner reward"
                  icon="ticket-confirmation-outline"
                  onPress={onRedeemPromo}
                  disabled={!onRedeemPromo}
                  style={styles.campaignButton}
                />
              ) : null}
            </GlassCard>
          );
        })}
      </View>
    </CardEntrance>
  );
};

const PartnerCampaignsSection = ({
  enabled = true,
  onWalletRefresh,
  onNotificationsRefresh,
  onRedeemPromo,
  delay,
}: PartnerCampaignsSectionProps) => {
  const {showAlert} = useThisAlert();
  const queryClient = useQueryClient();
  const requestInProgressRef = useRef<string | null>(null);
  const appStateRef = useRef<AppStateStatus>(AppState.currentState);
  const [activeRequestKey, setActiveRequestKey] = useState<string | null>(null);
  const [localStatuses, setLocalStatuses] = useState<Record<string, string>>(
    {},
  );
  const [localTaskStates, setLocalTaskStates] = useState<
    Record<string, LocalTaskState>
  >({});
  const {data: campaignData, refetch: refetchCampaigns} = useQuery(
    partnerCampaignQueryKey,
    fetchPartnerCampaigns,
    {
      retry: 0,
      staleTime: 30000,
      refetchOnWindowFocus: false,
      enabled,
    },
  );
  // A query cache can survive app upgrades. Re-normalize cached values at the
  // UI boundary so an older or malformed cache entry cannot crash Home render.
  const campaigns = useMemo(
    () => normalizePartnerCampaignList(campaignData),
    [campaignData],
  );

  const visibleCampaigns = useMemo(
    () =>
      campaigns.map(campaign => {
        const nextCampaign = applyLocalStatus(
          campaign,
          localStatuses[String(campaign.id)],
        );

        return {
          ...nextCampaign,
          tasks: nextCampaign.tasks.map(task =>
            applyLocalTaskState(
              task,
              localTaskStates[getTaskIdentityKey(campaign, task)],
            ),
          ),
        };
      }),
    [campaigns, localStatuses, localTaskStates],
  );

  const refreshCampaignData = async () => {
    await refetchCampaigns();
    queryClient.invalidateQueries(partnerCampaignQueryKey);
  };

  const refreshRewardData = async () => {
    queryClient.invalidateQueries(['auth/wallet', 'GET']);
    queryClient.invalidateQueries(['auth/count-notification', 'GET']);
    queryClient.invalidateQueries('blockrium-notification-inbox');
    queryClient.invalidateQueries(partnerCampaignQueryKey);
    await Promise.all([
      refetchCampaigns(),
      onWalletRefresh?.(),
      onNotificationsRefresh?.(),
    ]);
  };

  useEffect(() => {
    const subscription = AppState.addEventListener?.('change', nextState => {
      const wasInactive = /inactive|background/.test(appStateRef.current);

      if (wasInactive && nextState === 'active') {
        if (enabled) {
          refetchCampaigns();
        }
      }

      appStateRef.current = nextState;
    });

    return () => {
      subscription?.remove?.();
    };
  }, [enabled, refetchCampaigns]);

  const runExclusive = async (
    requestKey: string,
    action: () => Promise<void>,
  ) => {
    if (requestInProgressRef.current) {
      return;
    }

    requestInProgressRef.current = requestKey;
    setActiveRequestKey(requestKey);

    try {
      await action();
    } finally {
      requestInProgressRef.current = null;
      setActiveRequestKey(null);
    }
  };

  const handleStartCampaign = (campaign: PartnerCampaign) =>
    runExclusive(getRequestKey(campaign, 'start'), async () => {
      try {
        const result = await trackPartnerCampaignClick(campaign);

        setLocalStatuses(current => ({
          ...current,
          [String(campaign.id)]:
            campaign.reward.mode === 'admin_approval' ? 'pending' : 'clicked',
        }));

        await Linking.openURL(result.destinationUrl);
      } catch (error: any) {
        showAlert(
          error?.message || 'Unable to start this partner campaign.',
          'ERROR',
        );
      }
    });

  const handleClaimCampaign = (campaign: PartnerCampaign) =>
    runExclusive(getRequestKey(campaign, 'claim'), async () => {
      if (!campaign.canClaim) {
        showAlert('This reward is not ready to claim yet.', 'INFO');
        return;
      }

      try {
        await claimPartnerCampaignReward(campaign);
        setLocalStatuses(current => ({
          ...current,
          [String(campaign.id)]: 'claimed',
        }));
        showAlert('Partner campaign reward claimed.', 'SUCCESS');
        await refreshRewardData();
      } catch (error: any) {
        showAlert(
          error?.message || 'Unable to claim this partner reward.',
          'ERROR',
        );
      }
    });

  const handleStartTask = (
    campaign: PartnerCampaign,
    task: PartnerCampaignTask,
  ) =>
    runExclusive(getTaskRequestKey(campaign, task, 'start'), async () => {
      try {
        const result = await trackPartnerCampaignTaskClick(campaign, task);
        const isTelegramTask = task.type === 'join_telegram';

        setLocalTaskStates(current => ({
          ...current,
          [getTaskIdentityKey(campaign, task)]: {
            userStatus: 'clicked',
            clicked: true,
            canClaim: task.completionMode === 'click_claim' || task.canClaim,
          },
        }));

        if (isTelegramTask) {
          try {
            await claimPartnerCampaignTaskReward(campaign, task);
            setLocalTaskStates(current => ({
              ...current,
              [getTaskIdentityKey(campaign, task)]: {
                userStatus: 'claimed',
                clicked: true,
                claimed: true,
                canClaim: false,
              },
            }));
            showAlert('Telegram task reward claimed.', 'SUCCESS');
            refreshRewardData().catch(() => undefined);
          } catch (error: any) {
            showAlert(
              error?.message || 'Unable to claim the Telegram task reward.',
              'ERROR',
            );
            refreshCampaignData().catch(() => undefined);
          }
        }
        await Linking.openURL(result.destinationUrl);
      } catch (error: any) {
        showAlert(error?.message || 'Unable to open this partner task.', 'ERROR');
      }
    });

  const handleSubmitTaskProof = (
    campaign: PartnerCampaign,
    task: PartnerCampaignTask,
    proof: PartnerTaskProofPayload,
  ) =>
    runExclusive(getTaskRequestKey(campaign, task, 'submit'), async () => {
      const hasProof = Boolean(
        proof.evidenceUrl?.trim() || proof.evidenceNote?.trim(),
      );

      if (!hasProof) {
        showAlert('Add a proof link or a short note before submitting.', 'INFO');
        return;
      }

      try {
        await submitPartnerCampaignTaskProof(campaign, task, proof);
        setLocalTaskStates(current => ({
          ...current,
          [getTaskIdentityKey(campaign, task)]: {
            userStatus: 'pending',
            clicked: true,
            canClaim: false,
          },
        }));
        showAlert('Task proof submitted for admin approval.', 'SUCCESS');
        await refreshCampaignData();
      } catch (error: any) {
        showAlert(error?.message || 'Unable to submit this task proof.', 'ERROR');
      }
    });

  const handleClaimTask = (
    campaign: PartnerCampaign,
    task: PartnerCampaignTask,
  ) =>
    runExclusive(getTaskRequestKey(campaign, task, 'claim'), async () => {
      if (!task.canClaim) {
        showAlert('This task reward is not ready to claim yet.', 'INFO');
        return;
      }

      try {
        await claimPartnerCampaignTaskReward(campaign, task);
        setLocalTaskStates(current => ({
          ...current,
          [getTaskIdentityKey(campaign, task)]: {
            userStatus: 'claimed',
            clicked: true,
            claimed: true,
            canClaim: false,
          },
        }));
        showAlert('Partner task reward claimed.', 'SUCCESS');
        await refreshRewardData();
      } catch (error: any) {
        showAlert(error?.message || 'Unable to claim this task reward.', 'ERROR');
      }
    });

  return (
    <PartnerCampaignsSectionContent
      campaigns={visibleCampaigns}
      activeRequestKey={activeRequestKey}
      onStartCampaign={handleStartCampaign}
      onClaimCampaign={handleClaimCampaign}
      onStartTask={handleStartTask}
      onSubmitTaskProof={handleSubmitTaskProof}
      onClaimTask={handleClaimTask}
      onRedeemPromo={onRedeemPromo}
      delay={delay}
    />
  );
};

const styles = StyleSheet.create({
  emptyCampaignCard: {
    alignItems: 'flex-start',
    gap: 8,
    borderColor: 'rgba(0, 214, 176, 0.2)',
  },
  emptyCampaignTitle: {
    color: theme.colors.text,
    fontSize: 16,
    fontFamily: theme.typography.heading,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 2,
    marginBottom: 10,
  },
  sectionHeaderCopy: {
    flex: 1,
    minWidth: 0,
  },
  sectionTitle: {
    color: theme.colors.text,
    fontSize: 19,
    fontFamily: theme.typography.display,
  },
  sectionSubtitle: {
    marginTop: 4,
    color: theme.colors.textSoft,
    fontSize: 14,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  campaignCard: {
    borderColor: 'rgba(240, 185, 11, 0.16)',
  },
  campaignCardCompact: {
    padding: 14,
  },
  campaignTopRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 10,
  },
  campaignCopy: {
    flex: 1,
    minWidth: 0,
  },
  campaignTitle: {
    color: theme.colors.text,
    fontSize: 20,
    lineHeight: 24,
    fontFamily: theme.typography.heading,
  },
  campaignDescription: {
    marginTop: 7,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  statusBadge: {
    minHeight: 30,
    maxWidth: 132,
    borderRadius: theme.radii.pill,
    paddingHorizontal: 10,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
  },
  statusBadgeText: {
    fontSize: 11,
    textAlign: 'center',
    textTransform: 'capitalize',
    fontFamily: theme.typography.bodyBold,
  },
  campaignImage: {
    width: '100%',
    height: 154,
    borderRadius: theme.radii.lg,
    marginTop: 14,
    backgroundColor: theme.colors.surfaceStrong,
  },
  campaignImageFallback: {
    width: '100%',
    height: 132,
    borderRadius: theme.radii.lg,
    marginTop: 14,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(240, 185, 11, 0.08)',
    borderWidth: 1,
    borderColor: 'rgba(240, 185, 11, 0.16)',
  },
  campaignMetaRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 12,
  },
  rewardPill: {
    minHeight: 34,
    borderRadius: theme.radii.pill,
    paddingHorizontal: 11,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: 'rgba(240, 185, 11, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(240, 185, 11, 0.22)',
  },
  rewardPillText: {
    color: theme.colors.accentWarm,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  promoPill: {
    minHeight: 34,
    borderRadius: theme.radii.pill,
    paddingHorizontal: 11,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: 'rgba(0, 214, 176, 0.09)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.2)',
  },
  promoPillText: {
    color: theme.colors.primarySoft,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  datePill: {
    minHeight: 34,
    borderRadius: theme.radii.pill,
    paddingHorizontal: 11,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: 'rgba(151, 166, 195, 0.08)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  datePillText: {
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  taskList: {
    marginTop: 14,
    gap: 10,
  },
  taskListTitle: {
    color: theme.colors.textSoft,
    fontSize: 12,
    textTransform: 'uppercase',
    fontFamily: theme.typography.bodyBold,
  },
  taskCard: {
    borderRadius: theme.radii.lg,
    padding: 12,
    backgroundColor: 'rgba(8, 18, 30, 0.46)',
    borderWidth: 1,
    borderColor: 'rgba(151, 166, 195, 0.12)',
  },
  taskHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
  },
  taskIconShell: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0, 214, 176, 0.1)',
  },
  taskCopy: {
    flex: 1,
    minWidth: 0,
  },
  taskTitle: {
    color: theme.colors.text,
    fontSize: 15,
    lineHeight: 19,
    fontFamily: theme.typography.bodyBold,
  },
  taskInstructions: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
  taskStatusBadge: {
    minHeight: 28,
    maxWidth: 110,
    borderRadius: theme.radii.pill,
    paddingHorizontal: 8,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
  },
  taskStatusText: {
    fontSize: 10,
    textAlign: 'center',
    textTransform: 'capitalize',
    fontFamily: theme.typography.bodyBold,
  },
  taskMetaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 10,
  },
  taskRewardPill: {
    minHeight: 30,
    borderRadius: theme.radii.pill,
    paddingHorizontal: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: 'rgba(240, 185, 11, 0.1)',
    borderWidth: 1,
    borderColor: 'rgba(240, 185, 11, 0.18)',
  },
  taskRewardText: {
    color: theme.colors.accentWarm,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  taskModeText: {
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  helperText: {
    marginTop: 10,
    color: theme.colors.textSoft,
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
  helperTextDanger: {
    color: theme.colors.danger,
  },
  campaignButton: {
    marginTop: 14,
  },
  taskButton: {
    marginTop: 12,
  },
  proofBox: {
    marginTop: 10,
    gap: 8,
  },
  proofInput: {
    minHeight: 42,
    borderRadius: theme.radii.md,
    paddingHorizontal: 12,
    color: theme.colors.text,
    backgroundColor: 'rgba(3, 10, 18, 0.42)',
    borderWidth: 1,
    borderColor: theme.colors.border,
    fontFamily: theme.typography.body,
  },
  proofNoteInput: {
    minHeight: 76,
    paddingTop: 10,
    textAlignVertical: 'top',
  },
});

export default PartnerCampaignsSection;
