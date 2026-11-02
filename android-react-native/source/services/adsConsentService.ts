import {NativeModules, Platform} from 'react-native';

export type AdsConsentState = {
  flowCompleted: boolean;
  canRequestAds: boolean;
  privacyOptionsRequired: boolean;
  consentStatus: number;
};

type AdsConsentNativeModule = {
  requestConsent: () => Promise<AdsConsentState>;
  getConsentStatus: () => Promise<AdsConsentState>;
  showPrivacyOptions: () => Promise<AdsConsentState>;
};

const nativeConsent = NativeModules.AdsConsent as
  | AdsConsentNativeModule
  | undefined;

let cachedState: AdsConsentState | null = null;
let consentRequest: Promise<AdsConsentState> | null = null;
const listeners = new Set<(state: AdsConsentState) => void>();

const nonAndroidState: AdsConsentState = {
  flowCompleted: true,
  canRequestAds: true,
  privacyOptionsRequired: false,
  consentStatus: 0,
};

const unavailableState: AdsConsentState = {
  flowCompleted: true,
  canRequestAds: false,
  privacyOptionsRequired: false,
  consentStatus: 0,
};

const publishState = (state: AdsConsentState) => {
  cachedState = state;
  listeners.forEach(listener => listener(state));
  return state;
};

const debugLog = (...args: unknown[]) => {
  if (__DEV__) {
    console.log('[AdsConsent]', ...args);
  }
};

export const requestAdsConsent = async (): Promise<AdsConsentState> => {
  if (Platform.OS !== 'android') {
    return publishState(nonAndroidState);
  }

  if (!nativeConsent) {
    debugLog('Native UMP module unavailable; ad requests remain blocked.');
    return publishState(unavailableState);
  }

  if (cachedState?.flowCompleted) {
    return cachedState;
  }

  if (!consentRequest) {
    debugLog('Waiting for UMP consent flow.');
    consentRequest = nativeConsent
      .requestConsent()
      .then(state => {
        debugLog('UMP flow completed.', state);
        return publishState(state);
      })
      .catch(error => {
        debugLog('UMP flow failed; ad requests remain blocked.', error);
        return publishState(unavailableState);
      });
  }

  return consentRequest;
};

export const getAdsConsentStatus = async (): Promise<AdsConsentState> => {
  if (Platform.OS !== 'android') {
    return publishState(nonAndroidState);
  }

  if (!nativeConsent) {
    return publishState(unavailableState);
  }

  try {
    return publishState(await nativeConsent.getConsentStatus());
  } catch (error) {
    debugLog('Unable to read UMP status.', error);
    return cachedState ?? unavailableState;
  }
};

export const showAdsPrivacyOptions = async (): Promise<AdsConsentState> => {
  if (Platform.OS !== 'android' || !nativeConsent) {
    return cachedState ?? unavailableState;
  }

  const state = publishState(await nativeConsent.showPrivacyOptions());
  debugLog('Privacy options flow completed.', state);
  return state;
};

export const isAdsConsentGranted = () =>
  Platform.OS !== 'android' ||
  Boolean(cachedState?.flowCompleted && cachedState.canRequestAds);

export const subscribeAdsConsent = (
  listener: (state: AdsConsentState) => void,
) => {
  listeners.add(listener);
  if (cachedState) {
    listener(cachedState);
  }

  return () => {
    listeners.delete(listener);
  };
};
