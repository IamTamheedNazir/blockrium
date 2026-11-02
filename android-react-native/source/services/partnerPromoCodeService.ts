import {getApi, postApi} from '../storage/server';
import {
  PartnerPromoRedemption,
  PartnerPromoRedeemResult,
} from '../types/partnerPromo';

const unwrap = (response: any) => response?.data?.data ?? response?.data;

const messageFromResponse = (response: any, fallback: string) =>
  response?.error ||
  response?.data?.message ||
  response?.data?.error ||
  response?.data?.data?.message ||
  fallback;

const requestKey = () =>
  `promo-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;

export const redeemPartnerPromoCode = async (code: string) => {
  const response = await postApi('auth/partner-promo-codes/redeem', {
    code: code.trim().toUpperCase(),
    request_key: requestKey(),
  });

  if (!response.success) {
    throw new Error(
      messageFromResponse(response, 'Unable to redeem this promo code.'),
    );
  }

  const payload = unwrap(response);
  if (!payload?.success) {
    throw new Error(
      messageFromResponse(payload, 'This promo code could not be redeemed.'),
    );
  }

  return payload as PartnerPromoRedeemResult;
};

export const getPartnerPromoRedemptions = async () => {
  const response = await getApi('auth/partner-promo-codes/redemptions');
  if (!response.success) {
    throw new Error(
      messageFromResponse(response, 'Unable to load promo redemption history.'),
    );
  }

  const payload = unwrap(response);
  const redemptions = Array.isArray(payload)
    ? payload
    : payload?.data ?? payload?.redemptions ?? [];

  return Array.isArray(redemptions)
    ? (redemptions as PartnerPromoRedemption[])
    : [];
};
