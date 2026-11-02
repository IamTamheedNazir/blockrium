import {Pressable, StyleSheet, Text, View} from 'react-native';
import React from 'react';
import {Box, VStack} from '../ui/nativeBaseCompat';
import {hp, wp} from '../utils/styleConstants';
import colors from '../utils/colors';
import FastImage from 'react-native-fast-image';
import {images} from '../../assets/images';
import HeaderText from '../atoms/HeaderText';
import {RFValue} from 'react-native-responsive-fontsize';
import PrimaryCard from '../atoms/PrimaryCard';

const ReferralRank = (props: any) => {
  const {authReferral} = props;
  return (
    <PrimaryCard>
    <Box
      style={{
        width: '95%',
        marginVertical: hp(2),
        alignSelf: 'center',
        borderRadius: hp(2),
        alignItems: 'center',
        flexDirection: 'row',
        justifyContent: 'space-between',
        paddingHorizontal: wp(3),
      }}>
      <FastImage
        source={images.placeholder}
        style={{width: wp(16), height: hp(8), borderRadius: hp(2)}}
        resizeMode="contain"
      />
      <View style={{ flex: 1, marginLeft: hp(1), marginRight: hp(1) }}>
      <HeaderText
        text={authReferral?.name}
        style={{
          color: colors.white,
          fontSize: RFValue(14),
          textAlign: 'left',
        }}
        numberOfLines={1}
        ellipsizeMode="tail"
      />
    </View>
      <Pressable
        style={{
          backgroundColor: colors.secondary,
          justifyContent: 'center',
          alignItems: 'center',
          borderRadius: hp(2),
          padding: hp(2),
        }}>
        <HeaderText style={{color: colors.white}} text="Ping" />
      </Pressable>
    </Box>
    </PrimaryCard>
  );
};

export default ReferralRank;

const styles = StyleSheet.create({});
