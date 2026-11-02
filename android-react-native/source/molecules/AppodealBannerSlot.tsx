import React, {useEffect, useState} from 'react';
import {StyleSheet, useWindowDimensions, View} from 'react-native';
import {AppodealBanner} from 'react-native-appodeal';
import {isAdServiceReady, subscribeAdServiceReady} from '../services/adService';
import {
  isAdsConsentGranted,
  subscribeAdsConsent,
} from '../services/adsConsentService';

type AppodealBannerSlotProps = {
  placement?: string;
};

const AppodealBannerSlot = ({
  placement = 'default',
}: AppodealBannerSlotProps) => {
  const {width} = useWindowDimensions();
  const [sdkReady, setSdkReady] = useState(isAdServiceReady());
  const [consentAllowed, setConsentAllowed] = useState(isAdsConsentGranted());
  const [loaded, setLoaded] = useState(false);
  const scale = Math.min(1, Math.max(0.78, (width - 48) / 320));

  useEffect(() => {
    return subscribeAdServiceReady(() => setSdkReady(true));
  }, []);

  useEffect(() => {
    return subscribeAdsConsent(state => {
      setConsentAllowed(state.flowCompleted && state.canRequestAds);
    });
  }, []);

  if (!sdkReady || !consentAllowed) {
    return null;
  }

  return (
    <View
      style={[
        styles.shell,
        loaded && styles.shellLoaded,
        loaded && {height: 50 * scale + 8},
      ]}>
      <AppodealBanner
        adSize="phone"
        placement={placement}
        onAdLoaded={() => setLoaded(true)}
        onAdFailedToLoad={() => setLoaded(false)}
        onAdExpired={() => setLoaded(false)}
        style={[styles.banner, {transform: [{scale}]}]}
      />
    </View>
  );
};

const styles = StyleSheet.create({
  shell: {
    height: 0,
    opacity: 0,
    overflow: 'hidden',
    alignItems: 'center',
    justifyContent: 'center',
  },
  shellLoaded: {
    opacity: 1,
    marginVertical: 8,
  },
  banner: {
    width: 320,
    height: 50,
  },
});

export default React.memo(AppodealBannerSlot);
