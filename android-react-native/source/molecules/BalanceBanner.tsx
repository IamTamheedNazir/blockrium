import React from 'react';
import {hp, wp} from '../utils/styleConstants';
import colors from '../utils/colors';
import {HStack, Stack} from '../ui/nativeBaseCompat';
import {RFValue} from 'react-native-responsive-fontsize';
import HeaderText from '../atoms/HeaderText';
import SecondaryBanner from '../atoms/SecondaryBanner';

const BalanceBanner: React.FC<balanceBannerProps> = ({
  text,
  balance,
  status,
}) => {
  return (
    <SecondaryBanner>
      <Stack>
        <HeaderText
          text={text}
          style={{color: colors.white, fontSize: RFValue(14)}}
        />
        <HStack alignItems={'center'}>
          <HeaderText
            text={balance}
            style={{color: colors.white, fontSize: RFValue(20)}}
          />
          <HeaderText
            text={'BRC'}
            style={{
              color: colors.white,
              marginLeft: wp(1),
              fontSize: RFValue(14),
              alignSelf: 'flex-end',
              bottom: 3
            }}
          />
        </HStack>
      </Stack>
      <Stack>
        <HeaderText
          text={status}
          style={{
            color: colors.primary,
            padding: hp(1.4),
            backgroundColor: colors.white,
            borderRadius: hp(1),
          }}
        />
      </Stack>
    </SecondaryBanner>
  );
};

export default BalanceBanner;
