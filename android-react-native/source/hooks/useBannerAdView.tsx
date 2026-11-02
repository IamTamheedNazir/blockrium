import {useCallback, useRef, useState} from 'react';
import {useFocusEffect} from '@react-navigation/native';
import {
  LevelPlayAdError,
  LevelPlayAdInfo,
  LevelPlayAdSize,
  LevelPlayBannerAdViewMethods,
  LevelPlayBannerAdViewListener,
} from 'ironsource-mediation';
import {requestAdsConsent} from '../services/adsConsentService';

interface UseBannerAdViewProps {
  adUnitId: string;
  placementName: string;
  adSize: LevelPlayAdSize;
  error: boolean;
}

const useBannerAdView = ({
  adUnitId,
  placementName,
  adSize,
  error = false,
}: UseBannerAdViewProps) => {
  const bannerAdViewRef = useRef<LevelPlayBannerAdViewMethods>(null);
  const [adKey, setAdKey] = useState<number>(0);
  const [isError, setIsError] = useState<boolean>(error);
  const [consentAllowed, setConsentAllowed] = useState(false);

  const listener: LevelPlayBannerAdViewListener = {
    onAdLoaded: (adInfo: LevelPlayAdInfo) => {
      console.log('Banner Ad View - Ad Loaded: ', adInfo);
    },
    onAdLoadFailed: (error: LevelPlayAdError) => {
      // console.error('Banner Ad View - Ad Load Failed: ', error);
      setIsError(true);
    },
    onAdDisplayed: (adInfo: LevelPlayAdInfo) => {
      console.log('Banner Ad View - Ad Displayed: ', adInfo);
    },
    onAdDisplayFailed: (adInfo: LevelPlayAdInfo, error: LevelPlayAdError) => {
      // console.error('Banner Ad View - Ad Display Failed: ', adInfo, error);
    },
    onAdClicked: (adInfo: LevelPlayAdInfo) => {
      console.log('Banner Ad View - Ad Clicked: ', adInfo);
    },
  };

  const loadAd = useCallback(async () => {
    const consent = await requestAdsConsent();
    if (!consent.canRequestAds) {
      if (__DEV__) {
        console.log('LevelPlay banner blocked by UMP consent.');
      }
      return;
    }
    setConsentAllowed(true);
    bannerAdViewRef.current?.loadAd();
  }, []);

  const destroyAdAndCreateNew = useCallback(() => {
    bannerAdViewRef.current?.destroy();
    setAdKey(prevKey => prevKey + 1);
  }, []);

  const pauseAutoRefresh = useCallback(() => {
    bannerAdViewRef.current?.pauseAutoRefresh();
  }, []);

  const resumeAutoRefresh = useCallback(() => {
    bannerAdViewRef.current?.resumeAutoRefresh();
  }, []);

  useFocusEffect(
    useCallback(() => {
      loadAd();
    }, [loadAd]),
  );

  return {
    bannerAdViewRef,
    adKey,
    adUnitId,
    placementName,
    adSize,
    listener,
    loadAd,
    destroyAdAndCreateNew,
    pauseAutoRefresh,
    resumeAutoRefresh,
    isError,
    consentAllowed,
  };
};

export default useBannerAdView;
