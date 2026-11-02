import {NativeModules, Platform} from 'react-native';
import {requestAdsConsent} from '../services/adsConsentService';

const {IronSourceModule} = NativeModules;

const IronSource = {
  showRewardedVideo: async () => {
    const consent = await requestAdsConsent();
    if (!consent.canRequestAds) {
      if (__DEV__) {
        console.log('Legacy IronSource ad blocked by UMP consent.');
      }
      return;
    }
    if (Platform.OS === 'android' || Platform.OS === 'ios') {
      IronSourceModule?.showRewardedVideo?.();
    }
  },
};

export default IronSource;
