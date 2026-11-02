import {Animated, StyleSheet, View} from 'react-native';
import React, {useRef, useEffect} from 'react';
import {Box} from '../ui/nativeBaseCompat';
import { hp} from '../utils/styleConstants';
import colors from '../utils/colors';
import FastImage from 'react-native-fast-image';
import BoldText from '../atoms/BoldText';
import SecondaryButton from '../atoms/SecondaryButton';
import {RoundedBannerProps} from '../types/RoundedBannerProps';

const RoundedBanner: React.FC<RoundedBannerProps> = ({
  icon,
  onPress,
  text,
  buttonTitle,
}) => {
  const translateYAnim = useRef(new Animated.Value(0)).current;
  const opacityAnim = useRef(new Animated.Value(1)).current;
    const defaultTheme: 'dark' | 'light' = 'dark';
  

  useEffect(() => {

    const startAnimation = () => {
      Animated.loop(
        Animated.parallel([
          Animated.sequence([
            Animated.timing(translateYAnim, {
              toValue: -10,
              duration: 800,
              useNativeDriver: true,
            }),
            Animated.timing(translateYAnim, {
              toValue: 0,
              duration: 500,
              useNativeDriver: true,
            }),
          ]),
          Animated.sequence([
            Animated.timing(opacityAnim, {
              toValue: 0.8,
              duration: 500,
              useNativeDriver: true,
            }),
            Animated.timing(opacityAnim, {
              toValue: 1,
              duration: 300,
              useNativeDriver: true,
            }),
          ]),
        ]),
      ).start();
    };

    startAnimation();
  }, [translateYAnim, opacityAnim]);

  return (
    <Box
      style={{
        width: '95%',
        height: hp(8),
        borderRadius: hp(20),
        backgroundColor: defaultTheme == 'dark' ? colors.white : colors.lightBlack,
        marginVertical: hp(1),
        alignSelf: 'center',
        alignItems: 'center',
        flexDirection: 'row',
        justifyContent: 'space-around',
      }}>
      <Animated.View
        style={{
          transform: [{translateY: translateYAnim}],
          opacity: opacityAnim,
        }}>
        <FastImage
          source={icon}
          style={{width: 30, height: 30}}
          resizeMode="contain"
        />
      </Animated.View>
      <BoldText text={text} style={{color: defaultTheme == 'dark' ? colors.black : colors.white}}/>
      <SecondaryButton onPress={onPress} text={buttonTitle} />
    </Box>
  );
};

export default RoundedBanner;

const styles = StyleSheet.create({});
