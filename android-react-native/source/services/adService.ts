/* eslint-disable no-bitwise */
import {APPODEAL_APP_KEY} from '../utils/constants';
import {
  isAdsConsentGranted,
  requestAdsConsent,
  subscribeAdsConsent,
} from './adsConsentService';

let adsInitialized = false;
let listenersAttached = false;
let adsInitializationPromise: Promise<void> | null = null;
let sdkInitialized = false;
let activeInterstitialPromise: Promise<AdDisplayOutcome> | null = null;
let activeRewardedPromise: Promise<AdDisplayOutcome> | null = null;
let nativeAdsDisabledUntil = 0;
let consecutiveAdFailures = 0;
let appodealUserId: string | undefined;

const NATIVE_ADS_ENABLED = Boolean(APPODEAL_APP_KEY?.trim());
const AD_BACKOFF_MS = 45000;

type ActionAdOptions = {
  timeoutMs?: number;
  allowContinueWithoutAd?: boolean;
};

type AdDisplayOutcome = {
  shown: boolean;
  rewarded: boolean;
};

type AdLifecycleHandler = (event: string, payload?: any) => void;

const interstitialHandlers = new Set<AdLifecycleHandler>();
const rewardedHandlers = new Set<AdLifecycleHandler>();
const readyHandlers = new Set<() => void>();

const debugLog = (...args: unknown[]) => {
  if (__DEV__) {
    console.log(...args);
  }
};

const canAttemptNativeAds = () =>
  NATIVE_ADS_ENABLED &&
  isAdsConsentGranted() &&
  Date.now() >= nativeAdsDisabledUntil;

const ensureAdsConsent = async () => {
  const consent = await requestAdsConsent();
  const allowed = consent.flowCompleted && consent.canRequestAds;

  if (!allowed) {
    debugLog('Ad request blocked because UMP consent is not ready.');
  }

  return allowed;
};

const backOffNativeAds = (reason: unknown, durationMs = AD_BACKOFF_MS) => {
  consecutiveAdFailures += 1;
  const backoffMs = Math.min(
    durationMs * Math.max(1, consecutiveAdFailures),
    180000,
  );
  nativeAdsDisabledUntil = Date.now() + backoffMs;
  debugLog('Appodeal paused briefly', reason);
};

const noteNativeAdSuccess = () => {
  consecutiveAdFailures = 0;
  nativeAdsDisabledUntil = 0;
};

type AppodealSdk = typeof import('react-native-appodeal');
type AppodealRuntime = {
  Appodeal: any;
  AppodealAdType: any;
  AppodealBannerEvents: any;
  AppodealInterstitialEvents: any;
  AppodealLogLevel: any;
  AppodealRewardedEvents: any;
  AppodealSdkEvents: any;
};

let appodealSdk: AppodealSdk | null | undefined;

const getAppodealRuntime = (): AppodealRuntime | null => {
  try {
    if (appodealSdk === undefined) {
      appodealSdk = require('react-native-appodeal') as AppodealSdk;
    }

    if (!appodealSdk) {
      return null;
    }

    return {
      Appodeal: (appodealSdk as any).default ?? appodealSdk,
      AppodealAdType: (appodealSdk as any).AppodealAdType,
      AppodealBannerEvents: (appodealSdk as any).AppodealBannerEvents,
      AppodealInterstitialEvents: (appodealSdk as any)
        .AppodealInterstitialEvents,
      AppodealLogLevel: (appodealSdk as any).AppodealLogLevel,
      AppodealRewardedEvents: (appodealSdk as any).AppodealRewardedEvents,
      AppodealSdkEvents: (appodealSdk as any).AppodealSdkEvents,
    };
  } catch (error) {
    console.log('Appodeal native module unavailable', error);
    appodealSdk = null;
    return null;
  }
};

const requireAppodealRuntime = () => {
  const runtime = getAppodealRuntime();
  if (!runtime?.Appodeal || !runtime?.AppodealAdType) {
    throw new Error('Ad service is unavailable on this device.');
  }

  return runtime;
};

const wait = (timeout: number) =>
  new Promise(resolve => {
    setTimeout(resolve, timeout);
  });

const emitInterstitialEvent = (event: string, payload?: any) => {
  interstitialHandlers.forEach(handler => handler(event, payload));
};

