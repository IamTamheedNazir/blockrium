import React, {useMemo, useState} from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import {
  AppScreen,
  AppTextField,
  GlassCard,
  ScreenHeader,
  ScreenState,
  SectionEyebrow,
} from '../design/primitives';
import theme, {alpha} from '../design/theme';
import {navigate} from '../utils/helper';

const searchableDestinations = [
  {
    key: 'home',
    title: 'Home dashboard',
    subtitle: 'Balances, quick actions, updates, and rewards.',
    icon: 'home-outline',
    route: 'HomeFlow',
    terms: ['home', 'dashboard', 'balance', 'news', 'reward'],
  },
  {
    key: 'mining',
    title: 'Mining',
    subtitle: 'Start mining, countdown, streak, and task rewards.',
    icon: 'flash-outline',
    route: 'MiningFlow',
    terms: ['mining', 'mine', 'countdown', 'streak', 'task'],
  },
  {
    key: 'wallet',
    title: 'Wallet',
    subtitle: 'Send, receive, assets, networks, and history.',
    icon: 'wallet-outline',
    route: 'WalletFlow',
    terms: ['wallet', 'send', 'receive', 'token', 'history', 'withdraw'],
  },
  {
    key: 'community',
    title: 'Community',
    subtitle: 'Network pulse, rankings, referrers, and updates.',
    icon: 'people-outline',
    route: 'CommunityFlow',
    terms: ['community', 'leaderboard', 'rank', 'referral', 'network'],
  },
  {
    key: 'profile',
    title: 'Profile',
    subtitle: 'Account details, links, and secure session controls.',
    icon: 'person-circle-outline',
    route: 'ProfileFlow',
    terms: ['profile', 'account', 'logout', 'settings', 'edit'],
  },
  {
    key: 'tasks',
    title: 'Tasks',
    subtitle: 'Daily missions and referral activity.',
    icon: 'checkbox-outline',
    route: 'Tasks',
    terms: ['tasks', 'missions', 'friends', 'claim'],
  },
  {
    key: 'learn',
    title: 'Learn',
    subtitle: 'Wallet safety lessons, quizzes, XP, and badges.',
    icon: 'school-outline',
    route: 'LearnEarn',
    terms: ['learn', 'lesson', 'quiz', 'xp', 'badge', 'education'],
  },
  {
    key: 'squads',
    title: 'Squads',
    subtitle: 'Join a team, contribute weekly activity, and claim squad chests.',
    icon: 'people-circle-outline',
    route: 'Squads',
    terms: ['squad', 'team', 'community', 'weekly', 'chest', 'leaderboard'],
  },
  {
    key: 'trust',
    title: 'Trust Center',
    subtitle: 'Reward rules, privacy, wallet safety, and deletion links.',
    icon: 'shield-checkmark-outline',
    route: 'TrustCenter',
    terms: ['trust', 'privacy', 'delete', 'safety', 'wallet', 'policy'],
  },
];

const AppSearch = () => {
  const navigation = useNavigation<any>();
  const [query, setQuery] = useState('');

  const results = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase();

    if (!normalizedQuery) {
      return searchableDestinations;
    }

    return searchableDestinations.filter(item =>
      [item.title, item.subtitle, ...item.terms]
        .join(' ')
        .toLowerCase()
        .includes(normalizedQuery),
    );
  }, [query]);

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
        title="Search"
        subtitle="Jump to core Blockrium areas without stacking duplicate screens."
        showBack
        onBackPress={handleBackPress}
      />

      <AppTextField
        label="Search app"
        value={query}
        onChangeText={setQuery}
        placeholder="Mining, wallet, tasks..."
        leadingIcon="search-outline"
        autoCapitalize="none"
        autoCorrect={false}
      />

      <GlassCard>
        <SectionEyebrow text="Quick Jump" />
        <Text style={styles.heroTitle}>Where do you want to go?</Text>
        <Text style={styles.heroText}>
          Search stays local and fast, then navigates through the app router.
        </Text>
      </GlassCard>

      {results.length === 0 ? (
        <ScreenState
          icon="search-outline"
          title="No matches"
          message="Try searching for mining, wallet, tasks, community, or profile."
        />
      ) : null}

      {results.map(item => (
        <Pressable
          key={item.key}
          onPress={() => {
            if (navigation.canGoBack()) {
              navigation.goBack();
            }
            requestAnimationFrame(() => navigate(item.route));
          }}>
          <GlassCard style={styles.resultCard}>
            <View style={styles.resultIcon}>
              <Ionicons name={item.icon as any} size={20} color={theme.colors.primary} />
            </View>
            <View style={styles.resultCopy}>
              <Text style={styles.resultTitle}>{item.title}</Text>
              <Text style={styles.resultSubtitle}>{item.subtitle}</Text>
            </View>
            <Ionicons name="arrow-forward" size={18} color={theme.colors.textSoft} />
          </GlassCard>
        </Pressable>
      ))}
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {
    paddingTop: 4,
    paddingBottom: 126,
  },
  heroTitle: {
    color: theme.colors.text,
    fontSize: 19,
    fontFamily: theme.typography.heading,
  },
  heroText: {
    marginTop: 5,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  resultCard: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  resultIcon: {
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
  resultCopy: {
    flex: 1,
  },
  resultTitle: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  resultSubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
});

export default AppSearch;
