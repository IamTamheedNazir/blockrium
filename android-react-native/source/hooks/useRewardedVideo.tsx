import {useEffect, useState, useCallback} from 'react';
import {
  IronSource,
  IronSourceAdInfo,
  IronSourceError,
  IronSourceRVPlacement,
  LevelPlayRewardedVideoListener,
} from 'ironsource-mediation';
import {REWARDED_AD_UNIT_ID} from '../utils/constants';
import {requestAdsConsent} from '../services/adsConsentService';

const useRewardedVideo = () => {
  const [isRewardedVideoAvailable, setIsRewardedVideoAvailable] =
    useState<boolean>(false);
  const [isRewardedVideoVisible, setIsRewardedVideoVisible] =
    useState<boolean>(false);
  const [reservedPlacement, setReservedPlacement] = useState<
    IronSourceRVPlacement | undefined
  >(undefined);

  const showRewardedVideo = useCallback(async () => {
    try {
      const consent = await requestAdsConsent();
      if (!consent.canRequestAds) {
        if (__DEV__) {
          console.log('LevelPlay rewarded ad blocked by UMP consent.');
        }
        return;
      }
      const isAvailable = await IronSource.isRewardedVideoAvailable();
      if (isAvailable) {
        const isCapped = await IronSource.isRewardedVideoPlacementCapped(
          REWARDED_AD_UNIT_ID,
        );
        if (!isCapped) {
          console.log('>>>>');
          IronSource.showRewardedVideo(REWARDED_AD_UNIT_ID);
        } else {
          console.log(
            `Rewarded Video Placement ${REWARDED_AD_UNIT_ID} is capped`,
          );
        }
      } else {
        // console.error('Rewarded Video is not available');
      }
    } catch (error) {
      // console.error('Error showing rewarded video:', error);
    }
  }, [REWARDED_AD_UNIT_ID]);

  useEffect(() => {
    let isMounted = true;

    const listener: LevelPlayRewardedVideoListener = {
      onAdRewarded: (
        placement: IronSourceRVPlacement,
        adInfo: IronSourceAdInfo,
      ) => {
        console.log(
          `Rewarded Video Ad - onAdRewarded\n placement: ${JSON.stringify(
            placement,
          )}\n adInfo: ${JSON.stringify(adInfo)}`,
        );
        if (isMounted) {
          if (!isRewardedVideoVisible) {
            console.log(
              'Ad Rewarded',
              `placement: ${JSON.stringify(placement)}`,
            );
            setReservedPlacement(undefined);
          } else {
            setReservedPlacement(placement);
          }
        }
      },
      onAdShowFailed: (error: IronSourceError, adInfo: IronSourceAdInfo) => {
        // console.error(`Rewarded Video Ad - onAdShowFailed\n error: ${JSON.stringify(error)}\n adInfo: ${JSON.stringify(adInfo)}`);
        if (isMounted) setIsRewardedVideoVisible(false);
      },
    };

    // Ensure the SDK is initialized before setting the listener
    if (IronSource) {
      IronSource.setLevelPlayRewardedVideoListener(listener);
    } else {
      // console.error('IronSource SDK is not initialized');
    }

    return () => {
      isMounted = false; // Avoid updating state after unmount
      if (IronSource) {
        IronSource.setLevelPlayRewardedVideoListener({});
      }
    };
  }, [reservedPlacement, isRewardedVideoVisible]);

  return {
    isRewardedVideoAvailable,
    showRewardedVideo,
  };
};

export default useRewardedVideo;
