import React, {useEffect} from 'react';
import {hp, wp} from '../utils/styleConstants';
import {Box, HStack, Stack, VStack} from '../ui/nativeBaseCompat';
import colors from '../utils/colors';
import HeaderText from '../atoms/HeaderText';
import {RFValue} from 'react-native-responsive-fontsize';
import NormalText from '../atoms/NormalText';
import AnimatedBorderButton from '../atoms/AnimatedBorderButton';
import Divider from '../atoms/Divider';
import {ImageBackground} from 'react-native';
import {callMutation} from '../hooks';
import {useThisAlert} from '../hooks/AlertContext';
import {images} from '../../assets/images';
import fonts from '../../assets/fonts';
import { getApi } from '../storage/server';
import {showOptionalActionAd} from '../services/adService';

const MiningCard = ({amount, time, isButtonDisabled}: any) => {
  const [loaded, setLoaded] = React.useState(isButtonDisabled);
  const {data, mutate}: any = callMutation('auth/click-button', {});

  const {showAlert} = useThisAlert();

  const onPress = async () => {
    try {
      await showOptionalActionAd(18000);
      setLoaded(true);
      await triggerMutate();
    } catch (error) {
      console.log('Error starting mining:', error);
      showAlert('Mining could not be started right now.', 'ERROR');
    }
  };

  const triggerMutate = async () => {
    await mutate();
  };

  useEffect(() => {
    const fetchMiningData = async () => {
      try {
        if (data && (data?.message_title == "success" || data?.success)) {
          showAlert(data?.data?.message, 'SUCCESS');
          const response = await getApi('auth/get_mining');
          console.log('Mining data:', response);
        } else {
          console.log('Data:', data);
        }
      } catch (error) {
        console.error('Error fetching mining data:', error);
      }
    };
  
    fetchMiningData();
  }, [data]);
  

  return (
    <ImageBackground
      source={images.startMiningBG}
      style={{
        marginHorizontal: wp(2),
        padding: hp(2),
        borderRadius: hp(3),
        overflow: 'hidden', 
      }}>
      <Box flexDirection={'row'}>
        <Stack style={{paddingVertical: hp(2), alignItems: 'center'}}>
          <HStack style={{alignItems: 'center'}}>
            <HeaderText
              text={amount}
              style={{color: colors.white, fontSize: RFValue(20), right: 10}}
            />
            <HeaderText
              text={'BRC'}
              style={{
                marginLeft: wp(1.2),
                alignSelf: 'flex-end',
                fontSize: RFValue(20),
                right: 10
              }}
            />
          </HStack>
          <NormalText text="Reward is Ready" style={{color: colors.white}} />
        </Stack>
        <Stack
          style={{
            position: 'absolute',
            right: wp(7),
            top: hp(0.5),
            zIndex: 1,
          }}>
            <AnimatedBorderButton
              onPress={onPress}
              isButtonDisabled={!loaded}
              text="Start Mining"
            />
        </Stack>
      </Box>
      <Divider />
      <VStack
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          justifyContent: 'center',
        }}>
        <HeaderText
          style={{
            fontSize: RFValue(16),
            alignSelf: 'center',
            fontFamily: fonts.jaro_regular,
          }}
          text="Your basic harvest rate is"
        />
        <HeaderText
          style={{
            fontSize: RFValue(16),
            alignSelf: 'center',
            fontFamily: fonts.jaro_regular,
            marginLeft: wp(1),
          }}
          text="1 BRC"
        />
      </VStack>
    </ImageBackground>
  );
};

export default MiningCard;
