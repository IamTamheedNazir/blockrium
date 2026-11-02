import React, {useEffect, useMemo, useState} from 'react';
import {Alert, Linking, Pressable, StyleSheet, Text, View} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import MaterialCommunityIcons from 'react-native-vector-icons/MaterialCommunityIcons';
import {callMutation, callQuery} from '../../hooks';
import {useAppResumeRefresh} from '../../hooks/useAppResumeRefresh';
import {useBadges, useGamificationSummary} from '../../hooks/useEngagement';
import {useThisAlert} from '../../hooks/AlertContext';
import useAuthData from '../../storage/client/useAuthData';
import {removeLeadsTokenFromHeader} from '../../storage/server';
import {logoutBlockriumNotificationUser} from '../../services/blockriumNotificationService';
import {setStorageToken} from '../../utils/mmkvStorage';
import {navigate, restartApp} from '../../utils/helper';
import {
  cacheUserProfile,
  getCachedUserProfile,
  getProfileVerificationBadges,
  hasProfileIdentity,
  normalizeUserProfile,
  NormalizedUserProfile,
} from '../../utils/profile';
import {normalizeNotificationCount} from '../../utils/notifications';
import {
  AppScreen,
  AppTopNav,
  GlassCard,
  ScreenLoader,
  SectionEyebrow,
} from '../../design/primitives';
import theme from '../../design/theme';
import {BadgeStrip, LevelProgressCard} from '../../molecules/EngagementCards';

const profileActions = [
  {
    key: 'verified-member',
    title: 'Verified Member',
    subtitle: 'Track KYC approval and Blockrium Verified membership separately.',
    icon: 'shield-checkmark-outline',
    onPress: () => navigate('VerifiedMember'),
  },
  {
    key: 'promo-code',
    title: 'Partner rewards',
    subtitle: 'Redeem a partner campaign BRC reward.',
    icon: 'ticket-outline',
    onPress: () => navigate('PromoCodeRedeem'),
  },
  {
    key: 'network',
    title: 'Your network',
    subtitle: 'Review invited users and referral momentum.',
    icon: 'people-outline',
    onPress: () => navigate('Referral'),
  },
  {
    key: 'rank',
    title: 'Top miners',
    subtitle: 'Jump straight to the leaderboard.',
    icon: 'trophy-outline',
    onPress: () => navigate('LeaderBoard'),
  },
  {
    key: 'help',
    title: 'Help Center',
    subtitle: 'Report mining, rewards, login, wallet, or squad problems.',
    icon: 'help-buoy-outline',
    onPress: () => navigate('HelpCenter'),
  },
  {
    key: 'whitepaper',
    title: 'Whitepaper',
    subtitle: 'Open the Blockrium product vision.',
    icon: 'document-text-outline',
    onPress: () =>
      Linking.openURL(
        'https://medium.com/@blockrium/we-are-excited-to-announce-the-official-release-of-blockriums-whitepaper-outlining-our-vision-for-ba2e5941aff4',
      ),
  },
  {
    key: 'privacy',
    title: 'Privacy policy',
    subtitle: 'Review the latest account and data terms.',
    icon: 'shield-checkmark-outline',
    onPress: () => Linking.openURL('https://blockrium.com/privacy-policy'),
  },
];

