import {
  Animated,
  Easing,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import React, {useEffect, useRef} from 'react';
import colors from '../utils/colors';
import {hp, wp} from '../utils/styleConstants';
import NormalText from './NormalText';
import { RFValue } from 'react-native-responsive-fontsize';

const SecondaryButton: React.FC<SecondaryButtonProps> = ({props, onPress, text}) => {
  const scaleAnim = useRef(new Animated.Value(1)).current;

  useEffect(() => {
    Animated.loop(
      Animated.sequence([
        Animated.timing(scaleAnim, {
          toValue: 1.2,
          useNativeDriver: true,
          easing: Easing.ease,
          duration: 1000,
        }),
        Animated.timing(scaleAnim, {
          toValue: 0.8,
          useNativeDriver: true,
          easing: Easing.ease,
          duration: 1000,
        }),
      ]),
    ).start();
  }, [scaleAnim]);

  return (
    <Animated.View style={{transform: [{scale: scaleAnim}]}}>
      <TouchableOpacity
        {...props}
        style={{
          backgroundColor: colors.primary,
          borderRadius: hp(3),
          justifyContent: 'center',
          alignItems: 'center',
          padding: hp(1),
        }}
        onPress={onPress}>
        <NormalText
          text={text}
          style={{color: colors.white, fontSize: RFValue(12)}}
        />
      </TouchableOpacity>
    </Animated.View>
  );
};

export default SecondaryButton;

const styles = StyleSheet.create({});
