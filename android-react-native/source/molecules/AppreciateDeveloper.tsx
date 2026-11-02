import {Animated, Dimensions, StyleSheet, View} from 'react-native';
import React, {useRef, useEffect} from 'react';
import {heightPercentageToDP as hp} from 'react-native-responsive-screen';
import colors from '../utils/colors';
import FastImage from 'react-native-fast-image';
import LinearGradient from 'react-native-linear-gradient';
import {RFValue} from 'react-native-responsive-fontsize';
import fonts from '../../assets/fonts';
import NewButton from '../atoms/NewButton';
import BoldText from '../atoms/BoldText';
import {RoundedBannerProps} from '../types/RoundedBannerProps';

const AppreciateDeveloper: React.FC<RoundedBannerProps> = ({
  icon,
  onPress,
buttonTitle = 'Watch & Support',
}) => {
  const iconScaleAnim = useRef(new Animated.Value(1)).current;

  useEffect(() => {
    Animated.loop(
      Animated.sequence([
        Animated.timing(iconScaleAnim, {
          toValue: 1.1,
          duration: 600,
          useNativeDriver: true,
        }),
        Animated.timing(iconScaleAnim, {
          toValue: 1,
          duration: 600,
          useNativeDriver: true,
        }),
      ]),
    ).start();
  }, [iconScaleAnim]);

  return (
    <LinearGradient colors={['#192841', '#0F1D33']} style={styles.card}>
      <Animated.View
        style={[styles.iconBadge, {transform: [{scale: iconScaleAnim}]}]}>
        <LinearGradient
          colors={['#337CE1', '#06D5C7']}
          style={styles.iconCircle}>
          <FastImage source={icon} style={styles.icon} resizeMode="contain" />
        </LinearGradient>
      </Animated.View>
      {/* <BoldText
        text={text}
        style={styles.message}
      /> */}

      <BoldText text="Support the Builders!" style={styles.title} />

      <BoldText text="Every view fuels innovation." style={styles.subtitle} />

      <BoldText
        text="Appreciate our hardworking developers by watching a short ad. Your support keeps the experience awesome and ad-free!"
        style={styles.description}
      />

      <NewButton text={buttonTitle} onPress={onPress} styles={styles.button} />
    </LinearGradient>
  );
};

export default AppreciateDeveloper;

const styles: any = StyleSheet.create({
  card: {
    width: '90%',
    borderRadius: hp(2),
    padding: hp(2),
    alignSelf: 'center',
    marginVertical: hp(2),
    backgroundColor: 'rgba(255, 255, 255, 0.05)',
    shadowColor: '#06D5C7',
    shadowOffset: {width: 0, height: 4},
    shadowOpacity: 0.4,
    shadowRadius: 10,
    elevation: 6,
  },
  title: {
  fontFamily: fonts.jaro_regular,
  fontSize: RFValue(16),
  color: colors.white,
  textAlign: 'center',
  marginTop: hp(0.5),
},

subtitle: {
  fontFamily: fonts.jaro_regular,
  fontSize: RFValue(13),
  color: '#A0D7E7',
  textAlign: 'center',
  marginTop: hp(0.5),
},

description: {
  fontFamily: fonts.jaro_regular,
  fontSize: RFValue(12),
  color: '#C3C3C3',
  textAlign: 'center',
  marginVertical: hp(1),
  paddingHorizontal: hp(2),
},
  iconBadge: {
    alignItems: 'center',
    marginBottom: hp(1.5),
  },
  iconCircle: {
    width: hp(8),
    height: hp(8),
    borderRadius: hp(6),
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#FFA500',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.6,
    shadowRadius: 5,
  },
  icon: {
    width: '80%',
    height: '80%',
  },
  message: {
    fontFamily: fonts.jaro_regular,
    textAlign: 'center',
    fontSize: RFValue(13),
    marginTop: hp(1),
    color: colors.white,
  },
  button: {
    alignSelf: 'center',
    paddingHorizontal: hp(2.5),
    borderRadius: hp(1.5),
  },
});
