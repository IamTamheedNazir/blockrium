import React, {useCallback, useEffect, useState} from 'react';
import {Alert, Platform, Pressable, StyleSheet, Text, View} from 'react-native';
import {StackActions, useIsFocused, useNavigation} from '@react-navigation/native';
import MaterialCommunityIcons from 'react-native-vector-icons/MaterialCommunityIcons';
import {
  AppScreen,
  GlassCard,
  InlineLoader,
  PrimaryButton,
  ScreenState,
  SecondaryButton,
  SectionEyebrow,
} from '../../design/primitives';
import theme from '../../design/theme';
import {callQuery} from '../../hooks';
import {useAppResumeRefresh} from '../../hooks/useAppResumeRefresh';
import {getApi} from '../../storage/server';
import {
  clearPendingGooglePlayIntent,
  createAndLaunchGooglePlayPurchase,
  getPendingGooglePlayIntent,
  getGooglePlayProduct,
  GooglePlayProduct,
  restorePendingGooglePlayPurchase,
  subscribeToGooglePlayPurchases,
  verifyGooglePlayPurchase,
} from '../../services/googlePlayPurchaseService';

type BoostPlan = {
  id: number;
  name: string;
  description?: string | null;
  boost_percent: number | string;
  duration_days: number | string;
  price: number | string;
  currency?: string | null;
  google_play_product_id?: string | null;
  google_play_base_plan_id?: string | null;
  purchase_ready?: boolean;
  benefit_summary?: string | null;
  earnings_disclaimer?: string | null;
};

type BoostPurchase = {
  purchase_id?: string;
  plan_name?: string | null;
  status?: string | null;
  payment_status?: string | null;
  starts_at?: string | null;
  ends_at?: string | null;
  current_period_ends_at?: string | null;
};

const EMPTY_PLANS: BoostPlan[] = [];
const PLANS_LOADING_TIMEOUT_MS = 12000;
const PRODUCT_LOOKUP_TIMEOUT_MS = 8000;

const fetchBoostPlans = async () => {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), PLANS_LOADING_TIMEOUT_MS);
  try {
    return await getApi('auth/boost-plans', {signal: controller.signal});
  } finally {
    clearTimeout(timeout);
  }
};

