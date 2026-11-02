import { Dimensions } from 'react-native'
import React from 'react'
import {HStack} from '../ui/nativeBaseCompat'
import FastImage from 'react-native-fast-image'
import { images } from '../../assets/images'
import { hp, wp } from '../utils/styleConstants'
import HeaderText from '../atoms/HeaderText'
import { RFValue } from 'react-native-responsive-fontsize'
import colors from '../utils/colors'
import { MainContainer } from '../utils/helper'

const LeaderBoardRank = (props: any) => {
  const {leadMiners} = props;
  return (
    <HStack
        alignItems={'center'}
        justifyContent={'space-between'}
        mt={hp(6)}>
        <FastImage
          source={images.secondCard}
          style={{
            width: '40%',
            height: hp(40),
            alignItems: 'center',
            justifyContent: 'center',
          }}
          resizeMode="contain">
          <FastImage
            source={images.up}
            resizeMode="contain"
            style={{
              width: wp(5),
              height: hp(5),
              position: 'absolute',
              top: 50,
            }}
          />
          <FastImage
            source={images.logo}
            style={{width: wp(20), height: hp(10)}}
            resizeMode="contain"
          />
          <HeaderText
            text={leadMiners[1]?.name}
            style={{color: colors.white, fontSize: RFValue(14)}}
          />
          <HeaderText
            text={leadMiners[1]?.total}
            style={{color: colors.white, fontSize: RFValue(14)}}
          />
          <HeaderText
            text={"@" + leadMiners[1]?.name}
            style={{color: colors.white, fontSize: RFValue(10)}}
          />
        </FastImage>
        <FastImage
          source={images.primCard}
          style={{
            width: '40%',
            height: hp(55),
            position: 'absolute',
            left: Dimensions.get('screen').width / 2 - wp(20),
            alignItems: 'center',
            justifyContent: 'center',
            zIndex: 1,
          }}
          resizeMode="contain">
          <FastImage
            source={images.colorCrown}
            resizeMode="contain"
            style={{
              width: wp(15),
              height: hp(15),
              position: 'absolute',
              top: -20,
            }}
          />
          <FastImage
            source={images.logo}
            style={{
              width: wp(20),
              height: hp(10),
              position: 'absolute',
              top: 100,
            }}
            resizeMode="contain"
          />
          <HeaderText
            text={leadMiners[0]?.name}
            style={{color: colors.white, fontSize: RFValue(14)}}
          />
          <HeaderText
            text={leadMiners[0]?.total}
            style={{color: colors.white, fontSize: RFValue(14)}}
          />
          <HeaderText
            text={"@" + leadMiners[0]?.name}
            style={{color: colors.white, fontSize: RFValue(10)}}
          />
        </FastImage>
        <FastImage
          source={images.secondCard}
          style={{
            width: '40%',
            height: hp(40),
            marginHorizontal: wp(2),
            alignItems: 'center',
            justifyContent: 'center',
          }}
          resizeMode="contain">
          <FastImage
            source={images.down}
            resizeMode="contain"
            style={{
              width: wp(5),
              height: hp(5),
              position: 'absolute',
              top: 50,
            }}
          />
          <FastImage
            source={images.logo}
            style={{width: wp(20), height: hp(10)}}
            resizeMode="contain"
          />
          <HeaderText
            text={leadMiners[2]?.name}
            style={{color: colors.white, fontSize: RFValue(14)}}
          />
          <HeaderText
            text={leadMiners[2]?.total}
            style={{color: colors.white, fontSize: RFValue(14)}}
          />
          <HeaderText
            text={"@" + leadMiners[2]?.name}
            style={{color: colors.white, fontSize: RFValue(10)}}
          />
        </FastImage>
      </HStack>
  )
}

export default LeaderBoardRank
