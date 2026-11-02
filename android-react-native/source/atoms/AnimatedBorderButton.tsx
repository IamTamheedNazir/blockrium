import React, {useEffect, useRef} from 'react';
import {Animated, View, StyleSheet, TouchableOpacity, Text} from 'react-native';
import BoldText from './BoldText';
import colors from '../utils/colors';
import {RFValue} from 'react-native-responsive-fontsize';

const AnimatedBorderButton = ({text, isButtonDisabled, onPress}: {text: string, isButtonDisabled: boolean, onPress?: () => void | Promise<void>}) => {
  const animatedBorderWidth = useRef(new Animated.Value(1)).current;
  const animatedGradient = useRef(new Animated.Value(0)).current;

  const handlePress = () => {
    if (!onPress) {
      return;
    }

    try {
      const result = onPress();

      if (result && typeof (result as Promise<void>).catch === 'function') {
        (result as Promise<void>).catch(error => {
          console.error('Animated button action failed', error);
        });
      }
    } catch (error) {
      console.error('Animated button action failed', error);
    }
  };

  useEffect(() => {
    Animated.loop(
      Animated.sequence([
        Animated.timing(animatedBorderWidth, {
          toValue: 10,
          duration: 1000,
          useNativeDriver: false,
        }),
        Animated.timing(animatedBorderWidth, {
          toValue: 1,
          duration: 1000,
          useNativeDriver: false,
        }),
      ]),
    ).start();
  }, [animatedBorderWidth]);

  useEffect(() => {
    Animated.loop(
      Animated.timing(animatedGradient, {
        toValue: 1,
        duration: 3000,
        useNativeDriver: false,
      }),
    ).start();
  }, [animatedGradient]);

  // Interpolate gradient colors
  const gradientColors = animatedGradient.interpolate({
    inputRange: [0, 0.5, 1],
    outputRange: ['#D080D0', '#ED207B', '#F68069'],
  });

  return (
    <View style={styles.container}>
        <Animated.View
        style={[
          styles.animatedBorder,
          {
            borderWidth: animatedBorderWidth,
            borderColor: gradientColors,
          },
        ]}>
        <TouchableOpacity onPress={handlePress} disabled={isButtonDisabled} style={styles.button}>
          <BoldText style={styles.buttonText} text={text} />
        </TouchableOpacity>
      </Animated.View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    top: 10
  },
  animatedBorder: {
    borderRadius: 50,
    padding: 3
  },
  button: {
    backgroundColor: 'white',
    paddingVertical: 5,
    paddingHorizontal: 5,
    borderRadius: 30,
  },
  buttonText: {
    fontSize: RFValue(1),
    padding: 5,
    color: colors.primary,
  },
});

export default AnimatedBorderButton;
