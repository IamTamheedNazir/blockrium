import React, {useEffect} from 'react';
import {StyleSheet, Text, View} from 'react-native';
import FastImage from 'react-native-fast-image';
import {images} from '../../../assets/images';
import {AppScreen} from '../../design/primitives';
import theme from '../../design/theme';
import {navigate} from '../../utils/helper';

const Splash = () => {
  useEffect(() => {
    const timeout = setTimeout(() => {
      navigate('AuthStack');
    }, 1200);

    return () => clearTimeout(timeout);
  }, []);

  return (
    <AppScreen contentContainerStyle={styles.contentContainer}>
      <View style={styles.centered}>
        <FastImage source={images.logo} style={styles.logo} resizeMode="contain" />
        <Text style={styles.title}>Blockrium</Text>
        <Text style={styles.subtitle}>
          Professional mining, wallet visibility, and community flow in one modern shell.
        </Text>
      </View>
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  centered: {
    alignItems: 'center',
    paddingHorizontal: 24,
  },
  logo: {
    width: 150,
    height: 150,
    marginBottom: 18,
  },
  title: {
    color: theme.colors.text,
    fontSize: 44,
    lineHeight: 46,
    fontFamily: theme.typography.display,
  },
  subtitle: {
    marginTop: 12,
    color: theme.colors.textMuted,
    fontSize: 15,
    lineHeight: 21,
    textAlign: 'center',
    fontFamily: theme.typography.body,
  },
});

export default Splash;
