import React, {useCallback, useEffect, useRef, useState} from 'react';
import {Alert, Pressable, StyleSheet, Text, View} from 'react-native';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import {useQueryClient} from 'react-query';
import Ionicons from 'react-native-vector-icons/Ionicons';
import MaterialCommunityIcons from 'react-native-vector-icons/MaterialCommunityIcons';
import {callQuery} from '../../hooks';
import {useThisAlert} from '../../hooks/AlertContext';
import {GlassCard, PrimaryButton} from '../../design/primitives';
import theme, {alpha} from '../../design/theme';
import {
  checkBrcUsernameAvailability,
  createBrcUsername,
  getBrcTransfers,
  transferBrc,
} from '../../services/verificationService';
import {BrcTransfer, VerificationStatus} from '../../types/verification';
import {formatBrcAmount} from '../../utils/formatBrcAmount';
import {getStorageId} from '../../utils/mmkvStorage';
import {parseVerificationStatusResponse} from '../../utils/verificationStatusResponse';
import {getBrcMemberAccessCopy} from '../../utils/verifiedMemberAccessCopy';
import {
  calculateBrcTransferQuote,
  canCoverBrcTransfer,
} from '../../utils/brcTransferAmounts';
import {getBrcTransferHistoryState} from '../../utils/brcTransferHistoryState';
import {getBrcTransferFeeSummary} from '../../utils/brcTransferDisplay';
import {navigate as appNavigate} from '../../utils/helper';
import {
  WalletInputGroup,
  WalletModeLayout,
} from './WalletComponents';
import {WalletStackParamList} from './WalletScreens';
import {
  clearPendingBrcTransfer,
  getPendingBrcTransfer,
  isDefinitiveTransferRejection,
  PendingBrcTransfer,
  savePendingBrcTransfer,
} from '../../services/pendingBrcTransfer';

type Props = NativeStackScreenProps<WalletStackParamList, 'WalletBrcTransfer'>;

