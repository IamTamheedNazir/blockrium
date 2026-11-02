import React from 'react';
import {Linking, Pressable, StyleSheet, Switch, Text, View} from 'react-native';
import {useFocusEffect, useNavigation} from '@react-navigation/native';
import NetInfo from '@react-native-community/netinfo';
import Ionicons from 'react-native-vector-icons/Ionicons';
import {
  AppScreen,
  GlassCard,
  ScreenHeader,
  SectionEyebrow,
} from '../design/primitives';
import theme, {alpha} from '../design/theme';
import {useAppTheme} from '../providers/ThemeProvider';
import {navigate} from '../utils/helper';
import {
  getAdsConsentStatus,
  showAdsPrivacyOptions,
} from '../services/adsConsentService';

const settingsLinks = [
  {
    key: 'profile',
    title: 'Profile settings',
    subtitle: 'Edit account identity and session controls.',
    icon: 'person-circle-outline',
    onPress: () => navigate('ProfileFlow'),
  },
  {
    key: 'wallet',
    title: 'Wallet security',
    subtitle: 'Manage passcode, biometrics, networks, and phrase access.',
    icon: 'shield-checkmark-outline',
    onPress: () => navigate('WalletFlow'),
  },
  {
    key: 'notifications',
    title: 'Notifications',
    subtitle: 'Open account alerts and app notices.',
    icon: 'notifications-outline',
    onPress: () => navigate('Notifications'),
  },
  {
    key: 'learn',
    title: 'Learn',
    subtitle: 'Open safety lessons and backend-validated XP progress.',
    icon: 'school-outline',
    onPress: () => navigate('LearnEarn'),
  },
  {
    key: 'trust',
    title: 'Trust Center',
    subtitle:
      'Review wallet safety, reward rules, privacy, and account controls.',
    icon: 'shield-checkmark-outline',
    onPress: () => navigate('TrustCenter'),
  },
  {
    key: 'help',
    title: 'Help Center',
    subtitle: 'Report mining, rewards, wallet, login, ads, or squad issues.',
    icon: 'help-buoy-outline',
    onPress: () => navigate('HelpCenter'),
  },
  {
    key: 'privacy',
    title: 'Privacy policy',
    subtitle: 'Review Blockrium account and data policy.',
    icon: 'lock-closed-outline',
    onPress: () => Linking.openURL('https://blockrium.com/privacy-policy'),
  },
];

