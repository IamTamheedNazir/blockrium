import {FlatList, Linking, Pressable} from 'react-native';
import React, {useEffect} from 'react';
import {ProfileCardData} from '../utils/constants';
import {Box, HStack, Text} from '../ui/nativeBaseCompat';
import PrimaryCard from '../atoms/PrimaryCard';
import {defaultTheme, hp, wp} from '../utils/styleConstants';
import MaterialCommunityIcons from 'react-native-vector-icons/MaterialCommunityIcons';
import BoldText from '../atoms/BoldText';
import colors from '../utils/colors';
// import ActionSheet from '../atoms/ActionSheet';
import useAuthData from '../storage/client/useAuthData';
import {callMutation} from '../hooks';
import {useThisAlert} from '../hooks/AlertContext';
import {navigate, restartApp} from '../utils/helper';
import {removeLeadsTokenFromHeader} from '../storage/server';
import {setStorageToken} from '../utils/mmkvStorage';
import ActionSheet, {useSheetRef} from 'react-native-actions-sheet';
import InAppBrowser from 'react-native-inappbrowser-reborn';
import {RFValue} from 'react-native-responsive-fontsize';
import MaterialIcons from 'react-native-vector-icons/MaterialIcons';

const ProfileComponent = ({user}: {user: any}) => {
  const ref = useSheetRef('return-data');
  const password = useAuthData()?.fields?.loginPassword;
  const {showAlert} = useThisAlert();

  const buttons = [
    {label: 'Logout', icon: 'logout'},
    {label: 'Cancel', icon: 'cancel', isCancel: true},
  ];

  const jsonBody = {
    email: user?.email,
    password: password,
  };

  const {data, mutate}: any = callMutation('auth/logout', jsonBody);

  useEffect(() => {
    const handleData = async () => {
      if (data) {
        if (!data?.success) {
          showAlert(data?.error, 'ERROR');
          removeLeadsTokenFromHeader();
          await setStorageToken('authToken', '');
          restartApp();
        } else {
          showAlert(data?.data?.message, 'SUCCESS');
          removeLeadsTokenFromHeader();
          await setStorageToken('authToken', '');
          restartApp();
        }
      }
    };
    handleData();
  }, [data]);

  const openDeleteAccount = async (url: string) => {
    try {
      if (await InAppBrowser.isAvailable()) {
        await InAppBrowser.open(url, {
          toolbarColor: colors.primary,
          secondaryToolbarColor: colors.secondary,
          enableUrlBarHiding: true,
          showTitle: true,
          enableDefaultShare: true,
          forceCloseOnRedirection: false,
        });
      } else {
        // Fallback: open the URL using the default browser
        Linking.openURL(url);
      }
    } catch (error) {
      console.error('Error opening browser:', error);
    }
  };

  const renderData = ({item}: any) => {
    const handlePress = () => {
      switch (item?.name) {
        case 'Logout':
          console.log('logout');
          return ref.current?.show();
        case 'Your Network':
          return navigate('Referral');
        case 'Top Users':
          return navigate('LeaderBoard');
        case 'My Referral Code':
          return navigate('Referral');
        case 'WhitePaper':
          Linking.openURL(
            'https://medium.com/@blockrium/we-are-excited-to-announce-the-official-release-of-blockriums-whitepaper-outlining-our-vision-for-ba2e5941aff4',
          );
          return;
        case 'Delete Account':
          openDeleteAccount('https://blockrium.com/delete.html');
          return;
      }
    };

    return (
      <PrimaryCard>
        <Pressable onPress={handlePress}>
          <HStack
            my={hp(0.5)}
            alignItems="center"
            justifyContent="space-between">
            <HStack alignItems="center">
              <MaterialCommunityIcons
                style={{marginRight: wp(3)}}
                name={item?.icon}
                size={25}
                color={colors.white}
              />
              <BoldText
                text={item?.name}
                style={{
                  color: colors.white,
                }}
              />
            </HStack>
            <MaterialCommunityIcons
              name="chevron-right"
              size={25}
              color={colors.white}
            />
          </HStack>
        </Pressable>
      </PrimaryCard>
    );
  };

  const onPress = (button: any) => {
    if (button.isCancel) {
      ref.current?.hide();
    } else {
      mutate();
      ref.current?.hide();
    }
  }

  return (
    <>
      <FlatList data={ProfileCardData} renderItem={renderData} />
      <Box style={{height: hp(5)}} />
      <ActionSheet
        gestureEnabled={true}
        indicatorStyle={{
          width: 100,
        }}>
        <Box
          w="100%"
          h={60}
          px={4}
          justifyContent="center"
          backgroundColor={
            defaultTheme === 'dark' ? colors.black : colors.white
          }>
          <Text
            fontSize={RFValue(16)}
            color={colors.primary}
            fontWeight={'bold'}
            textAlign={'center'}>
            {'Are you sure you want to logout?'}
          </Text>
        </Box>
        {buttons.map(button => (
          <Pressable style={{flexDirection: 'row', padding: hp(2), alignItems: 'center'}} onPress={() => onPress(button)} key={button.label}>
            <MaterialIcons
              color={colors.primary}
              name={button.icon}
              size={30}
            />
            <Text style={{ color: colors.primary, fontWeight: "bold", fontSize: RFValue(16), paddingLeft: wp(2) }}>{button.label}</Text>
          </Pressable>
        ))}
      </ActionSheet>
    </>
  );
};

export default ProfileComponent;
