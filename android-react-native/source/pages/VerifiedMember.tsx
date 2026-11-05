import React, {useCallback, useEffect, useMemo, useState} from 'react';
import {Platform, StyleSheet, Text, View} from 'react-native';
import {launchImageLibrary, Asset} from 'react-native-image-picker';
import {useIsFocused, useNavigation} from '@react-navigation/native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import MaterialCommunityIcons from 'react-native-vector-icons/MaterialCommunityIcons';
import {useThisAlert} from '../hooks/AlertContext';
import {
  AppScreen,
  AppTextField,
  GlassCard,
  PrimaryButton,
  ScreenHeader,
  ScreenLoader,
  ScreenState,
  SecondaryButton,
} from '../design/primitives';
import theme, {alpha} from '../design/theme';
import {
  replyToPaymentSupport,
  retryVerification,
  startVerification,
  submitPaymentSupport,
} from '../services/verificationService';
import {
  clearPendingGooglePlayIntent,
  createAndLaunchGooglePlayPurchase,
  getPendingGooglePlayIntent,
  restorePendingGooglePlayPurchase,
  subscribeToGooglePlayPurchases,
  verifyGooglePlayPurchase,
} from '../services/googlePlayPurchaseService';
import {VerificationStatus} from '../types/verification';
import {callQuery} from '../hooks';
import {useAppResumeRefresh} from '../hooks/useAppResumeRefresh';
import {openHostedVerification} from '../services/verificationBrowser';

const normalizeStatus = (payload: any): VerificationStatus => ({
  is_verified: Boolean(payload?.is_verified),
  badge: payload?.badge,
  kyc_status: String(payload?.kyc_status ?? 'not_started'),
  didit_status: payload?.didit_status ?? null,
  kyc_rejection_reason: payload?.kyc_rejection_reason ?? null,
  kyc_retry_available: Boolean(payload?.kyc_retry_available),
  kyc_retry_reason: payload?.kyc_retry_reason ?? null,
  kyc_country: payload?.kyc_country ?? null,
  payment_status: String(payload?.payment_status ?? 'pending'),
  access_suspended: Boolean(payload?.access_suspended),
  next_action: payload?.next_action,
  next_action_label: payload?.next_action_label ?? null,
  next_action_url: payload?.next_action_url ?? null,
  payment: payload?.payment ?? null,
  payment_support: payload?.payment_support,
  verified_at: payload?.verified_at ?? null,
  username: payload?.username ?? null,
  benefits: payload?.benefits,
});

