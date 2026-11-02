import React from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';
import LinearGradient from 'react-native-linear-gradient';
import Ionicons from 'react-native-vector-icons/Ionicons';
import theme from '../design/theme';

const routeConfig: Record<string, {label: string; ionicon: string}> = {
  Home: {label: 'Home', ionicon: 'grid-outline'},
  Mining: {label: 'Mining', ionicon: 'hammer-outline'},
  Wallet: {label: 'Wallet', ionicon: 'wallet-outline'},
  Community: {label: 'Community', ionicon: 'people-outline'},
  Profile: {label: 'Profile', ionicon: 'person-circle-outline'},
};

const NavigationIcon = ({route, isFocused, onPress}: any) => {
  const config = routeConfig[route] ?? {
    label: route,
    ionicon: 'ellipse-outline',
  };

  const iconColor = isFocused ? theme.colors.primary : theme.colors.textSoft;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`Open ${config.label}`}
      accessibilityState={{selected: isFocused}}
      onPress={onPress}
      style={({pressed}) => [
        styles.tabButton,
        isFocused && styles.tabButtonActive,
        pressed && styles.tabButtonPressed,
      ]}>
      <View style={[styles.iconShell, isFocused && styles.iconShellActive]}>
        {isFocused ? (
          <LinearGradient
            colors={['rgba(0, 214, 176, 0.20)', 'rgba(47, 124, 255, 0.10)']}
            style={StyleSheet.absoluteFill}
          />
        ) : null}
        <Ionicons name={config.ionicon as any} size={21} color={iconColor} />
      </View>
      <Text style={[styles.label, isFocused && styles.labelActive]}>
        {config.label}
      </Text>
    </Pressable>
  );
};

const styles = StyleSheet.create({
  tabButton: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 58,
    paddingVertical: 6,
    borderRadius: 18,
  },
  tabButtonActive: {
    backgroundColor: 'rgba(0, 214, 176, 0.06)',
  },
  tabButtonPressed: {
    opacity: 0.78,
    transform: [{scale: 0.97}],
  },
  iconShell: {
    width: 34,
    height: 30,
    borderRadius: 15,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 3,
    overflow: 'hidden',
  },
  iconShellActive: {
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.24)',
  },
  label: {
    color: theme.colors.textSoft,
    fontSize: 10,
    fontFamily: theme.typography.bodyMedium,
  },
  labelActive: {
    color: theme.colors.primarySoft,
    fontFamily: theme.typography.bodyBold,
  },
});

export default NavigationIcon;
