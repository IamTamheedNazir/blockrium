import React, {useEffect, useMemo, useState} from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import {callMutation, callQuery} from '../hooks';
import theme from '../design/theme';
import {showOptionalActionAd} from '../services/adService';
import {showRewardNotification} from '../services/miningReminderService';
import {useThisAlert} from '../hooks/AlertContext';

const rewardLabels = ['Day 1', 'Day 2', 'Day 3', 'Day 4'];

const mapClaimedRewards = (status: number) => {
  if (status <= 0) {
    return [false, false, false, false];
  }

  return rewardLabels.map((_, index) => index < status);
};

const RewardsList = () => {
  const [claimedRewards, setClaimedRewards] = useState([
    false,
    false,
    false,
    false,
  ]);
  const {data: rewardData, refetch} = callQuery('auth/reward-status');
  const {mutateAsync: claimReward, isLoading} = callMutation(
    'auth/random-dailyreward',
    {},
  );
  const {showAlert} = useThisAlert();

  useEffect(() => {
    const status =
      rewardData?.data?.data?.status ?? rewardData?.data?.status ?? 0;
    setClaimedRewards(mapClaimedRewards(Number(status)));
  }, [rewardData]);

  const nextRewardIndex = useMemo(
    () => claimedRewards.findIndex(reward => !reward),
    [claimedRewards],
  );

  const handleClaimReward = async () => {
    try {
      const adResult = await showOptionalActionAd(18000);

      const response = await claimReward?.();
      await refetch();

      if (!response?.success) {
        showAlert(response?.error || 'Reward could not be claimed.', 'ERROR');
        return;
      }

      showAlert(
        adResult.shown
          ? 'Reward claimed successfully.'
          : 'Reward claimed. Ad did not load in time, so the claim continued.',
        'SUCCESS',
      );
      showRewardNotification(
        'Reward received',
        'Your daily reward was added to your Blockrium wallet.',
        'MiningFlow',
      );
    } catch (error) {
      console.error('Error handling claim reward:', error);
      showAlert('Reward flow failed. Please try again.', 'ERROR');
    }
  };

  return (
    <View style={styles.container}>
      {rewardLabels.map((label, index) => {
        const isClaimed = claimedRewards[index];
        const isNext = index === nextRewardIndex;

        return (
          <Pressable
            key={label}
            disabled={isClaimed || isLoading || !isNext}
            onPress={handleClaimReward}
            style={[
              styles.card,
              isClaimed && styles.claimedCard,
              isNext && !isClaimed && styles.activeCard,
            ]}>
            <View
              style={[styles.iconShell, isClaimed && styles.claimedIconShell]}>
              <Ionicons
                name={isClaimed ? 'checkmark-circle' : 'gift-outline'}
                size={26}
                color={
                  isClaimed ? theme.colors.success : theme.colors.accentWarm
                }
              />
            </View>
            <Text style={styles.label}>{label}</Text>
            <Text style={styles.stateText}>
              {isClaimed
                ? 'Claimed'
                : isLoading && isNext
                ? 'Processing'
                : isNext
                ? 'Ready now'
                : 'Locked'}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 10,
  },
  card: {
    flex: 1,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(18, 39, 66, 0.72)',
    paddingVertical: 16,
    paddingHorizontal: 10,
    alignItems: 'center',
  },
  activeCard: {
    borderColor: 'rgba(244, 183, 116, 0.4)',
    backgroundColor: 'rgba(244, 183, 116, 0.12)',
  },
  claimedCard: {
    borderColor: 'rgba(93, 211, 158, 0.3)',
  },
  iconShell: {
    width: 42,
    height: 42,
    borderRadius: 21,
    backgroundColor: 'rgba(244, 183, 116, 0.12)',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 10,
  },
  claimedIconShell: {
    backgroundColor: 'rgba(93, 211, 158, 0.12)',
  },
  label: {
    color: theme.colors.text,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
  },
  stateText: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
});

export default RewardsList;
