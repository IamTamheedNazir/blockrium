import {
  normalizeBrcRewardNotification,
  shouldHandleBrcRewardNotification,
  showBrcRewardDialog,
  openBrcRewardWallet,
} from '../utils/brcRewardNotifications';
import {openBlockriumNotificationFromPayload} from './blockriumNotificationService';

type MessagingInstance = {
  getToken?: () => Promise<string | null>;
  getInitialNotification?: () => Promise<unknown | null>;
  onNotificationOpenedApp?: (
    listener: (message: unknown) => void,
  ) => () => void;
  onMessage?: (listener: (message: unknown) => void) => () => void;
};

const FIREBASE_MESSAGING_ENABLED = true;
let messagingModule: any;

const isNotificationNullError = (error: unknown) =>
  error instanceof Error &&
  /getInitialNotification|onNotificationOpenedApp|onMessage|getToken/i.test(
    error.message,
  ) &&
  /null|undefined|not available/i.test(error.message);

const safeMessagingMethod = <T extends keyof MessagingInstance>(
  messaging: MessagingInstance | null,
  methodName: T,
): MessagingInstance[T] | undefined => {
  if (!messaging) {
    return undefined;
  }

  try {
    const method = messaging[methodName];
    return typeof method === 'function' ? method : undefined;
  } catch (error) {
    if (!isNotificationNullError(error)) {
      console.log(
        `Firebase messaging ${String(methodName)} unavailable`,
        error,
      );
    }
    return undefined;
  }
};

const getMessagingInstance = (): MessagingInstance | null => {
  try {
    if (!messagingModule) {
      messagingModule = require('@react-native-firebase/messaging');
    }

    const messagingFactory = messagingModule?.default ?? messagingModule;
    return typeof messagingFactory === 'function' ? messagingFactory() : null;
  } catch (error) {
    if (!isNotificationNullError(error)) {
      console.log('Firebase messaging module unavailable', error);
    }
    return null;
  }
};

const handleBrcRewardMessageOpen = async (message: unknown) => {
  const notification = normalizeBrcRewardNotification(message);

  if (notification) {
    const shouldHandle = await shouldHandleBrcRewardNotification(notification);
    if (shouldHandle) {
      openBrcRewardWallet(notification);
    }
    return;
  }

  const payload = (message as {data?: unknown} | null)?.data ?? message;
  if (payload && typeof payload === 'object') {
    openBlockriumNotificationFromPayload(
      payload as Record<string, unknown>,
    );
  }
};

const handleBrcRewardForegroundMessage = async (message: unknown) => {
  const notification = normalizeBrcRewardNotification(message);

  if (!notification) {
    return;
  }

  const shouldHandle = await shouldHandleBrcRewardNotification(notification);
  if (!shouldHandle) {
    return;
  }

  showBrcRewardDialog(notification);
};

export const getFirebaseMessagingTokenSafely = async () => {
  if (!FIREBASE_MESSAGING_ENABLED) {
    return '';
  }

  const messaging = getMessagingInstance();
  const getToken = safeMessagingMethod(messaging, 'getToken');

  if (!getToken) {
    return '';
  }

  try {
    return (await getToken.call(messaging)) ?? '';
  } catch (error) {
    console.log('Firebase messaging token warning', error);
    return '';
  }
};

export const initializeFirebaseMessagingSafely = () => {
  if (!FIREBASE_MESSAGING_ENABLED) {
    return () => undefined;
  }

  const messaging = getMessagingInstance();

  if (!messaging) {
    return () => undefined;
  }

  const unsubscribers: Array<() => void> = [];

  try {
    const getInitialNotification = safeMessagingMethod(
      messaging,
      'getInitialNotification',
    );
    if (getInitialNotification) {
      getInitialNotification
        .call(messaging)
        ?.then(handleBrcRewardMessageOpen)
        .catch(error => {
          if (!isNotificationNullError(error)) {
            console.log('Firebase initial notification warning', error);
          }
        });
    }
  } catch (error) {
    if (!isNotificationNullError(error)) {
      console.log('Firebase initial notification setup warning', error);
    }
  }

  try {
    const onNotificationOpenedApp = safeMessagingMethod(
      messaging,
      'onNotificationOpenedApp',
    );
    if (onNotificationOpenedApp) {
      const unsubscribe = onNotificationOpenedApp.call(
        messaging,
        handleBrcRewardMessageOpen,
      );
      if (typeof unsubscribe === 'function') {
        unsubscribers.push(unsubscribe);
      }
    }
  } catch (error) {
    if (!isNotificationNullError(error)) {
      console.log('Firebase notification open listener warning', error);
    }
  }

  try {
    const onMessage = safeMessagingMethod(messaging, 'onMessage');
    if (onMessage) {
      const unsubscribe = onMessage.call(
        messaging,
        handleBrcRewardForegroundMessage,
      );
      if (typeof unsubscribe === 'function') {
        unsubscribers.push(unsubscribe);
      }
    }
  } catch (error) {
    if (!isNotificationNullError(error)) {
      console.log('Firebase foreground notification listener warning', error);
    }
  }

  return () => {
    unsubscribers.forEach(unsubscribe => {
      try {
        unsubscribe();
      } catch (error) {
        console.log('Firebase messaging unsubscribe warning', error);
      }
    });
  };
};
