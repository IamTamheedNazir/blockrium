import React, {useEffect, useRef} from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  Animated,
  StyleSheet,
  Dimensions,
  Linking,
} from 'react-native';
import FastImage from 'react-native-fast-image';
import {RFValue} from 'react-native-responsive-fontsize';
import {images} from '../../assets/images';
import fonts from '../../assets/fonts';
import theme from '../design/theme';

const {width, height} = Dimensions.get('screen');

const RemotePopUp = ({visible}: any) => {
  const scaleAnim = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    if (visible) {
      Animated.spring(scaleAnim, {
        toValue: 1,
        useNativeDriver: true,
      }).start();
    }
  }, [scaleAnim, visible]);

  const onPress = async () => {
    Linking.openURL("https://play.google.com/store/apps/details?id=com.blockrium&hl=en_IN")
  };

  return (
    <>
      {visible && (
        <View style={styles.overlay}>
          <Animated.View
            style={[styles.modal, {transform: [{scale: scaleAnim}]}]}>
            <FastImage
              source={images.logo}
              style={{width: width / 2.5, height: height / 5}}
              resizeMode="contain"
            />
            <Text style={styles.modalTitle}>A newer Blockrium build is available.</Text>
            <Text style={styles.modalContent}>
              Update from Google Play to get the latest design, performance, and monetization fixes.
            </Text>
            <TouchableOpacity style={styles.claimButton} onPress={onPress}>
              <Text style={styles.buttonText}>Update Now</Text>
            </TouchableOpacity>
          </Animated.View>
        </View>
      )}
    </>
  );
};

const styles = StyleSheet.create({
  buttonText: {
    color: '#fff',
    fontSize: RFValue(16),
    fontFamily: fonts.jaro_regular,
  },
  overlay: {
    width: '100%',
    height: '100%',
    backgroundColor: 'rgba(0, 0, 0, 0.85)',
    alignItems: 'center',
    zIndex: 10,
    ...StyleSheet.absoluteFillObject,
  },
  modal: {
    width: width * 0.86,
    padding: 16,
    backgroundColor: theme.colors.surface,
    borderRadius: 20,
    alignItems: 'center',
    elevation: 5,
    top: height * 0.2,
    maxHeight: height * 0.8,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  modalTitle: {
    fontSize: RFValue(18),
    fontFamily: fonts.heading,
    color: theme.colors.text,
    textAlign: 'center',
    marginVertical: 5,
  },
  modalContent: {
    fontSize: RFValue(13),
    fontFamily: fonts.body,
    color: theme.colors.textMuted,
    textAlign: 'center',
    marginTop: 6,
  },
  claimButton: {
    width: '60%',
    height: 45,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: theme.colors.primary,
    borderRadius: 16,
    marginTop: 18,
  },
});

export default RemotePopUp;
