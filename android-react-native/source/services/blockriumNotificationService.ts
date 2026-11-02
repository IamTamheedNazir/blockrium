import {Linking} from 'react-native';
import type {
  NotificationClickEvent,
  NotificationWillDisplayEvent,
} from 'react-native-onesignal';
import {navigate, navigationRef} from '../utils/helper';
import {NormalizedUserProfile} from '../utils/profile';

export const ONESIGNAL_APP_ID = 'c956c25f-1b9a-4aaf-88c9-ea67f5db8a68';

export const BLOCKRIUM_NOTIFICATION_EVENTS = {
  mining_completed: {
    title: 'Mining completed',
    message: 'Your mining session ended',
    route: 'MiningFlow',
  },
  daily_rewards_available: {
    title: 'Daily rewards available',
    message: 'Daily rewards available',
    route: 'Tasks',
  },
  brc_reward: {
    title: 'BRC Reward Received',
    message: 'BRC reward received',
    route: 'WalletFlow',
  },
  referral_joined: {
    title: 'Referral joined',
    message: 'Referral joined',
    route: 'Referral',
  },
  wallet_deposit_successful: {
    title: 'Wallet deposit successful',
    message: 'Wallet deposit successful',
    route: 'WalletFlow',
  },
  withdrawal_approved: {
    title: 'Withdrawal approved',
    message: 'Withdrawal approved',
    route: 'WalletFlow',
  },
  launchpad_project_announced: {
    title: 'Launchpad project announced',
    message: 'Launchpad project announced',
    route: 'HomeFlow',
  },
  security_alert: {
    title: 'Security alert from Blockrium Shield',
    message: 'Security alert from Blockrium Shield',
    route: 'WalletFlow',
  },
  marketing_campaign: {
    title: 'Marketing campaign',
    message: 'Marketing campaigns from admin panel',
    route: 'HomeFlow',
  },
  kyc_waitlist_invitation: {
    title: 'KYC verification is available',
    message: 'Your KYC batch is open. Start identity verification.',
    route: 'VerifiedMember',
  },
  kyc_payment_reminder: {
    title: 'KYC approved · membership payment pending',
    message: 'Your KYC is complete. The one-time payment is for Blockrium Verified membership, not KYC. It unlocks BRC transfers and member benefits.',
    route: 'VerifiedMember',
  },
  kyc_invitation: {
    title: 'KYC verification is available',
    message: 'Your KYC invitation is ready. Start identity verification.',
    route: 'VerifiedMember',
  },
  kyc_invited: {
    title: 'KYC verification is available',
    message: 'Your KYC invitation is ready. Start identity verification.',
    route: 'VerifiedMember',
  },
  verification_invitation: {
    title: 'KYC verification is available',
    message: 'Your KYC invitation is ready. Start identity verification.',
    route: 'VerifiedMember',
  },
  verification_invited: {
    title: 'KYC verification is available',
    message: 'Your KYC invitation is ready. Start identity verification.',
    route: 'VerifiedMember',
  },
} as const;

export type BlockriumNotificationType =
  keyof typeof BLOCKRIUM_NOTIFICATION_EVENTS;

type BlockriumNotificationPayload = {
  type?: string;
  notificationType?: string;
  notification_type?: string;
  event?: string;
  screen?: string;
  action?: string;
  route?: string;
  url?: string;
  data?: {
    type?: string;
    notification_type?: string;
    action?: string;
    [key: string]: unknown;
  };
  [key: string]: unknown;
};

let isInitialized = false;
let isClickListenerRegistered = false;
let isForegroundListenerRegistered = false;
let oneSignalModule: typeof import('react-native-onesignal') | undefined;

const getOneSignalModule = () => {
  try {
    if (!oneSignalModule) {
      oneSignalModule =
        require('react-native-onesignal') as typeof import('react-native-onesignal');
    }

    return oneSignalModule;
  } catch (error) {
    console.log('OneSignal native module unavailable', error);
    return undefined;
  }
};

const runOneSignalSafely = <T>(
  action: (sdk: typeof import('react-native-onesignal')) => T,
  fallback: T,
  label: string,
) => {
  const oneSignalSdk = getOneSignalModule();
  if (!oneSignalSdk) {
    return fallback;
  }

  try {
    return action(oneSignalSdk);
  } catch (error) {
    console.log(`OneSignal ${label} warning`, error);
    return fallback;
  }
};

