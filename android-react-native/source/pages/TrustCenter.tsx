import React from 'react';
import {Linking, Pressable, StyleSheet, Text, View} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import {
  AppScreen,
  GlassCard,
  InlineLoader,
  ScreenHeader,
  ScreenState,
  SectionEyebrow,
} from '../design/primitives';
import theme, {alpha} from '../design/theme';
import {useTrustCenter} from '../hooks/useTrustLearn';
import {navigate} from '../utils/helper';

const linkItems = [
  {
    key: 'privacyPolicy',
    title: 'Privacy policy',
    icon: 'lock-closed-outline',
  },
  {
    key: 'deleteAccount',
    title: 'Account deletion',
    icon: 'trash-outline',
  },
  {
    key: 'terms',
    title: 'Terms',
    icon: 'document-text-outline',
  },
  {
    key: 'support',
    title: 'Support',
    icon: 'help-circle-outline',
  },
] as const;

const TrustCenter = () => {
  const navigation = useNavigation<any>();
  const {data, isLoading, isError, error, refetch, isFetching} =
    useTrustCenter();

  const handleBackPress = () => {
    if (navigation.canGoBack()) {
      navigation.goBack();
      return;
    }

    navigate('HomeFlow');
  };

  const openLink = async (url?: string) => {
    if (!url) {
      return;
    }

    try {
      await Linking.openURL(url);
    } catch (linkError) {
      if (__DEV__) {
        console.log('Trust Center link failed', linkError);
      }
    }
  };

  const availableLinks = linkItems.filter(item => Boolean(data?.links?.[item.key]));

  return (
    <AppScreen
      scrollable
      refreshing={isFetching}
      onRefresh={refetch}
      contentContainerStyle={styles.contentContainer}>
      <ScreenHeader
        title="Trust Center"
        subtitle="Clear safety, privacy, and reward explanations from Blockrium."
        showBack
        onBackPress={handleBackPress}
      />

      <GlassCard style={styles.heroCard}>
        <SectionEyebrow text="Blockrium Shield" />
        <Text style={styles.heroTitle}>Understand the app before you act.</Text>
        <Text style={styles.heroText}>
          These notes explain reward balance, wallet safety, privacy, and account
          controls using the latest backend content.
        </Text>
      </GlassCard>

      {isLoading ? <InlineLoader label="Loading Trust Center..." /> : null}

      {isError ? (
        <ScreenState
          icon="shield-outline"
          title="Trust Center unavailable"
          message={(error as Error)?.message || 'Please try again shortly.'}
          actionLabel="Retry"
          onActionPress={refetch}
        />
      ) : null}

      {!isLoading && !isError && data?.sections?.length === 0 ? (
        <ScreenState
          icon="document-text-outline"
          title="No safety notes yet"
          message="Trust Center content has not been published from the backend."
          actionLabel="Refresh"
          onActionPress={refetch}
        />
      ) : null}

      {data?.sections?.map(section => (
        <GlassCard key={section.key} style={styles.sectionCard}>
          <Text style={styles.sectionTitle}>{section.title}</Text>
          <Text style={styles.sectionBody}>{section.body}</Text>
        </GlassCard>
      ))}

      {availableLinks.length > 0 ? (
        <GlassCard>
          <SectionEyebrow text="Helpful Links" />
          {availableLinks.map(item => (
            <Pressable
              key={item.key}
              onPress={() => openLink(data?.links?.[item.key])}
              style={styles.linkRow}>
              <View style={styles.linkIcon}>
                <Ionicons
                  name={item.icon}
                  size={18}
                  color={theme.colors.primary}
                />
              </View>
              <Text style={styles.linkTitle}>{item.title}</Text>
              <Ionicons
                name="open-outline"
                size={18}
                color={theme.colors.textSoft}
              />
            </Pressable>
          ))}
        </GlassCard>
      ) : null}
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {
    paddingTop: 4,
    paddingBottom: 126,
  },
  heroCard: {
    gap: 6,
  },
  heroTitle: {
    color: theme.colors.text,
    fontSize: 21,
    lineHeight: 27,
    fontFamily: theme.typography.heading,
  },
  heroText: {
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  sectionCard: {
    gap: 6,
  },
  sectionTitle: {
    color: theme.colors.text,
    fontSize: 16,
    lineHeight: 22,
    fontFamily: theme.typography.heading,
  },
  sectionBody: {
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  linkRow: {
    minHeight: 50,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  linkIcon: {
    width: 34,
    height: 34,
    borderRadius: theme.radii.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.primary, 0.12),
  },
  linkTitle: {
    flex: 1,
    color: theme.colors.text,
    fontSize: 14,
    fontFamily: theme.typography.bodyBold,
  },
});

export default TrustCenter;