const emitRewardedEvent = (event: string, payload?: any) => {
  rewardedHandlers.forEach(handler => handler(event, payload));
};

const waitUntil = async (
  check: () => boolean,
  timeoutMs = 6000,
  intervalMs = 300,
) => {
  const startedAt = Date.now();

  while (Date.now() - startedAt < timeoutMs) {
    if (check()) {
      return true;
    }

    await wait(intervalMs);
  }

  return check();
};

const isAdReady = (adType: number) => {
  try {
    const runtime = getAppodealRuntime();
    if (!runtime?.Appodeal) {
      return false;
    }

    const {Appodeal} = runtime;
    return Appodeal.canShow(adType) && Appodeal.isLoaded(adType);
  } catch (error) {
    debugLog('Appodeal ready check failed', error);
    backOffNativeAds(error);
    return false;
  }
};

const waitForInterstitialResult = (timeoutMs = 25000) =>
  new Promise<AdDisplayOutcome>(resolve => {
    let isSettled = false;
    let hasShown = false;

    const finish = () => {
      if (isSettled) {
        return;
      }

      isSettled = true;
      clearTimeout(timeoutId);
      interstitialHandlers.delete(handler);
      resolve({shown: hasShown, rewarded: false});
    };

    const timeoutId = setTimeout(finish, timeoutMs);

    const handler: AdLifecycleHandler = event => {
      if (event === 'shown' || event === 'clicked') {
        hasShown = true;
        return;
      }

      if (
        event === 'closed' ||
        event === 'expired' ||
        event === 'failed_to_show' ||
        event === 'failed_to_load'
      ) {
        finish();
      }
    };

    interstitialHandlers.add(handler);
  });

const waitForRewardedResult = (timeoutMs = 30000) =>
  new Promise<AdDisplayOutcome>(resolve => {
    let isSettled = false;
    let hasShown = false;
    let hasReward = false;

    const finish = () => {
      if (isSettled) {
        return;
      }

      isSettled = true;
      clearTimeout(timeoutId);
      rewardedHandlers.delete(handler);
      resolve({shown: hasShown, rewarded: hasReward});
    };

    const timeoutId = setTimeout(finish, timeoutMs);

    const handler: AdLifecycleHandler = event => {
      if (event === 'shown' || event === 'clicked') {
        hasShown = true;
        return;
      }

      if (event === 'reward') {
        hasShown = true;
        hasReward = true;
        return;
      }

      if (
        event === 'closed' ||
        event === 'expired' ||
        event === 'failed_to_show' ||
        event === 'failed_to_load'
      ) {
        finish();
      }
    };

    rewardedHandlers.add(handler);
  });

