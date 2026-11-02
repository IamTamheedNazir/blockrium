import React, {useCallback, useEffect, useMemo, useState} from 'react';
import {MainContainer, navigate} from '../../utils/helper';
import colors from '../../utils/colors';
import HeaderText from '../../atoms/HeaderText';
import {RFValue} from 'react-native-responsive-fontsize';
import FastImage from 'react-native-fast-image';
import {images} from '../../../assets/images';
import {ImageBackground} from 'react-native';
import {
  Box,
  Divider,
  HStack,
  ScrollView,
  Stack,
  View,
  VStack,
} from '../../ui/nativeBaseCompat';
import {hp, wp} from '../../utils/styleConstants';
import BalanceBanner from '../../molecules/BalanceBanner';
import MiningCard from '../../molecules/MiningCard';
import PrimaryCard from '../../atoms/PrimaryCard';
import RewardsList from '../../molecules/RewardsList';
import NormalText from '../../atoms/NormalText';
import {callMutation, callQuery} from '../../hooks';
import MiningAreaSkeleton from '../../pages/MiningFlow/MiningAreaSkeleton';
import Header from '../../molecules/Header';
import {useThisAlert} from '../../hooks/AlertContext';
import {getApi} from '../../storage/server';
import AnimatedBorderButton from '../../atoms/AnimatedBorderButton';
import fonts from '../../../assets/fonts';
import {showOptionalActionAd} from '../../services/adService';

