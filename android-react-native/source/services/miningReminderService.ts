import {PermissionsAndroid, Platform} from 'react-native';
import PushNotification from 'react-native-push-notification';
import {navigate, navigationRef} from '../utils/helper';

const MINING_CHANNEL_ID = 'blockrium-mining-reminders';
const MINING_NOTIFICATION_ID = '901001';
const REWARD_CHANNEL_ID = 'blockrium-rewards';
let rewardNotificationId = 902000;

let isConfigured = false;
let notificationsAvailable = true;

const isNotificationNativeError = (error: unknown) => {
  const message = error instanceof Error ? error.message : String(error ?? '');
  return /getInitialNotification|configure|createChannel|localNotification|cancelLocalNotifications/i.test(
    message,
  );
};

const getPushNotification = () => {
  if (!notificationsAvailable || !PushNotification) {
    return null;
  }

  return PushNotification;
};

const requestAndroidNotificationPermission = async () => {
  if (Platform.OS !== 'android' || Number(Platform.Version) < 33) {
    return;
  }

  try {
    await PermissionsAndroid.request(
      PermissionsAndroid.PERMISSIONS.POST_NOTIFICATIONS,
    );
  } catch (error) {
    console.log('Unable to request mining notification permission', error);
  }
};

const openMiningScreen = (route = 'MiningFlow', attempt = 0) => {
  if (navigationRef.current?.isReady()) {
    navigate(route);
    return;
  }

  if (attempt >= 8) {
    return;
  }

  setTimeout(() => openMiningScreen(route, attempt + 1), 350);
};

export const initializeMiningReminderNotifications = () => {
  if (isConfigured) {
    return;
  }

  const notifier = getPushNotification();
  if (!notifier?.configure) {
    notificationsAvailable = false;
    isConfigured = true;
    return;
  }

  try {
    notifier.configure({
      onNotification: notification => {
        const data = notification?.data || notification?.userInfo;
        if (data?.type === 'mining_completed') {
          openMiningScreen('MiningFlow');
        } else if (typeof data?.route === 'string' && data.route.trim()) {
          openMiningScreen(data.route.trim());
        }
      },
      requestPermissions: Platform.OS === 'ios',
      popInitialNotification: false,
    });

    if (notifier.createChannel) {
      notifier.createChannel(
        {
          channelId: MINING_CHANNEL_ID,
          channelName: 'Mining reminders',
          channelDescription:
            'Alerts when your Blockrium mining cycle is complete.',
          importance: 4,
          vibrate: true,
          playSound: true,
          soundName: 'default',
        },
        () => undefined,
      );
      notifier.createChannel(
        {
          channelId: REWARD_CHANNEL_ID,
          channelName: 'Reward notifications',
          channelDescription: 'Alerts when Blockrium rewards are credited.',
          importance: 4,
          vibrate: true,
          playSound: true,
          soundName: 'default',
        },
        () => undefined,
      );
    }

    requestAndroidNotificationPermission();
  } catch (error) {
    notificationsAvailable = false;
    if (!isNotificationNativeError(error)) {
      console.log('Mining reminder notification setup warning', error);
    }
  } finally {
    isConfigured = true;
  }
};

export const cancelMiningCompletionReminder = () => {
  initializeMiningReminderNotifications();
  const notifier = getPushNotification();
  if (!notifier?.cancelLocalNotifications) {
    return;
  }

  try {
    notifier.cancelLocalNotifications({id: MINING_NOTIFICATION_ID});
  } catch (error) {
    if (!isNotificationNativeError(error)) {
      console.log('Mining reminder cancel warning', error);
    }
  }
};

export const scheduleMiningCompletionReminder = (
  deadlineMs: number,
  rewardLabel = 'BRC',
) => {
  initializeMiningReminderNotifications();

  const delayMs = deadlineMs - Date.now();
  if (!Number.isFinite(deadlineMs) || delayMs <= 5000) {
    cancelMiningCompletionReminder();
    return;
  }

  cancelMiningCompletionReminder();

  const notifier = getPushNotification();
  if (!notifier?.localNotificationSchedule) {
    return;
  }

  try {
    notifier.localNotificationSchedule({
      id: MINING_NOTIFICATION_ID,
      channelId: MINING_CHANNEL_ID,
      date: new Date(deadlineMs),
      allowWhileIdle: true,
      title: 'Mining stopped',
      message: `Your mining cycle is complete. Open Blockrium to claim rewards and start the next ${rewardLabel} cycle.`,
      userInfo: {
        type: 'mining_completed',
        route: 'MiningFlow',
      },
    });
  } catch (error) {
    notificationsAvailable = false;
    if (!isNotificationNativeError(error)) {
      console.log('Mining reminder schedule warning', error);
    }
  }
};

export const showRewardNotification = (
  title: string,
  message: string,
  route = 'Tasks',
) => {
  initializeMiningReminderNotifications();

  const notifier = getPushNotification();
  if (!notifier?.localNotificationSchedule) {
    return;
  }

  try {
    const id = String(rewardNotificationId++);
    if (rewardNotificationId > 902999) {
      rewardNotificationId = 902000;
    }

    notifier.localNotificationSchedule({
      id,
      channelId: REWARD_CHANNEL_ID,
      date: new Date(Date.now() + 250),
      allowWhileIdle: true,
      title,
      message,
      userInfo: {
        type: 'reward_received',
        route,
      },
    });
  } catch (error) {
    if (!isNotificationNativeError(error)) {
      console.log('Reward notification warning', error);
    }
  }
};