const normalizeNotificationType = (value?: string) => {
  if (!value) {
    return undefined;
  }

  return value
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, '_') as BlockriumNotificationType;
};

const getPayloadType = (payload?: BlockriumNotificationPayload) =>
  normalizeNotificationType(
    payload?.type ??
      payload?.notificationType ??
      payload?.notification_type ??
      payload?.event ??
      payload?.data?.type ??
      payload?.data?.notification_type ??
      (payload as any)?.app_message_type ??
      (payload as any)?.data?.app_message_type,
  );

const getPayloadAction = (payload?: BlockriumNotificationPayload) => {
  const action = normalizeNotificationType(
    payload?.action ?? payload?.data?.action,
  );
  return action ? String(action) : undefined;
};

const waitForNavigationAndOpen = (routeName: string, attempt = 0) => {
  if (navigationRef.current?.isReady()) {
    navigate(routeName);
    return;
  }

  if (attempt >= 8) {
    return;
  }

  setTimeout(() => waitForNavigationAndOpen(routeName, attempt + 1), 350);
};

const getRouteFromPayload = (payload?: BlockriumNotificationPayload) => {
  const explicitRoute = payload?.route ?? payload?.screen;
  if (typeof explicitRoute === 'string' && explicitRoute.trim()) {
    return explicitRoute.trim();
  }

  const payloadType = getPayloadType(payload);
  const payloadAction = getPayloadAction(payload);
  if (payloadAction === 'verification') {
    return 'VerifiedMember';
  }

  if (
    payloadType === 'kyc_waitlist_invitation' ||
    payloadType === 'kyc_invitation' ||
    payloadType === 'kyc_invited' ||
    payloadType === 'verification_invitation' ||
    payloadType === 'verification_invited'
  ) {
    return 'VerifiedMember';
  }

  if (payloadType && BLOCKRIUM_NOTIFICATION_EVENTS[payloadType]) {
    return BLOCKRIUM_NOTIFICATION_EVENTS[payloadType].route;
  }

  return 'Notifications';
};

export const openBlockriumNotificationFromPayload = (
  payload?: BlockriumNotificationPayload,
) => {
  const data = payload?.data ?? payload;
  const url = data?.url;

  if (typeof url === 'string' && /^https?:\/\//i.test(url)) {
    Linking.openURL(url).catch(error => {
      console.log('Unable to open Firebase notification URL', error);
    });
    return;
  }

  waitForNavigationAndOpen(getRouteFromPayload(data as BlockriumNotificationPayload));
};

const handleNotificationClick = (event: NotificationClickEvent) => {
  const payload = event.notification.additionalData as
    | BlockriumNotificationPayload
    | undefined;
  const url = event.result?.url ?? event.notification.launchURL ?? payload?.url;

  openBlockriumNotificationFromPayload({ ...payload, url });
};

const handleForegroundNotification = (event: NotificationWillDisplayEvent) => {
  const notification = event.getNotification();
  const payload = notification.additionalData as
    | BlockriumNotificationPayload
    | undefined;
  const payloadType = getPayloadType(payload);

  if (payloadType && BLOCKRIUM_NOTIFICATION_EVENTS[payloadType]) {
    runOneSignalSafely(
      ({OneSignal}) => {
        const userApi = OneSignal?.User as any;
        if (typeof userApi?.trackEvent === 'function') {
          userApi.trackEvent('blockrium_push_received', {
            notification_type: payloadType,
            title:
              notification.title ??
              BLOCKRIUM_NOTIFICATION_EVENTS[payloadType].title,
          });
          return;
        }

        OneSignal?.Session?.addOutcome?.('blockrium_push_received');
      },
      undefined,
      'foreground tracking',
    );
  }

  // The native SDK pauses foreground delivery while this callback runs.
  // Explicitly resume it so reward pushes are visible while the app is open.
  notification.display();
};

