import React from 'react';
import {View} from 'react-native';
import {LevelPlayBannerAdView, LevelPlayAdSize} from 'ironsource-mediation';
import useBannerAdView from '../hooks/useBannerAdView';
import {BANNER_AD_UNIT_ID} from '../utils/constants';

const BannerAdScreen: React.FC = () => {
  const {
    bannerAdViewRef,
    adKey,
    adUnitId,
    placementName,
    adSize,
    listener,
    isError: error,
    consentAllowed,
  } = useBannerAdView({
    adUnitId: BANNER_AD_UNIT_ID,
    placementName: '',
    adSize: LevelPlayAdSize.BANNER,
    error: false,
  });

  return (
    <View>
      {!error && consentAllowed && (
        <LevelPlayBannerAdView
          ref={bannerAdViewRef}
          key={adKey}
          adUnitId={adUnitId}
          adSize={adSize}
          placementName={placementName}
          listener={listener}
          style={{
            width: adSize.width,
            height: adSize.height,
            alignSelf: 'center',
          }}
        />
      )}
    </View>
  );
};

export default BannerAdScreen;
