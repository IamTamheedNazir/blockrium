import Purchases, {
  CustomerInfo,
  LOG_LEVEL,
  MakePurchaseResult,
  PurchasesOffering,
  PurchasesPackage,
  PurchasesOfferings,
} from 'react-native-purchases';
import RevenueCatUI, {PAYWALL_RESULT} from 'react-native-purchases-ui';
import {
  BLOCKRIUM_PRO_ENTITLEMENT_ID,
  REVENUECAT_API_KEY,
  REVENUECAT_PRODUCT_ORDER,
} from '../config/revenueCat';
import {getStorageId} from '../utils/mmkvStorage';

let configurePromise: Promise<void> | null = null;
let configuredAppUserId: string | undefined;

export type BlockriumSubscriptionSnapshot = {
  isPro: boolean;
  activePlan?: string;
  expiryDate?: string | null;
  productIdentifier?: string;
};

export const buildRevenueCatAppUserId = (userId?: string | number | null) => {
  if (userId === undefined || userId === null || String(userId).trim() === '') {
    return undefined;
  }

  return String(userId).trim();
};

export const getStoredRevenueCatAppUserId = async () => {
  const storedUserId = await getStorageId('userId');
  return buildRevenueCatAppUserId(storedUserId);
};

export const isBlockriumProCustomer = (customerInfo?: CustomerInfo | null) => {
  if (!customerInfo?.entitlements?.active) {
    return false;
  }

  return Boolean(customerInfo.entitlements.active[BLOCKRIUM_PRO_ENTITLEMENT_ID]);
};

export const getBlockriumProEntitlement = (customerInfo?: CustomerInfo | null) => {
  return customerInfo?.entitlements?.active?.[BLOCKRIUM_PRO_ENTITLEMENT_ID] ?? null;
};

export const getBlockriumSubscriptionSnapshot = (
  customerInfo?: CustomerInfo | null,
): BlockriumSubscriptionSnapshot => {
  const entitlement = getBlockriumProEntitlement(customerInfo);

  return {
    isPro: Boolean(entitlement),
    activePlan: entitlement?.identifier,
    expiryDate: entitlement?.expirationDate,
    productIdentifier: entitlement?.productIdentifier,
  };
};

export const normalizeRevenueCatError = (error: any) => {
  if (error?.userCancelled) {
    return 'Purchase cancelled.';
  }

  if (error?.message) {
    return String(error.message);
  }

  if (error?.underlyingErrorMessage) {
    return String(error.underlyingErrorMessage);
  }

  return 'Subscription service is unavailable right now.';
};

const validateRevenueCatApiKey = () => {
  const apiKey = REVENUECAT_API_KEY?.trim();

  if (!apiKey) {
    throw new Error(
      'Blockrium Boost checkout is not ready in this build.',
    );
  }

  if (apiKey.startsWith('sk_')) {
    throw new Error(
      'Invalid subscription key configured. Use the public RevenueCat SDK key instead.',
    );
  }

  if (!__DEV__ && apiKey.startsWith('test_')) {
    throw new Error(
      'Blockrium Boost checkout needs the production RevenueCat SDK key before release.',
    );
  }

  return apiKey;
};

export const configureRevenueCat = async (appUserId?: string) => {
  if (!configurePromise) {
    configurePromise = (async () => {
      const apiKey = validateRevenueCatApiKey();
      await Purchases.setLogLevel(__DEV__ ? LOG_LEVEL.DEBUG : LOG_LEVEL.INFO);
      Purchases.configure({
        apiKey,
        appUserID: appUserId,
      });
      configuredAppUserId = appUserId;
    })();
  }

  await configurePromise;

  if (appUserId && appUserId !== configuredAppUserId) {
    await Purchases.logIn(appUserId);
    configuredAppUserId = appUserId;
  }
};

export const identifyRevenueCatUser = async (userId?: string | number | null) => {
  const appUserId = buildRevenueCatAppUserId(userId);
  await configureRevenueCat(appUserId);

  if (!appUserId) {
    return Purchases.getCustomerInfo();
  }

  const result = await Purchases.logIn(appUserId);
  configuredAppUserId = appUserId;
  return result.customerInfo;
};

export const logoutRevenueCatUser = async () => {
  await configureRevenueCat();
  configuredAppUserId = undefined;
  return Purchases.logOut();
};

export const fetchRevenueCatCustomerInfo = async () => {
  await configureRevenueCat(await getStoredRevenueCatAppUserId());
  return Purchases.getCustomerInfo();
};

export const fetchRevenueCatOfferings = async (): Promise<PurchasesOfferings> => {
  await configureRevenueCat(await getStoredRevenueCatAppUserId());
  return Purchases.getOfferings();
};

export const getCurrentRevenueCatOffering = async () => {
  const offerings = await fetchRevenueCatOfferings();
  return offerings.current;
};

export const sortRevenueCatPackages = (offering?: PurchasesOffering | null) => {
  const packages = offering?.availablePackages ?? [];

  return [...packages].sort((first, second) => {
    const firstIndex = REVENUECAT_PRODUCT_ORDER.indexOf(first.product.identifier as any);
    const secondIndex = REVENUECAT_PRODUCT_ORDER.indexOf(second.product.identifier as any);
    const safeFirstIndex = firstIndex === -1 ? 99 : firstIndex;
    const safeSecondIndex = secondIndex === -1 ? 99 : secondIndex;

    if (safeFirstIndex !== safeSecondIndex) {
      return safeFirstIndex - safeSecondIndex;
    }

    return first.product.price - second.product.price;
  });
};

export const purchaseRevenueCatPackage = async (
  revenueCatPackage: PurchasesPackage,
): Promise<MakePurchaseResult> => {
  await configureRevenueCat(await getStoredRevenueCatAppUserId());
  return Purchases.purchasePackage(revenueCatPackage);
};

export const restoreRevenueCatPurchases = async () => {
  await configureRevenueCat(await getStoredRevenueCatAppUserId());
  return Purchases.restorePurchases();
};

export const presentRevenueCatPaywall = async (
  offering?: PurchasesOffering | null,
) => {
  await configureRevenueCat(await getStoredRevenueCatAppUserId());
  return RevenueCatUI.presentPaywall({
    offering: offering ?? undefined,
    displayCloseButton: true,
  });
};

export const presentRevenueCatPaywallIfNeeded = async (
  offering?: PurchasesOffering | null,
) => {
  await configureRevenueCat(await getStoredRevenueCatAppUserId());
  return RevenueCatUI.presentPaywallIfNeeded({
    requiredEntitlementIdentifier: BLOCKRIUM_PRO_ENTITLEMENT_ID,
    offering: offering ?? undefined,
    displayCloseButton: true,
  });
};

export const presentRevenueCatCustomerCenter = async () => {
  await configureRevenueCat(await getStoredRevenueCatAppUserId());
  if (typeof (RevenueCatUI as any).presentCustomerCenter !== 'function') {
    throw new Error('Subscription management is not available in this SDK build.');
  }

  return RevenueCatUI.presentCustomerCenter();
};

export const isSuccessfulPaywallResult = (result: PAYWALL_RESULT | string) => {
  return result === PAYWALL_RESULT.PURCHASED || result === PAYWALL_RESULT.RESTORED;
};
