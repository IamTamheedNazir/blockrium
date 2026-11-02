import React, {useCallback, useEffect, useRef, useState} from 'react';
import {BannerAd, BannerAdSize} from 'react-native-cas';
import type {BannerAdRef} from 'react-native-cas';
import {Button, View} from 'react-native';
import {useCasContext} from '../context/casContext';
import {StyleSheet} from 'react-native';
import {requestAdsConsent} from '../services/adsConsentService';

export const Banners = () => {
  const context = useCasContext();
  const ref = useRef<BannerAdRef | null>(null);
  const [consentAllowed, setConsentAllowed] = useState(false);

  useEffect(() => {
    let active = true;
    requestAdsConsent().then(state => {
      if (active) {
        setConsentAllowed(state.canRequestAds);
      }
    });
    return () => {
      active = false;
    };
  }, []);

  const nextAd = useCallback(() => {
    if (consentAllowed) {
      ref.current?.loadNextAd();
    }
  }, [consentAllowed]);

  if (!consentAllowed) {
    return null;
  }

  return (
    <View style={styles.screen}>
      <Button title={'Next ad'} onPress={nextAd} />
      <BannerAd
        size={BannerAdSize.Banner}
        onAdViewLoaded={() => context.logCasInfo('Banner ad loaded')}
        onAdViewClicked={() => context.logCasInfo('Banner ad clicked')}
        onAdViewFailed={e =>
          context.logCasInfo('Banner ad failed', JSON.stringify(e))
        }
        onAdViewPresented={e => {
          context.logCasInfo('Banner ad presented', JSON.stringify(e));
        }}
      />
      <BannerAd
        ref={ref}
        isAutoloadEnabled={false}
        size={BannerAdSize.MediumRectangle}
      />
      <BannerAd size={BannerAdSize.Leaderboard} refreshInterval={20} />
    </View>
  );
};

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    justifyContent: 'space-around',
    alignItems: 'center',
    overflow: 'hidden',
  },
});
