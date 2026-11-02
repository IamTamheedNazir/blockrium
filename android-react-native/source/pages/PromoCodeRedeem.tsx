import React, {useCallback, useEffect, useState} from 'react';
import {StyleSheet, Text, View} from 'react-native';
import {useIsFocused, useNavigation} from '@react-navigation/native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import {
  AppScreen,
  AppTextField,
  GlassCard,
  InlineLoader,
  PrimaryButton,
  ScreenHeader,
  ScreenState,
  SecondaryButton,
  SectionEyebrow,
} from '../design/primitives';
import theme from '../design/theme';
import {useThisAlert} from '../hooks/AlertContext';
import {
  getPartnerPromoRedemptions,
  redeemPartnerPromoCode,
} from '../services/partnerPromoCodeService';
import {PartnerPromoRedemption} from '../types/partnerPromo';

const normalizeCode = (value: string) =>
  value.toUpperCase().replace(/\s+/g, '').slice(0, 40);

const formatAmount = (value: unknown) => {
  const amount = Number(value);
  return Number.isFinite(amount)
    ? amount.toLocaleString(undefined, {maximumFractionDigits: 4})
    : '0';
};

const formatDate = (value?: string | null) => {
  if (!value) {
    return 'Recently';
  }

  const date = new Date(String(value).replace(' ', 'T'));
  return Number.isNaN(date.getTime())
    ? 'Recently'
    : date.toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
};