const formatDate = (value?: string | null) => {
  if (!value) {
    return null;
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return null;
  }

  return date.toLocaleDateString(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
};

const BoostStore = () => {
  const navigation = useNavigation<any>();
  const isFocused = useIsFocused();
  const [selectedPlanId, setSelectedPlanId] = useState<number | null>(null);
  const [purchase, setPurchase] = useState<BoostPurchase | null>(null);
  const [isStartingCheckout, setIsStartingCheckout] = useState(false);
  const [isRefreshingPurchase, setIsRefreshingPurchase] = useState(false);
  const [productDetails, setProductDetails] = useState<Record<number, GooglePlayProduct | null>>({});
  const [productLookupErrors, setProductLookupErrors] = useState<Record<number, string>>({});
  const [productsLoaded, setProductsLoaded] = useState(false);
  const [productLookupAttempt, setProductLookupAttempt] = useState(0);
  const [plansLoadTimedOut, setPlansLoadTimedOut] = useState(false);
  const [plansLoadAttempt, setPlansLoadAttempt] = useState(0);

  const {
    data: boostData,
    isLoading: isBoostLoading,
    refetch: refetchBoost,
  }: any = callQuery('auth/my-boost', {
    retry: 0,
    staleTime: 30000,
  });
  const {
    data: plansResponse,
    isLoading: isPlansLoading,
    isFetching: isPlansFetching,
    refetch: refetchPlans,
  }: any = callQuery('auth/boost-plans', {
    queryFn: fetchBoostPlans,
    retry: 0,
    staleTime: 30000,
  });

  const rate = boostData?.data?.data ?? boostData?.data ?? {};
  const paidBoostPercent = Number(rate?.paid_boost_percent ?? 0);
  const teamBoostPercent = Number(rate?.team_boost_percent ?? 0);
  const miningReward = Number(rate?.mining_reward ?? rate?.reward ?? 1.5);
  const responseData = plansResponse?.data ?? {};
  const rawPlans = responseData?.data?.data ?? responseData?.data ?? EMPTY_PLANS;
  const plans: BoostPlan[] = Array.isArray(rawPlans) ? rawPlans : EMPTY_PLANS;
  const storeEnabled = Boolean(responseData?.store_enabled);
  const checkoutEnabled = Boolean(responseData?.checkout_enabled);
  const plansRequestFailed =
    plansResponse?.success === false ||
    responseData?.success === false ||
    Number(responseData?.status) === 0;
  const plansRequestError =
    (typeof plansResponse?.error === 'string' ? plansResponse.error : null) ||
    (typeof responseData?.message === 'string' ? responseData.message : null) ||
    (typeof responseData?.error === 'string' ? responseData.error : null);
  const activePlanEnd = formatDate(
    rate?.active_paid_boost?.ends_at || purchase?.current_period_ends_at || purchase?.ends_at,
  );

  const refreshBoostPaymentState = useCallback(async () => {
    let recovered: Awaited<ReturnType<typeof restorePendingGooglePlayPurchase>> | null = null;
    if (Platform.OS === 'android') {
      try {
        recovered = await restorePendingGooglePlayPurchase('boost');
      } catch {
        // A server refresh still reconciles completed RTDN and prior verification events.
      }
    }
    await refetchBoost?.();
    if (recovered?.found) {
      setPurchase(current => ({
        ...current,
        status: recovered?.verified ? 'active' : 'pending',
        payment_status: recovered?.verified ? 'paid' : 'pending',
      }));
    }
  }, [refetchBoost]);

  useAppResumeRefresh(isFocused, refreshBoostPaymentState);

  useEffect(() => {
    if (!isPlansLoading) {
      setPlansLoadTimedOut(false);
      return;
    }

    const timeout = setTimeout(
      () => setPlansLoadTimedOut(true),
      PLANS_LOADING_TIMEOUT_MS,
    );
    return () => clearTimeout(timeout);
  }, [isPlansLoading, plansLoadAttempt]);

  useEffect(() => {
    if (!isFocused) {
      return;
    }
    if (Platform.OS !== 'android') {
      setProductsLoaded(true);
      return;
    }
    let active = true;
    setProductsLoaded(false);
    setProductDetails({});
    setProductLookupErrors({});
    const lookupTimeout = setTimeout(() => {
      if (active) {
        setProductsLoaded(true);
      }
    }, PRODUCT_LOOKUP_TIMEOUT_MS);
    Promise.all(plans.map(async plan => {
      if (!plan.purchase_ready || !plan.google_play_product_id || !plan.google_play_base_plan_id) {
        return [plan.id, null, 'The Play product or base plan is not configured on the server.'] as const;
      }
      try {
        const product = await getGooglePlayProduct(
          plan.google_play_product_id,
          'subs',
          plan.google_play_base_plan_id,
        );
        return [plan.id, product, product?.formattedPrice ? '' : 'Google Play returned the product without a usable price or subscription offer.'] as const;
      } catch (error: any) {
        const code = String(error?.code || '');
        const message = String(error?.message || 'Google Play product lookup failed.');
        const detail = code ? `${code}: ${message}` : message;
        return [plan.id, null, detail] as const;
      }
    })).then(entries => {
      if (active) {
        clearTimeout(lookupTimeout);
        setProductDetails(Object.fromEntries(entries.map(([id, product]) => [id, product])));
        setProductLookupErrors(Object.fromEntries(entries.filter(([, , error]) => Boolean(error)).map(([id, , error]) => [id, error])));
        setProductsLoaded(true);
      }
    });
    return () => {
      active = false;
      clearTimeout(lookupTimeout);
    };
  }, [isFocused, plans, productLookupAttempt]);

  useEffect(() => {
    const subscription = subscribeToGooglePlayPurchases(async update => {
      if (!isFocused) {
        return;
      }
      const intent = await getPendingGooglePlayIntent();
      if (intent?.purpose !== 'boost') {
        return;
      }
      if (update.status === 'cancelled') {
        await clearPendingGooglePlayIntent(intent.purchase_id);
        setPurchase({status: 'cancelled', payment_status: 'cancelled'});
        setIsStartingCheckout(false);
        return;
      }
      if (update.status === 'pending') {
        setPurchase(current => ({...current, status: 'pending', payment_status: 'pending'}));
        return;
      }
      if (update.status !== 'purchased') {
        if (update.status === 'error' || update.status === 'unknown') {
          setPurchase(current => ({...current, status: 'failed', payment_status: 'failed'}));
          setIsStartingCheckout(false);
          Alert.alert('Google Play unavailable', update.message || 'Google Play could not complete the purchase.');
        }
        return;
      }

      try {
        const confirmation = await verifyGooglePlayPurchase(update, intent);
        await refetchBoost?.();
        setPurchase(current => ({
          ...current,
          status: confirmation.verified ? 'active' : 'pending',
          payment_status: confirmation.verified ? 'paid' : 'pending',
        }));
        Alert.alert(
          confirmation.verified ? 'Boost activated' : 'Payment confirmation pending',
          confirmation.verified
            ? 'Google Play confirmed the subscription. Your server-side mining boost is now active.'
            : 'The purchase was sent to Blockrium for verification. The boost activates only after server confirmation.',
        );
      } catch (error: any) {
        setPurchase(current => ({...current, status: 'pending', payment_status: 'pending'}));
        Alert.alert('Purchase needs confirmation', error?.message || 'The boost remains inactive until Google Play is verified by Blockrium. Refresh this screen to retry.');
      } finally {
        setIsStartingCheckout(false);
      }
    });
    return () => subscription.remove();
  }, [isFocused, refetchBoost]);

  useEffect(() => {
    if (!isFocused || Platform.OS !== 'android') {
      return;
    }
    refreshBoostPaymentState().catch(() => undefined);
  }, [isFocused, refreshBoostPaymentState]);

  const startPurchase = async (plan: BoostPlan) => {
    if (isStartingCheckout) {
      return;
    }

    setSelectedPlanId(plan.id);
    setIsStartingCheckout(true);

    try {
      if (Platform.OS !== 'android') {
        Alert.alert('Google Play unavailable', 'Mining boost purchases are currently available in the Android app only.');
        return;
      }
      const product = productDetails[plan.id];
      if (!checkoutEnabled || !plan.purchase_ready || !product?.formattedPrice) {
        Alert.alert('Boost checkout unavailable', 'This boost is not configured for Google Play purchases yet. Please try again later.');
        return;
      }

      setPurchase({plan_name: plan.name, status: 'pending', payment_status: 'pending'});
      const launch = await createAndLaunchGooglePlayPurchase('boost', plan.id);
      if (launch.cancelled) {
        setPurchase(null);
        return;
      }
    } catch (error: any) {
      setPurchase({plan_name: plan.name, status: 'failed', payment_status: 'failed'});
      Alert.alert(
        'Boost checkout unavailable',
        error?.message || 'Unable to open the payment page.',
      );
    } finally {
      setIsStartingCheckout(false);
    }
  };

  const refreshStatus = async () => {
    if (isRefreshingPurchase) {
      return;
    }

    setIsRefreshingPurchase(true);
    try {
      if (Platform.OS !== 'android') {
        return;
      }
      const recovered = await restorePendingGooglePlayPurchase('boost');
      if (recovered.verified) {
        setPurchase(current => ({...current, status: 'active', payment_status: 'paid'}));
        Alert.alert('Boost activated', 'Your Google Play subscription is verified and the mining boost is active.');
      } else if (recovered.pending || recovered.found) {
        setPurchase(current => ({...current, status: 'pending', payment_status: 'pending'}));
      } else if (paidBoostPercent > 0) {
        setPurchase(current => ({...current, status: 'active', payment_status: 'paid'}));
      }
    } catch (error: any) {
      Alert.alert(
        'Could not refresh payment status',
        error?.message || 'Google Play status could not be checked. Please try again.',
      );
    } finally {
      setIsRefreshingPurchase(false);
    }
  };

  const returnHome = () => {
    if (navigation.canGoBack()) {
      navigation.dispatch(StackActions.popToTop());
      return;
    }
    navigation.navigate('HomeFlow');
  };

  const openMining = () => {
    navigation.getParent()?.navigate('Mining', {screen: 'MiningFlow'});
  };

  return (
    <AppScreen
      scrollable
      stickyHeader={
        <View style={styles.stickyNavigation}>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Return to Home"
            testID="boost-store-home"
            onPress={returnHome}
            style={styles.stickyNavigationAction}>
            <MaterialCommunityIcons name="home-outline" size={18} color={theme.colors.primary} />
            <Text style={styles.stickyNavigationText}>Home</Text>
          </Pressable>
          <View style={styles.stickyNavigationTitle}>
            <SectionEyebrow text="MINING BOOSTS" />
          </View>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Open Mining"
            testID="boost-store-mining"
            onPress={openMining}
            style={styles.stickyNavigationAction}>
            <MaterialCommunityIcons name="pickaxe" size={18} color={theme.colors.primary} />
            <Text style={styles.stickyNavigationText}>Mining</Text>
          </Pressable>
        </View>
      }
      refreshing={isPlansFetching || isBoostLoading}
      onRefresh={() => {
        refetchPlans?.();
        refetchBoost?.();
        refreshStatus();
      }}
      contentContainerStyle={styles.content}>
      <View style={styles.intro}>
        <View style={styles.introIcon}>
          <MaterialCommunityIcons name="rocket-launch" size={22} color={theme.colors.primary} />
        </View>
        <View style={styles.introCopy}>
          <SectionEyebrow text="MINING BOOSTS" />
          <Text style={styles.introTitle}>Choose your plan</Text>
          <Text style={styles.introDescription}>See the rate increase and duration before you subscribe.</Text>
        </View>
      </View>

      <GlassCard style={styles.rateCard}>
        <View style={styles.rateHeader}>
          <View>
            <SectionEyebrow text="YOUR CURRENT RATE" />
            <Text style={styles.rate}>{miningReward.toFixed(4)} BRC</Text>
            <Text style={styles.rateCaption}>per mining cycle</Text>
          </View>
          <MaterialCommunityIcons name="pickaxe" size={28} color={theme.colors.primary} />
        </View>
        <View style={styles.metricGrid}>
          <View style={styles.metricTile}>
            <Text style={styles.metricValue}>{paidBoostPercent}%</Text>
            <Text style={styles.metricLabel}>Paid boost</Text>
          </View>
          <View style={styles.metricTile}>
            <Text style={styles.metricValue}>{teamBoostPercent}%</Text>
            <Text style={styles.metricLabel}>Network boost</Text>
          </View>
        </View>
        {paidBoostPercent > 0 ? (
          <Text style={styles.activeMessage}>
            Paid mining boost active
            {activePlanEnd ? ` until ${activePlanEnd}` : ''}.
          </Text>
        ) : null}
      </GlassCard>

      {purchase ? (
        <GlassCard style={styles.statusCard}>
          <View style={styles.statusHeader}>
            <View>
              <SectionEyebrow text="LATEST PAYMENT" />
              <Text style={styles.statusTitle}>
                {purchase.plan_name || 'Mining boost'}
              </Text>
            </View>
            <View style={styles.statusPill}>
              <Text style={styles.statusPillText}>
                {purchase.payment_status === 'paid' &&
                purchase.status === 'active'
                  ? 'Active'
                  : purchase.payment_status === 'failed'
                  ? 'Failed'
                  : 'Pending'}
              </Text>
            </View>
          </View>
          <Text style={styles.statusCopy}>
            {purchase.payment_status === 'paid' && purchase.status === 'active'
              ? 'Payment confirmed. Your server-side mining boost is active.'
              : purchase.payment_status === 'cancelled'
                ? 'Google Play checkout was cancelled. No boost was activated.'
                : purchase.payment_status === 'failed'
                  ? 'The purchase was not confirmed, so the boost remains inactive.'
                  : 'Payment confirmation is pending. The boost remains inactive until Blockrium verifies the purchase with Google Play.'}
          </Text>
          <SecondaryButton
            label="Refresh payment status"
            icon="refresh"
            loading={isRefreshingPurchase}
            onPress={refreshStatus}
          />
        </GlassCard>
      ) : null}

      <View style={styles.sectionHeading}>
        <View>
          <SectionEyebrow text="GOOGLE PLAY PLANS" />
          <Text style={styles.sectionTitle}>Pick your boost</Text>
        </View>
        <Text style={styles.sectionHint}>Billed by Google Play</Text>
      </View>

      {isPlansLoading && !plansLoadTimedOut ? (
        <InlineLoader label="Loading boost plans..." />
      ) : null}
      {plansLoadTimedOut || plansRequestFailed ? (
        <ScreenState
          icon="cloud-alert-outline"
          title={
            plansLoadTimedOut
              ? 'Boost plans are taking longer than expected'
              : 'Boost plans could not be loaded'
          }
          message={
            plansRequestError ||
            'You can leave this screen safely. Check your connection and retry to load the latest plans.'
          }
          actionLabel="Retry loading plans"
          onActionPress={() => {
            setPlansLoadTimedOut(false);
            setPlansLoadAttempt(attempt => attempt + 1);
            refetchPlans?.();
          }}
        />
      ) : null}
      {!isPlansLoading && !plansLoadTimedOut && !plansRequestFailed && (!storeEnabled || plans.length === 0) ? (
        <ScreenState
          icon="rocket-launch-outline"
          title={storeEnabled ? 'Plans are not available' : 'Boost purchases are paused'}
          message={
            storeEnabled
              ? 'No active boost plans are available right now.'
              : 'Boost plans are hidden. An admin can show them in Admin > Verified Members > Settings. Purchases stay unavailable until Google Play checkout is enabled.'
          }
          actionLabel="Refresh plans"
          onActionPress={() => refetchPlans?.()}
        />
      ) : null}

      {plans.map((plan, index) => {
        const isSelected = selectedPlanId === plan.id && isStartingCheckout;
        const boostPercent = Number(plan.boost_percent || 0);
        const durationDays = Number(plan.duration_days || 30);
        const product = productDetails[plan.id];
        const ready = checkoutEnabled && plan.purchase_ready && Boolean(product?.formattedPrice);
        return (
          <GlassCard
            key={plan.id}
            style={[styles.planCard, index === 1 && styles.planCardFeatured]}>
            {index === 1 ? (
              <View style={styles.recommendedBadge}>
                <Text style={styles.recommendedText}>POPULAR CHOICE</Text>
              </View>
            ) : null}
            <View style={styles.planTopRow}>
              <View style={[styles.planIcon, index === 2 && styles.planIconElite]}>
                <MaterialCommunityIcons
                  name={index === 2 ? 'rocket-launch' : 'rocket-outline'}
                  size={22}
                  color={
                    index === 2 ? theme.colors.accentWarm : theme.colors.primary
                  }
                />
              </View>
              <View style={styles.planCopy}>
                <Text style={styles.planName}>{plan.name}</Text>
                <Text style={styles.planDescription}>{plan.benefit_summary || plan.description || 'A higher mining rate while your subscription is active.'}</Text>
              </View>
            </View>
            {plan.purchase_ready && Platform.OS === 'android' && productsLoaded && !product?.formattedPrice ? (
              <View style={styles.productHintBox}>
                <Text style={styles.productHint}>
                  {productLookupErrors[plan.id] || 'Google Play did not return this plan.'}{' '}
                  Google Play did not return the configured plan for this app/account. Requested product <Text style={styles.productHintId}>{plan.google_play_product_id}</Text>, base plan <Text style={styles.productHintId}>{plan.google_play_base_plan_id}</Text>. Confirm these exact IDs in Play Console, that the base plan is active, and that it is available in this Google Play account’s country.
                </Text>
                <Text
                  accessibilityRole="button"
                  onPress={() => setProductLookupAttempt(attempt => attempt + 1)}
                  style={styles.productRetry}>
                  Retry Google Play check
                </Text>
              </View>
            ) : null}
            <View style={styles.planBenefits}>
              <View style={styles.planBenefit}>
                <MaterialCommunityIcons name="trending-up" size={17} color={theme.colors.primary} />
                <Text style={styles.planBenefitText}>+{boostPercent}% mining rate</Text>
              </View>
              <View style={styles.planBenefit}>
                <MaterialCommunityIcons name="calendar-clock" size={17} color={theme.colors.primary} />
                <Text style={styles.planBenefitText}>{durationDays} days</Text>
              </View>
            </View>
            <View style={styles.planPurchaseRow}>
              <View style={styles.priceBlock}>
                <Text style={styles.priceLabel}>PRICE</Text>
                <Text style={styles.planPrice}>{product?.formattedPrice || (!productsLoaded ? 'Checking…' : 'Unavailable')}</Text>
              </View>
              <PrimaryButton
                label={
                  isSelected
                    ? 'Opening Play…'
                    : !checkoutEnabled
                      ? 'Purchases paused'
                      : ready
                        ? 'Boost Now'
                        : 'Plan unavailable'
                }
                icon="credit-card-outline"
                loading={isSelected}
                onPress={() => startPurchase(plan)}
                disabled={!ready || (isStartingCheckout && !isSelected)}
                accessibilityLabel={`Purchase ${plan.name}`}
                style={styles.planButton}
              />
            </View>
          </GlassCard>
        );
      })}

      <Text style={styles.disclaimer}>
        {plans.find(plan => plan.earnings_disclaimer)?.earnings_disclaimer || 'A boost increases your configured mining rate while active. It does not guarantee a token amount or financial return.'} Google Play handles billing. Blockrium applies the boost only after server verification.
      </Text>
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  content: {
    paddingBottom: 140,
  },
  stickyNavigation: {
    minHeight: 58,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 2,
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(151, 166, 195, 0.14)',
    backgroundColor: 'rgba(7, 11, 17, 0.96)',
  },
  stickyNavigationAction: {
    minWidth: 76,
    minHeight: 42,
    borderRadius: theme.radii.pill,
    paddingHorizontal: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 5,
    backgroundColor: 'rgba(0, 214, 176, 0.09)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.18)',
  },
  stickyNavigationText: {
    color: theme.colors.text,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  stickyNavigationTitle: {
    flex: 1,
    alignItems: 'center',
  },
  intro: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 13,
    marginTop: 14,
    marginBottom: 4,
  },
  introIcon: {
    width: 48,
    height: 48,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.25)',
  },
  introCopy: {
    flex: 1,
  },
  introTitle: {
    marginTop: 3,
    color: theme.colors.text,
    fontSize: 21,
    fontFamily: theme.typography.heading,
  },
  introDescription: {
    marginTop: 3,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
  rateCard: {
    marginTop: 14,
    paddingVertical: 16,
    paddingHorizontal: 16,
  },
  rateHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  rate: {
    marginTop: 5,
    color: theme.colors.text,
    fontSize: 25,
    fontFamily: theme.typography.heading,
  },
  rateCaption: {
    marginTop: 2,
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  metricGrid: {
    flexDirection: 'row',
    gap: 9,
    marginTop: 13,
  },
  metricTile: {
    flex: 1,
    borderRadius: 14,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(20, 46, 78, 0.68)',
  },
  metricValue: {
    color: theme.colors.text,
    fontSize: 16,
    fontFamily: theme.typography.heading,
  },
  metricLabel: {
    marginTop: 5,
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  activeMessage: {
    marginTop: 16,
    color: theme.colors.success,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
  },
  statusCard: {
    marginTop: 18,
  },
  statusHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 12,
  },
  statusTitle: {
    marginTop: 8,
    color: theme.colors.text,
    fontSize: 19,
    fontFamily: theme.typography.heading,
  },
  statusPill: {
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 7,
    backgroundColor: 'rgba(0, 214, 176, 0.14)',
  },
  statusPillText: {
    color: theme.colors.primarySoft,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  statusCopy: {
    marginTop: 12,
    marginBottom: 16,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 20,
    fontFamily: theme.typography.body,
  },
  sectionHeading: {
    marginTop: 22,
    marginBottom: 10,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-end',
  },
  sectionTitle: {
    marginTop: 4,
    color: theme.colors.text,
    fontSize: 20,
    fontFamily: theme.typography.heading,
  },
  sectionHint: {
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  planCard: {
    marginBottom: 10,
    paddingVertical: 15,
    paddingHorizontal: 15,
  },
  planCardFeatured: {
    borderColor: 'rgba(0, 214, 176, 0.44)',
  },
  recommendedBadge: {
    alignSelf: 'flex-start',
    borderRadius: 999,
    paddingHorizontal: 9,
    paddingVertical: 5,
    marginBottom: 10,
    backgroundColor: 'rgba(0, 214, 176, 0.16)',
  },
  recommendedText: {
    color: theme.colors.primarySoft,
    fontSize: 10,
    letterSpacing: 0.8,
    fontFamily: theme.typography.bodyBold,
  },
  planTopRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 11,
  },
  planIcon: {
    width: 42,
    height: 42,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
  },
  planIconElite: {
    backgroundColor: 'rgba(255, 186, 73, 0.14)',
  },
  planCopy: {
    flex: 1,
  },
  planName: {
    color: theme.colors.text,
    fontSize: 17,
    fontFamily: theme.typography.heading,
  },
  planDescription: {
    marginTop: 3,
    color: theme.colors.textMuted,
    fontSize: 11,
    lineHeight: 16,
    fontFamily: theme.typography.body,
  },
  planPrice: {
    color: theme.colors.text,
    fontSize: 17,
    fontFamily: theme.typography.heading,
    marginTop: 2,
  },
  productHint: {
    color: theme.colors.warning,
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
  productHintBox: {
    marginTop: 10,
    padding: 10,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
  },
  productHintId: {
    color: theme.colors.text,
    fontFamily: theme.typography.heading,
  },
  productRetry: {
    marginTop: 8,
    color: theme.colors.primary,
    fontSize: 12,
    fontFamily: theme.typography.heading,
  },
  planBenefits: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 12,
    paddingTop: 11,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  planBenefit: {
    flex: 1,
    minHeight: 36,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 9,
    borderRadius: 11,
    backgroundColor: 'rgba(20, 46, 78, 0.56)',
  },
  planBenefitText: {
    color: theme.colors.text,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  planPurchaseRow: {
    marginTop: 11,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  priceBlock: {
    minWidth: 80,
  },
  priceLabel: {
    color: theme.colors.textSoft,
    fontSize: 9,
    letterSpacing: 0.8,
    fontFamily: theme.typography.bodyBold,
  },
  planBoost: {
    color: theme.colors.primarySoft,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
  },
  planDuration: {
    color: theme.colors.textSoft,
    fontSize: 13,
    fontFamily: theme.typography.body,
  },
  planButton: {
    flex: 1,
    minHeight: 44,
  },
  disclaimer: {
    marginTop: 8,
    color: theme.colors.textSoft,
    fontSize: 11,
    lineHeight: 17,
    textAlign: 'center',
    fontFamily: theme.typography.body,
  },
});

export default BoostStore;
