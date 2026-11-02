import type {
  LastPageAdContent,
  MediationManagerEventListener,
  ShowAdCallbacks,
} from './types';
import {NativeEventEmitter} from 'react-native';
import {MediationManagerEvent} from './types';
import type {AdImpression} from './types';
import {MediationManagerModule} from './native';
import {requestAdsConsent} from '../services/adsConsentService';

type GenericEvent = {
  callbackId: string;
  data?: AdImpression | string;
};

export class MediationManager {
  private eventEmitter = MediationManagerModule
    ? new NativeEventEmitter(MediationManagerModule)
    : null;
  private showAdListeners: Record<string, ShowAdCallbacks> = {};

  private canUseAds = async () => {
    const consent = await requestAdsConsent();
    if (!consent.canRequestAds && __DEV__) {
      console.log('CAS mediation request blocked by UMP consent.');
    }
    return consent.canRequestAds;
  };

  constructor() {
    if (!this.eventEmitter) {
      return;
    }

    this.eventEmitter.addListener('onShown', (e: GenericEvent) => {
      this.showAdListeners[e.callbackId]?.onShown?.();
    });

    this.eventEmitter.addListener('onAdRevenuePaid', (e: GenericEvent) => {
      this.showAdListeners[e.callbackId]?.onImpression?.(
        e.data as AdImpression,
      );
    });

    this.eventEmitter.addListener('onShowFailed', (e: GenericEvent) => {
      this.showAdListeners[e.callbackId]?.onShowFailed?.(e.data as string);
      delete this.showAdListeners[e.callbackId];
    });

    this.eventEmitter.addListener('onClicked', (e: GenericEvent) => {
      this.showAdListeners[e.callbackId]?.onClicked?.();
    });

    this.eventEmitter.addListener('onComplete', (e: GenericEvent) => {
      this.showAdListeners[e.callbackId]?.onComplete?.();
      delete this.showAdListeners[e.callbackId];
    });

    this.eventEmitter.addListener('onClosed', (e: GenericEvent) => {
      this.showAdListeners[e.callbackId]?.onClosed?.();
      delete this.showAdListeners[e.callbackId];
    });
  }

  addListener = (
    event: MediationManagerEvent,
    listener: MediationManagerEventListener,
  ) => {
    return (
      this.eventEmitter?.addListener(event, listener) ?? {
        remove: () => undefined,
      }
    );
  };

  private createShowAdCallback = (props: ShowAdCallbacks) => {
    const id = Math.random().toString(36).slice(2, 7);
    this.showAdListeners[id] = props;
    return id;
  };

  setLastPageAdContent = async (params: LastPageAdContent) => {
    return MediationManagerModule?.setLastPageAdContent?.(params);
  };

  loadInterstitial = async () =>
    (await this.canUseAds())
      ? MediationManagerModule?.loadInterstitial?.()
      : undefined;

  isInterstitialReady = async (): Promise<boolean> =>
    (await this.canUseAds()) &&
    Boolean(await MediationManagerModule?.isInterstitialReady?.());

  showInterstitial = async (props: ShowAdCallbacks) => {
    if (!(await this.canUseAds())) {
      return;
    }
    return MediationManagerModule?.showInterstitial?.(
      this.createShowAdCallback(props),
    );
  };

  loadRewardedAd = async () =>
    (await this.canUseAds())
      ? MediationManagerModule?.loadRewardedAd?.()
      : undefined;

  isRewardedAdReady = async (): Promise<boolean> =>
    (await this.canUseAds()) &&
    Boolean(await MediationManagerModule?.isRewardedAdReady?.());

  showRewardedAd = async (props: ShowAdCallbacks) => {
    if (!(await this.canUseAds())) {
      return;
    }
    return MediationManagerModule?.showRewardedAd?.(
      this.createShowAdCallback(props),
    );
  };

  enableAppReturnAds = async (props: ShowAdCallbacks) => {
    if (!(await this.canUseAds())) {
      return;
    }
    return MediationManagerModule?.enableAppReturnAds?.(
      this.createShowAdCallback(props),
    );
  };

  disableAppReturnAds = async () =>
    MediationManagerModule?.disableAppReturnAds?.();

  skipNextAppReturnAds = async () =>
    MediationManagerModule?.skipNextAppReturnAds?.();

  loadAppOpenAd = async (isLandscape: boolean) =>
    (await this.canUseAds())
      ? MediationManagerModule?.loadAppOpenAd?.(isLandscape)
      : undefined;

  isAppOpenAdAvailable = async (): Promise<boolean> =>
    (await this.canUseAds()) &&
    Boolean(await MediationManagerModule?.isAppOpenAdAvailable?.());

  showAppOpenAd = async (props: ShowAdCallbacks) => {
    if (!(await this.canUseAds())) {
      return;
    }
    return MediationManagerModule?.showAppOpenAd?.(
      this.createShowAdCallback(props),
    );
  };
}
