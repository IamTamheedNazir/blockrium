import {getApi, postApi} from '../storage/server';
import {
  normalizeNotificationItem,
  normalizeNotificationList,
} from '../utils/notifications';

const getNotificationIdentity = (item: any, index: number) => {
  const stableId =
    item?.id ??
    item?.notification_id ??
    item?.notificationId ??
    item?.uuid ??
    item?.key;

  if (stableId !== undefined && stableId !== null && String(stableId).trim()) {
    return `id:${String(stableId)}`;
  }

  return [
    index,
    item?.title,
    item?.message,
    item?.coin,
    item?.action,
    item?.notification_type,
    item?.date,
  ]
    .map(value => String(value ?? '').trim())
    .join('|');
};

const mergeNotifications = (...lists: any[][]) => {
  const seen = new Set<string>();
  const merged: any[] = [];

  lists.flat().forEach((rawItem, index) => {
    const item = normalizeNotificationItem(rawItem);
    const identity = getNotificationIdentity(item, index);

    if (seen.has(identity)) {
      return;
    }

    seen.add(identity);
    merged.push(item);
  });

  return merged;
};

export const fetchNotificationsWithFallback = async () => {
  const [authResponse, fallbackResponse] = await Promise.all([
    getApi('auth/all-notification'),
    getApi('all-notification'),
  ]);
  const authNotifications = authResponse?.success
    ? normalizeNotificationList(authResponse)
    : [];
  const fallbackNotifications = fallbackResponse?.success
    ? normalizeNotificationList(fallbackResponse)
    : [];
  const mergedNotifications = mergeNotifications(
    authNotifications,
    fallbackNotifications,
  );

  if (!authResponse?.success && !fallbackResponse?.success) {
    return fallbackResponse?.error ? fallbackResponse : authResponse;
  }

  return {
    success: true,
    data: {
      data: mergedNotifications,
    },
  };
};

const getNotificationReadBody = (notification?: any) => {
  if (!notification) {
    return {};
  }

  return {
    id: notification?.id,
    notification_id:
      notification?.notification_id ??
      notification?.notificationId ??
      notification?.id,
    notification_type: notification?.notification_type,
    action: notification?.action,
  };
};

export const markNotificationAsRead = async (notification?: any) => {
  const body = getNotificationReadBody(notification);
  const response = await postApi('notifications/mark-as-read', body);

  if (response?.success) {
    return response;
  }

  return postApi('auth/notifications/mark-as-read', body);
};
