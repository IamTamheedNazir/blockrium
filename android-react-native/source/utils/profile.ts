import {getStorageToken, setStorageToken} from './mmkvStorage';

export type NormalizedUserProfile = {
  name?: string;
  email?: string;
  uid?: string;
  id?: string;
  phone_number?: string;
  referralCode?: string;
  isPro?: boolean;
  subscriptionStatus?: string;
  subscriptionType?: string;
  subscriptionExpiry?: string;
  revenueCatCustomerId?: string;
  raw?: any;
};

export type ProfileVerificationBadges = {
  kycVerified: boolean;
  blockriumVerified: boolean;
};

export const getProfileVerificationBadges = (
  status: any,
): ProfileVerificationBadges => {
  const kycVerified = status?.kyc_status === 'approved';

  return {
    kycVerified,
    blockriumVerified:
      kycVerified && status?.payment_status === 'paid' && status?.is_verified === true,
  };
};

const PROFILE_CACHE_KEY = '@blockrium:user-profile';

const toCleanString = (value: unknown) => {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return String(value);
  }

  if (typeof value !== 'string') {
    return undefined;
  }

  const trimmedValue = value.trim();
  return trimmedValue ? trimmedValue : undefined;
};

const pickString = (...values: unknown[]) => {
  for (const value of values) {
    const normalizedValue = toCleanString(value);
    if (normalizedValue) {
      return normalizedValue;
    }
  }

  return undefined;
};

const pickBoolean = (...values: unknown[]) => {
  for (const value of values) {
    if (typeof value === 'boolean') {
      return value;
    }

    if (typeof value === 'number' && Number.isFinite(value)) {
      return value === 1;
    }

    if (typeof value === 'string') {
      const normalizedValue = value.trim().toLowerCase();

      if (['1', 'true', 'yes', 'active', 'pro'].includes(normalizedValue)) {
        return true;
      }

      if (
        ['0', 'false', 'no', 'inactive', 'expired', 'free'].includes(
          normalizedValue,
        )
      ) {
        return false;
      }
    }
  }

  return undefined;
};

const joinName = (...values: unknown[]) =>
  values.map(toCleanString).filter(Boolean).join(' ').trim() || undefined;

const collectProfileCandidates = (payload: any) => {
  const responseData = payload?.data ?? payload;
  const nestedData = responseData?.data ?? responseData;

  return [
    responseData?.user,
    responseData?.authUser,
    responseData?.profile,
    responseData?.userData,
    responseData?.miner,
    responseData?.data?.user,
    responseData?.data?.authUser,
    responseData?.data?.profile,
    responseData?.data?.userData,
    nestedData?.user,
    nestedData?.authUser,
    nestedData?.profile,
    nestedData?.userData,
    nestedData,
    responseData,
    payload,
  ].filter(
    candidate =>
      candidate && !Array.isArray(candidate) && typeof candidate === 'object',
  );
};

export const normalizeUserProfile = (
  payload: any,
  fallback?: Partial<NormalizedUserProfile>,
): NormalizedUserProfile => {
  const candidates = collectProfileCandidates(payload);
  const selectedCandidate =
    candidates.find(candidate =>
      Boolean(
        pickString(
          candidate?.name,
          candidate?.full_name,
          candidate?.username,
          candidate?.email,
          candidate?.uid,
          candidate?.user_id,
          candidate?.id,
        ),
      ),
    ) ?? {};

  const firstName =
    selectedCandidate?.first_name ?? selectedCandidate?.firstname;
  const lastName = selectedCandidate?.last_name ?? selectedCandidate?.lastname;
  const normalizedProfile: NormalizedUserProfile = {
    name:
      pickString(
        selectedCandidate?.name,
        selectedCandidate?.full_name,
        selectedCandidate?.display_name,
        selectedCandidate?.username,
        joinName(firstName, lastName),
        fallback?.name,
      ) ?? undefined,
    email: pickString(selectedCandidate?.email, fallback?.email),
    uid: pickString(
      selectedCandidate?.uid,
      selectedCandidate?.uuid,
      selectedCandidate?.block_id,
      selectedCandidate?.blockrium_id,
      selectedCandidate?.user_uid,
      selectedCandidate?.user_id,
      selectedCandidate?.id,
      fallback?.uid,
    ),
    id: pickString(
      selectedCandidate?.id,
      selectedCandidate?.user_id,
      fallback?.id,
    ),
    phone_number: pickString(
      selectedCandidate?.phone_number,
      selectedCandidate?.phone,
      selectedCandidate?.mobile,
      fallback?.phone_number,
    ),
    referralCode: pickString(
      selectedCandidate?.refferal_code,
      selectedCandidate?.referral_code,
      fallback?.referralCode,
    ),
    isPro: pickBoolean(
      selectedCandidate?.is_pro,
      selectedCandidate?.isPro,
      selectedCandidate?.pro,
      fallback?.isPro,
    ),
    subscriptionStatus: pickString(
      selectedCandidate?.subscription_status,
      selectedCandidate?.subscriptionStatus,
      fallback?.subscriptionStatus,
    ),
    subscriptionType: pickString(
      selectedCandidate?.subscription_type,
      selectedCandidate?.subscriptionType,
      selectedCandidate?.revenuecat_entitlement_id,
      fallback?.subscriptionType,
    ),
    subscriptionExpiry: pickString(
      selectedCandidate?.subscription_expiry,
      selectedCandidate?.subscriptionExpiry,
      fallback?.subscriptionExpiry,
    ),
    revenueCatCustomerId: pickString(
      selectedCandidate?.revenuecat_customer_id,
      selectedCandidate?.revenueCatCustomerId,
      fallback?.revenueCatCustomerId,
    ),
    raw: selectedCandidate,
  };

  return normalizedProfile;
};

export const hasProfileIdentity = (profile?: Partial<NormalizedUserProfile>) =>
  Boolean(profile?.name || profile?.email || profile?.uid || profile?.id);

export const shouldWaitForHomeProfile = (
  isProfileLoading: boolean,
  cachedProfile?: Partial<NormalizedUserProfile>,
  waitExpired = false,
) => isProfileLoading && !waitExpired && !hasProfileIdentity(cachedProfile);

export const cacheUserProfile = async (
  profile?: Partial<NormalizedUserProfile>,
) => {
  if (!hasProfileIdentity(profile)) {
    return;
  }

  await setStorageToken(
    PROFILE_CACHE_KEY,
    JSON.stringify({
      name: profile?.name,
      email: profile?.email,
      uid: profile?.uid,
      id: profile?.id,
      phone_number: profile?.phone_number,
      referralCode: profile?.referralCode,
      isPro: profile?.isPro,
      subscriptionStatus: profile?.subscriptionStatus,
      subscriptionType: profile?.subscriptionType,
      subscriptionExpiry: profile?.subscriptionExpiry,
      revenueCatCustomerId: profile?.revenueCatCustomerId,
    }),
  );
};

export const getCachedUserProfile = async () => {
  const cachedProfile = await getStorageToken(PROFILE_CACHE_KEY);
  if (!cachedProfile) {
    return undefined;
  }

  try {
    return JSON.parse(cachedProfile) as NormalizedUserProfile;
  } catch (error) {
    return undefined;
  }
};
