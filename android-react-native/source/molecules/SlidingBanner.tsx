import React, { useEffect, useRef } from 'react';
import { Animated, Easing, Pressable, Dimensions } from 'react-native';
import FastImage from 'react-native-fast-image';
import { images } from '../../assets/images';

const { height, width } = Dimensions.get('window');

const SlidingBanner = ({img}: {img: string}) => {
  const slideYAnim = useRef(new Animated.Value(-height / 3)).current; 
  const fadeAnim = useRef(new Animated.Value(0)).current; 
  const slideXAnim = useRef(new Animated.Value(0)).current; 

  useEffect(() => {
    Animated.sequence([
      // Step 1: Fade In
      Animated.timing(fadeAnim, {
        toValue: 1,
        duration: 400,
        easing: Easing.out(Easing.ease),
        useNativeDriver: true,
      }),
      // Step 2: Slide Down Once
      Animated.timing(slideYAnim, {
        toValue: 0,
        duration: 600,
        easing: Easing.out(Easing.ease),
        useNativeDriver: true,
      }),
    ]).start(() => {
      // Step 3: Start Horizontal Sliding Loop
      Animated.loop(
        Animated.sequence([
          Animated.timing(slideXAnim, {
            toValue: 10, // Slide slightly to the right
            duration: 800,
            easing: Easing.inOut(Easing.ease),
            useNativeDriver: true,
          }),
          Animated.timing(slideXAnim, {
            toValue: -10, // Slide slightly to the left
            duration: 800,
            easing: Easing.inOut(Easing.ease),
            useNativeDriver: true,
          }),
          Animated.timing(slideXAnim, {
            toValue: 0, // Return to center
            duration: 800,
            easing: Easing.inOut(Easing.ease),
            useNativeDriver: true,
          }),
        ])
      ).start();
    });
  }, []);

  return (
    <Animated.View
      style={{
        opacity: fadeAnim,
        transform: [{ translateY: slideYAnim }, { translateX: slideXAnim }],
      }}>
        <FastImage
          source={{ uri: img }}
          style={{
            height: height / 6,
            width: width - 40,
            alignSelf: 'center',
            borderRadius: 10,
          }}
          resizeMode='contain'
        />
    </Animated.View>
  );
};

export default SlidingBanner;
