import React, {useMemo, useState} from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import {
  AppScreen,
  AppTextField,
  GlassCard,
  PrimaryButton,
  ScreenHeader,
  SectionEyebrow,
  SecondaryButton,
} from '../design/primitives';
import theme, {alpha} from '../design/theme';
import {logAppIssue} from '../services/appIssueLogger';
import {useThisAlert} from '../hooks/AlertContext';
import {navigate} from '../utils/helper';

const issueCategories = [
  {
    key: 'mining',
    title: 'Mining timer',
    subtitle: 'Start mining, timer sync, or active cycle issue.',
    icon: 'hammer-outline',
  },
  {
    key: 'streak',
    title: 'Daily streak',
    subtitle: 'Calendar, streak claim, or 7-day chest issue.',
    icon: 'calendar-outline',
  },
  {
    key: 'rewards_ads',
    title: 'Rewards / ads',
    subtitle: 'Ad reward, task reward, or credit issue.',
    icon: 'gift-outline',
  },
  {
    key: 'login_signup',
    title: 'Login / signup',
    subtitle: 'Account access, OTP, or referral code issue.',
    icon: 'person-circle-outline',
  },
  {
    key: 'wallet',
    title: 'Wallet',
    subtitle: 'Balance, receive, send, or wallet screen issue.',
    icon: 'wallet-outline',
  },
  {
    key: 'squad_referral',
    title: 'Squad / referral',
    subtitle: 'Invite link, squad join, network, or ping issue.',
    icon: 'people-outline',
  },
  {
    key: 'other',
    title: 'Other',
    subtitle: 'Anything else that needs Blockrium support.',
    icon: 'help-circle-outline',
  },
];

const HelpCenter = () => {
  const {showAlert} = useThisAlert();
  const [selectedCategory, setSelectedCategory] = useState(issueCategories[0].key);
  const [details, setDetails] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const selectedIssue = useMemo(
    () => issueCategories.find(item => item.key === selectedCategory) ?? issueCategories[0],
    [selectedCategory],
  );
  const canSubmit = details.trim().length >= 10 && !isSubmitting;

  const handleSubmit = async () => {
    if (!canSubmit) {
      showAlert('Add at least 10 characters so support can understand the issue.', 'ERROR');
      return;
    }

    setIsSubmitting(true);
    try {
      const sent = await logAppIssue({
        severity: 'warning',
        source: 'user_report',
        action: 'user_report',
        endpoint: 'manual/help-center',
        method: 'POST',
        message: `${selectedIssue.title}: ${details.trim()}`,
        metadata: {
          category: selectedIssue.key,
          category_title: selectedIssue.title,
          user_message: details.trim(),
        },
      });

      if (!sent) {
        showAlert('Report could not be sent right now. Please try again.', 'ERROR');
        return;
      }

      setDetails('');
      showAlert('Report sent. Blockrium support can now see it in App Health.', 'SUCCESS');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AppScreen scrollable contentContainerStyle={styles.contentContainer}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={styles.keyboard}>
        <ScreenHeader
          title="Help Center"
          subtitle="Report mining, rewards, login, wallet, squad, and app problems."
          showBack
          onBackPress={() => navigate('AppSettings')}
        />

        <GlassCard style={styles.heroCard}>
          <View style={styles.heroIcon}>
            <Ionicons name="pulse-outline" size={24} color={theme.colors.primary} />
          </View>
          <View style={styles.heroCopy}>
            <SectionEyebrow text="Support" />
            <Text style={styles.heroTitle}>Send a useful issue report</Text>
            <Text style={styles.heroSubtitle}>
              Reports include app version, device, and safe technical details so
              fixes can be found faster.
            </Text>
          </View>
        </GlassCard>

        <GlassCard>
          <SectionEyebrow text="Issue Type" />
          <View style={styles.categoryGrid}>
            {issueCategories.map(item => {
              const active = item.key === selectedCategory;

              return (
                <Pressable
                  key={item.key}
                  onPress={() => setSelectedCategory(item.key)}
                  style={[styles.categoryCard, active && styles.categoryCardActive]}>
                  <View style={[styles.categoryIcon, active && styles.categoryIconActive]}>
                    <Ionicons
                      name={item.icon as any}
                      size={18}
                      color={active ? theme.colors.background : theme.colors.primary}
                    />
                  </View>
                  <View style={styles.categoryCopy}>
                    <Text style={styles.categoryTitle}>{item.title}</Text>
                    <Text style={styles.categorySubtitle}>{item.subtitle}</Text>
                  </View>
                </Pressable>
              );
            })}
          </View>
        </GlassCard>

        <GlassCard>
          <SectionEyebrow text="Details" />
          <AppTextField
            label="What happened?"
            leadingIcon="chatbox-ellipses-outline"
            multiline
            value={details}
            onChangeText={setDetails}
            placeholder="Example: I tapped Claim today, watched the ad, but the streak did not update."
            style={styles.detailsInput}
            textAlignVertical="top"
            maxLength={800}
          />
          <Text style={styles.characterCount}>{details.trim().length}/800</Text>
          <PrimaryButton
            label="Send report"
            icon="send"
            loading={isSubmitting}
            disabled={!canSubmit}
            onPress={handleSubmit}
            style={styles.submitButton}
          />
          <SecondaryButton
            label="Open Trust Center"
            icon="shield-check"
            onPress={() => navigate('TrustCenter')}
            style={styles.secondaryAction}
          />
        </GlassCard>
      </KeyboardAvoidingView>
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {
    paddingTop: 6,
    paddingBottom: 126,
  },
  keyboard: {
    flex: 1,
  },
  heroCard: {
    flexDirection: 'row',
    alignItems: 'center',
    borderColor: alpha(theme.colors.primary, 0.22),
  },
  heroIcon: {
    width: 48,
    height: 48,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 14,
    backgroundColor: alpha(theme.colors.primary, 0.12),
    borderWidth: 1,
    borderColor: alpha(theme.colors.primary, 0.24),
  },
  heroCopy: {
    flex: 1,
  },
  heroTitle: {
    marginTop: 5,
    color: theme.colors.text,
    fontSize: 19,
    fontFamily: theme.typography.heading,
  },
  heroSubtitle: {
    marginTop: 6,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  categoryGrid: {
    gap: 10,
    marginTop: 12,
  },
  categoryCard: {
    minHeight: 68,
    borderRadius: theme.radii.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.28),
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
  },
  categoryCardActive: {
    borderColor: alpha(theme.colors.primary, 0.72),
    backgroundColor: alpha(theme.colors.primary, 0.1),
  },
  categoryIcon: {
    width: 38,
    height: 38,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
    backgroundColor: alpha(theme.colors.primary, 0.1),
  },
  categoryIconActive: {
    backgroundColor: theme.colors.primary,
  },
  categoryCopy: {
    flex: 1,
  },
  categoryTitle: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  categorySubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 16,
    fontFamily: theme.typography.body,
  },
  detailsInput: {
    minHeight: 130,
    paddingTop: 14,
  },
  characterCount: {
    alignSelf: 'flex-end',
    marginTop: 8,
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  submitButton: {
    marginTop: 14,
  },
  secondaryAction: {
    marginTop: 12,
  },
});

export default HelpCenter;
