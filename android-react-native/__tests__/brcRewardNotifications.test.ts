import {Alert} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  getBrcRewardPopupMessage,
  isClaimRewardsAction,
  normalizeBrcRewardNotification,
  shouldHandleBrcRewardNotification,
  showBrcRewardDialog,
} from '../source/utils/brcRewardNotifications';
import {
  getNotificationAction,
  normalizeNotificationItem,
} from '../source/utils/notifications';
import {navigate} from '../source/utils/helper';

jest.mock('../source/utils/helper', () => ({
  navigate: jest.fn(),
  navigationRef: {current: {isReady: jest.fn(() => true)}},
}));

describe('BRC reward notifications', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    (AsyncStorage.getItem as jest.Mock).mockResolvedValue(null);
    (AsyncStorage.setItem as jest.Mock).mockResolvedValue(undefined);
  });

  it('normalizes the backend FCM reward payload', () => {
    const notification = normalizeBrcRewardNotification({
      messageId: 'reward-10',
      notification: {
        title: 'BRC reward received',
        body: 'Congratulations! 10 BRC has been added to your wallet.',
      },
      data: {
        type: 'BRC_REWARD',
        action: 'CLAIM_REWARDS',
        amount: '10',
      },
    });

    expect(notification).toEqual(
      expect.objectContaining({
        key: 'brc-reward:reward-10',
        amount: '10',
        action: 'CLAIM_REWARDS',
        title: 'BRC reward received',
      }),
    );
    expect(getBrcRewardPopupMessage('10')).toBe(
      'Congratulations! 10 BRC has been added to your wallet.',
    );
    expect(isClaimRewardsAction('CLAIM_REWARDS')).toBe(true);
  });

  it('dedupes an already handled reward notification', async () => {
    const notification = normalizeBrcRewardNotification({
      messageId: 'duplicate-reward',
      data: {
        type: 'BRC_REWARD',
        action: 'CLAIM_REWARDS',
        amount: '10',
      },
    });

    expect(notification).not.toBeNull();
    expect(await shouldHandleBrcRewardNotification(notification!)).toBe(true);
    expect(await shouldHandleBrcRewardNotification(notification!)).toBe(false);
  });

  it('shows the foreground popup with Claim Reward and Later actions', () => {
    const alertSpy = jest.spyOn(Alert, 'alert').mockImplementation(jest.fn());
    const notification = normalizeBrcRewardNotification({
      messageId: 'foreground-reward',
      data: {
        type: 'BRC_REWARD',
        action: 'CLAIM_REWARDS',
        amount: '10',
      },
    });

    showBrcRewardDialog(notification!);

    expect(alertSpy).toHaveBeenCalledWith(
      'BRC Reward Received',
      'Congratulations! 10 BRC has been added to your wallet.',
      expect.arrayContaining([
        expect.objectContaining({text: 'Later'}),
        expect.objectContaining({text: 'Claim Reward'}),
      ]),
      {cancelable: true},
    );

    expect(navigate).not.toHaveBeenCalled();

    const buttons = alertSpy.mock.calls[0][2] as Array<{
      text: string;
      onPress?: () => void;
    }>;
    expect(buttons.find(button => button.text === 'Later')?.onPress).toBe(
      undefined,
    );
    buttons.find(button => button.text === 'Claim Reward')?.onPress?.();

    expect(navigate).toHaveBeenCalledWith(
      'WalletFlow',
      expect.objectContaining({
        openedFromRewardNotification: true,
        rewardNotificationAction: 'CLAIM_REWARDS',
        rewardNotificationId: 'brc-reward:foreground-reward',
      }),
    );
  });

  it('routes CLAIM_REWARDS API notifications to the wallet', () => {
    const notification = normalizeNotificationItem({
      title: 'BRC reward received',
      message: 'Congratulations! 10 BRC has been added to your wallet.',
      coin: '10',
      action: 'CLAIM_REWARDS',
      notification_type: 'BRC_REWARD',
    });

    expect(notification).toEqual(
      expect.objectContaining({
        title: 'BRC reward received',
        message: 'Congratulations! 10 BRC has been added to your wallet.',
        coin: '10',
        action: 'CLAIM_REWARDS',
        notification_type: 'BRC_REWARD',
      }),
    );
    expect(
      getNotificationAction(notification.notification_type, notification),
    ).toEqual({
      label: 'Claim Reward',
      route: 'WalletFlow',
    });
  });

  it('routes KYC invitations to the verification screen', () => {
    expect(
      getNotificationAction('KYC_WAITLIST_INVITATION', {
        action: 'verification',
      }),
    ).toEqual({
      label: 'Open KYC',
      route: 'VerifiedMember',
    });

    expect(getNotificationAction('verification_invited')).toEqual({
      label: 'Open KYC',
      route: 'VerifiedMember',
    });
  });
});