const formatDate = (value?: string) => {
  if (!value) {
    return 'No expiry';
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleDateString(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
};

const ProfileOrg = () => {
  const {data, error, isLoading, isFetching, refetch} =
    callQuery('auth/profile');
  useAppResumeRefresh(true, refetch);
  const {data: notificationCountData, refetch: refetchNotificationCount}: any =
    callQuery('auth/count-notification', {
      retry: 0,
      staleTime: 30000,
    });
  const {showAlert} = useThisAlert();
  const {
    data: gamificationSummary,
    isLoading: isGamificationLoading,
    isError: isGamificationError,
    refetch: refetchGamification,
  } = useGamificationSummary();
  const {
    data: badges,
    isLoading: isBadgesLoading,
    isError: isBadgesError,
    refetch: refetchBadges,
  } = useBadges();
  const password = useAuthData()?.fields?.loginPassword;
  const [cachedProfile, setCachedProfile] = useState<NormalizedUserProfile>();
  const user = useMemo(
    () => normalizeUserProfile(data, cachedProfile),
    [cachedProfile, data],
  );
  const {mutateAsync: logout} = callMutation('auth/logout', {
    email: user?.email ?? '',
    password: password ?? '',
  });

  useEffect(() => {
    getCachedUserProfile()
      .then(profile => {
        if (profile) {
          setCachedProfile(profile);
        }
      })
      .catch(cacheLoadError => {
        console.log('Unable to load cached profile', cacheLoadError);
      });
  }, []);

  useEffect(() => {
    if (error) {
      showAlert('An error occurred while fetching profile data.', 'ERROR');
    } else if (data && data?.success === false) {
      showAlert(data?.error || 'Something went wrong', 'ERROR');
    } else if (hasProfileIdentity(user)) {
      cacheUserProfile(user).catch(cacheError => {
        console.log('Unable to cache profile', cacheError);
      });
    }
  }, [data, error, showAlert, user]);

  const confirmLogout = () => {
    Alert.alert('Logout', 'Do you want to securely end this session?', [
      {text: 'Cancel', style: 'cancel'},
      {
        text: 'Logout',
        style: 'destructive',
        onPress: async () => {
          try {
            await logout?.();
          } catch (logoutError) {
            console.error('Logout request failed', logoutError);
          } finally {
            logoutBlockriumNotificationUser();
            removeLeadsTokenFromHeader();
            await setStorageToken('authToken', '');
            restartApp();
          }
        },
      },
    ]);
  };

  const confirmDeleteAccount = () => {
    Alert.alert(
      'Delete account',
      'This will open the account deletion page in your browser.',
      [
        {text: 'Cancel', style: 'cancel'},
        {
          text: 'Continue',
          style: 'destructive',
          onPress: () => Linking.openURL('https://blockrium.com/delete.html'),
        },
      ],
    );
  };

  if (isLoading) {
    return (
      <ScreenLoader
        title="Loading profile"
        subtitle="Preparing account identity, links, and secure session controls."
      />
    );
  }

  const displayName = user?.name ?? 'Blockrium User';
  const displayEmail = user?.email ?? 'No email available';
  const displayUid = user?.uid ?? user?.id ?? '--';
  const isPro = Boolean(user?.isPro);
  const subscriptionStatus =
    user?.subscriptionStatus ?? (isPro ? 'active' : 'free');
  const subscriptionType =
    user?.subscriptionType ?? (isPro ? 'Blockrium Pro' : 'Standard');
  const notificationCount = normalizeNotificationCount(notificationCountData);
  const verificationStatus = data?.data?.verified_member;
  const {kycVerified, blockriumVerified} =
    getProfileVerificationBadges(verificationStatus);

  return (
    <AppScreen
      scrollable
      refreshing={Boolean(isFetching)}
      onRefresh={() => {
        refetch?.();
        refetchNotificationCount?.();
        refetchGamification?.();
        refetchBadges?.();
      }}
      contentContainerStyle={styles.contentContainer}>
      <AppTopNav
        title="Profile"
        subtitle="Identity, badges, referrals, and secure session controls."
        notificationCount={notificationCount}
        onNotificationsPress={() => navigate('Notifications')}
        onSearchPress={() => navigate('AppSearch')}
        onSettingsPress={() => navigate('AppSettings')}
      />

      <GlassCard style={styles.accountCard}>
        <SectionEyebrow text="Account" />
        <View style={styles.accountHeader}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>
              {displayName?.[0]?.toUpperCase?.() ?? 'B'}
            </Text>
          </View>
          <View style={styles.accountCopy}>
            <Text style={styles.name} numberOfLines={2}>
              {displayName}
            </Text>
            {kycVerified || blockriumVerified ? (
              <View style={styles.verificationBadges}>
                {kycVerified ? (
                  <View style={styles.kycVerifiedBadge}>
                    <Ionicons name="shield-checkmark" size={13} color="#071C21" />
                    <Text style={styles.kycVerifiedBadgeText}>KYC Verified</Text>
                  </View>
                ) : null}
                {blockriumVerified ? (
                  <View style={styles.blockriumVerifiedBadge}>
                    <Ionicons name="checkmark-circle" size={14} color="#DDF8FF" />
                    <Text style={styles.blockriumVerifiedBadgeText}>
                      Blockrium Verified
                    </Text>
                  </View>
                ) : null}
              </View>
            ) : null}
            <Text style={styles.email} numberOfLines={1}>
              {displayEmail}
            </Text>
            <View style={styles.uidRow}>
              <Text style={styles.uidLabel}>UID</Text>
              <Text style={styles.uidValue} numberOfLines={1}>
                {displayUid}
              </Text>
            </View>
          </View>
          <Pressable
            onPress={() => navigate('EditScreen', {user})}
            style={styles.editButton}>
            <Ionicons
              name="create-outline"
              size={18}
              color={theme.colors.text}
            />
          </Pressable>
        </View>
      </GlassCard>

      <LevelProgressCard
        summary={gamificationSummary}
        loading={isGamificationLoading}
        error={isGamificationError}
        onRetry={() => refetchGamification()}
      />

      <BadgeStrip
        data={badges}
        summaryBadges={gamificationSummary?.badges}
        loading={isBadgesLoading}
        error={isBadgesError}
        onRetry={() => refetchBadges()}
      />

      <GlassCard style={styles.subscriptionCard}>
        <View style={styles.subscriptionHeader}>
          <View
            style={[
              styles.subscriptionIcon,
              isPro && styles.subscriptionIconActive,
            ]}>
            <MaterialCommunityIcons
              name={isPro ? 'crown' : 'crown-outline'}
              size={20}
              color={isPro ? theme.colors.background : theme.colors.primary}
            />
          </View>
          <View style={styles.subscriptionCopy}>
            <SectionEyebrow text="Subscription" />
            <Text style={styles.subscriptionTitle}>
              {isPro ? 'Blockrium Pro active' : 'Standard account'}
            </Text>
          </View>
        </View>
        <View style={styles.profileMetaGrid}>
          <View style={styles.profileMetaCard}>
            <Text style={styles.profileMetaLabel}>Plan</Text>
            <Text style={styles.profileMetaValue} numberOfLines={1}>
              {subscriptionType}
            </Text>
          </View>
          <View style={styles.profileMetaCard}>
            <Text style={styles.profileMetaLabel}>Status</Text>
            <Text style={styles.profileMetaValue} numberOfLines={1}>
              {subscriptionStatus}
            </Text>
          </View>
          <View style={styles.profileMetaCard}>
            <Text style={styles.profileMetaLabel}>Expires</Text>
            <Text style={styles.profileMetaValue} numberOfLines={1}>
              {formatDate(user?.subscriptionExpiry)}
            </Text>
          </View>
        </View>
        {user?.referralCode ? (
          <Text style={styles.subscriptionNote} numberOfLines={1}>
            Referral code: {user.referralCode}
          </Text>
        ) : null}
      </GlassCard>

      {profileActions.map(action => (
        <Pressable key={action.key} onPress={action.onPress}>
          <GlassCard style={styles.actionCard}>
            <View style={styles.actionIconShell}>
              <Ionicons
                name={action.icon as any}
                size={22}
                color={theme.colors.primary}
              />
            </View>
            <View style={styles.actionCopy}>
              <Text style={styles.actionTitle}>{action.title}</Text>
              <Text style={styles.actionSubtitle}>{action.subtitle}</Text>
            </View>
            <Ionicons
              name="arrow-forward"
              size={18}
              color={theme.colors.textSoft}
            />
          </GlassCard>
        </Pressable>
      ))}

      <GlassCard style={styles.sessionCard}>
        <SectionEyebrow text="Session" />
        <Text style={styles.actionTitle}>Security controls</Text>
        <Text style={styles.actionSubtitle}>
          Secure session actions stay separate from the rest of the profile.
        </Text>
        <View style={styles.sessionRow}>
          <Pressable onPress={confirmLogout} style={styles.sessionButton}>
            <MaterialCommunityIcons
              name="logout"
              size={18}
              color={theme.colors.text}
            />
            <Text style={styles.sessionButtonText}>Logout</Text>
          </Pressable>
          <Pressable
            onPress={confirmDeleteAccount}
            style={[styles.sessionButton, styles.destructiveButton]}>
            <MaterialCommunityIcons
              name="delete-outline"
              size={18}
              color={theme.colors.danger}
            />
            <Text style={[styles.sessionButtonText, styles.destructiveText]}>
              Delete account
            </Text>
          </Pressable>
        </View>
      </GlassCard>
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {
    paddingTop: 6,
    paddingBottom: 126,
  },
  topBar: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    marginBottom: 12,
    gap: 12,
  },
  topBarCopy: {
    flex: 1,
  },
  screenTitle: {
    color: theme.colors.text,
    fontSize: 29,
    lineHeight: 33,
    fontFamily: theme.typography.display,
  },
  screenSubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  walletSwitchButton: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(25, 36, 53, 0.72)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  accountHeader: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  accountCard: {
    borderColor: 'rgba(0, 214, 176, 0.16)',
    backgroundColor: 'rgba(14, 21, 31, 0.94)',
  },
  avatar: {
    width: 62,
    height: 62,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0, 214, 176, 0.14)',
    borderWidth: 1,
    borderColor: theme.colors.border,
    marginRight: 14,
  },
  avatarText: {
    color: theme.colors.primary,
    fontSize: 25,
    fontFamily: theme.typography.display,
  },
  accountCopy: {
    flex: 1,
  },
  name: {
    color: theme.colors.text,
    fontSize: 20,
    fontFamily: theme.typography.heading,
  },
  email: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 14,
    fontFamily: theme.typography.body,
  },
  uidRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 8,
  },
  uidLabel: {
    color: theme.colors.textSoft,
    fontSize: 12,
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    fontFamily: theme.typography.bodyMedium,
    marginRight: 8,
  },
  uidValue: {
    flex: 1,
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.bodyBold,
  },
  verificationBadges: {
    flexDirection: 'row',
    alignSelf: 'flex-start',
    flexWrap: 'wrap',
    gap: 6,
    marginTop: 7,
  },
  kycVerifiedBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 5,
    borderRadius: theme.radii.pill,
    backgroundColor: '#48E0C4',
    borderWidth: 1,
    borderColor: '#79F0DA',
  },
  kycVerifiedBadgeText: {
    color: '#071C21',
    fontSize: 10,
    letterSpacing: 0.1,
    fontFamily: theme.typography.bodyBold,
  },
  blockriumVerifiedBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 5,
    borderRadius: theme.radii.pill,
    backgroundColor: '#176B8A',
    borderWidth: 1,
    borderColor: '#55BDE2',
  },
  blockriumVerifiedBadgeText: {
    color: '#DDF8FF',
    fontSize: 10,
    letterSpacing: 0.1,
    fontFamily: theme.typography.bodyBold,
  },
  editButton: {
    width: 40,
    height: 40,
    borderRadius: 15,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(25, 36, 53, 0.72)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  subscriptionCard: {
    gap: 12,
  },
  subscriptionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  subscriptionIcon: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(47, 224, 196, 0.1)',
  },
  subscriptionIconActive: {
    borderColor: theme.colors.primary,
    backgroundColor: theme.colors.primary,
  },
  subscriptionCopy: {
    flex: 1,
  },
  subscriptionTitle: {
    marginTop: 5,
    color: theme.colors.text,
    fontSize: 18,
    fontFamily: theme.typography.heading,
  },
  profileMetaGrid: {
    flexDirection: 'row',
    gap: 8,
  },
  profileMetaCard: {
    flex: 1,
    minHeight: 62,
    borderRadius: theme.radii.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(8, 20, 34, 0.68)',
    padding: 11,
  },
  profileMetaLabel: {
    color: theme.colors.textSoft,
    fontSize: 11,
    textTransform: 'uppercase',
    letterSpacing: 0.7,
    fontFamily: theme.typography.bodyMedium,
  },
  profileMetaValue: {
    marginTop: 6,
    color: theme.colors.text,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
  },
  subscriptionNote: {
    color: theme.colors.textMuted,
    fontSize: 13,
    fontFamily: theme.typography.body,
  },
  actionCard: {
    flexDirection: 'row',
    alignItems: 'center',
    minHeight: 68,
    paddingVertical: 12,
  },
  actionIconShell: {
    width: 40,
    height: 40,
    borderRadius: 15,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
    marginRight: 12,
  },
  actionCopy: {
    flex: 1,
  },
  actionTitle: {
    color: theme.colors.text,
    fontSize: 16,
    fontFamily: theme.typography.heading,
  },
  actionSubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
  sessionRow: {
    marginTop: 12,
    gap: 8,
  },
  sessionCard: {
    marginTop: 2,
  },
  sessionButton: {
    minHeight: 48,
    borderRadius: theme.radii.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(8, 20, 34, 0.68)',
    paddingHorizontal: 16,
    flexDirection: 'row',
    alignItems: 'center',
  },
  destructiveButton: {
    borderColor: 'rgba(255, 123, 137, 0.25)',
    backgroundColor: 'rgba(255, 123, 137, 0.08)',
  },
  sessionButtonText: {
    color: theme.colors.text,
    fontSize: 15,
    marginLeft: 10,
    fontFamily: theme.typography.bodyBold,
  },
  destructiveText: {
    color: theme.colors.danger,
  },
});

export default ProfileOrg;