export const initializeAds = async () => {
  if (!NATIVE_ADS_ENABLED) {
    adsInitialized = true;
    readyHandlers.forEach(handler => handler());
    return;
  }

  if (!(await ensureAdsConsent())) {
    return;
  }

  if (!canAttemptNativeAds()) {
    return;
  }

  if (adsInitialized) {
    return;
  }

  if (adsInitializationPromise) {
    await adsInitializationPromise;
    return;
  }

  adsInitializationPromise = (async () => {
    const {
      Appodeal,
      AppodealAdType,
      AppodealBannerEvents,
      AppodealInterstitialEvents,
      AppodealLogLevel,
      AppodealRewardedEvents,
      AppodealSdkEvents,
    } = requireAppodealRuntime();
    const allAdTypes =
      AppodealAdType.INTERSTITIAL |
      AppodealAdType.BANNER |
      AppodealAdType.REWARDED_VIDEO;
    // Release APKs must always request production inventory. Previously every
    // sideloaded release was put in test mode, which produces no revenue.
    const useTestAds = __DEV__;

    try {
      Appodeal.setTesting(useTestAds);
      Appodeal.setLogLevel(
        useTestAds ? AppodealLogLevel.VERBOSE : AppodealLogLevel.NONE,
      );
      Appodeal.setAutoCache(AppodealAdType.INTERSTITIAL, true);
      Appodeal.setAutoCache(AppodealAdType.BANNER, true);
      Appodeal.setAutoCache(AppodealAdType.REWARDED_VIDEO, true);
      Appodeal.setTriggerPrecacheCallbacks?.(AppodealAdType.INTERSTITIAL, true);
      Appodeal.setTriggerPrecacheCallbacks?.(
        AppodealAdType.REWARDED_VIDEO,
        true,
      );
      Appodeal.setChildDirectedTreatment(false);
      Appodeal.setBannerAnimation(true);
      Appodeal.setSmartBanners(false);
      Appodeal.setTabletBanners(false);
      if (appodealUserId) {
        Appodeal.setUserId(appodealUserId);
      }
    } catch (error) {
      debugLog('Appodeal pre-initialization options skipped', error);
    }

    if (!listenersAttached) {
      Appodeal.addEventListener(AppodealSdkEvents.INITIALIZED, () => {
        sdkInitialized = true;
        debugLog('Appodeal SDK initialized');
      });
      Appodeal.addEventListener(
        AppodealSdkEvents.AD_REVENUE,
        (revenue: any) => {
          debugLog('Appodeal paid impression', {
            network: revenue?.networkName,
            placement: revenue?.placement,
            adType: revenue?.adType,
            revenue: revenue?.revenue,
            currency: revenue?.currency,
          });
        },
      );
      Appodeal.addEventListener(AppodealInterstitialEvents.LOADED, () => {
        debugLog('Appodeal interstitial loaded');
        emitInterstitialEvent('loaded');
      });
      Appodeal.addEventListener(
        AppodealInterstitialEvents.FAILED_TO_LOAD,
        () => {
          debugLog('Appodeal interstitial failed to load');
          emitInterstitialEvent('failed_to_load');
        },
      );
      Appodeal.addEventListener(AppodealInterstitialEvents.SHOWN, () => {
        debugLog('Appodeal interstitial shown');
        emitInterstitialEvent('shown');
      });
      Appodeal.addEventListener(AppodealInterstitialEvents.CLICKED, () => {
        debugLog('Appodeal interstitial clicked');
        emitInterstitialEvent('clicked');
      });
      Appodeal.addEventListener(AppodealInterstitialEvents.CLOSED, () => {
        debugLog('Appodeal interstitial closed');
        emitInterstitialEvent('closed');
      });
      Appodeal.addEventListener(AppodealInterstitialEvents.EXPIRED, () => {
        debugLog('Appodeal interstitial expired');
        emitInterstitialEvent('expired');
      });
      Appodeal.addEventListener(
        AppodealInterstitialEvents.FAILED_TO_SHOW,
        () => {
          debugLog('Appodeal interstitial failed to show');
          emitInterstitialEvent('failed_to_show');
        },
      );
      Appodeal.addEventListener(AppodealRewardedEvents.LOADED, () => {
        debugLog('Appodeal rewarded loaded');
        emitRewardedEvent('loaded');
      });
      Appodeal.addEventListener(AppodealRewardedEvents.FAILED_TO_LOAD, () => {
        debugLog('Appodeal rewarded failed to load');
        emitRewardedEvent('failed_to_load');
      });
      Appodeal.addEventListener(AppodealRewardedEvents.SHOWN, () => {
        debugLog('Appodeal rewarded shown');
        emitRewardedEvent('shown');
      });
      Appodeal.addEventListener(
        AppodealRewardedEvents.REWARD,
        (reward: any) => {
          debugLog('Appodeal reward earned', reward);
          emitRewardedEvent('reward', reward);
        },
      );
      Appodeal.addEventListener(AppodealRewardedEvents.CLICKED, () => {
        debugLog('Appodeal rewarded clicked');
        emitRewardedEvent('clicked');
      });
      Appodeal.addEventListener(AppodealRewardedEvents.CLOSED, () => {
        debugLog('Appodeal rewarded closed');
        emitRewardedEvent('closed');
      });
      Appodeal.addEventListener(AppodealRewardedEvents.EXPIRED, () => {
        debugLog('Appodeal rewarded expired');
        emitRewardedEvent('expired');
      });
      Appodeal.addEventListener(AppodealRewardedEvents.FAILED_TO_SHOW, () => {
        debugLog('Appodeal rewarded failed to show');
        emitRewardedEvent('failed_to_show');
      });
      Appodeal.addEventListener(AppodealBannerEvents.LOADED, () => {
        debugLog('Appodeal banner loaded');
      });
      listenersAttached = true;
    }

    Appodeal.initialize(APPODEAL_APP_KEY, allAdTypes);
    const initialized = await waitUntil(
      () =>
        sdkInitialized ||
        Appodeal.isInitialized?.(AppodealAdType.INTERSTITIAL) ||
        Appodeal.isInitialized?.(AppodealAdType.REWARDED_VIDEO),
      6000,
      250,
    );

    if (!initialized) {
      debugLog(
        'Appodeal initialization event was not received; continuing with cache readiness checks.',
      );
    }

    adsInitialized = true;
    readyHandlers.forEach(handler => handler());
  })();

  try {
    await adsInitializationPromise;
  } catch (error) {
    backOffNativeAds(error);
    adsInitializationPromise = null;
    throw error;
  }
};

