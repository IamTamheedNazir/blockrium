import React from 'react';
import {Linking, Pressable, StyleProp, StyleSheet, Text, View, ViewStyle} from 'react-native';
import FastImage from 'react-native-fast-image';
import theme from '../design/theme';

type SponsoredBannerProps = {
  imageUri?: string;
  fallbackUrl?: string;
  placement?: string;
  style?: StyleProp<ViewStyle>;
};

const SponsoredBanner = ({
  imageUri,
  fallbackUrl,
  style,
}: SponsoredBannerProps) => {
  const handleFallbackPress = async () => {
    if (!fallbackUrl) {
      return;
    }

    try {
      await Linking.openURL(fallbackUrl);
    } catch (error) {
      console.log('Unable to open sponsored placement', error);
    }
  };

  const fallbackContent = imageUri ? (
    <Pressable onPress={handleFallbackPress} style={styles.fallbackPressable}>
      <FastImage
        source={{uri: imageUri}}
        style={styles.fallbackImage}
        resizeMode="cover"
      />
    </Pressable>
  ) : (
    <View style={styles.placeholder}>
      <Text style={styles.placeholderTitle}>Sponsored placement</Text>
      <Text style={styles.placeholderCopy}>
        Ad inventory is syncing. Your app session stays smooth while it refreshes.
      </Text>
    </View>
  );

  return (
    <View style={[styles.container, style]}>
      {fallbackContent}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    width: '100%',
    minHeight: 56,
    justifyContent: 'center',
  },
  fallbackPressable: {
    borderRadius: 18,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  fallbackImage: {
    width: '100%',
    height: 132,
    borderRadius: 18,
  },
  placeholder: {
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(18, 39, 66, 0.72)',
    paddingHorizontal: 16,
    paddingVertical: 14,
  },
  placeholderTitle: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  placeholderCopy: {
    marginTop: 6,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
});

export default SponsoredBanner;
