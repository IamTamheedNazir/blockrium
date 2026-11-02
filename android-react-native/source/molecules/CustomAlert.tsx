import React, { useEffect, useCallback, useMemo, useRef } from 'react';
import { StyleSheet, TouchableOpacity, Animated, Easing, Image } from 'react-native';
import {useSafeAreaInsets} from 'react-native-safe-area-context';
import colors from '../utils/colors';
import {Box, Text} from '../ui/nativeBaseCompat';
import { images } from '../../assets/images';
import { useThisAlert } from '../hooks/AlertContext';
import fonts from '../../assets/fonts';

const HIDDEN_OFFSET = -150;
const BEZIER = Easing.bezier(0.5, 1, 0.89, 1);

export default function CustomAlert() {
  const top = useRef(new Animated.Value(HIDDEN_OFFSET)).current;
  const insets = useSafeAreaInsets();
  const { show, title, mode, hideAlert } = useThisAlert();

  const closeAnimate = useCallback(() => {
    Animated.timing(top, {
      toValue: HIDDEN_OFFSET,
      duration: 260,
      easing: BEZIER,
      useNativeDriver: false,
    }).start(() => {
      hideAlert();
    });
  }, [top, hideAlert]);

  const startClosing = useCallback(() => {
    const timer = setTimeout(() => {
      closeAnimate();
    }, 5000);
    return () => clearTimeout(timer);
  }, [closeAnimate]);

  const showAlert = useCallback(() => {
    Animated.timing(top, {
      toValue: Math.max(8, insets.top + 8),
      duration: 280,
      easing: BEZIER,
      useNativeDriver: false,
    }).start();
    startClosing();
  }, [top, insets.top, startClosing]);


  useEffect(() => {
    if (show) {
      showAlert();
    }
  }, [show, showAlert]);

  // Define snackOptions using useMemo
  const snackOptions = useMemo(() => {
    switch (mode) {
      case 'SUCCESS':
        return { textColor: colors.white, bgColor: '#0E8F72', borderColor: 'rgba(45, 245, 205, 0.45)' };
      case 'ERROR':
        return { textColor: colors.white, bgColor: '#7F1D2D', borderColor: 'rgba(255, 125, 148, 0.55)' };
      case 'INFO':
        return { textColor: colors.white, bgColor: '#0C2E43', borderColor: 'rgba(45, 245, 205, 0.45)' };
      case 'DEFAULT':
        return { textColor: colors.white, bgColor: 'rgba(8, 18, 30, 0.96)', borderColor: 'rgba(255, 255, 255, 0.12)' };
      default:
        return {};
    }
  }, [mode]);

  const animatedStyles = { top }; // Replace useAnimatedStyle with plain Animated.Value binding

  return !show ? null : (
    <Animated.View
      style={[
        styles.container,
        animatedStyles,
        { backgroundColor: snackOptions.bgColor, borderColor: snackOptions.borderColor },
      ]}
    >
      <TouchableOpacity style={styles.touchable} onPress={closeAnimate}>
        <Box style={styles.iconWrap}>
          <Image source={images.logo} style={styles.logo} resizeMode="contain" />
        </Box>
        <Box style={styles.copy}>
          <Text fontSize={11} fontFamily={fonts.jaro_regular} style={[styles.mode, { color: snackOptions.textColor }]}>
            {mode === 'DEFAULT' ? 'NOTICE' : mode}
          </Text>
          <Text fontSize={14} fontFamily={fonts.jaro_regular} style={[styles.title, { color: snackOptions.textColor }]} numberOfLines={3}>
            {String(title)}
          </Text>
        </Box>
      </TouchableOpacity>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  container: {
    position: 'absolute',
    left: '4%',
    width: '92%',
    minHeight: 62,
    borderRadius: 18,
    backgroundColor: '#333',
    borderWidth: 1,
    zIndex: 999,
    elevation: 18,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 10},
    shadowOpacity: 0.25,
    shadowRadius: 18,
  },
  touchable: {
    width: '100%',
    minHeight: 62,
    flexDirection: 'row',
    alignSelf: 'center',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  iconWrap: {
    width: 38,
    height: 38,
    borderRadius: 13,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(45, 245, 205, 0.1)',
    marginRight: 12,
  },
  logo: {
    width: 28,
    height: 28,
  },
  copy: {
    flex: 1,
    minWidth: 0,
  },
  mode: {
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    opacity: 0.76,
    marginBottom: 2,
  },
  title: {
    color: '#FFF',
    flexWrap: 'wrap',
    textAlignVertical: 'center',
    lineHeight: 18,
  },
});