const AppSettings = () => {
  const {theme: appTheme} = useAppTheme();
  const navigation = useNavigation<any>();
  const [isOnline, setIsOnline] = React.useState(true);
  const [privacyOptionsRequired, setPrivacyOptionsRequired] =
    React.useState(false);
  const [privacyOptionsBusy, setPrivacyOptionsBusy] = React.useState(false);

  React.useEffect(() => {
    const unsubscribe = NetInfo.addEventListener(state => {
      setIsOnline(state.isConnected !== false);
    });

    return unsubscribe;
  }, []);

  useFocusEffect(
    React.useCallback(() => {
      let active = true;
      getAdsConsentStatus().then(status => {
        if (active) {
          setPrivacyOptionsRequired(status.privacyOptionsRequired);
        }
      });

      return () => {
        active = false;
      };
    }, []),
  );

  const handlePrivacyOptions = async () => {
    if (privacyOptionsBusy) {
      return;
    }

    setPrivacyOptionsBusy(true);
    try {
      const status = await showAdsPrivacyOptions();
      setPrivacyOptionsRequired(status.privacyOptionsRequired);
    } catch (error) {
      if (__DEV__) {
        console.log('Privacy options unavailable', error);
      }
    } finally {
      setPrivacyOptionsBusy(false);
    }
  };

  const handleBackPress = () => {
    if (navigation.canGoBack()) {
      navigation.goBack();
      return;
    }

    navigate('HomeFlow');
  };

  return (
    <AppScreen scrollable contentContainerStyle={styles.contentContainer}>
      <ScreenHeader
        title="Settings"
        subtitle="App-wide controls and safe routes into deeper settings."
        showBack
        onBackPress={handleBackPress}
      />

      <GlassCard>
        <SectionEyebrow text="Appearance" />
        <Text style={[styles.appearanceTitle, {color: appTheme.colors.text}]}>
          Choose your app theme
        </Text>
        <Text
          style={[
            styles.appearanceSubtitle,
            {color: appTheme.colors.textMuted},
          ]}>
          Dark theme is currently enabled for the best contrast across the app.
        </Text>
        <View
          style={[
            styles.appearancePicker,
            {
              backgroundColor: appTheme.colors.surfaceMuted,
              borderColor: appTheme.colors.border,
            },
          ]}>
          <View
            accessibilityRole="text"
            style={[
              styles.appearanceOption,
              styles.appearanceOptionLocked,
              {
                backgroundColor: appTheme.colors.surface,
                borderColor: appTheme.colors.primary,
              },
            ]}>
            <Ionicons
              name="moon-outline"
              size={16}
              color={appTheme.colors.primary}
            />
            <Text
              style={[
                styles.appearanceOptionText,
                {color: appTheme.colors.text},
              ]}>
              Dark
            </Text>
          </View>
        </View>
      </GlassCard>

      <GlassCard>
        <SectionEyebrow text="App Status" />
        <View style={styles.statusRow}>
          <View>
            <Text style={styles.statusTitle}>Network connection</Text>
            <Text style={styles.statusSubtitle}>
              {isOnline
                ? 'Online and ready to sync.'
                : 'Offline mode is active.'}
            </Text>
          </View>
          <View
            style={[
              styles.statusBadge,
              isOnline ? styles.statusBadgeOnline : styles.statusBadgeOffline,
            ]}>
            <Text style={styles.statusBadgeText}>
              {isOnline ? 'Online' : 'Offline'}
            </Text>
          </View>
        </View>
        <View style={styles.settingToggle}>
          <View style={styles.toggleCopy}>
            <Text style={styles.toggleTitle}>Reduced motion</Text>
            <Text style={styles.toggleSubtitle}>
              Animations stay subtle by default.
            </Text>
          </View>
          <Switch
            value={false}
            disabled
            trackColor={{
              false: alpha(theme.colors.textSoft, 0.35),
              true: theme.colors.primary,
            }}
            thumbColor={theme.colors.text}
          />
        </View>
      </GlassCard>

      {settingsLinks.map(item => (
        <Pressable
          key={item.key}
          onPress={() => {
            if (navigation.canGoBack()) {
              navigation.goBack();
            }
            requestAnimationFrame(item.onPress);
          }}>
          <GlassCard style={styles.linkCard}>
            <View style={styles.linkIcon}>
              <Ionicons
                name={item.icon as any}
                size={20}
                color={theme.colors.primary}
              />
            </View>
            <View style={styles.linkCopy}>
              <Text style={styles.linkTitle}>{item.title}</Text>
              <Text style={styles.linkSubtitle}>{item.subtitle}</Text>
            </View>
            <Ionicons
              name="chevron-forward"
              size={18}
              color={theme.colors.textSoft}
            />
          </GlassCard>
        </Pressable>
      ))}

      {privacyOptionsRequired ? (
        <Pressable
          disabled={privacyOptionsBusy}
          onPress={handlePrivacyOptions}
          accessibilityRole="button"
          accessibilityLabel="Manage privacy choices">
          <GlassCard style={styles.linkCard}>
            <View style={styles.linkIcon}>
              <Ionicons
                name="options-outline"
                size={20}
                color={theme.colors.primary}
              />
            </View>
            <View style={styles.linkCopy}>
              <Text style={styles.linkTitle}>Manage privacy choices</Text>
              <Text style={styles.linkSubtitle}>
                {privacyOptionsBusy
                  ? 'Opening privacy options...'
                  : 'Review or change your advertising consent.'}
              </Text>
            </View>
            <Ionicons
              name="chevron-forward"
              size={18}
              color={theme.colors.textSoft}
            />
          </GlassCard>
        </Pressable>
      ) : null}
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {
    paddingTop: 4,
    paddingBottom: 126,
  },
  statusRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 12,
  },
  appearanceTitle: {
    fontSize: 16,
    fontFamily: theme.typography.heading,
  },
  appearanceSubtitle: {
    marginTop: 5,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  appearancePicker: {
    flexDirection: 'row',
    gap: 6,
    marginTop: 14,
    padding: 4,
    borderRadius: theme.radii.lg,
    borderWidth: 1,
  },
  appearanceOption: {
    flex: 1,
    minHeight: 42,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 5,
    borderRadius: theme.radii.md,
    borderWidth: 1,
    borderColor: 'transparent',
  },
  appearanceOptionLocked: {
    opacity: 1,
  },
  appearanceOptionText: {
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  statusTitle: {
    color: theme.colors.text,
    fontSize: 17,
    fontFamily: theme.typography.heading,
  },
  statusSubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  statusBadge: {
    minHeight: 28,
    borderRadius: theme.radii.pill,
    paddingHorizontal: 10,
    justifyContent: 'center',
  },
  statusBadgeOnline: {
    backgroundColor: alpha(theme.colors.success, 0.16),
  },
  statusBadgeOffline: {
    backgroundColor: alpha(theme.colors.danger, 0.16),
  },
  statusBadgeText: {
    color: theme.colors.text,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  settingToggle: {
    marginTop: 14,
    paddingTop: 14,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 14,
  },
  toggleCopy: {
    flex: 1,
  },
  toggleTitle: {
    color: theme.colors.text,
    fontSize: 16,
    fontFamily: theme.typography.bodyBold,
  },
  toggleSubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 13,
    fontFamily: theme.typography.body,
  },
  linkCard: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  linkIcon: {
    width: 38,
    height: 38,
    borderRadius: theme.radii.pill,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
    backgroundColor: alpha(theme.colors.primary, 0.12),
    borderWidth: 1,
    borderColor: alpha(theme.colors.primary, 0.22),
  },
  linkCopy: {
    flex: 1,
  },
  linkTitle: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  linkSubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
});

export default AppSettings;
