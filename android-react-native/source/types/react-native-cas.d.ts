declare module 'react-native-cas' {
  import type React from 'react';
  import type {ComponentType} from 'react';
  import type {ViewProps} from 'react-native';

  export type AdImpression = Record<string, unknown>;

  export enum MediationManagerEvent {
    AdFailedToLoad = 'adFailedToLoad',
    AdLoaded = 'adLoaded',
    AD_IMPRESSION = 'adImpression',
    AD_LOADED = 'adLoaded',
    AD_FAILED = 'adFailedToLoad',
  }

  export const BannerAdSize: {
    Banner: string;
    Leaderboard: string;
    MediumRectangle: string;
    Adaptive: string;
    Smart: string;
    BANNER: string;
    LEADERBOARD: string;
    MEDIUM_RECTANGLE: string;
    [key: string]: string;
  };

  export type BannerAdRef = {
    load?: () => void;
    loadNextAd: () => Promise<void>;
    reload?: () => void;
    isAdReady?: () => Promise<boolean>;
  };

  export const BannerAd: ComponentType<
    ViewProps & {
      size?: string;
      managerId?: string;
      onAdLoaded?: () => void;
      onAdFailedToLoad?: (error: unknown) => void;
      onAdViewLoaded?: () => void;
      onAdViewClicked?: () => void;
      onAdViewFailed?: (error: unknown) => void;
      onAdViewPresented?: (event: unknown) => void;
      isAutoloadEnabled?: boolean;
      refreshInterval?: number;
      ref?: React.Ref<BannerAdRef>;
    }
  >;
}
