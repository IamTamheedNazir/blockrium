const normalizeActionToken = (value: unknown) =>
  String(value ?? '')
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, '_');

const asText = (value: unknown) => {
  const text = String(value ?? '').trim();
  return text.length > 0 ? text : undefined;
};

export const normalizeNotificationItem = (item: any) => {
  const data = item?.data && typeof item.data === 'object' ? item.data : {};

  return {
    ...item,
    title:
      asText(item?.title) ??
      asText(item?.notification_title) ??
      asText(data?.title) ??
      asText(data?.notification_title),
    message:
      asText(item?.message) ??
      asText(item?.body) ??
      asText(item?.description) ??
      asText(data?.message) ??
      asText(data?.body),
    coin:
      asText(item?.coin) ??
      asText(item?.amount) ??
      asText(data?.coin) ??
      asText(data?.amount),
    action: asText(item?.action) ?? asText(data?.action),
    notification_type:
      asText(item?.notification_type) ??
      asText(item?.notificationType) ??
      asText(item?.type) ??
      asText(data?.notification_type) ??
      asText(data?.notificationType) ??
      asText(data?.type),
    date:
      item?.date ??
      item?.created_at ??
      item?.createdAt ??
      data?.date ??
      data?.created_at ??
      data?.createdAt,
  };
};

export const normalizeNotificationCount = (payload: any) => {
  const rawCount =
    payload?.data?.countnotifications ??
    payload?.data?.countNotifications ??
    payload?.data?.data?.countnotifications ??
    payload?.data?.data?.countNotifications ??
    payload?.countnotifications ??
    payload?.countNotifications ??
    0;

  const count = Number(rawCount);
  return Number.isFinite(count) ? Math.max(0, count) : 0;
};

export const normalizeNotificationList = (payload: any) => {
  const candidates = [
    payload?.data?.data,
    payload?.data?.notifications,
    payload?.data,
    payload?.notifications,
    payload,
  ];

  const list = candidates.find(Array.isArray);
  return Array.isArray(list)
    ? list.filter(Boolean).map(normalizeNotificationItem)
    : [];
};

export const getNotificationIconName = (type: unknown) => {
  const normalized = String(type ?? '').toLowerCase();

  if (normalized.includes('mining')) {
    return 'flash-outline';
  }

  if (normalized.includes('squad')) {
    return 'people-outline';
  }

  if (normalized.includes('task') || normalized.includes('mission')) {
    return 'checkmark-done-outline';
  }

  if (normalized.includes('streak')) {
    return 'calendar-outline';
  }

  if (normalized.includes('boost')) {
    return 'trending-up-outline';
  }

  if (normalized.includes('reward') || normalized.includes('daily')) {
    return 'gift-outline';
  }

  if (normalized.includes('referral') || normalized.includes('refferal')) {
    return 'person-add-outline';
  }

  if (normalized.includes('deposit') || normalized.includes('wallet')) {
    return 'wallet-outline';
  }

  if (normalized.includes('withdraw')) {
    return 'cash-outline';
  }

  if (normalized.includes('launchpad') || normalized.includes('project')) {
    return 'rocket-outline';
  }

  if (normalized.includes('security') || normalized.includes('shield')) {
    return 'shield-checkmark-outline';
  }

  if (normalized.includes('marketing') || normalized.includes('campaign')) {
    return 'megaphone-outline';
  }

  return 'notifications-outline';
};

export const getNotificationBucket = (type: unknown, message?: unknown) => {
  const normalized = `${String(type ?? '')} ${String(
    message ?? '',
  )}`.toLowerCase();

  if (normalized.includes('brc_reward') || normalized.includes('brc reward')) {
    return 'Reward';
  }

  if (normalized.includes('mining')) {
    return 'Mining';
  }
  if (normalized.includes('squad')) {
    return 'Squad';
  }
  if (normalized.includes('task') || normalized.includes('mission')) {
    return 'Tasks';
  }
  if (normalized.includes('streak') || normalized.includes('daily')) {
    return 'Streak';
  }
  if (normalized.includes('boost')) {
    return 'Boost';
  }
  if (normalized.includes('referral') || normalized.includes('refferal')) {
    return 'Referral';
  }

  return 'System';
};

export const getNotificationAction = (
  type: unknown,
  message?: unknown,
  action?: unknown,
) => {
  const normalizedAction = normalizeActionToken(
    action ??
      (typeof message === 'object' && message
        ? (message as any).action
        : undefined),
  );
  const normalizedType = normalizeActionToken(type);

  if (
    normalizedAction === 'claim_rewards' ||
    normalizedType === 'brc_reward' ||
    normalizedType === 'reward'
  ) {
    return {label: 'Claim Reward', route: 'WalletFlow'};
  }

  if (
    normalizedAction === 'verification' ||
    normalizedAction === 'open_kyc' ||
    normalizedAction === 'start_kyc' ||
    normalizedType === 'kyc_waitlist_invitation' ||
    normalizedType === 'kyc_payment_reminder' ||
    normalizedType === 'kyc_invitation' ||
    normalizedType === 'kyc_invited' ||
    normalizedType === 'kyc_invite' ||
    normalizedType === 'verification_invitation' ||
    normalizedType === 'verification_invited' ||
    normalizedType === 'verification_invite'
  ) {
    return {label: 'Open KYC', route: 'VerifiedMember'};
  }

  const bucket = getNotificationBucket(type, message);

  if (bucket === 'Mining' || bucket === 'Streak') {
    return {label: 'Open Mining', route: 'MiningFlow'};
  }
  if (bucket === 'Tasks') {
    return {label: 'Open Tasks', route: 'Tasks'};
  }
  if (bucket === 'Squad') {
    return {label: 'Open Squads', route: 'Squads'};
  }
  if (bucket === 'Boost') {
    return {label: 'Open Boosts', route: 'BoostStore'};
  }
  if (bucket === 'Referral') {
    return {label: 'Open Network', route: 'Referral'};
  }

  return {label: 'Open Home', route: 'HomeFlow'};
};

export const formatNotificationType = (type: unknown) => {
  const label = String(type ?? '')
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

  if (!label) {
    return 'Blockrium alert';
  }

  return label.replace(/\b\w/g, char => char.toUpperCase());
};

export const formatNotificationCoin = (coin: unknown) => {
  const value = asText(coin);

  if (!value) {
    return undefined;
  }

  return /brc/i.test(value) ? value : `${value} BRC`;
};

export const formatNotificationAction = (action: unknown) => {
  const label = String(action ?? '')
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

  if (!label) {
    return undefined;
  }

  return label.replace(/\b\w/g, char => char.toUpperCase());
};

export const formatNotificationDate = (value: unknown) => {
  if (!value) {
    return 'Just now';
  }

  const date = new Date(String(value).replace(' ', 'T'));

  if (Number.isNaN(date.getTime())) {
    return String(value);
  }

  return date.toLocaleString(undefined, {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  });
};