const createIdempotencyKey = () =>
  `brc-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;

const WalletBrcTransferScreen = ({navigation}: Props) => {
  const {showAlert} = useThisAlert();
  const queryClient = useQueryClient();
  const submittingRef = useRef(false);
  const {
    data: statusResponse,
    isLoading: isLoadingStatus,
    isError: isStatusError,
    refetch: refetchStatus,
  }: any = callQuery('auth/verification/status', {staleTime: 15000, retry: 0});
  const statusResult = parseVerificationStatusResponse(statusResponse);
  const status = statusResult.status ?? ({} as VerificationStatus);
  const accessCopy = getBrcMemberAccessCopy(Boolean(status.access_suspended));
  const statusUnavailable = isStatusError || !statusResult.status;
  const balance = status.brc_transfer_balance ?? '0.00000000';
  const [usernameInput, setUsernameInput] = useState('');
  const [usernameAvailable, setUsernameAvailable] = useState<boolean | null>(null);
  const [isCheckingUsername, setIsCheckingUsername] = useState(false);
  const [recipient, setRecipient] = useState('');
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [transfers, setTransfers] = useState<BrcTransfer[]>([]);
  const [isLoadingTransfers, setIsLoadingTransfers] = useState(false);
  const [transfersUnavailable, setTransfersUnavailable] = useState(false);
  const [isSavingUsername, setIsSavingUsername] = useState(false);
  const [isSending, setIsSending] = useState(false);
  const [pendingTransfer, setPendingTransfer] = useState<PendingBrcTransfer | null>(null);
  const [isRestoringTransfer, setIsRestoringTransfer] = useState(true);

  const refreshTransfers = useCallback(async () => {
    if (!status.is_verified) {
      setTransfers([]);
      setTransfersUnavailable(false);
      return;
    }
    setIsLoadingTransfers(true);
    try {
      const result = await getBrcTransfers();
      if (result.response.success) {
        setTransfers(result.data);
        setTransfersUnavailable(false);
      } else {
        setTransfersUnavailable(true);
      }
    } catch {
      setTransfersUnavailable(true);
    } finally {
      setIsLoadingTransfers(false);
    }
  }, [status.is_verified]);

  const transferHistoryState = getBrcTransferHistoryState(
    isLoadingTransfers,
    transfersUnavailable,
    transfers.length,
  );

  useEffect(() => {
    refetchStatus();
  }, [refetchStatus]);

  useEffect(() => {
    let active = true;
    if (!status.is_verified) {
      setPendingTransfer(null);
      setIsRestoringTransfer(false);
      return () => {
        active = false;
      };
    }

    setIsRestoringTransfer(true);
    getStorageId('userId')
      .then(userId => userId ? getPendingBrcTransfer(userId) : null)
      .then(intent => {
        if (!active || !intent) {
          return;
        }
        setPendingTransfer(intent);
        setRecipient(intent.recipient_username);
        setAmount(intent.amount);
        setNote(intent.note ?? '');
      })
      .catch(() => undefined)
      .finally(() => {
        if (active) {
          setIsRestoringTransfer(false);
        }
      });

    return () => {
      active = false;
    };
  }, [status.is_verified]);

  useEffect(() => {
    refreshTransfers().catch(() => undefined);
  }, [refreshTransfers]);

  useEffect(() => {
    let active = true;
    const username = usernameInput.trim().toLowerCase();
    setUsernameAvailable(null);
    setIsCheckingUsername(false);

    if (!status.is_verified || status.username || !/^[a-z0-9_]{4,20}$/.test(username)) {
      return () => {
        active = false;
      };
    }

    const timeout = setTimeout(async () => {
      setIsCheckingUsername(true);
      try {
        const result = await checkBrcUsernameAvailability(username);
        if (active) {
          setUsernameAvailable(result.response.success ? result.data : null);
        }
      } catch {
        if (active) {
          setUsernameAvailable(null);
        }
      } finally {
        if (active) {
          setIsCheckingUsername(false);
        }
      }
    }, 400);

    return () => {
      active = false;
      clearTimeout(timeout);
    };
  }, [status.is_verified, status.username, usernameInput]);

  const feeAndTotal = calculateBrcTransferQuote(amount);

  const createUsername = async () => {
    const username = usernameInput.trim().toLowerCase();
    if (!/^[a-z0-9_]{4,20}$/.test(username)) {
      showAlert('Use 4-20 lowercase letters, numbers, or underscores.', 'ERROR');
      return;
    }
    if (usernameAvailable === false) {
      showAlert('That username is already taken.', 'ERROR');
      return;
    }

    setIsSavingUsername(true);
    try {
      const result = await createBrcUsername(username);
      if (!result.response.success || !result.data) {
        showAlert(result.response.error || 'Unable to create your BRC username.', 'ERROR');
        return;
      }
      setUsernameInput('');
      setUsernameAvailable(null);
      await refetchStatus();
      showAlert(`@${username} is ready for Blockrium transfers.`, 'SUCCESS');
    } catch (error: any) {
      showAlert(error?.message || 'Unable to create your BRC username.', 'ERROR');
    } finally {
      setIsSavingUsername(false);
    }
  };

  const submitTransfer = async () => {
    if (submittingRef.current || isSending || isRestoringTransfer) {
      return;
    }
    const normalizedRecipient = pendingTransfer?.recipient_username ?? recipient.trim().toLowerCase();
    const transferAmount = pendingTransfer?.amount ?? amount;
    const transferNote = pendingTransfer?.note ?? (note.trim() || undefined);
    if (!/^[a-z0-9_]{4,20}$/.test(normalizedRecipient)) {
      showAlert('Enter a valid recipient username.', 'ERROR');
      return;
    }
    if (normalizedRecipient === status.username?.toLowerCase()) {
      showAlert('You cannot transfer BRC to yourself.', 'ERROR');
      return;
    }
    const quote = calculateBrcTransferQuote(transferAmount);
    if (!quote) {
      showAlert('Enter an amount of at least 0.00000050 BRC in 0.00000050 BRC steps.', 'ERROR');
      return;
    }
    if (!pendingTransfer && !canCoverBrcTransfer(balance, quote)) {
      showAlert('Your available BRC balance does not cover the amount plus fee.', 'ERROR');
      return;
    }

    setIsSending(true);
    submittingRef.current = true;
    let intent = pendingTransfer;
    let intentPersisted = Boolean(pendingTransfer);
    try {
      if (!intent) {
        const userId = await getStorageId('userId');
        if (!userId) {
          showAlert('Please sign in again before sending BRC.', 'ERROR');
          return;
        }
        intent = {
          recipient_username: normalizedRecipient,
          amount: quote.amount,
          idempotency_key: createIdempotencyKey(),
          note: transferNote,
          user_id: userId,
        };
        await savePendingBrcTransfer(intent);
        intentPersisted = true;
        setPendingTransfer(intent);
      }

      const result = await transferBrc({
        recipient_username: intent.recipient_username,
        amount: intent.amount,
        idempotency_key: intent.idempotency_key,
        note: intent.note,
      });
      if (!result.response.success || !result.data) {
        if (isDefinitiveTransferRejection(result.response.status)) {
          await clearPendingBrcTransfer(intent.user_id, intent.idempotency_key);
          setPendingTransfer(null);
        } else {
          showAlert(
            'We could not confirm the transfer result. Your request is saved on this device; retry it here to check without creating a duplicate.',
            'ERROR',
          );
          return;
        }
        showAlert(result.response.error || 'Unable to complete this transfer.', 'ERROR');
        return;
      }
      await clearPendingBrcTransfer(intent.user_id, intent.idempotency_key);
      setPendingTransfer(null);
      setRecipient('');
      setAmount('');
      setNote('');
      await Promise.allSettled([
        refetchStatus(),
        refreshTransfers(),
        queryClient.invalidateQueries(['auth/wallet', 'GET']),
      ]);
      showAlert(
        `${formatBrcAmount(result.data.amount)} BRC sent to @${normalizedRecipient}. Fee: ${formatBrcAmount(result.data.fee_amount ?? '0')} BRC.`,
        'SUCCESS',
      );
    } catch (error: any) {
      showAlert(
        intentPersisted
          ? 'We could not confirm the transfer result. Your request is saved on this device; retry it here to check without creating a duplicate.'
          : 'Could not save this transfer request on your device. No transfer was sent; please try again.',
        'ERROR',
      );
    } finally {
      submittingRef.current = false;
      setIsSending(false);
    }
  };

  const confirmTransfer = () => {
    const normalizedRecipient = recipient.trim().toLowerCase();
    if (!/^[a-z0-9_]{4,20}$/.test(normalizedRecipient)) {
      showAlert('Enter a valid recipient username.', 'ERROR');
      return;
    }
    if (normalizedRecipient === status.username?.toLowerCase()) {
      showAlert('You cannot transfer BRC to yourself.', 'ERROR');
      return;
    }
    if (!feeAndTotal) {
      showAlert('Enter a valid amount first.', 'ERROR');
      return;
    }
    Alert.alert(
      'Confirm BRC transfer',
      `${formatBrcAmount(feeAndTotal.amount)} BRC to @${normalizedRecipient}\nBlockrium fee (exactly 2%): ${formatBrcAmount(feeAndTotal.fee)} BRC\nTotal debit: ${formatBrcAmount(feeAndTotal.totalDebit)} BRC`,
      [
        {text: 'Cancel', style: 'cancel'},
        {text: 'Transfer', onPress: submitTransfer},
      ],
    );
  };

  if (isLoadingStatus) {
    return (
      <WalletModeLayout title="BRC transfer" showBack onBackPress={() => navigation.goBack()}>
        <GlassCard style={styles.loadingCard}>
          <Text style={styles.bodyText}>Loading verified wallet…</Text>
        </GlassCard>
      </WalletModeLayout>
    );
  }

  if (statusUnavailable) {
    return (
      <WalletModeLayout title="BRC transfer" showBack onBackPress={() => navigation.goBack()}>
        <GlassCard style={styles.lockedCard}>
          <Text style={styles.cardTitle}>Transfer details unavailable</Text>
          <Text style={styles.bodyText}>{statusResult.error ?? 'We could not confirm your membership or BRC balance. Please try again.'}</Text>
          <PrimaryButton label="Retry" icon="refresh" onPress={() => refetchStatus()} style={styles.cta} />
        </GlassCard>
      </WalletModeLayout>
    );
  }

  return (
    <WalletModeLayout
      title="BRC transfer"
      subtitle="Send BRC to another verified Blockrium member."
      showBack
      onBackPress={() => navigation.goBack()}
      contentContainerStyle={styles.content}>
      {!status.is_verified ? (
        <GlassCard style={styles.lockedCard}>
          <View style={styles.iconBadge}>
            <Ionicons name="lock-closed-outline" size={22} color={theme.colors.primary} />
          </View>
          <Text style={styles.cardTitle}>{accessCopy.title}</Text>
          <Text style={styles.bodyText}>{accessCopy.message}</Text>
          <PrimaryButton
            label="View verification"
            icon="shield-check"
            onPress={() => appNavigate('VerifiedMember')}
            style={styles.cta}
          />
        </GlassCard>
      ) : (
        <>
          <GlassCard style={styles.balanceCard}>
            <Text style={styles.balanceLabel}>Available BRC</Text>
            <Text style={styles.balanceValue}>{formatBrcAmount(balance)} BRC</Text>
            <Text style={styles.balanceCaption}>Blockrium account balance</Text>
          </GlassCard>

          <GlassCard style={styles.formCard}>
            <View style={styles.formHeader}>
              <View style={styles.iconBadge}>
                <MaterialCommunityIcons name="account-arrow-right-outline" size={22} color={theme.colors.primary} />
              </View>
              <View style={styles.formHeaderCopy}>
                <Text style={styles.cardTitle}>Send to a member</Text>
                <Text style={styles.bodyText}>Your username: {status.username ? `@${status.username}` : 'not set'}</Text>
              </View>
            </View>

            {isRestoringTransfer ? (
              <Text style={styles.bodyText}>Checking for a pending transfer…</Text>
            ) : pendingTransfer ? (
              <View style={styles.pendingTransfer}>
                <Text style={styles.cardTitle}>Transfer awaiting confirmation</Text>
                <Text style={styles.bodyText}>
                  {formatBrcAmount(pendingTransfer.amount)} BRC to @{pendingTransfer.recipient_username}
                  {pendingTransfer.note ? `\nNote: ${pendingTransfer.note}` : ''}
                </Text>
                <Text style={styles.feeNote}>
                  This request is saved on this device. Retry checks it with the original reference to prevent a duplicate transfer.
                </Text>
                <PrimaryButton
                  label="Retry pending transfer"
                  icon="refresh"
                  onPress={submitTransfer}
                  loading={isSending}
                  disabled={isSending}
                  style={styles.cta}
                />
              </View>
            ) : !status.username ? (
              <>
                <WalletInputGroup
                  label="Choose your BRC username"
                  value={usernameInput}
                  onChangeText={setUsernameInput}
                  placeholder="your_username"
                  autoCapitalize="none"
                  maxLength={20}
                />
                <Text style={[
                  styles.availability,
                  usernameAvailable === true && styles.available,
                  usernameAvailable === false && styles.unavailable,
                ]}>
                  {isCheckingUsername ? 'Checking availability…' : usernameAvailable === true ? 'Username is available' : usernameAvailable === false ? 'Username is taken' : '4-20 letters, numbers, or underscores'}
                </Text>
                <PrimaryButton
                  label="Create username"
                  icon="account-plus-outline"
                  onPress={createUsername}
                  loading={isSavingUsername}
                  disabled={isCheckingUsername || usernameAvailable === false}
                  style={styles.cta}
                />
              </>
            ) : (
              <>
                <WalletInputGroup
                  label="Recipient username"
                  value={recipient}
                  onChangeText={value => setRecipient(value.replace(/^@/, '').toLowerCase())}
                  placeholder="username"
                  autoCapitalize="none"
                  maxLength={20}
                />
                <WalletInputGroup
                  label="Amount recipient receives"
                  value={amount}
                  onChangeText={setAmount}
                  placeholder="0.00 BRC"
                  keyboardType="decimal-pad"
                  autoCapitalize="none"
                  maxLength={30}
                />
                <WalletInputGroup
                  label="Note (optional)"
                  value={note}
                  onChangeText={setNote}
                  placeholder="Add a note"
                  maxLength={500}
                />
                <View style={styles.feeSummary}>
                  <FeeRow label="Recipient receives" value={`${formatBrcAmount(feeAndTotal?.amount ?? '0')} BRC`} />
                  <FeeRow label="Blockrium fee" value={`${formatBrcAmount(feeAndTotal?.fee ?? '0')} BRC`} />
                  <Text style={styles.feeNote}>Exact 2% fee on top. Amounts must use 0.00000050 BRC steps.</Text>
                  <View style={styles.totalDivider} />
                  <FeeRow emphasized label="Total debited" value={`${formatBrcAmount(feeAndTotal?.totalDebit ?? '0')} BRC`} />
                </View>
                <PrimaryButton
                  label="Review transfer"
                  icon="arrow-up-outline"
                  onPress={confirmTransfer}
                  loading={isSending}
                  disabled={!feeAndTotal || isSending}
                  style={styles.cta}
                />
              </>
            )}
          </GlassCard>

          <GlassCard style={styles.historyCard}>
            <View style={styles.historyHeader}>
              <Text style={styles.cardTitle}>Recent transfers</Text>
              <Pressable
                accessibilityRole="button"
                accessibilityLabel="Refresh transfer history"
                disabled={isLoadingTransfers}
                onPress={() => refreshTransfers()}>
                <Ionicons name="refresh-outline" size={20} color={theme.colors.primary} />
              </Pressable>
            </View>
            {transferHistoryState === 'loading' ? (
              <Text style={styles.bodyText}>Loading transfers…</Text>
            ) : transferHistoryState === 'unavailable' ? (
              <Text style={styles.bodyText}>Transfer history could not be loaded. Tap refresh to try again.</Text>
            ) : transferHistoryState === 'empty' ? (
              <Text style={styles.bodyText}>No transfers yet.</Text>
            ) : (
              <>
                {transferHistoryState === 'stale' ? (
                  <Text style={styles.bodyText}>Could not refresh history. Showing the activity already loaded.</Text>
                ) : null}
                {transfers.map(item => (
                  <TransferRow key={String(item.id)} transfer={item} />
                ))}
              </>
            )}
          </GlassCard>
        </>
      )}
    </WalletModeLayout>
  );
};

const FeeRow = ({label, value, emphasized = false}: {label: string; value: string; emphasized?: boolean}) => (
  <View style={styles.feeRow}>
    <Text style={[styles.feeLabel, emphasized && styles.totalLabel]}>{label}</Text>
    <Text style={[styles.feeValue, emphasized && styles.totalValue]}>{value}</Text>
  </View>
);

const TransferRow = ({transfer}: {transfer: BrcTransfer}) => {
  const isSent = transfer.direction === 'sent';
  const counterparty = isSent ? transfer.recipient_username : transfer.sender_username;
  const primaryValue = isSent ? transfer.total_debit ?? transfer.amount : transfer.amount;
  return (
    <View style={styles.transferRow}>
      <View style={[styles.transferIcon, {backgroundColor: alpha(isSent ? theme.colors.accentWarm : theme.colors.primary, 0.14)}]}>
        <Ionicons name={isSent ? 'arrow-up-outline' : 'arrow-down-outline'} size={17} color={isSent ? theme.colors.accentWarm : theme.colors.primary} />
      </View>
      <View style={styles.transferCopy}>
        <Text style={styles.transferName}>{isSent ? 'To' : 'From'} @{counterparty || 'member'}</Text>
        <Text style={styles.transferMeta}>{transfer.created_at ? new Date(transfer.created_at).toLocaleDateString() : 'Date unavailable'} · {transfer.status}</Text>
        {getBrcTransferFeeSummary(transfer, isSent) ? (
          <Text style={styles.transferMeta}>
            {getBrcTransferFeeSummary(transfer, isSent)}
          </Text>
        ) : null}
      </View>
      <Text style={[styles.transferAmount, {color: isSent ? theme.colors.accentWarm : theme.colors.primary}]}>
        {isSent ? '−' : '+'}{formatBrcAmount(primaryValue)} BRC
      </Text>
    </View>
  );
};

const styles = StyleSheet.create({
  content: {paddingTop: 4, paddingBottom: 128, gap: 14},
  loadingCard: {paddingVertical: 24},
  lockedCard: {alignItems: 'center', paddingVertical: 24},
  balanceCard: {paddingVertical: 18},
  formCard: {paddingVertical: 18},
  pendingTransfer: {padding: 14, borderRadius: theme.radii.md, backgroundColor: alpha(theme.colors.surfaceStrong, 0.65), borderWidth: 1, borderColor: theme.colors.border},
  historyCard: {paddingVertical: 18},
  iconBadge: {width: 46, height: 46, borderRadius: 16, alignItems: 'center', justifyContent: 'center', backgroundColor: alpha(theme.colors.primary, 0.13)},
  formHeader: {flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 16},
  formHeaderCopy: {flex: 1},
  cardTitle: {color: theme.colors.text, fontSize: 16, fontFamily: theme.typography.heading},
  bodyText: {marginTop: 5, color: theme.colors.textMuted, fontSize: 13, lineHeight: 19, fontFamily: theme.typography.body},
  balanceLabel: {color: theme.colors.textMuted, fontSize: 12, letterSpacing: 0.7, textTransform: 'uppercase', fontFamily: theme.typography.bodyBold},
  balanceValue: {marginTop: 7, color: theme.colors.text, fontSize: 29, fontFamily: theme.typography.display},
  balanceCaption: {marginTop: 4, color: theme.colors.textSoft, fontSize: 12, fontFamily: theme.typography.body},
  availability: {marginTop: -6, marginBottom: 12, color: theme.colors.textSoft, fontSize: 12, fontFamily: theme.typography.body},
  available: {color: theme.colors.primary},
  unavailable: {color: theme.colors.danger},
  feeSummary: {marginTop: 2, padding: 14, borderRadius: theme.radii.md, backgroundColor: alpha(theme.colors.surfaceStrong, 0.65), borderWidth: 1, borderColor: theme.colors.border},
  feeNote: {marginTop: 3, color: theme.colors.textSoft, fontSize: 10, lineHeight: 15, fontFamily: theme.typography.body},
  feeRow: {minHeight: 27, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12},
  feeLabel: {color: theme.colors.textMuted, fontSize: 12, fontFamily: theme.typography.body},
  feeValue: {color: theme.colors.text, fontSize: 12, fontFamily: theme.typography.bodyMedium},
  totalDivider: {height: 1, marginVertical: 7, backgroundColor: theme.colors.border},
  totalLabel: {color: theme.colors.text, fontFamily: theme.typography.bodyBold},
  totalValue: {color: theme.colors.primary, fontFamily: theme.typography.bodyBold},
  cta: {marginTop: 14},
  historyHeader: {flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 4},
  transferRow: {minHeight: 65, flexDirection: 'row', alignItems: 'center', gap: 10, borderTopWidth: 1, borderTopColor: alpha(theme.colors.border, 0.55)},
  transferIcon: {width: 34, height: 34, borderRadius: 17, alignItems: 'center', justifyContent: 'center'},
  transferCopy: {flex: 1},
  transferName: {color: theme.colors.text, fontSize: 13, fontFamily: theme.typography.bodyBold},
  transferMeta: {marginTop: 3, color: theme.colors.textMuted, fontSize: 10, fontFamily: theme.typography.body},
  transferAmount: {fontSize: 12, fontFamily: theme.typography.bodyBold},
});

export default WalletBrcTransferScreen;
