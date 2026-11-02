import { Pressable } from 'react-native';
import React from 'react';
import { hp, wp } from '../utils/styleConstants';
import PrimaryCard from '../atoms/PrimaryCard';
import FastImage from 'react-native-fast-image';
import { images } from '../../assets/images';
import {Box, HStack, Skeleton, VStack} from '../ui/nativeBaseCompat';
import colors from '../utils/colors';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import Feather from 'react-native-vector-icons/Feather';
import HeaderText from '../atoms/HeaderText';
import { RFValue } from 'react-native-responsive-fontsize';
import { NavProps } from '../types/navigation';
import { screenNames } from '../navigators';
import { copyToClipboard, navigate } from '../utils/helper';

const ProfileCard: React.FC<{
  user: any;
  navigation?: NavProps;
}> = ({ user }) => {
  return (
    <PrimaryCard>
      {user ? (
        <HStack alignItems={'center'} justifyContent={'space-between'}>
          <FastImage
            source={images.placeholder}
            style={{ width: wp(20), height: hp(10) }}
            resizeMode="contain"
          />
          <VStack>
            <HeaderText
              text={user?.name}
              style={{ color: colors.white, fontSize: RFValue(18) }}
            />
            <HeaderText
              text={user?.email}
              style={{
                color: colors.white,
                fontSize: RFValue(12),
                width: '95%',
              }}
              numberOfLines={2}
            />
            <Pressable onPress={() => copyToClipboard(user?.uid.toString())}>
              <HStack alignItems={'center'}>
                <HeaderText
                  text={"UID: " + user?.uid}
                  style={{ color: colors.white, fontSize: RFValue(12) }}
                />
                <Feather
                  name="copy"
                  color={colors.white}
                  size={15}
                  style={{ marginLeft: wp(2) }}
                />
              </HStack>
            </Pressable>
          </VStack>
          <Icon
            name="file-edit-outline"
            color={colors.white}
            size={25}
            style={{ marginRight: wp(2) }}
            onPress={() => navigate(screenNames.EditScreen, { user })}
          />
        </HStack>
      ) : (
        <Box
          style={{
            backgroundColor: colors.black,
            padding: 16,
            borderRadius: 10,
          }}
        >
          <Skeleton
            color={colors.gray[700]}
            backgroundColor={colors.gray[800]}
            height={16}
            width={16}
            borderRadius="full"
            opacity={2}
          />
          <Skeleton.Text
            lines={2}
            mt={4}
            color={colors.white}
          />
        </Box>
      )}
    </PrimaryCard>
  );
};

export default ProfileCard;
