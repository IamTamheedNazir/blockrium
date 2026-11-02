import AsyncStorage from '@react-native-async-storage/async-storage';
import {Alert} from 'react-native';
import {navigate, navigationRef} from './helper';

export const BRC_REWARD_TYPE = 'brc_reward';
export const CLAIM_REWARDS_ACTION = 'claim_rewards';

const HANDLED_BRC_REWARD_IDS_KEY = 'blockrium.handledBrcRewardNotifications.v1';
const MAX_STORED_NOTIFICATION_IDS = 50;
const handledNotificationIds = new Set<string>();

export type BrcRewardNotification = {
  key: string;
  amount: string;
  action?: string;
  title?: string;
  body?: string;
  raw: any;
};

const normalizeToken = (value: unknown) =>
  String(value ?? '')
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, '_');

const asTrimmedString = (value: unknown) => {
  const text = String(value ?? '').trim();
  return text.length > 0 ? text : undefined;
};

const safeJson = (value: unknown) => {
  try {
    return JSON.stringify(value ?? {});
  } catch {
    return String(value ?? '');
  }
};

const compactHash = (value: string) => {
  let hash = 0;

  for (let index = 0; index < value.length; index += 1) {
    hash = (hash << 5) - hash + value.charCodeAt(index);
    hash |= 0;
  }

  return Math.abs(hash).toString(36);
};

export const isBrcRewardType = (value: unknown) =>
  normalizeToken(value) === BRC_REWARD_TYPE;

export const isClaimRewardsAction = (value: unknown) =>
  normalizeToken(value) === CLAIM_REWARDS_ACTION;

export const getBrcRewardNotificationKey = (payload: any) => {
  const data = payload?.data ?? payload ?? {};
  const candidates = [
    payload?.messageId,
    payload?.message_id,
    payload?.notificationId,
    payload?.notification_id,
    payload?.id,
    data?.messageId,
    data?.message_id,
    data?.notificationId,
    data?.notification_id,
    data?.id,
  ];

  const stableId = candidates.map(asTrimmedString).find(Boolean);
  if (stableId) {
    return `brc-reward:${stableId}`;
  }

  return `brc-reward:${compactHash(
    safeJson({
      type: data?.type ?? data?.notification_type ?? payload?.notification_type,
      action: data?.action ?? payload?.action,
      amount: data?.amount ?? data?.coin ?? payload?.amount ?? payload?.coin,
      title: payload?.notification?.title ?? payload?.title,
      body: payload?.notification?.body ?? payload?.message,
      sentTime: payload?.sentTime ?? payload?.date ?? payload?.created_at,
    }),
  )}`;
};

export const normalizeBrcRewardNotification = (
  payload: any,
): BrcRewardNotification | null => {
  const data = payload?.data ?? payload ?? {};
  const notificationType =
    data?.type ??
    data?.notification_type ??
    data?.notificationType ??
    payload?.notification_type ??
    payload?.notificationType ??
    payload?.type;
  const action = data?.action ?? payload?.action;

  if (!isBrcRewardType(notificationType)) {
    return null;
  }

  const amount =
    asTrimmedString(data?.amount) ??
    asTrimmedString(data?.coin) ??
    asTrimmedString(payload?.amount) ??
    asTrimmedString(payload?.coin) ??
    '0';

  return {
    key: getBrcRewardNotificationKey(payload),
    amount,
    action: asTrimmedString(action),
    title: asTrimmedString(payload?.notification?.title ?? payload?.title),
    body: asTrimmedString(payload?.notification?.body ?? payload?.message),
    raw: payload,
  };
};

export const getBrcRewardPopupMessage = (amount: string) =>
  `Congratulations! ${amount} BRC has been added to your wallet.`;

const loadHandledNotificationIds = async () => {
  try {
    const storedValue = await AsyncStorage.getItem(HANDLED_BRC_REWARD_IDS_KEY);
    const storedIds = storedValue ? JSON.parse(storedValue) : [];

    if (Array.isArray(storedIds)) {
      storedIds
        .filter((item): item is string => typeof item === 'string')
        .forEach(item => handledNotificationIds.add(item));
    }
  } catch (error) {
    console.log('BRC reward notification dedupe lookup warning', error);
  }
};

export const hasHandledBrcRewardNotification = async (key: string) => {
  if (handledNotificationIds.has(key)) {
    return true;
  }

  await loadHandledNotificationIds();
  return handledNotificationIds.has(key);
};

export const rememberBrcRewardNotification = async (key: string) => {
  handledNotificationIds.add(key);

  try {
    const nextIds = Array.from(handledNotificationIds).slice(
      -MAX_STORED_NOTIFICATION_IDS,
    );
    await AsyncStorage.setItem(
      HANDLED_BRC_REWARD_IDS_KEY,
      JSON.stringify(nextIds),
    );
  } catch (error) {
    console.log('BRC reward notification dedupe save warning', error);
  }
};

export const shouldHandleBrcRewardNotification = async (
  notification: BrcRewardNotification,
) => {
  const alreadyHandled = await hasHandledBrcRewardNotification(
    notification.key,
  );

  if (alreadyHandled) {
    return false;
  }

  await rememberBrcRewardNotification(notification.key);
  return true;
};

const getWalletNavigationParams = (
  notification?: BrcRewardNotification | null,
) => ({
  openedFromRewardNotification: true,
  rewardNotificationAction: notification?.action ?? 'CLAIM_REWARDS',
  rewardNotificationId: notification?.key,
  refreshWalletBalanceAt: Date.now(),
});

export const openBrcRewardWallet = (
  notification?: BrcRewardNotification | null,
  attempt = 0,
) => {
  const params = getWalletNavigationParams(notification);

  if (navigationRef.current?.isReady()) {
    navigate('WalletFlow', params);
    return;
  }

  if (attempt >= 10) {
    return;
  }

  setTimeout(() => openBrcRewardWallet(notification, attempt + 1), 300);
};

export const getBrcRewardWalletNavigationParams = (
  notification?: BrcRewardNotification | null,
) => {
  return getWalletNavigationParams(notification);
};

export const showBrcRewardDialog = (notification: BrcRewardNotification) => {
  Alert.alert(
    'BRC Reward Received',
    getBrcRewardPopupMessage(notification.amount),
    [
      {
        text: 'Later',
        style: 'cancel',
      },
      {
        text: 'Claim Reward',
        onPress: () => openBrcRewardWallet(notification),
      },
    ],
    {cancelable: true},
  );
};