const prepareAd = async (adType: number, timeoutMs = 7000) => {
  await initializeAds();

  if (isAdReady(adType)) {
    return true;
  }

  // Autocache is enabled before SDK initialization. Waiting here avoids
  // issuing duplicate cache requests while mediation is already loading.
  return waitUntil(() => isAdReady(adType), timeoutMs, 300);
};

const runInterstitialAd = async (timeoutMs = 9000) => {
  if (!(await ensureAdsConsent()) || !canAttemptNativeAds()) {
    return {shown: false, rewarded: false};
  }

  try {
    const {Appodeal, AppodealAdType} = requireAppodealRuntime();
    const readyTimeout = Math.max(2500, Math.floor(timeoutMs * 0.45));
    const resultTimeout = Math.max(1500, timeoutMs - readyTimeout);
    const ready = await prepareAd(AppodealAdType.INTERSTITIAL, readyTimeout);
    if (!ready) {
      return {shown: false, rewarded: false};
    }

    const resultPromise = waitForInterstitialResult(resultTimeout);
    Appodeal.show(AppodealAdType.INTERSTITIAL);
    const outcome = await resultPromise;

    if (outcome.shown) {
      noteNativeAdSuccess();
    }
    return outcome;
  } catch (error) {
    debugLog('Unable to show Appodeal interstitial', error);
    backOffNativeAds(error);
    return {shown: false, rewarded: false};
  }
};

const getInterstitialOutcome = async (timeoutMs = 9000) => {
  if (activeInterstitialPromise) {
    return activeInterstitialPromise;
  }

  const attempt = runInterstitialAd(timeoutMs);
  activeInterstitialPromise = attempt;

  try {
    return await attempt;
  } finally {
    if (activeInterstitialPromise === attempt) {
      activeInterstitialPromise = null;
    }
  }
};

export const showInterstitialAd = async (timeoutMs = 9000) =>
  (await getInterstitialOutcome(timeoutMs)).shown;

const runRewardedAd = async (timeoutMs = 11000) => {
  if (!(await ensureAdsConsent()) || !canAttemptNativeAds()) {
    return {shown: false, rewarded: false};
  }

  try {
    const {Appodeal, AppodealAdType} = requireAppodealRuntime();
    const readyTimeout = Math.max(3000, Math.floor(timeoutMs * 0.45));
    const resultTimeout = Math.max(2000, timeoutMs - readyTimeout);
    const ready = await prepareAd(AppodealAdType.REWARDED_VIDEO, readyTimeout);
    if (!ready) {
      return {shown: false, rewarded: false};
    }

    const resultPromise = waitForRewardedResult(resultTimeout);
    Appodeal.show(AppodealAdType.REWARDED_VIDEO);
    const outcome = await resultPromise;

    if (outcome.shown) {
      noteNativeAdSuccess();
    }
    return outcome;
  } catch (error) {
    debugLog('Unable to show Appodeal rewarded ad', error);
    backOffNativeAds(error);
    return {shown: false, rewarded: false};
  }
};

const getRewardedOutcome = async (timeoutMs = 11000) => {
  if (activeRewardedPromise) {
    return activeRewardedPromise;
  }

  const attempt = runRewardedAd(timeoutMs);
  activeRewardedPromise = attempt;

  try {
    return await attempt;
  } finally {
    if (activeRewardedPromise === attempt) {
      activeRewardedPromise = null;
    }
  }
};

export const showRewardedAd = async (timeoutMs = 11000) =>
  (await getRewardedOutcome(timeoutMs)).rewarded;