const kycStatusCopy = (status: VerificationStatus) => {
  if (status.kyc_status === 'approved') return {label: 'KYC verified', title: 'Your identity check is approved', message: 'Your KYC Verified badge is active. Blockrium Verified membership is a separate optional purchase.', icon: 'shield-checkmark', tone: theme.colors.primary};
  if (status.kyc_status === 'in_review') return {label: 'Under review', title: 'Your identity check is being reviewed', message: 'We will update this status when the verification provider returns its result.', icon: 'time-outline', tone: theme.colors.accentWarm};
  if (status.kyc_status === 'rejected') return {label: 'Action needed', title: 'Identity check needs another attempt', message: status.kyc_rejection_reason ? `${status.kyc_rejection_reason} Correct the issue and retry your identity check.` : 'Correct the issue shown by the verification provider and retry your identity check.', icon: 'alert-circle-outline', tone: theme.colors.danger};
  if (status.kyc_status === 'country_limit') return {label: 'Waiting for capacity', title: 'Identity verification is temporarily unavailable', message: 'The current country allocation is full. Please check again later.', icon: 'hourglass-outline', tone: theme.colors.accentWarm};
  if (['started', 'submitted'].includes(status.kyc_status)) return {label: 'In progress', title: 'Continue your identity check', message: 'Finish the secure identity verification flow. Membership payment is handled separately.', icon: 'finger-print-outline', tone: theme.colors.primary};
  return {label: 'Not started', title: 'Verify your identity', message: 'Complete the identity check to receive your KYC Verified badge. This step does not require a membership purchase.', icon: 'shield-checkmark-outline', tone: theme.colors.primary};
};
const VerifiedMember = () => {
  const navigation = useNavigation<any>();
  const isFocused = useIsFocused();
  const {showAlert} = useThisAlert();
  const {data: queryResponse, isLoading, isFetching, refetch} = callQuery(
    'auth/verification/status',
    {staleTime: 15000, retry: 0},
  );
  const [status, setStatus] = useState<VerificationStatus>();
  const [isStarting, setIsStarting] = useState(false);
  const [isPaying, setIsPaying] = useState(false);
  const [isSubmittingSupport, setIsSubmittingSupport] = useState(false);
  const [supportMessage, setSupportMessage] = useState('');
  const [supportOrderId, setSupportOrderId] = useState('');
  const [supportPlayEmail, setSupportPlayEmail] = useState('');
  const [supportReply, setSupportReply] = useState('');
  const [supportReceipt, setSupportReceipt] = useState<Asset | null>(null);
  const [isSendingSupportReply, setIsSendingSupportReply] = useState(false);
  useEffect(() => {
    const payload = queryResponse?.data?.data;
    if (payload) {
      setStatus(normalizeStatus(payload));
    }
  }, [queryResponse]);

  useEffect(() => {
    if (isFocused) {
      refetch();
    }
  }, [isFocused, refetch]);

  const copy = useMemo(
    () => kycStatusCopy(status ?? normalizeStatus(undefined)),
    [status],
  );

  const refreshStatus = useCallback(async () => {
    const result = await refetch();
    const payload = result?.data?.data?.data;
    if (payload) {
      const nextStatus = normalizeStatus(payload);
      setStatus(nextStatus);
      return nextStatus;
    }

    return undefined;
  }, [refetch]);

  const refreshPaymentOnResume = useCallback(async () => {
    if (Platform.OS === 'android') {
      try {
        await restorePendingGooglePlayPurchase('kyc');
      } catch {
        // Keep server status authoritative; a later retry can recover Play-owned purchases.
      }
    }
    await refreshStatus();
  }, [refreshStatus]);

  useAppResumeRefresh(isFocused, refreshPaymentOnResume);

  useEffect(() => {
    const subscription = subscribeToGooglePlayPurchases(async update => {
      if (!isFocused) {
        return;
      }
      const intent = await getPendingGooglePlayIntent();
      if (intent?.purpose !== 'kyc') {
        return;
      }
      if (update.status === 'cancelled') {
        await clearPendingGooglePlayIntent(intent.purchase_id);
        setIsPaying(false);
        showAlert('Google Play checkout was cancelled. No payment was completed.', 'INFO');
        return;
      }
      if (update.status === 'pending') {
        setIsPaying(false);
        await refreshStatus();
        showAlert('Google Play is processing your payment. Member benefits stay locked until it confirms.', 'INFO');
        return;
      }
      if (update.status !== 'purchased') {
        if (update.status === 'error' || update.status === 'unknown') {
          setIsPaying(false);
          showAlert(update.message || 'Google Play could not complete the payment.', 'ERROR');
        }
        return;
      }

      setIsPaying(true);
      try {
        const confirmation = await verifyGooglePlayPurchase(update, intent);
        const latest = await refreshStatus();
        if (confirmation.verified && latest?.is_verified) {
          showAlert('Payment confirmed. Your Blockrium verified-member benefits are active.', 'SUCCESS');
        } else {
          showAlert('Google Play sent the purchase for verification. Benefits stay locked until the server confirms it.', 'INFO');
        }
      } catch (error: any) {
        showAlert(error?.message || 'Purchase confirmation is pending. Refresh status or contact support if it remains pending.', 'ERROR');
      } finally {
        setIsPaying(false);
      }
    });
    return () => subscription.remove();
  }, [isFocused, refreshStatus, showAlert]);

  useEffect(() => {
    if (!isFocused || Platform.OS !== 'android') {
      return;
    }

    let active = true;
    restorePendingGooglePlayPurchase('kyc')
      .then(async recovered => {
        if (!active || !recovered.found) {
          return;
        }
        await refreshStatus();
        if (recovered.pending) {
          showAlert('Your Google Play payment is still pending. Benefits will activate only after confirmation.', 'INFO');
        } else if (recovered.verified) {
          showAlert('Your Google Play payment was restored and verified.', 'SUCCESS');
        }
      })
      .catch(() => undefined);

    return () => {
      active = false;
    };
  }, [isFocused, refreshStatus, showAlert]);

  const waitForKycResult = async () => {
    let latestStatus: VerificationStatus | undefined;
    for (let attempt = 0; attempt < 6; attempt += 1) {
      latestStatus = await refreshStatus();
      if (
        latestStatus?.next_action === 'payment_required' ||
        latestStatus?.next_action === 'payment_processing' ||
        latestStatus?.next_action === 'payment_support' ||
        latestStatus?.is_verified
      ) {
        return latestStatus;
      }

      await new Promise(resolve => setTimeout(resolve, 1500));
    }

    return latestStatus;
  };

  const openPaymentCheckout = async () => {
    if (isPaying) {
      return;
    }

    setIsPaying(true);
    let purchaseFlowOpened = false;
    try {
      if (Platform.OS !== 'android') {
        showAlert('Blockrium Verified membership activation is available in the Android app only.', 'INFO');
        return;
      }
      const launch = await createAndLaunchGooglePlayPurchase('kyc');
      if (launch.cancelled) {
        return;
      }
      purchaseFlowOpened = launch.launched;
    } catch (error: any) {
      showAlert(error?.message || 'Unable to open the payment page.', 'ERROR');
    } finally {
      if (!purchaseFlowOpened) {
        setIsPaying(false);
      }
    }
  };

  const handleStartVerification = async () => {
    if (isStarting) {
      return;
    }

    setIsStarting(true);
    try {
      const result = await startVerification();
      if (!result.response.success || !result.data) {
        showAlert(result.response.error || 'Unable to start verification.', 'ERROR');
        return;
      }

      if (result.data.verification_url) {
        await openHostedVerification(result.data.verification_url);
      }

      // Android hands KYC to the system browser. Wait for status when the user returns,
      // rather than polling in the background or launching Play billing over the browser.
      if (Platform.OS === 'android') {
        return;
      }

      await waitForKycResult();
    } catch (error: any) {
      showAlert(error?.message || 'Unable to open Didit verification.', 'ERROR');
    } finally {
      setIsStarting(false);
    }
  };

  const handleRestartVerification = async () => {
    if (isStarting) {
      return;
    }

    setIsStarting(true);
    try {
      const result = await retryVerification();
      if (!result.response.success || !result.data) {
        showAlert(result.response.error || 'Unable to restart verification.', 'ERROR');
        return;
      }
      if (result.data.verification_url) {
        await openHostedVerification(result.data.verification_url);
      }
      await refreshStatus();
    } catch (error: any) {
      showAlert(error?.message || 'Unable to restart Didit verification.', 'ERROR');
    } finally {
      setIsStarting(false);
    }
  };

  const handlePayment = async () => {
    await openPaymentCheckout();
  };

  const handlePaymentSupport = async () => {
    if (isSubmittingSupport) {
      return;
    }

    setIsSubmittingSupport(true);
    try {
      const result = await submitPaymentSupport({
        payment_id: status?.payment?.id,
        provider_reference: supportOrderId.trim() || status?.payment?.provider_payment_id || undefined,
        provider_account_email: supportPlayEmail.trim() || undefined,
        message: supportMessage.trim() || undefined,
        receipt: supportReceipt?.uri ? {uri: supportReceipt.uri, name: supportReceipt.fileName, type: supportReceipt.type} : null,
      });
      if (!result.response.success || !result.data) {
        showAlert(result.response.error || 'Unable to submit payment support.', 'ERROR');
        return;
      }

      setSupportMessage('');
      setSupportOrderId('');
      setSupportPlayEmail('');
      setSupportReceipt(null);
      await refreshStatus();
      showAlert('Payment support request submitted.', 'SUCCESS');
    } catch (error: any) {
      showAlert(error?.message || 'Unable to submit payment support.', 'ERROR');
    } finally {
      setIsSubmittingSupport(false);
    }
  };

  const handleSupportReply = async () => {
    const caseId = latestSupportCase?.id;
    const message = supportReply.trim();
    if (!caseId || message.length < 2 || isSendingSupportReply) {
      return;
    }
    setIsSendingSupportReply(true);
    try {
      const result = await replyToPaymentSupport(caseId, message);
      if (!result.response.success || !result.data) {
        showAlert(result.response.error || 'Unable to send your reply.', 'ERROR');
        return;
      }
      setSupportReply('');
      await refreshStatus();
      showAlert('Your reply was sent to Blockrium Support.', 'SUCCESS');
    } catch (error: any) {
      showAlert(error?.message || 'Unable to send your reply.', 'ERROR');
    } finally {
      setIsSendingSupportReply(false);
    }
  };

  const handleUpdateSupportPaymentDetails = async () => {
    if (!latestSupportCase?.id || (!supportOrderId.trim() && !supportPlayEmail.trim() && !supportReceipt) || isSubmittingSupport) {
      return;
    }
    setIsSubmittingSupport(true);
    try {
      const result = await submitPaymentSupport({
        provider_reference: supportOrderId.trim() || undefined,
        provider_account_email: supportPlayEmail.trim() || undefined,
        receipt: supportReceipt?.uri ? {uri: supportReceipt.uri, name: supportReceipt.fileName, type: supportReceipt.type} : null,
      });
      if (!result.response.success || !result.data) {
        showAlert(result.response.error || 'Unable to update payment details.', 'ERROR');
        return;
      }
      setSupportOrderId('');
      setSupportPlayEmail('');
      setSupportReceipt(null);
      await refreshStatus();
      showAlert('Payment details were added to your open support request.', 'SUCCESS');
    } catch (error: any) {
      showAlert(error?.message || 'Unable to update payment details.', 'ERROR');
    } finally {
      setIsSubmittingSupport(false);
    }
  };

  const chooseSupportReceipt = async () => {
    const result = await launchImageLibrary({mediaType: 'photo', selectionLimit: 1, quality: 0.8});
    if (result.didCancel) return;
    if (result.errorCode) {
      showAlert(result.errorMessage || 'Unable to open your image gallery.', 'ERROR');
      return;
    }
    const asset = result.assets?.[0];
    if (!asset?.uri) return;
    if ((asset.fileSize ?? 0) > 5 * 1024 * 1024) {
      showAlert('Choose a receipt image smaller than 5 MB.', 'ERROR');
      return;
    }
    setSupportReceipt(asset);
  };

  if (isLoading && !status) {
    return (
      <ScreenLoader
        title="Loading verification"
        subtitle="Checking your KYC result, payment, and member benefits."
      />
    );
  }

  if (!status) {
    return (
      <AppScreen contentContainerStyle={styles.contentContainer}>
        <ScreenHeader title="Verification & membership" showBack onBackPress={() => navigation.goBack()} />
        <ScreenState
          icon="shield-outline"
          title="Verification status unavailable"
          message="We could not load your verification status. Please try again."
          actionLabel="Retry"
          onActionPress={() => refetch()}
        />
      </AppScreen>
    );
  }

  const isApproved = status.kyc_status === 'approved';
  const paymentPending = !status.access_suspended && isApproved && status.payment_status !== 'paid';
  const paymentProcessing = status.next_action === 'payment_processing';
  const paymentSupportAvailable = !status.access_suspended && isApproved && status.payment_status !== 'paid';
  const latestSupportCase = status.payment_support?.latest;
  const supportCaseOpen = Boolean(status.payment_support?.open_count);
  const supportStatusLabel = latestSupportCase?.status === 'in_review'
    ? 'In review'
    : latestSupportCase?.status === 'resolved'
      ? 'Resolved'
      : latestSupportCase?.status === 'rejected'
        ? 'Closed'
        : 'Received';
  const canStart =
    !status.access_suspended &&
    !status.is_verified &&
    !paymentPending &&
    status.kyc_status !== 'in_review' &&
    status.kyc_status !== 'country_limit';

  return (
    <AppScreen
      scrollable
      refreshing={Boolean(isFetching)}
      onRefresh={async () => {
        await refreshStatus();
      }}
      contentContainerStyle={styles.contentContainer}>
      <ScreenHeader
        title="Verification & membership"
        subtitle="Your identity status and paid membership are separate"
        showBack
        onBackPress={() => navigation.goBack()}
      />

      <GlassCard style={[styles.heroCard, {borderColor: alpha(copy.tone, 0.42)}]}>
        <View style={styles.cardEyebrow}><Ionicons name="finger-print-outline" size={15} color={copy.tone} /><Text style={[styles.cardEyebrowText, {color: copy.tone}]}>IDENTITY VERIFICATION</Text></View>
        <View style={[styles.heroIcon, {backgroundColor: alpha(copy.tone, 0.16)}]}>
          <Ionicons name={copy.icon as any} size={29} color={copy.tone} />
        </View>
        <View style={styles.statusPill}><View style={[styles.statusDot, {backgroundColor: copy.tone}]} /><Text style={[styles.statusPillText, {color: copy.tone}]}>{copy.label}</Text></View>
        <Text style={styles.heroTitle}>{copy.title}</Text>
        <Text style={styles.heroMessage}>{copy.message}</Text>
        {status.kyc_country ? <Text style={styles.countryText}>Verified country: {status.kyc_country}</Text> : null}
        {canStart ? <PrimaryButton label={status.kyc_status === 'rejected' ? 'Retry KYC' : status.kyc_status === 'not_started' ? 'Start identity verification' : 'Continue verification'} icon={status.kyc_status === 'rejected' ? 'refresh' : 'shield-check'} onPress={status.kyc_status === 'rejected' ? handleRestartVerification : handleStartVerification} loading={isStarting} style={styles.heroButton} /> : null}
        {status.kyc_retry_available && status.kyc_retry_reason === 'stale_session' ? <SecondaryButton label="Restart verification" icon="refresh" onPress={handleRestartVerification} loading={isStarting} style={styles.heroButton} /> : null}
        {status.kyc_status === 'in_review' ? <SecondaryButton label="Refresh result" icon="refresh" onPress={async () => { await refreshStatus(); }} loading={isFetching} style={styles.heroButton} /> : null}
      </GlassCard>

      <GlassCard style={[styles.membershipCard, {borderColor: alpha(status.is_verified ? theme.colors.primary : theme.colors.accentWarm, 0.35)}]}>
        <View style={styles.cardEyebrow}><Ionicons name="ribbon-outline" size={16} color={status.is_verified ? theme.colors.primary : theme.colors.accentWarm} /><Text style={[styles.cardEyebrowText, {color: status.is_verified ? theme.colors.primary : theme.colors.accentWarm}]}>BLOCKRIUM VERIFIED MEMBERSHIP</Text></View>
        <View style={styles.membershipHeading}><View style={styles.membershipIcon}><Ionicons name={status.is_verified ? 'checkmark-circle' : 'card-outline'} size={21} color={status.is_verified ? theme.colors.primary : theme.colors.accentWarm} /></View><View style={styles.membershipCopy}><Text style={styles.membershipTitle}>{status.is_verified ? 'Membership active' : status.access_suspended ? 'Membership access suspended' : paymentProcessing ? 'Payment processing' : status.payment_status === 'refunded' ? 'Payment refunded' : status.payment_status === 'revoked' ? 'Membership revoked' : status.payment_status === 'paid' ? 'Payment recorded · identity review pending' : isApproved ? 'Optional one-time membership' : 'Available after identity approval'}</Text><Text style={styles.membershipMessage}>{status.is_verified ? 'Your Blockrium Verified badge and member benefits are active.' : status.access_suspended ? 'Member benefits are currently locked. Your KYC result remains separate from membership access.' : paymentProcessing ? 'Google Play is confirming the order. Benefits stay locked until the server confirms payment.' : status.payment_status === 'refunded' || status.payment_status === 'revoked' ? 'Member benefits are locked. Your KYC approval and payment history remain recorded.' : status.payment_status === 'paid' ? 'Your payment is recorded. Complete identity review to activate membership. Do not pay again.' : isApproved ? 'Your identity is verified. Membership has not been activated. The one-time payment is for membership, not KYC.' : 'This paid membership is separate from the KYC identity check.'}</Text></View></View>
        {paymentPending && isApproved && !status.access_suspended ? Platform.OS === 'android' ? <PrimaryButton label={status.payment_status === 'refunded' || status.payment_status === 'revoked' ? 'Activate membership again' : 'Activate Blockrium Verified'} icon="credit-card-outline" onPress={handlePayment} loading={isPaying} style={styles.heroButton} /> : <Text style={styles.membershipMessage}>Membership activation is available in the Android app.</Text> : null}
        {paymentProcessing ? <SecondaryButton label="Refresh payment status" icon="refresh" onPress={async () => { await refreshStatus(); }} loading={isFetching} style={styles.heroButton} /> : null}
      </GlassCard>

      {paymentSupportAvailable && !supportCaseOpen ? <GlassCard style={styles.supportCard}>
        <View style={styles.sectionHeading}><Ionicons name="help-buoy-outline" size={20} color={theme.colors.accentWarm} /><Text style={styles.sectionTitle}>Payment support</Text></View>
        <Text style={styles.supportText}>If Google Play charged you but membership still shows pending, submit the Google Play order ID from your receipt (it starts with GPA.). The request is tied to your signed-in Blockrium account. Never send card details or a purchase token.</Text>
        <AppTextField label="Google Play order ID (GPA…)" value={supportOrderId} onChangeText={setSupportOrderId} placeholder="GPA.3341-4998-6152-94375" autoCapitalize="characters" maxLength={64} />
        <AppTextField label="Google Play account email (optional)" value={supportPlayEmail} onChangeText={setSupportPlayEmail} placeholder="Email used for the Play purchase" autoCapitalize="none" keyboardType="email-address" maxLength={190} />
        <AppTextField label="Optional message" value={supportMessage} onChangeText={setSupportMessage} placeholder="Membership payment was charged, but access is still pending" multiline numberOfLines={3} maxLength={1000} />
        <SecondaryButton label={supportReceipt ? `Receipt: ${supportReceipt.fileName || 'image selected'}` : 'Attach receipt image (optional)'} icon="image-outline" onPress={chooseSupportReceipt} />
        {supportReceipt ? <SecondaryButton label="Remove attached image" icon="close-outline" onPress={() => setSupportReceipt(null)} /> : null}
        <SecondaryButton label="Report payment issue" icon="flag-outline" onPress={handlePaymentSupport} loading={isSubmittingSupport} />
      </GlassCard> : null}

      {latestSupportCase ? <GlassCard style={styles.supportCard}>
        <View style={styles.sectionHeading}>
          <Ionicons name={supportCaseOpen ? 'time-outline' : latestSupportCase.status === 'resolved' ? 'checkmark-circle-outline' : 'chatbox-ellipses-outline'} size={20} color={supportCaseOpen ? theme.colors.accentWarm : theme.colors.primary} />
          <Text style={styles.sectionTitle}>Payment support · {supportStatusLabel}</Text>
        </View>
        <Text style={styles.supportText}>
          {supportCaseOpen
            ? 'Our team is reviewing your payment report.'
            : latestSupportCase.status === 'resolved'
              ? 'This support request has been resolved.'
              : latestSupportCase.status === 'rejected'
                ? 'This request was closed. Contact support again if the issue remains.'
                : 'Your payment report has been received.'}
        </Text>
        {latestSupportCase.provider_reference ? <Text style={styles.timelineText}>Google Play order: {latestSupportCase.provider_reference}</Text> : null}
        {latestSupportCase.provider_account_email ? <Text style={styles.timelineText}>Play account email: {latestSupportCase.provider_account_email}</Text> : null}
        {supportCaseOpen ? <View style={styles.supportDetails}>
          <Text style={styles.timelineText}>Add or correct your Google Play order details so support can match the receipt.</Text>
          <AppTextField label="Google Play order ID (GPA…)" value={supportOrderId} onChangeText={setSupportOrderId} placeholder="GPA.3341-4998-6152-94375" autoCapitalize="characters" maxLength={64} />
          <AppTextField label="Google Play account email (optional)" value={supportPlayEmail} onChangeText={setSupportPlayEmail} placeholder="Email used for the Play purchase" autoCapitalize="none" keyboardType="email-address" maxLength={190} />
          <SecondaryButton label={supportReceipt ? `Receipt: ${supportReceipt.fileName || 'image selected'}` : 'Attach receipt image (optional)'} icon="image-outline" onPress={chooseSupportReceipt} />
          <SecondaryButton label="Update payment details" icon="save-outline" onPress={handleUpdateSupportPaymentDetails} loading={isSubmittingSupport} />
        </View> : null}
        {latestSupportCase.messages?.map(message => <View key={message.id} style={[styles.supportMessage, message.sender_type === 'user' ? styles.supportMessageUser : styles.supportMessageAdmin]}>
          <Text style={styles.supportMessageSender}>{message.sender_type === 'user' ? 'You' : 'Blockrium Support'}</Text>
          <Text style={styles.supportMessageText}>{message.message}</Text>
          {message.created_at ? <Text style={styles.timelineText}>{new Date(message.created_at).toLocaleString()}</Text> : null}
        </View>)}
        {supportCaseOpen ? <>
          <AppTextField label="Reply to support" value={supportReply} onChangeText={setSupportReply} placeholder="Add information or answer our support team" multiline numberOfLines={3} maxLength={2000} />
          <SecondaryButton label="Send reply" icon="send-outline" onPress={handleSupportReply} loading={isSendingSupportReply} />
        </> : null}
        <Text style={styles.timelineText}>Request #{latestSupportCase.id}{latestSupportCase.created_at ? ` · ${new Date(latestSupportCase.created_at).toLocaleDateString()}` : ''}</Text>
      </GlassCard> : null}

      <GlassCard style={styles.timelineCard}>
        <View style={styles.sectionHeading}><MaterialCommunityIcons name="timeline-check-outline" size={20} color={theme.colors.primary} /><Text style={styles.sectionTitle}>Verification journey</Text></View>
        <View style={styles.timelineRow}><View style={[styles.timelineIcon, status.kyc_status !== 'not_started' && styles.timelineIconDone]}><Ionicons name={status.kyc_status !== 'not_started' ? 'checkmark' : 'ellipse-outline'} size={16} color={status.kyc_status !== 'not_started' ? theme.colors.background : theme.colors.textSoft} /></View><View style={styles.timelineCopy}><Text style={styles.timelineTitle}>Identity review</Text><Text style={styles.timelineText}>{status.didit_status || 'Not started'}</Text></View></View>
        <View style={styles.timelineLine} />
        <View style={styles.timelineRow}><View style={[styles.timelineIcon, isApproved && styles.timelineIconDone]}><Ionicons name={isApproved ? 'checkmark' : 'ellipse-outline'} size={16} color={isApproved ? theme.colors.background : theme.colors.textSoft} /></View><View style={styles.timelineCopy}><Text style={styles.timelineTitle}>Blockrium Verified membership</Text><Text style={styles.timelineText}>{status.access_suspended ? 'Payment recorded; access suspended' : status.payment_status === 'paid' ? 'Active' : isApproved ? 'One-time payment pending' : 'Available after KYC approval'}</Text></View></View>
        <View style={styles.timelineLine} />
        <View style={styles.timelineRow}><View style={[styles.timelineIcon, status.is_verified && styles.timelineIconDone]}><Ionicons name={status.is_verified ? 'checkmark' : 'ellipse-outline'} size={16} color={status.is_verified ? theme.colors.background : theme.colors.textSoft} /></View><View style={styles.timelineCopy}><Text style={styles.timelineTitle}>Verified member access</Text><Text style={styles.timelineText}>{status.access_suspended ? 'Suspended by Blockrium' : status.is_verified ? 'Active' : 'Locked until both steps are complete'}</Text></View></View>
      </GlassCard>

      <GlassCard style={styles.benefitsCard}>
        <View style={styles.sectionHeading}><Ionicons name="sparkles-outline" size={20} color={theme.colors.primary} /><Text style={styles.sectionTitle}>Member benefits</Text></View>
        {[
          ['BRC username and transfers', Boolean(status.benefits?.brc_username_transfers)],
          ['Wallet early access', Boolean(status.benefits?.wallet_early_access)],
          ['Mining boost eligibility', Boolean(status.benefits?.mining_boost_eligibility)],
          ['Presale access', Boolean(status.benefits?.presale_access)],
        ].map(([label, enabled]) => <View key={String(label)} style={styles.benefitRow}><Ionicons name={enabled ? 'checkmark-circle' : 'lock-closed'} size={19} color={enabled ? theme.colors.primary : theme.colors.textSoft} /><Text style={[styles.benefitText, !enabled && styles.benefitTextLocked]}>{label}</Text><Text style={[styles.benefitState, {color: enabled ? theme.colors.primary : theme.colors.textSoft}]}>{enabled ? 'Unlocked' : 'Locked'}</Text></View>)}
      </GlassCard>


    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {paddingTop: 6, paddingBottom: 132},
  heroCard: {alignItems: 'center', paddingVertical: 18},
  cardEyebrow: {alignSelf: 'stretch', flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7, marginBottom: 12},
  cardEyebrowText: {fontSize: 10, letterSpacing: 1, fontFamily: theme.typography.bodyBold},
  membershipCard: {paddingVertical: 17},
  membershipHeading: {flexDirection: 'row', alignItems: 'flex-start', gap: 12},
  membershipIcon: {width: 42, height: 42, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: alpha(theme.colors.surfaceStrong, 0.8), borderWidth: 1, borderColor: theme.colors.border},
  membershipCopy: {flex: 1},
  membershipTitle: {color: theme.colors.text, fontSize: 16, lineHeight: 21, fontFamily: theme.typography.heading},
  membershipMessage: {marginTop: 5, color: theme.colors.textMuted, fontSize: 13, lineHeight: 19, fontFamily: theme.typography.body},
  heroIcon: {width: 52, height: 52, borderRadius: 18, alignItems: 'center', justifyContent: 'center', marginBottom: 10},
  statusPill: {flexDirection: 'row', alignItems: 'center', gap: 7, paddingHorizontal: 12, paddingVertical: 7, borderRadius: theme.radii.pill, backgroundColor: alpha(theme.colors.surfaceStrong, 0.7), borderWidth: 1, borderColor: theme.colors.border},
  statusDot: {width: 7, height: 7, borderRadius: 4},
  statusPillText: {fontSize: 12, fontFamily: theme.typography.bodyBold},
  heroTitle: {marginTop: 12, color: theme.colors.text, fontSize: 21, lineHeight: 26, textAlign: 'center', fontFamily: theme.typography.display},
  heroMessage: {marginTop: 7, color: theme.colors.textMuted, fontSize: 13, lineHeight: 19, textAlign: 'center', fontFamily: theme.typography.body},
  countryText: {marginTop: 10, color: theme.colors.textSoft, fontSize: 12, fontFamily: theme.typography.bodyMedium},
  heroButton: {alignSelf: 'stretch', marginTop: 18},
  timelineCard: {paddingVertical: 18},
  sectionHeading: {flexDirection: 'row', alignItems: 'center', gap: 9, marginBottom: 16},
  sectionTitle: {color: theme.colors.text, fontSize: 17, fontFamily: theme.typography.heading},
  timelineRow: {flexDirection: 'row', alignItems: 'center', gap: 12},
  timelineIcon: {width: 30, height: 30, borderRadius: 15, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: theme.colors.border, backgroundColor: alpha(theme.colors.surfaceStrong, 0.6)},
  timelineIconDone: {borderColor: theme.colors.primary, backgroundColor: theme.colors.primary},
  timelineCopy: {flex: 1},
  timelineTitle: {color: theme.colors.text, fontSize: 14, fontFamily: theme.typography.bodyBold},
  timelineText: {marginTop: 3, color: theme.colors.textMuted, fontSize: 12, fontFamily: theme.typography.body},
  timelineLine: {height: 18, width: 1, marginLeft: 15, backgroundColor: theme.colors.border},
  benefitsCard: {paddingVertical: 18},
  benefitRow: {minHeight: 42, flexDirection: 'row', alignItems: 'center', gap: 10, borderTopWidth: 1, borderTopColor: alpha(theme.colors.border, 0.5)},
  benefitText: {flex: 1, color: theme.colors.text, fontSize: 13, fontFamily: theme.typography.bodyMedium},
  benefitTextLocked: {color: theme.colors.textMuted},
  benefitState: {fontSize: 11, fontFamily: theme.typography.bodyBold},
  supportCard: {paddingVertical: 18},
  supportText: {color: theme.colors.textMuted, fontSize: 13, lineHeight: 19, fontFamily: theme.typography.body, marginBottom: 14},
  supportMessage: {borderRadius: 14, padding: 12, marginBottom: 10, borderWidth: 1, borderColor: alpha(theme.colors.primary, 0.16)},
  supportMessageUser: {backgroundColor: alpha(theme.colors.primary, 0.08), alignSelf: 'flex-end', width: '94%'},
  supportMessageAdmin: {backgroundColor: alpha(theme.colors.surface, 0.72), alignSelf: 'flex-start', width: '94%'},
  supportMessageSender: {color: theme.colors.text, fontSize: 12, fontWeight: '700', marginBottom: 4},
  supportMessageText: {color: theme.colors.text, fontSize: 14, lineHeight: 20, fontFamily: theme.typography.body},
  supportDetails: {marginTop: 14},
});

export default VerifiedMember;