const PromoCodeRedeem = () => {
  const navigation = useNavigation<any>();
  const isFocused = useIsFocused();
  const {showAlert} = useThisAlert();
  const [code, setCode] = useState('');
  const [redemptions, setRedemptions] = useState<PartnerPromoRedemption[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isRedeeming, setIsRedeeming] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadRedemptions = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      setRedemptions(await getPartnerPromoRedemptions());
    } catch (loadError: any) {
      setError(loadError?.message || 'Unable to load your promo history.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    if (isFocused) {
      loadRedemptions();
    }
  }, [isFocused, loadRedemptions]);

  const handleRedeem = async () => {
    const normalized = normalizeCode(code);
    if (normalized.length < 4) {
      showAlert('Enter a valid promo code.', 'ERROR');
      return;
    }

    setIsRedeeming(true);
    try {
      const result = await redeemPartnerPromoCode(normalized);
      setCode('');
      await loadRedemptions();
      const amount = formatAmount(result.claim?.amount);
      const label = result.claim?.label || 'BRC';
      showAlert(
        result.already_claimed
          ? `This code was already redeemed. Your ${amount} ${label} reward is already recorded.`
          : `Success. ${amount} ${label} has been added to your Blockrium wallet.`,
        'SUCCESS',
      );
    } catch (redeemError: any) {
      showAlert(
        redeemError?.message ||
          'This promo code could not be redeemed. Check it and try again.',
        'ERROR',
      );
    } finally {
      setIsRedeeming(false);
    }
  };

  return (
    <AppScreen
      scrollable
      refreshing={isLoading}
      onRefresh={loadRedemptions}
      contentContainerStyle={styles.content}>
      <ScreenHeader
        title="Promo code"
        subtitle="Enter your code to claim a reward."
        showBack
        onBackPress={() =>
          navigation.canGoBack()
            ? navigation.goBack()
            : navigation.navigate('HomeFlow')
        }
      />

      <GlassCard style={styles.heroCard}>
        <View style={styles.heroIcon}>
          <Ionicons
            name="ticket-outline"
            size={28}
            color={theme.colors.background}
          />
        </View>
        <SectionEyebrow text="REWARDS" />
        <Text style={styles.heroTitle}>Redeem your code</Text>
        <Text style={styles.heroCopy}>Enter your promo code below.</Text>
      </GlassCard>

      <GlassCard style={styles.formCard}>
        <AppTextField
          label="Promo code"
          leadingIcon="ticket-outline"
          value={code}
          onChangeText={value => setCode(normalizeCode(value))}
          placeholder="BRC-XXXX-XXXX-XXXX"
          autoCapitalize="characters"
          autoCorrect={false}
          maxLength={40}
          returnKeyType="done"
          onSubmitEditing={handleRedeem}
        />
        <PrimaryButton
          label={isRedeeming ? 'Redeeming...' : 'Redeem code'}
          icon="gift-outline"
          loading={isRedeeming}
          disabled={!code.trim()}
          onPress={handleRedeem}
        />
      </GlassCard>

      <View style={styles.sectionHeading}>
        <View>
          <SectionEyebrow text="YOUR REWARDS" />
          <Text style={styles.sectionTitle}>Redemption history</Text>
        </View>
        <SecondaryButton
          label="Refresh"
          icon="refresh"
          loading={isLoading}
          onPress={loadRedemptions}
          style={styles.refreshButton}
        />
      </View>

      {isLoading && redemptions.length === 0 ? (
        <InlineLoader label="Loading redemption history..." />
      ) : null}
      {!isLoading && error ? (
        <ScreenState
          icon="cloud-offline-outline"
          title="History unavailable"
          message={error}
          actionLabel="Try again"
          onActionPress={loadRedemptions}
        />
      ) : null}
      {!isLoading && !error && redemptions.length === 0 ? (
        <ScreenState
          icon="gift-outline"
          title="No rewards yet"
          message="Your redeemed promo code rewards will appear here."
        />
      ) : null}
      {redemptions.map(item => (
        <GlassCard key={String(item.id)} style={styles.historyCard}>
          <View style={styles.historyIcon}>
            <Ionicons
              name="checkmark"
              size={18}
              color={theme.colors.background}
            />
          </View>
          <View style={styles.historyCopy}>
            <Text style={styles.historyTitle} numberOfLines={1}>
              Promo code reward
            </Text>
            <Text style={styles.historyDate}>
              {formatDate(item.created_at)}
            </Text>
          </View>
          <Text style={styles.historyAmount}>
            +{formatAmount(item.reward_amount)} {item.reward_label || 'BRC'}
          </Text>
        </GlassCard>
      ))}
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  content: {
    paddingBottom: 140,
  },
  heroCard: {
    alignItems: 'flex-start',
  },
  heroIcon: {
    width: 62,
    height: 62,
    borderRadius: 22,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.primary,
    marginBottom: 18,
  },
  heroTitle: {
    marginTop: 6,
    color: theme.colors.text,
    fontSize: 29,
    lineHeight: 36,
    fontFamily: theme.typography.display,
  },
  heroCopy: {
    marginTop: 10,
    color: theme.colors.textMuted,
    fontSize: 15,
    lineHeight: 22,
    fontFamily: theme.typography.body,
  },
  formCard: {
    marginTop: 18,
  },
  formHint: {
    marginTop: 12,
    color: theme.colors.textSoft,
    fontSize: 12,
    lineHeight: 18,
    textAlign: 'center',
    fontFamily: theme.typography.body,
  },
  sectionHeading: {
    marginTop: 28,
    marginBottom: 12,
    flexDirection: 'row',
    alignItems: 'flex-end',
    justifyContent: 'space-between',
    gap: 12,
  },
  sectionTitle: {
    marginTop: 6,
    color: theme.colors.text,
    fontSize: 23,
    fontFamily: theme.typography.heading,
  },
  refreshButton: {
    minHeight: 42,
    paddingHorizontal: 14,
  },
  historyCard: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 13,
  },
  historyIcon: {
    width: 38,
    height: 38,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.primary,
  },
  historyCopy: {
    flex: 1,
    marginLeft: 12,
    marginRight: 10,
  },
  historyTitle: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  historyDate: {
    marginTop: 4,
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  historyAmount: {
    color: theme.colors.primarySoft,
    fontSize: 14,
    textAlign: 'right',
    fontFamily: theme.typography.bodyBold,
  },
});

export default PromoCodeRedeem;
