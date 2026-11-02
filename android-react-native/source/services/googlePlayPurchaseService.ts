import {NativeEventEmitter, NativeModules, Platform} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {postApi} from '../storage/server';
import {getStorageId} from '../utils/mmkvStorage';

const PENDING_INTENT_KEY = 'blockrium.google-play.pending-intent.v1';
const PURCHASE_EVENT = 'BlockriumGooglePlayPurchase';

export type GooglePlayIntent = {
  purchase_id: string;
  purpose: 'kyc' | 'boost';
  product_id: string;
  base_plan_id?: string | null;
  obfuscated_account_id: string;
  benefits?: Record<string, unknown>;
  plan_id?: number;
  user_id: number;
};

export type GooglePlayPurchaseUpdate = {
  status: 'purchased' | 'pending' | 'cancelled' | 'error' | 'unknown';
  productId?: string;
  purchaseToken?: string;
  orderId?: string;
  obfuscatedAccountId?: string;
  purchaseState?: number;
  message?: string;
};

export type GooglePlayProduct = {
  productId: string;
  title: string;
  description: string;
  formattedPrice?: string | null;
  currencyCode?: string | null;
  priceAmountMicros?: string | null;
  billingPeriod?: string | null;
  offerToken?: string | null;
  basePlanId?: string | null;
};

const billingModule = NativeModules.GooglePlayBilling;
const eventEmitter = billingModule ? new NativeEventEmitter(billingModule) : null;

const requireAndroidBilling = () => {
  if (Platform.OS !== 'android' || !billingModule) {
    throw new Error('Google Play purchases are available in the Android app only.');
  }
};

const responsePayload = (response: any) => response?.data?.data ?? response?.data ?? null;

export const subscribeToGooglePlayPurchases = (
  listener: (update: GooglePlayPurchaseUpdate) => void,
) => {
  if (!eventEmitter) {
    return {remove: () => undefined};
  }
  return eventEmitter.addListener(PURCHASE_EVENT, listener);
};

export const getGooglePlayProduct = async (
  productId: string,
  productType: 'inapp' | 'subs',
  basePlanId?: string | null,
): Promise<GooglePlayProduct> => {
  requireAndroidBilling();
  return billingModule.queryProduct(productId, productType, basePlanId ?? null);
};

export const createAndLaunchGooglePlayPurchase = async (
  purpose: 'kyc' | 'boost',
  planId?: number,
) => {
  requireAndroidBilling();

  const userId = await getStorageId('userId');
  if (!userId) {
    throw new Error('Sign in again before starting a Google Play purchase.');
  }

  const response = await postApi('auth/google-play/purchase-intents', {
    purpose,
    ...(purpose === 'boost' && planId ? {plan_id: planId} : {}),
  });
  const payload = responsePayload(response);
  const apiStatus = payload?.status ?? payload?.data?.status;
  const intentData = payload?.data ?? payload;
  if (!response.success || apiStatus === 0 || !intentData?.purchase_id) {
    throw new Error(response.error || payload?.message || 'Google Play checkout is not available yet.');
  }

  const intent: GooglePlayIntent = {
    ...intentData,
    purpose,
    user_id: userId,
    plan_id: planId,
  };
  if (!intent.product_id || !intent.obfuscated_account_id) {
    throw new Error('The server returned an incomplete purchase setup. Please contact support.');
  }

  // The purchase token stays in memory only; persist the intent for recovery after process death.
  await AsyncStorage.setItem(PENDING_INTENT_KEY, JSON.stringify(intent));
  const launch = await billingModule.launchPurchase(
    intent.product_id,
    purpose === 'kyc' ? 'inapp' : 'subs',
    intent.base_plan_id ?? null,
    null,
    intent.obfuscated_account_id,
  );

  if (launch?.cancelled) {
    await clearPendingGooglePlayIntent(intent.purchase_id);
    return {intent, launched: false, cancelled: true};
  }
  if (!launch?.launched) {
    throw new Error('Google Play checkout could not be opened. Please try again.');
  }

  return {intent, launched: true, cancelled: false};
};

export const getPendingGooglePlayIntent = async (): Promise<GooglePlayIntent | null> => {
  const raw = await AsyncStorage.getItem(PENDING_INTENT_KEY);
  if (!raw) {
    return null;
  }

  try {
    const intent = JSON.parse(raw) as GooglePlayIntent;
    const userId = await getStorageId('userId');
    if (!userId || intent.user_id !== userId) {
      await AsyncStorage.removeItem(PENDING_INTENT_KEY);
      return null;
    }
    return intent;
  } catch {
    await AsyncStorage.removeItem(PENDING_INTENT_KEY);
    return null;
  }
};

export const clearPendingGooglePlayIntent = async (purchaseId?: string) => {
  if (purchaseId) {
    const current = await getPendingGooglePlayIntent();
    if (current?.purchase_id !== purchaseId) {
      return;
    }
  }
  await AsyncStorage.removeItem(PENDING_INTENT_KEY);
};

export const verifyGooglePlayPurchase = async (
  update: GooglePlayPurchaseUpdate,
  intent?: GooglePlayIntent | null,
) => {
  const pendingIntent = intent ?? (await getPendingGooglePlayIntent());
  if (!pendingIntent || update.status !== 'purchased' || !update.purchaseToken) {
    return {verified: false, pending: update.status === 'pending', result: null};
  }
  if (
    update.productId !== pendingIntent.product_id ||
    update.obfuscatedAccountId !== pendingIntent.obfuscated_account_id
  ) {
    throw new Error('This Play purchase does not match the Blockrium account that started it. Contact support before retrying.');
  }

  const response = await postApi('auth/google-play/verify', {
    purchase_id: pendingIntent.purchase_id,
    purchase_token: update.purchaseToken,
  });
  const result = responsePayload(response);
  const status = result?.status ?? result?.data?.status;
  if (!response.success || status === 0) {
    throw new Error(response.error || result?.message || 'Google Play is still confirming this purchase.');
  }

  const verified = status === 'paid' || status === 'active' || result?.entitlement_status === 'active';
  if (verified) {
    await clearPendingGooglePlayIntent(pendingIntent.purchase_id);
  }
  return {verified, pending: !verified, result};
};

export const restorePendingGooglePlayPurchase = async (
  expectedPurpose?: GooglePlayIntent['purpose'],
) => {
  requireAndroidBilling();
  const intent = await getPendingGooglePlayIntent();
  if (!intent || (expectedPurpose && intent.purpose !== expectedPurpose)) {
    return {found: false, pending: false, verified: false, result: null};
  }

  const purchases: GooglePlayPurchaseUpdate[] = await billingModule.getOwnedPurchases();
  const purchase = purchases.find(item =>
    item.productId === intent.product_id &&
    item.obfuscatedAccountId === intent.obfuscated_account_id,
  );
  if (!purchase) {
    return {found: false, pending: false, verified: false, result: null};
  }
  if (purchase.purchaseState === 2 || purchase.status === 'pending') {
    return {found: true, pending: true, verified: false, result: null};
  }

  const verification = await verifyGooglePlayPurchase({...purchase, status: 'purchased'}, intent);
  return {found: true, ...verification};
};
