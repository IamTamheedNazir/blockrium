import {Keyboard, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import React, { useEffect } from 'react';
import {Box, Button as NativeBaseButton} from '../ui/nativeBaseCompat';
import {hp, wp} from '../utils/styleConstants';
import LinearGradient from 'react-native-linear-gradient';
import colors from '../utils/colors';
import fonts from '../../assets/fonts';
import { RFValue } from 'react-native-responsive-fontsize';


const NewButton: React.FC<newButtonProps> = ({text, onPress, styles, gradient = ['#06D5C7', '#337CE1']}) => {

  const handlePress = () => {
    Keyboard.dismiss();
    if (!onPress) {
      return;
    }

    try {
      const result: any = onPress();

      if (result && typeof result.catch === 'function') {
        result.catch((error: unknown) => {
          console.error('Button action failed', error);
        });
      }
    } catch (error) {
      console.error('Button action failed', error);
    }
  };
  return (
    <TouchableOpacity
      onPress={() => handlePress()}
      style={{alignItems: 'center', marginVertical: hp(2)}}>
      <LinearGradient
        start={{x: 0, y: 0.5}}
        end={{x: 1, y: 0.4}}
        colors={gradient}
        style={{
          borderRadius: hp(2),
          height: hp(6),
          width: wp(50),
          elevation: 3,
          shadowColor: 'transparent',
          marginVertical: hp(1),
          paddingHorizontal: wp(3),
          justifyContent: 'center',
          alignItems: 'center',
          ...styles
        }}>
        <Text
          style={{
            color: colors.white,
            fontSize: RFValue(16),
            fontFamily: fonts['jaro_regular'],
          }}>
          {text}
        </Text>
      </LinearGradient>
    </TouchableOpacity>
  );
};

export default NewButton;
