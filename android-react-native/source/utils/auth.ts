import {normalizeUserProfile} from './profile';

const toTrimmedString = (value: unknown) => {
  if (typeof value !== 'string') {
    return '';
  }

  return value.trim();
};

const pickFirstString = (...values: unknown[]) => {
  for (const value of values) {
    const normalizedValue = toTrimmedString(value);
    if (normalizedValue) {
      return normalizedValue;
    }
  }

  return '';
};

const pickFirstNumber = (...values: unknown[]) => {
  for (const value of values) {
    if (typeof value === 'number' && Number.isFinite(value)) {
      return value;
    }

    if (typeof value === 'string' && value.trim()) {
      const parsedValue = Number(value);
      if (Number.isFinite(parsedValue)) {
        return parsedValue;
      }
    }
  }

  return undefined;
};

export const normalizeAuthResponse = (response: any) => {
  const payload = response?.data ?? {};
  const data = payload?.data ?? {};
  const tokenContainer =
    payload?.token?.original ??
    payload?.token ??
    data?.token?.original ??
    data?.token ??
    payload?.user?.token ??
    data?.user?.token ??
    data?.authUser?.token ??
    {};
  const userContainer =
    payload?.user ??
    payload?.authUser ??
    payload?.res_user ??
    payload?.resUser ??
    data?.user ??
    data?.authUser ??
    data?.res_user ??
    data?.resUser ??
    data?.profile ??
    {};

  const accessToken = pickFirstString(
    tokenContainer?.access_token,
    tokenContainer?.token,
    payload?.access_token,
    payload?.token,
    data?.access_token,
    data?.token,
  );

  const userId = pickFirstNumber(
    tokenContainer?.id,
    tokenContainer?.user_id,
    userContainer?.id,
    userContainer?.user_id,
    userContainer?.uid,
    payload?.id,
    payload?.user?.id,
    payload?.authUser?.id,
    data?.id,
    data?.user_id,
    data?.user?.id,
    data?.authUser?.id,
  );

  const successMessage = pickFirstString(
    payload?.message_title,
    payload?.message,
    data?.message_title,
    data?.message,
  );

  return {
    accessToken,
    userId,
    successMessage,
    userProfile: normalizeUserProfile(payload),
  };
};
