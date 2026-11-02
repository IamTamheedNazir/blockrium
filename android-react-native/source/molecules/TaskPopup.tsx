import React, {useEffect, useRef, useState} from 'react';
import {
  Animated,
  Dimensions,
  KeyboardAvoidingView,
  Linking,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import FastImage from 'react-native-fast-image';
import {RFValue} from 'react-native-responsive-fontsize';
import MaterialCommunityIcon from 'react-native-vector-icons/MaterialCommunityIcons';
import InAppBrowser from 'react-native-inappbrowser-reborn';
import {HStack} from '../ui/nativeBaseCompat';
import {images} from '../../assets/images';
import {hp, wp} from '../utils/styleConstants';
import Input from '../atoms/Input';
import {useThisAlert} from '../hooks/AlertContext';
import fonts from '../../assets/fonts';
import theme from '../design/theme';
import {postApi} from '../storage/server';
import {logAppIssue} from '../services/appIssueLogger';
import {queueEngagementEvent} from '../services/engagementAnalyticsService';
import {showRewardNotification} from '../services/miningReminderService';

const {width, height} = Dimensions.get('screen');

type TaskClaimState =
  | 'ready'
  | 'opened'
  | 'verifying'
  | 'claiming'
  | 'claimed'
  | 'failed';

const isHiddenCodeTask = (task: any) => String(task?.id) === '6';

const getFriendlyTaskMessage = (value: unknown, fallback: string) => {
  const message =
    value instanceof Error
      ? value.message
      : typeof value === 'string'
      ? value
      : '';

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

const getNormalizedLink = (link: string) => {
  if (!link) {
    return '';
  }

  if (/^[a-z][a-z0-9+.-]*:\/\//i.test(link) || /^mailto:/i.test(link)) {
    return link;
  }

  return `https://${link.replace(/^\/+/, '')}`;
};

const TaskPopup = ({visible, setVisible, popUpData, onClaimed}: any) => {
  const translateYAnim = useRef(new Animated.Value(height)).current;
  const verifyTimerRef = useRef<NodeJS.Timeout | null>(null);
  const [claimState, setClaimState] = useState<TaskClaimState>('ready');
  const [code, setCode] = useState('');
  const [failureMessage, setFailureMessage] = useState('');
  const {showAlert} = useThisAlert();
  const isBusy = claimState === 'verifying' || claimState === 'claiming';
  const canClaim = claimState === 'opened' || claimState === 'failed';
  const rewardLabel = popUpData?.coin_reward
    ? `+${popUpData.coin_reward} BRC`
    : 'Task reward';
  const taskAnalyticsPayload = {
    taskId: popUpData?.id,
    reward: popUpData?.coin_reward,
    title: popUpData?.task_name,
  };

  useEffect(() => {
    if (visible) {
      setClaimState('ready');
      setCode('');
      setFailureMessage('');
      Animated.spring(translateYAnim, {
        toValue: 0,
        useNativeDriver: true,
        damping: 26,
        stiffness: 190,
      }).start();
    }

    return () => {
      if (verifyTimerRef.current) {
        clearTimeout(verifyTimerRef.current);
        verifyTimerRef.current = null;
      }
    };
  }, [translateYAnim, visible]);

  const openTaskLink = async (rawLink: string) => {
    const normalizedLink = getNormalizedLink(rawLink);

    if (!normalizedLink) {
      throw new Error('Task link is unavailable.');
    }

    if (
      /^https?:\/\//i.test(normalizedLink) &&
      (await InAppBrowser.isAvailable())
    ) {
      await InAppBrowser.open(normalizedLink, {
        toolbarColor: theme.colors.surfaceStrong,
        secondaryToolbarColor: theme.colors.surface,
        showTitle: true,
        enableDefaultShare: true,
        enableUrlBarHiding: true,
      });
      return;
    }

    await Linking.openURL(normalizedLink);
  };

  const openPressGo = async () => {
    if (!popUpData?.task_link) {
      showAlert('Task link is missing for this activity.', 'ERROR');
      return;
    }

    setClaimState('verifying');
    queueEngagementEvent({
      eventName: 'task_open_attempt',
      area: 'task',
      status: 'attempted',
      route: 'Tasks',
      metadata: taskAnalyticsPayload,
    });

    try {
      await openTaskLink(popUpData?.task_link);
      queueEngagementEvent({
        eventName: 'task_open_success',
        area: 'task',
        status: 'succeeded',
        route: 'Tasks',
        metadata: taskAnalyticsPayload,
      });
      verifyTimerRef.current = setTimeout(() => {
        setClaimState('opened');
      }, 900);
      showAlert(
        'Task opened securely. Return here and claim once you finish it.',
        'INFO',
      );
    } catch (error) {
      console.error('Error opening task link:', error);
      const message = getFriendlyTaskMessage(
        error,
        'Unable to open this task right now. Please try again.',
      );
      queueEngagementEvent({
        eventName: 'task_open_failed',
        area: 'task',
        status: 'failed',
        route: 'Tasks',
        metadata: {
          ...taskAnalyticsPayload,
          error: error instanceof Error ? error.message : 'Task open failed.',
        },
      });
      logAppIssue({
        severity: 'warning',
        source: 'task',
        action: 'task_open_failed',
        endpoint: 'task_link',
        method: 'OPEN',
        message,
        metadata: taskAnalyticsPayload,
      }).catch(logError => {
        console.log('Unable to queue task open issue log', logError);
      });
      setClaimState('ready');
      setFailureMessage(message);
      showAlert(message, 'ERROR');
    }
  };

  const closeModal = () => {
    if (verifyTimerRef.current) {
      clearTimeout(verifyTimerRef.current);
      verifyTimerRef.current = null;
    }
    setCode('');
    setClaimState('ready');
    setFailureMessage('');
    Animated.timing(translateYAnim, {
      toValue: height,
      duration: 180,
      useNativeDriver: true,
    }).start(() => setVisible(false));
  };

  const onPressClaimed = async () => {
    if (isBusy || claimState === 'claimed') {
      return;
    }

    if (isHiddenCodeTask(popUpData) && !code) {
      showAlert('Enter the hidden code before claiming the reward.', 'ERROR');
      return;
    }

    setClaimState('claiming');
    setFailureMessage('');
    const startedAt = Date.now();
    queueEngagementEvent({
      eventName: 'task_claim_attempt',
      area: 'task',
      status: 'attempted',
      route: 'Tasks',
      endpoint: 'auth/claimed-task-reward',
      metadata: taskAnalyticsPayload,
    });

    try {
      const response = await postApi('auth/claimed-task-reward', {
        reward: popUpData?.coin_reward,
        task_id: popUpData?.id,
        code,
      });
      const message =
        response?.error ||
        response?.data?.message ||
        response?.data?.data?.message ||
        'Task reward could not be claimed right now.';
      const friendlyMessage = getFriendlyTaskMessage(
        message,
        'Task reward could not be claimed right now. Please retry.',
      );

      if (!response?.success || response?.data?.success === false) {
        setClaimState('failed');
        setFailureMessage(friendlyMessage);
        logAppIssue({
          severity: 'warning',
          source: 'task',
          action: 'task_claim_failed',
          endpoint: 'auth/claimed-task-reward',
          method: 'POST',
          message: friendlyMessage,
          metadata: taskAnalyticsPayload,
        }).catch(error => {
          console.log('Unable to queue task claim issue log', error);
        });
        queueEngagementEvent({
          eventName: 'task_claim_failed',
          area: 'task',
          status: 'failed',
          route: 'Tasks',
          endpoint: 'auth/claimed-task-reward',
          durationMs: Date.now() - startedAt,
          metadata: {...taskAnalyticsPayload, message: friendlyMessage},
        });
        showAlert(friendlyMessage, 'ERROR');
        return;
      }

      setClaimState('claimed');
      queueEngagementEvent({
        eventName: 'task_claim_success',
        area: 'task',
        status: 'succeeded',
        route: 'Tasks',
        endpoint: 'auth/claimed-task-reward',
        durationMs: Date.now() - startedAt,
        metadata: taskAnalyticsPayload,
      });
      showAlert(
        response?.data?.message || 'Task reward claimed successfully.',
        'SUCCESS',
      );
      showRewardNotification(
        'Task reward received',
        `${rewardLabel} was added to your Blockrium wallet.`,
        'Tasks',
      );
      await onClaimed?.(response);
      setCode('');
      Animated.timing(translateYAnim, {
        toValue: height,
        duration: 180,
        useNativeDriver: true,
      }).start(() => setVisible(false));
    } catch (error) {
      console.error('Task reward claim error', error);
      setClaimState('failed');
      const message = getFriendlyTaskMessage(
        error,
        'Task reward could not be claimed right now. Please retry.',
      );
      setFailureMessage(message);
      logAppIssue({
        severity: 'warning',
        source: 'task',
        action: 'task_claim_exception',
        endpoint: 'auth/claimed-task-reward',
        method: 'POST',
        message,
        errorCode: error instanceof Error ? error.name : undefined,
        metadata: taskAnalyticsPayload,
      }).catch(logError => {
        console.log('Unable to queue task claim exception issue log', logError);
      });
      queueEngagementEvent({
        eventName: 'task_claim_exception',
        area: 'task',
        status: 'failed',
        route: 'Tasks',
        endpoint: 'auth/claimed-task-reward',
        durationMs: Date.now() - startedAt,
        metadata: {
          ...taskAnalyticsPayload,
          error:
            error instanceof Error ? error.message : 'Task claim exception.',
        },
      });
      showAlert(message, 'ERROR');
    }
  };

  return (
    <Modal
      transparent
      visible={Boolean(visible)}
      animationType="fade"
      statusBarTranslucent
      onRequestClose={closeModal}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={styles.overlay}>
        <Pressable
          style={styles.backdrop}
          onPress={isBusy ? undefined : closeModal}
        />
        <Animated.View
          style={[styles.sheet, {transform: [{translateY: translateYAnim}]}]}>
          <View style={styles.dragHandle} />
          <View style={styles.sheetHeader}>
            <View style={styles.sheetHeaderCopy}>
              <Text style={styles.eyebrow}>Task</Text>
              <Text style={styles.modalTitle} numberOfLines={2}>
                {isHiddenCodeTask(popUpData)
                  ? 'Find hidden code'
                  : popUpData?.task_name ?? 'Join task link'}
              </Text>
            </View>
            <TouchableOpacity
              onPress={closeModal}
              disabled={isBusy}
              style={styles.closeButton}>
              <MaterialCommunityIcon
                name="close"
                size={22}
                color={theme.colors.text}
              />
            </TouchableOpacity>
          </View>

          <ScrollView
            showsVerticalScrollIndicator={false}
            keyboardShouldPersistTaps="handled"
            contentContainerStyle={styles.sheetContent}>
            <View style={styles.visualRow}>
              <View style={styles.imageShell}>
                <FastImage
                  source={
                    popUpData?.task_icon
                      ? {uri: popUpData.task_icon}
                      : images.placeholder
                  }
                  style={styles.taskImage}
                  resizeMode="contain"
                />
              </View>
              <View style={styles.rewardPanel}>
                <Text style={styles.rewardLabel}>Reward</Text>
                {popUpData?.coin_reward ? (
                  <HStack>
                    <FastImage
                      source={images.brcCoin}
                      style={styles.rewardCoin}
                      resizeMode="contain"
                    />
                    <Text style={styles.modalContent}>
                      +{popUpData?.coin_reward}
                    </Text>
                  </HStack>
                ) : (
                  <Text style={styles.modalContent}>BRC</Text>
                )}
              </View>
            </View>

            <Text style={styles.modalDescription}>
              {isHiddenCodeTask(popUpData)
                ? 'Watch the video, find the hidden code, enter it here, then claim.'
                : 'Open the task in the in-app browser, complete the action, then return here to claim.'}
            </Text>

            <View style={styles.statusRail}>
              {[
                {key: 'start', label: 'Start', done: claimState !== 'ready'},
                {
                  key: 'verify',
                  label: claimState === 'failed' ? 'Failed' : 'Verify',
                  done:
                    canClaim ||
                    claimState === 'claiming' ||
                    claimState === 'claimed',
                  failed: claimState === 'failed',
                },
                {
                  key: 'claim',
                  label: claimState === 'claimed' ? 'Claimed' : 'Claim',
                  done: claimState === 'claimed',
                },
              ].map(item => (
                <View key={item.key} style={styles.statusItem}>
                  <View
                    style={[
                      styles.statusDot,
                      item.done && styles.statusDotDone,
                      item.failed && styles.statusDotFailed,
                    ]}>
                    <MaterialCommunityIcon
                      name={
                        item.done
                          ? 'check'
                          : item.failed
                          ? 'alert'
                          : 'circle-outline'
                      }
                      size={12}
                      color={
                        item.done
                          ? theme.colors.background
                          : item.failed
                          ? theme.colors.white
                          : theme.colors.textSoft
                      }
                    />
                  </View>
                  <Text
                    style={[
                      styles.statusText,
                      item.done && styles.statusTextDone,
                      item.failed && styles.statusTextFailed,
                    ]}>
                    {item.label}
                  </Text>
                </View>
              ))}
            </View>

            {failureMessage ? (
              <View style={styles.failureCard}>
                <MaterialCommunityIcon
                  name="information-outline"
                  size={18}
                  color={theme.colors.warning}
                />
                <Text style={styles.failureText}>{failureMessage}</Text>
              </View>
            ) : null}

            {isHiddenCodeTask(popUpData) && canClaim ? (
              <Input
                keyboardType="numeric"
                value={code}
                onChangeText={text => setCode(text)}
                placeholder="Enter hidden code"
              />
            ) : null}

            <TouchableOpacity
              style={[styles.goButton, isBusy && styles.buttonDisabled]}
              disabled={isBusy}
              onPress={openPressGo}>
              <MaterialCommunityIcon
                name="open-in-new"
                size={18}
                color={theme.colors.background}
              />
              <Text style={styles.primaryButtonText}>
                {claimState === 'verifying'
                  ? 'Verifying...'
                  : canClaim
                  ? 'Open again'
                  : 'Open task'}
              </Text>
            </TouchableOpacity>
            {canClaim ||
            claimState === 'claiming' ||
            claimState === 'claimed' ? (
              <TouchableOpacity
                style={[styles.claimButton, isBusy && styles.buttonDisabled]}
                disabled={isBusy || claimState === 'claimed'}
                onPress={onPressClaimed}>
                <MaterialCommunityIcon
                  name={
                    claimState === 'failed'
                      ? 'refresh'
                      : 'check-decagram-outline'
                  }
                  size={18}
                  color={theme.colors.background}
                />
                <Text style={styles.primaryButtonText}>
                  {claimState === 'claiming'
                    ? 'Claiming...'
                    : claimState === 'failed'
                    ? 'Retry claim'
                    : claimState === 'claimed'
                    ? 'Claimed'
                    : `Claim ${rewardLabel}`}
                </Text>
              </TouchableOpacity>
            ) : null}
          </ScrollView>
        </Animated.View>
      </KeyboardAvoidingView>
    </Modal>
  );
};

const styles = StyleSheet.create({
  rewardCoin: {
    width: wp(6),
    height: hp(3),
    marginRight: 4,
  },
  overlay: {
    flex: 1,
    justifyContent: 'center',
  },
  backdrop: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0, 0, 0, 0.76)',
  },
  sheet: {
    width,
    maxHeight: height * 0.88,
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: theme.colors.surface,
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    borderWidth: 1,
    borderColor: theme.colors.border,
    paddingHorizontal: 18,
    paddingTop: 10,
    paddingBottom: 18,
    elevation: 12,
  },
  dragHandle: {
    width: 44,
    height: 5,
    borderRadius: 99,
    backgroundColor: 'rgba(151, 166, 195, 0.28)',
    alignSelf: 'center',
    marginBottom: 14,
  },
  sheetHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 12,
  },
  sheetHeaderCopy: {
    flex: 1,
  },
  eyebrow: {
    color: theme.colors.primary,
    fontSize: RFValue(11),
    letterSpacing: 2,
    textTransform: 'uppercase',
    fontFamily: theme.typography.bodyBold,
  },
  closeButton: {
    width: 42,
    height: 42,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(151, 166, 195, 0.12)',
  },
  sheetContent: {
    paddingTop: 12,
    paddingBottom: 10,
  },
  modalTitle: {
    marginTop: 4,
    fontSize: RFValue(20),
    lineHeight: RFValue(25),
    fontFamily: fonts.heading,
    color: theme.colors.text,
  },
  modalDescription: {
    marginTop: 14,
    marginBottom: 10,
    color: theme.colors.textMuted,
    fontSize: RFValue(13),
    lineHeight: 20,
    fontFamily: theme.typography.body,
  },
  modalContent: {
    fontSize: RFValue(22),
    fontFamily: fonts.heading,
    color: theme.colors.text,
  },
  visualRow: {
    flexDirection: 'row',
    gap: 12,
  },
  imageShell: {
    flex: 1,
    minHeight: 128,
    borderRadius: 22,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(8, 20, 34, 0.72)',
  },
  taskImage: {
    width: Math.min(width / 2.8, 132),
    height: Math.min(height / 6.4, 126),
  },
  rewardPanel: {
    width: 118,
    borderRadius: 22,
    borderWidth: 1,
    borderColor: 'rgba(240, 185, 11, 0.24)',
    backgroundColor: 'rgba(240, 185, 11, 0.10)',
    padding: 14,
    justifyContent: 'center',
  },
  rewardLabel: {
    color: theme.colors.warning,
    fontSize: RFValue(11),
    marginBottom: 8,
    fontFamily: theme.typography.bodyBold,
  },
  statusRail: {
    width: '100%',
    flexDirection: 'row',
    justifyContent: 'space-between',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(8, 20, 34, 0.72)',
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginTop: 8,
  },
  statusItem: {
    alignItems: 'center',
    flex: 1,
  },
  statusDot: {
    width: 24,
    height: 24,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(147, 163, 185, 0.14)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  statusDotDone: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  statusDotFailed: {
    backgroundColor: theme.colors.danger,
    borderColor: theme.colors.danger,
  },
  statusText: {
    marginTop: 5,
    color: theme.colors.textMuted,
    fontSize: RFValue(10),
    fontFamily: theme.typography.bodyBold,
  },
  statusTextDone: {
    color: theme.colors.primary,
  },
  statusTextFailed: {
    color: theme.colors.danger,
  },
  failureCard: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 8,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: 'rgba(240, 185, 11, 0.24)',
    backgroundColor: 'rgba(240, 185, 11, 0.10)',
    padding: 12,
    marginTop: 12,
  },
  failureText: {
    flex: 1,
    color: theme.colors.textMuted,
    fontSize: RFValue(12),
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  goButton: {
    minHeight: 52,
    justifyContent: 'center',
    alignItems: 'center',
    flexDirection: 'row',
    gap: 8,
    backgroundColor: theme.colors.primary,
    borderRadius: 18,
    marginTop: 14,
  },
  claimButton: {
    minHeight: 52,
    justifyContent: 'center',
    alignItems: 'center',
    flexDirection: 'row',
    gap: 8,
    backgroundColor: theme.colors.primary,
    borderRadius: 18,
    marginTop: 10,
  },
  primaryButtonText: {
    color: theme.colors.background,
    fontSize: RFValue(15),
    fontFamily: theme.typography.bodyBold,
  },
  buttonDisabled: {
    opacity: 0.6,
  },
});

export default TaskPopup;