export const initializeBlockriumNotifications = () => {
  if (isInitialized) {
    return true;
  }

  const didInitialize = runOneSignalSafely(
    ({LogLevel, OneSignal}) => {
      if (
        !OneSignal?.initialize ||
        !OneSignal?.Notifications ||
        !OneSignal?.User
      ) {
        return false;
      }

      if (__DEV__) {
        OneSignal.Debug?.setLogLevel?.(LogLevel.Verbose);
      }

      OneSignal.initialize(ONESIGNAL_APP_ID);
      OneSignal.User.addTags({
        app: 'blockrium',
        notification_channels: Object.keys(BLOCKRIUM_NOTIFICATION_EVENTS).join(
          ',',
        ),
        push_provider: 'onesignal',
      });

      if (!isClickListenerRegistered) {
        OneSignal.Notifications.addEventListener?.(
          'click',
          handleNotificationClick,
        );
        isClickListenerRegistered = true;
      }

      if (!isForegroundListenerRegistered) {
        OneSignal.Notifications.addEventListener?.(
          'foregroundWillDisplay',
          handleForegroundNotification,
        );
        isForegroundListenerRegistered = true;
      }

      return true;
    },
    false,
    'initialization',
  );

  isInitialized = didInitialize;
  return didInitialize;
};

export const requestBlockriumNotificationPermission = async () => {
  if (!initializeBlockriumNotifications()) {
    return false;
  }

  const oneSignalSdk = getOneSignalModule();
  if (!oneSignalSdk) {
    return false;
  }

  try {
    const notifications = oneSignalSdk.OneSignal?.Notifications;
    if (!notifications) {
      return false;
    }

    const canAsk = await notifications.canRequestPermission?.();
    if (!canAsk) {
      return notifications.getPermissionAsync?.() ?? false;
    }

    return notifications.requestPermission?.(true) ?? false;
  } catch (error) {
    console.log('OneSignal permission request warning', error);
    return false;
  }
};

export const identifyBlockriumNotificationUser = async (
  profile?: Partial<NormalizedUserProfile>,
) => {
  if (!initializeBlockriumNotifications()) {
    return;
  }

  const externalId = profile?.uid ?? profile?.id ?? profile?.email;
  runOneSignalSafely(
    ({OneSignal}) => {
      if (externalId) {
        OneSignal?.login?.(String(externalId));
      }

      const tags: Record<string, string> = {
        user_uid: String(profile?.uid ?? profile?.id ?? ''),
        user_id: String(profile?.id ?? profile?.uid ?? ''),
        marketing_campaigns_enabled: 'true',
        mining_notifications_enabled: 'true',
        reward_notifications_enabled: 'true',
        referral_notifications_enabled: 'true',
        wallet_notifications_enabled: 'true',
        launchpad_notifications_enabled: 'true',
        shield_notifications_enabled: 'true',
      };

      if (profile?.email) {
        tags.user_email = profile.email;
        OneSignal?.User?.addEmail?.(profile.email);
      }

      if (profile?.name) {
        tags.user_name = profile.name;
      }

      OneSignal?.User?.addTags?.(tags);
    },
    undefined,
    'user identification',
  );
};

export const setBlockriumWalletNotificationPreference = (enabled: boolean) => {
  if (!initializeBlockriumNotifications()) {
    return;
  }

  runOneSignalSafely(
    ({OneSignal}) =>
      OneSignal?.User?.addTag?.(
        'wallet_notifications_enabled',
        enabled ? 'true' : 'false',
      ),
    undefined,
    'wallet tag update',
  );
};

export const getBlockriumPushSubscriptionId = async () => {
  if (!initializeBlockriumNotifications()) {
    return null;
  }

  const oneSignalSdk = getOneSignalModule();
  if (!oneSignalSdk) {
    return null;
  }

  try {
    return (
      oneSignalSdk.OneSignal?.User?.pushSubscription?.getIdAsync?.() ?? null
    );
  } catch (error) {
    console.log('OneSignal subscription lookup warning', error);
    return null;
  }
};

export const getBlockriumPushToken = async () => {
  if (!initializeBlockriumNotifications()) {
    return null;
  }

  const oneSignalSdk = getOneSignalModule();
  if (!oneSignalSdk) {
    return null;
  }

  try {
    const pushSubscription = oneSignalSdk.OneSignal?.User
      ?.pushSubscription as any;
    if (typeof pushSubscription?.getTokenAsync === 'function') {
      return pushSubscription.getTokenAsync();
    }
  } catch (error) {
    console.log('OneSignal token lookup warning', error);
  }

  return null;
};

export const logoutBlockriumNotificationUser = () => {
  if (!initializeBlockriumNotifications()) {
    return;
  }

  runOneSignalSafely(
    ({OneSignal}) => OneSignal?.logout?.(),
    undefined,
    'logout',
  );
};
