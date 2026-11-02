import React, {useEffect, useRef} from 'react';
import {View, Text, StyleSheet, Animated} from 'react-native';
import colors from '../utils/colors';
import LinearGradient from 'react-native-linear-gradient';

interface ProgressBarProps {
  current: number;
  total: number;
}

const ProgressBar: React.FC<ProgressBarProps> = ({current, total}) => {
  const progress = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    const ratio = total === 0 ? 0 : current / total;

    Animated.timing(progress, {
      toValue: ratio,
      duration: 500,
      useNativeDriver: false,
    }).start();
  }, [current, total]);

  const widthInterpolated = progress.interpolate({
    inputRange: [0, 1],
    outputRange: ['0%', '100%'],
  });

  return (
    <View style={styles.container}>
      <View style={styles.track}>
        <Animated.View style={[styles.fill, {width: widthInterpolated}]}>
          <LinearGradient
            colors={['#07D6C8', '#2F83E0']}
            start={{x: 0, y: 4.5}}
            end={{x: 0.6, y: 4.5}}
            style={[StyleSheet.absoluteFill, {borderRadius: 50}]}
          />
        </Animated.View>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    height: 30,
    width: '100%',
    alignSelf: 'center',
    borderRadius: 50,
    marginVertical: 20,
  },
  track: {
    height: '100%',
    backgroundColor: colors.lightBlack, 
    borderRadius: 50,
    overflow: 'hidden',
  },
  fill: {
    height: '100%',
    backgroundColor: colors.primary,
    borderRadius: 50,
    position: 'absolute',
    left: 0,
    top: 0,
  },
  labelContainer: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
    pointerEvents: 'none', // allows taps through if needed
  },
  labelText: {
    color: 'white',
    fontWeight: 'bold',
    fontSize: 13,
  },
});

export default ProgressBar;
