import {Platform} from 'react-native';

export const REVENUECAT_TEST_API_KEY = 'test_nVqpfADuOHLUFzZjFQUycApZozm';

// Use public SDK API keys from RevenueCat > Project settings > API keys > SDK API keys.
// Never put a RevenueCat Secret API key (`sk_...`) in the mobile app.
export const REVENUECAT_ANDROID_PRODUCTION_API_KEY = '';
export const REVENUECAT_IOS_PRODUCTION_API_KEY = '';

export const REVENUECAT_API_KEY = __DEV__
  ? REVENUECAT_TEST_API_KEY
  : Platform.select({
      android: REVENUECAT_ANDROID_PRODUCTION_API_KEY,
      ios: REVENUECAT_IOS_PRODUCTION_API_KEY,
      default: '',
    });

export const BLOCKRIUM_PRO_ENTITLEMENT_ID = 'blockrium Pro';

export const REVENUECAT_PRODUCT_IDS = {
  yearly: 'yearly',
  sixMonth: 'six_month',
  threeMonth: 'three_month',
  monthly: 'monthly',
} as const;

export const REVENUECAT_PRODUCT_LABELS: Record<string, string> = {
  [REVENUECAT_PRODUCT_IDS.yearly]: 'Yearly',
  [REVENUECAT_PRODUCT_IDS.sixMonth]: 'Six Month',
  [REVENUECAT_PRODUCT_IDS.threeMonth]: 'Three Month',
  [REVENUECAT_PRODUCT_IDS.monthly]: 'Monthly',
};

export const REVENUECAT_PRODUCT_ORDER = [
  REVENUECAT_PRODUCT_IDS.monthly,
  REVENUECAT_PRODUCT_IDS.threeMonth,
  REVENUECAT_PRODUCT_IDS.sixMonth,
  REVENUECAT_PRODUCT_IDS.yearly,
];
