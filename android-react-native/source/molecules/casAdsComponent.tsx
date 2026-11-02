import React, {useCallback, useEffect} from 'react';
import {SafeAreaView} from 'react-native-safe-area-context';
import {Button, StyleSheet} from 'react-native';
import {useCasContext} from '../context/casContext';
import {MediationManagerEvent} from '../utils/types';
import type {AdImpression} from '../utils/types';
import {requestAdsConsent} from '../services/adsConsentService';

export const Ads = () => {
  const context = useCasContext();

  useEffect(() => {
    const loadedRemove = context.manager?.addListener(
      MediationManagerEvent.AdLoaded,
      e => {
        context.logCasInfo('Ad loaded, type: ', JSON.stringify(e));
      },
    );

    const failedRemove = context.manager?.addListener(
      MediationManagerEvent.AdFailedToLoad,
      e => {
        context.logCasInfo('Ad failed to load, type: ', JSON.stringify(e));
      },
    );

    return () => {
      loadedRemove?.remove();
      failedRemove?.remove();
    };
  }, [context]);

  const setLastPageContent = useCallback(async () => {
    const params = {
      headline: 'Headline',
      adText: 'Ad text',
      destinationURL: 'https://www.google.com',
    };

    await context.manager?.setLastPageAdContent(params);

    context.logCasInfo(
      'Last page content was set with params: ',
      JSON.stringify(params),
    );
  }, [context]);

  const showInterstitial = useCallback(async () => {
    if (!(await requestAdsConsent()).canRequestAds) {
      return;
    }
    const {manager} = context;

    if (manager) {
      await manager.loadInterstitial();

      while (true) {
        const isReady = await manager.isInterstitialReady();

        if (isReady) {
          await manager.showInterstitial(
            createCallbacks('Interstitial', context.logCasInfo),
          );
          break;
        } else {
          await delay(1000);
        }
      }
    }
  }, [context]);

  const showRewarded = useCallback(async () => {
    if (!(await requestAdsConsent()).canRequestAds) {
      return;
    }
    const {manager} = context;

    if (manager) {
      await manager.loadRewardedAd();

      while (true) {
        const isReady = await manager.isRewardedAdReady();

        if (isReady) {
          await manager.showRewardedAd(
            createCallbacks('Rewarded', context.logCasInfo),
          );
          break;
        } else {
          await delay(1000);
        }
      }
    }
  }, [context]);

  const enableAppReturn = useCallback(async () => {
    context.manager?.enableAppReturnAds(
      createCallbacks('AppReturn', context.logCasInfo),
    );

    context.logCasInfo('App Returns enabled');
  }, [context]);

  const disableAppReturn = useCallback(async () => {
    context.manager?.disableAppReturnAds();
    context.logCasInfo('App Returns disabled');
  }, [context]);

  const skipNextReturn = useCallback(() => {
    context.manager?.skipNextAppReturnAds();
    context.logCasInfo('App Returns next skipped');
  }, [context]);

  const showAppOpenedAd = useCallback(async () => {
    if (!(await requestAdsConsent()).canRequestAds) {
      return;
    }
    const {manager} = context;

    if (manager) {
      await manager.loadAppOpenAd(true);

      while (true) {
        const isReady = await manager.isAppOpenAdAvailable();

        if (isReady) {
          await manager.showAppOpenAd(
            createCallbacks('AppOpened', context.logCasInfo),
          );
          break;
        } else {
          await delay(1000);
        }
      }
    }
  }, [context]);

  return (
    <SafeAreaView style={styles.screen}>
      <Button title={'Set last page content'} onPress={setLastPageContent} />
      <Button title={'Show interstitial'} onPress={showInterstitial} />
      <Button title={'Show rewarded'} onPress={showRewarded} />
      <Button title={'Enable app return'} onPress={enableAppReturn} />
      <Button title={'Disable app return'} onPress={disableAppReturn} />
      <Button title={'Skip next return'} onPress={skipNextReturn} />
      <Button title={'Show app opened'} onPress={showAppOpenedAd} />
    </SafeAreaView>
  );
};

const delay = async (ms: number) => new Promise(res => setTimeout(res, ms));

const createCallbacks = (adType: string, logger: (...data: any[]) => void) => ({
  onShown: () => {
    logger(adType + ' shown');
  },
  onShowFailed: (message: string) => {
    logger(adType + ' shown failed, error: ', message);
  },
  onClicked: () => {
    logger(adType + ' shown clicked');
  },
  onComplete: () => {
    logger(adType + ' shown completed');
  },
  onClosed: () => {
    logger(adType + ' shown closed');
  },
  onImpression: (ad: AdImpression) => {
    logger(adType + ' shown, impression: ', JSON.stringify(ad));
  },
});

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    justifyContent: 'space-around',
    alignItems: 'center',
    overflow: 'hidden',
  },
});