const MiningOrg = () => {
  const {data, isLoading} = callQuery('auth/get_mining');
  const [miningDetails, setMiningDetails] = useState(null);
  const [timeLeft, setTimeLeft] = useState(0); // Time left in seconds
  const [isButtonEnabled, setIsButtonEnabled] = useState(false);
  const {data: newData} = callQuery('auth/wallet');
  const {data: clickData, mutate}: any = callMutation('auth/click-button', {});

  const {showAlert} = useThisAlert();

  useEffect(() => {
    mutate()
  },[])

  useEffect(() => {
    try {
      if (!clickData?.data?.nextMining) {
        console.error("nextMining is undefined or missing:", clickData?.data);
        return;
      }
  
      // Convert "2025-03-23 13:55:38" into timestamp
      const startTime = new Date(clickData.data.nextMining.replace(" ", "T")).getTime();
  
      if (isNaN(startTime)) {
        console.error("Failed to parse start time:", clickData.data.nextMining);
        return;
      }
  
      console.log("Parsed start time:", new Date(startTime).toLocaleString());
  
      const currentTime = Date.now();
      const remainingTime = startTime - currentTime; // Just subtract nextMining from now
  
      if (remainingTime <= 0) {
        setIsButtonEnabled(true);
        setTimeLeft(0);
        console.log("✅ Button enabled");
      } else {
        setIsButtonEnabled(false);
        setTimeLeft(Math.ceil(remainingTime / 1000)); // Convert ms to seconds
        console.log("⏳ Remaining time (seconds):", Math.ceil(remainingTime / 1000));
      }
    } catch (error) {
      console.error("❌ Error processing mining time:", error);
    }
  }, [clickData, mutate]);
  
  const formatTime = (seconds: number) => {
    const hrs = Math.floor(seconds / 3600).toString().padStart(2, '0');
    const mins = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
    const secs = (seconds % 60).toString().padStart(2, '0');
    return `${hrs}:${mins}:${secs}`;
  };
    
  console.log("isButtonEnabled>>>",isButtonEnabled)
  console.log("formated time left", formatTime(timeLeft))
  
  useEffect(() => {
    if (!timeLeft || timeLeft <= 0) return;
  
    const intervalId = setInterval(() => {
      setTimeLeft((prevTime) => {
        if (prevTime <= 1) {
          clearInterval(intervalId);
          setIsButtonEnabled(true);
          return 0;
        }
        return prevTime - 1;
      });
    }, 1000);
  
    return () => clearInterval(intervalId);
  }, [timeLeft]);
  


  useEffect(() => {
    if (data) {
      if (!data?.success) {
        console.log('Error>>>>');
      } else {
        setMiningDetails(data?.data?.Mining_Details);
      }
    }
  }, [data?.success]);

  if (isLoading) {
    return <MiningAreaSkeleton />;
  }

  const onPress = async () => {
    try {
      await showOptionalActionAd(18000);
      await triggerMutate();
      setIsButtonEnabled(false);
      navigate('HomeFlow');
    } catch (error) {
      console.log('Error starting mining:', error);
      showAlert('Mining could not be started right now.', 'ERROR');
    }
  };

  const triggerMutate = async () => {
    await mutate();
    await fetchMiningData()
  };

  const fetchMiningData = async () => {
    try {
      if (
        clickData && clickData?.message_title == 'success'
      ) {
        showAlert(clickData?.data?.message, 'SUCCESS');
        const response = await getApi('auth/get_mining');
        console.log('Mining data:', response);
      } else {
        console.log('Data:', clickData);
      }
    } catch (error) {
      console.error('Error fetching mining data:', error);
    }
  };


  return (
    <ImageBackground source={images.miningMainBg} style={{flex: 1, marginTop: hp(5)}}>
      <Header textColor={colors.white} name={'arrow-back'} text="Mining Area" />
      <VStack mx={wp(1)} flex={1} justifyContent={'flex-end'}>
        <Box my={hp(2)}>
          <BalanceBanner
            text="Total Balance"
            balance={newData?.data?.wallet_balance}
            status="Verified"
          />
        </Box>
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
                  text={'06:00'}
                  style={{
                    color: colors.white,
                    fontSize: RFValue(20),
                    right: 10,
                  }}
                />
                <HeaderText
                  text={'BRC'}
                  style={{
                    marginLeft: wp(1.2),
                    alignSelf: 'flex-end',
                    fontSize: RFValue(20),
                    right: 10,
                  }}
                />
              </HStack>
              <NormalText
                text="Reward is Ready"
                style={{color: colors.white}}
              />
            </Stack>
            <Stack
              style={{
                position: 'absolute',
                right: wp(7),
                top: hp(0.5),
                zIndex: 1,
              }}>
              {isButtonEnabled ? (
                <AnimatedBorderButton
                  onPress={onPress}
                  isButtonDisabled={!isButtonEnabled}
                  text="Start Mining"
                />
              ) : (
                <View style={{flex: 1}}>
                  <NormalText
                    style={{
                      fontSize: RFValue(14),
                      flexWrap: 'wrap',
                      width: wp(30),
                      textAlign: 'center',
                      top: 10,
                    }}
                    numberOfLines={3}
                    text={`Time Left until next Mining ${formatTime(timeLeft)}`}
                  />
                </View>
              )}
            </Stack>
          </Box>
          {/* <Divider /> */}
          <VStack
            style={{
              flexDirection: 'row',
              alignItems: 'center',
              justifyContent: 'center',
              margin: hp(1),
            }}>
            <HeaderText
              style={{
                fontSize: RFValue(14),
                alignSelf: 'center',
                fontFamily: fonts.jaro_regular,
              }}
              text="Your basic harvest rate is"
            />
            <HeaderText
              style={{
                fontSize: RFValue(14),
                alignSelf: 'center',
                fontFamily: fonts.jaro_regular,
                marginLeft: wp(1),
              }}
              text="1 BRC"
            />
          </VStack>
        </ImageBackground>
        {/* <MiningCard
          isButtonDisabled={!isButtonEnabled}
          time={formatTime(timeLeft)}
          amount="06:00"
        /> */}
        <PrimaryCard>
          <Box style={{alignItems: 'center', flexDirection: 'row'}}>
            <FastImage
              source={images.rocket}
              style={{width: wp(25), height: hp(10), alignSelf: 'center'}}
              resizeMode="cover"
            />
            <FastImage
              source={images.comingSoon}
              style={{
                width: wp(15),
                height: hp(15),
                alignSelf: 'center',
                position: 'absolute',
                right: 0,
                zIndex: 30,
              }}
              resizeMode="contain"
            />
            <HeaderText
              style={{
                flex: 1,
                flexWrap: 'wrap',
                fontSize: RFValue(16),
                textAlign: 'center',
                fontFamily: 'MontserratAlternates-Bold',
                color: colors.white,
              }}
              text="Get an extra 300% booster"
            />
          </Box>
        </PrimaryCard>
        <Stack mx={wp(3)}>
          <NormalText
            text="Daily Rewards"
            style={{
              color: colors.white,
              margin: 10,
              fontSize: RFValue(16),
            }}
          />
          <RewardsList />
        </Stack>
      </VStack>
      <Box style={{marginBottom: hp(2)}} />
    </ImageBackground>
  );
};

export default MiningOrg;
