import {IronSource, LevelPlayInterstitialListener} from 'ironsource-mediation';
import {INTERSTITIAL_AD_UNIT_ID} from '../utils/constants';
import {useCallback, useEffect, useState} from 'react';
import {requestAdsConsent} from '../services/adsConsentService';

const useInterstitialAd = () => {
  const [isInterstitialAdAvailable, setIsInterstitialAdAvailable] =
    useState(false);

  useEffect(() => {
    let isMounted = true;

    const loadAd = async () => {
      const consent = await requestAdsConsent();
      if (!consent.canRequestAds) {
        if (__DEV__) {
          console.log('LevelPlay interstitial blocked by UMP consent.');
        }
        return;
      }
      IronSource.loadInterstitial().catch(error => {
        // console.error('Failed to initiate interstitial ad loading:', error);
      });
    };

    const listener: LevelPlayInterstitialListener = {
      onAdReady: adInfo => {
        console.log('Interstitial Ad is ready:', adInfo);
        if (isMounted) setIsInterstitialAdAvailable(true);
      },
      onAdLoadFailed: error => {
        // console.error('Failed to load interstitial ad:', error);
        if (isMounted) setIsInterstitialAdAvailable(false);
        setTimeout(loadAd, 5000); // Retry after 5 seconds
      },
      onAdClosed: adInfo => {
        console.log('Interstitial Ad closed:', adInfo);
        if (isMounted) {
          setIsInterstitialAdAvailable(false);
          loadAd(); // Load the next ad
        }
      },
    };

    if (IronSource) {
      IronSource.setLevelPlayInterstitialListener(listener);
      loadAd();
    } else {
      // console.error('IronSource SDK is not initialized');
    }

    return () => {
      isMounted = false;
      if (IronSource) {
        IronSource.setLevelPlayInterstitialListener({});
      }
    };
  }, []);

  const showInterstitialAd = useCallback(async () => {
    try {
      const consent = await requestAdsConsent();
      if (!consent.canRequestAds) {
        return;
      }
      const isAvailable = await IronSource.isInterstitialReady();
      if (isAvailable) {
        try {
          console.log('>>>>');
          await IronSource.showInterstitial(INTERSTITIAL_AD_UNIT_ID);
        } catch (err) {
          console.error('Failed to show interstitial ad:', err);
        }
      }
    } catch (error) {
      console.log('Failed to show interstitial ad:', error);
    }
  }, [INTERSTITIAL_AD_UNIT_ID]);

  return {
    isInterstitialAdAvailable,
    showInterstitialAd,
  };
};

export default useInterstitialAd;