export const showActionAd = async ({
  timeoutMs = 20000,
  allowContinueWithoutAd = false,
}: ActionAdOptions = {}) => {
  if (!NATIVE_ADS_ENABLED) {
    console.log('Native ads are disabled; continuing action without ad.');
    return {
      shown: false,
      type: 'none' as const,
      canContinue: allowContinueWithoutAd,
      timedOut: true,
    };
  }

  if (!(await ensureAdsConsent())) {
    return {
      shown: false,
      type: 'none' as const,
      canContinue: allowContinueWithoutAd,
      timedOut: true,
    };
  }

  if (!canAttemptNativeAds()) {
    return {
      shown: false,
      type: 'none' as const,
      canContinue: allowContinueWithoutAd,
      timedOut: true,
    };
  }

  const actionFlow = async () => {
    const startedAt = Date.now();
    const rewardedBudget = Math.max(8000, Math.floor(timeoutMs * 0.55));
    const rewardedOutcome = await getRewardedOutcome(rewardedBudget);

    if (rewardedOutcome.shown) {
      return {
        shown: true,
        type: 'rewarded' as const,
        canContinue: true,
        timedOut: false,
        rewarded: rewardedOutcome.rewarded,
      };
    }

    const elapsed = Date.now() - startedAt;
    const remainingBudget = Math.max(0, timeoutMs - elapsed);
    const interstitialOutcome =
      remainingBudget >= 2500
        ? await getInterstitialOutcome(remainingBudget)
        : {shown: false, rewarded: false};

    return {
      shown: interstitialOutcome.shown,
      type: interstitialOutcome.shown
        ? ('interstitial' as const)
        : ('none' as const),
      canContinue: interstitialOutcome.shown || allowContinueWithoutAd,
      timedOut: !interstitialOutcome.shown,
      rewarded: false,
    };
  };

  try {
    // Each ad attempt is already bounded by its own readiness and lifecycle
    // timeout. Avoid Promise.race here because it leaves the native request
    // alive and can display a late ad after the user action has continued.
    return await actionFlow();
  } catch (error) {
    console.log('Action ad gate skipped', error);
    return {
      shown: false,
      type: 'none' as const,
      canContinue: allowContinueWithoutAd,
      timedOut: true,
      rewarded: false,
    };
  }
};

export const showOptionalActionAd = async (timeoutMs = 18000) =>
  showActionAd({
    timeoutMs,
    allowContinueWithoutAd: true,
  });

export const isAdServiceReady = () => adsInitialized;

export const identifyAdUser = (userId?: string | number | null) => {
  const normalizedUserId = String(userId ?? '').trim();
  appodealUserId = normalizedUserId || undefined;

  if (!appodealUserId || !adsInitialized) {
    return;
  }

  try {
    getAppodealRuntime()?.Appodeal?.setUserId(appodealUserId);
  } catch (error) {
    debugLog('Unable to update Appodeal user id', error);
  }
};

export const subscribeAdServiceReady = (handler: () => void) => {
  readyHandlers.add(handler);

  if (adsInitialized) {
    handler();
  }

  return () => {
    readyHandlers.delete(handler);
  };
};

subscribeAdsConsent(state => {
  if (state.flowCompleted && state.canRequestAds) {
    if (adsInitialized) {
      try {
        const runtime = getAppodealRuntime();
        if (runtime) {
          const {Appodeal, AppodealAdType} = runtime;
          Appodeal.setAutoCache(AppodealAdType.INTERSTITIAL, true);
          Appodeal.setAutoCache(AppodealAdType.BANNER, true);
          Appodeal.setAutoCache(AppodealAdType.REWARDED_VIDEO, true);
        }
      } catch (error) {
        debugLog('Unable to resume Appodeal auto-cache.', error);
      }
    } else {
      initializeAds().catch(error => {
        debugLog('Ad initialization after consent failed.', error);
      });
    }
    return;
  }

  if (!adsInitialized) {
    return;
  }

  try {
    const runtime = getAppodealRuntime();
    if (!runtime) {
      return;
    }
    const {Appodeal, AppodealAdType} = runtime;
    Appodeal.setAutoCache(AppodealAdType.INTERSTITIAL, false);
    Appodeal.setAutoCache(AppodealAdType.BANNER, false);
    Appodeal.setAutoCache(AppodealAdType.REWARDED_VIDEO, false);
    debugLog('Appodeal auto-cache disabled because ads are not consented.');
  } catch (error) {
    debugLog('Unable to suspend Appodeal auto-cache.', error);
  }
});
