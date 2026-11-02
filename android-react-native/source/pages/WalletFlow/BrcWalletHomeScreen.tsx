import React, {useCallback, useState} from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import {useFocusEffect} from '@react-navigation/native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import {callQuery} from '../../hooks';
import {useThisAlert} from '../../hooks/AlertContext';
import {GlassCard, PrimaryButton, SecondaryButton} from '../../design/primitives';
import theme, {alpha} from '../../design/theme';
import {
  BRC_WALLET_REQUEST_TIMEOUT_MS,
  getBrcTransfers,
} from '../../services/verificationService';
import {BrcTransfer, VerificationStatus} from '../../types/verification';
import {formatBrcAmount} from '../../utils/formatBrcAmount';
import {getBrcTransferFeeSummary} from '../../utils/brcTransferDisplay';
import {parseVerificationStatusResponse} from '../../utils/verificationStatusResponse';
import {getBrcMemberAccessCopy} from '../../utils/verifiedMemberAccessCopy';
import {copyToClipboard, navigate as appNavigate} from '../../utils/helper';
import {WalletModeLayout} from './WalletComponents';
import type {WalletStackParamList} from './WalletScreens';

type Props = NativeStackScreenProps<WalletStackParamList, 'BrcWalletHome'>;

const BrcWalletHomeScreen = ({navigation}: Props) => {
  const {showAlert} = useThisAlert();
  const {
    data: statusResponse,
    isLoading,
    isError,
    refetch,
  }: any = callQuery('auth/verification/status', {
    requestTimeoutMs: BRC_WALLET_REQUEST_TIMEOUT_MS,
    staleTime: 15000,
    retry: 0,
  });
  const statusResult = parseVerificationStatusResponse(statusResponse);
  const status = statusResult.status ?? ({} as VerificationStatus);
  const accessCopy = getBrcMemberAccessCopy(Boolean(status.access_suspended));
  const statusUnavailable = isError || !statusResult.status;
  const [transfers, setTransfers] = useState<BrcTransfer[]>([]);
  const [isLoadingTransfers, setIsLoadingTransfers] = useState(false);
  const [transfersUnavailable, setTransfersUnavailable] = useState(false);

  const refreshTransfers = useCallback(async () => {
    if (!status.is_verified) {
      setTransfers([]);
      setTransfersUnavailable(false);
      return;
    }

    setIsLoadingTransfers(true);
    try {
      const result = await getBrcTransfers(5);
      setTransfers(result.data);
      setTransfersUnavailable(!result.response.success);
    } catch {
      setTransfersUnavailable(true);
    } finally {
      setIsLoadingTransfers(false);
    }
  }, [status.is_verified]);

  useFocusEffect(
    useCallback(() => {
      refetch();
      refreshTransfers();
    }, [refetch, refreshTransfers]),
  );

  const openTransfer = () => navigation.navigate('WalletBrcTransfer');
  const openBlockchainWallet = () => navigation.navigate('BlockchainWallet');
  const copyHandle = () => {
    if (status.username) {
      copyToClipboard(`@${status.username}`);
      showAlert('BRC username copied.', 'SUCCESS');
    }
  };

  if (isLoading) {
    return (
      <WalletModeLayout title="BRC wallet" subtitle="Your Blockrium balance">
        <GlassCard style={styles.stateCard}>
          <Text style={styles.stateText}>Loading your BRC wallet...</Text>
        </GlassCard>
      </WalletModeLayout>
    );
  }

  if (statusUnavailable) {
    return (
      <WalletModeLayout title="BRC wallet" subtitle="Your Blockrium balance">
        <GlassCard style={styles.stateCard}>
          <Text style={styles.cardTitle}>Wallet details unavailable</Text>
          <Text style={styles.mutedText}>{statusResult.error ?? 'We could not load your BRC account right now.'}</Text>
          <PrimaryButton label="Try again" icon="refresh" onPress={() => refetch()} style={styles.fullButton} />
        </GlassCard>
      </WalletModeLayout>
    );
  }

  return (
    <WalletModeLayout
      title="BRC wallet"
      subtitle="Your Blockrium account balance"
      contentContainerStyle={styles.content}>
      <GlassCard style={styles.balanceCard}>
        <View style={styles.balanceTopline}>
          <View style={styles.coinMark}>
            <Text style={styles.coinMarkText}>B</Text>
          </View>
          <Text style={styles.accountLabel}>BLOCKRIUM ACCOUNT</Text>
        </View>
        <Text style={styles.balanceLabel}>Available balance</Text>
        <Text style={styles.balanceValue} adjustsFontSizeToFit numberOfLines={1}>
          {formatBrcAmount(status.brc_transfer_balance ?? '0')} <Text style={styles.currency}>BRC</Text>
        </Text>
        <View style={styles.balanceDivider} />
        {status.username ? (
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={`Copy your BRC username ${status.username}`}
            onPress={copyHandle}
            style={styles.handleRow}>
            <View>
              <Text style={styles.handleLabel}>Your BRC username</Text>
              <Text style={styles.handleValue}>@{status.username}</Text>
            </View>
            <Ionicons name="copy-outline" size={19} color={theme.colors.primary} />
          </Pressable>
        ) : (
          <View style={styles.handleRow}>
            <Text style={styles.mutedText}>Create a username to receive BRC from members.</Text>
          </View>
        )}
      </GlassCard>

      {status.is_verified ? (
        <View style={styles.actionsRow}>
          <Pressable accessibilityRole="button" onPress={openTransfer} style={styles.actionCard}>
            <View style={styles.actionIcon}><Ionicons name="arrow-up-outline" size={22} color={theme.colors.background} /></View>
            <Text style={styles.actionTitle}>Send BRC</Text>
            <Text style={styles.actionHint}>To a username</Text>
          </Pressable>
          <Pressable accessibilityRole="button" onPress={status.username ? copyHandle : openTransfer} style={styles.actionCard}>
            <View style={[styles.actionIcon, styles.receiveIcon]}><Ionicons name="arrow-down-outline" size={22} color={theme.colors.primary} /></View>
            <Text style={styles.actionTitle}>Receive BRC</Text>
            <Text style={styles.actionHint}>Share your username</Text>
          </Pressable>
        </View>
      ) : (
        <GlassCard style={styles.lockedCard}>
          <View style={styles.lockedHeader}>
            <View style={styles.lockIcon}><Ionicons name="lock-closed-outline" size={19} color={theme.colors.primary} /></View>
            <View style={styles.lockedCopy}>
              <Text style={styles.cardTitle}>{status.access_suspended ? accessCopy.title : 'BRC transfers are not active'}</Text>
              <Text style={styles.mutedText}>{status.access_suspended ? accessCopy.message : 'BRC member transfers are available when account access is active.'}</Text>
            </View>
          </View>
          <PrimaryButton label="View access status" icon="shield-check-outline" onPress={() => appNavigate('VerifiedMember')} style={styles.fullButton} />
        </GlassCard>
      )}

      <GlassCard style={styles.historyCard}>
        <View style={styles.sectionHeader}>
          <View>
            <Text style={styles.cardTitle}>Recent activity</Text>
            <Text style={styles.mutedText}>BRC transfers between Blockrium members</Text>
          </View>
          {status.is_verified ? (
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Refresh BRC activity"
              disabled={isLoadingTransfers}
              onPress={refreshTransfers}
              style={styles.refreshButton}>
              <Ionicons name="refresh-outline" size={18} color={theme.colors.primary} />
            </Pressable>
          ) : null}
        </View>
        {!status.is_verified ? (
          <Text style={styles.emptyText}>Your transfer activity will appear here.</Text>
        ) : isLoadingTransfers && transfers.length === 0 ? (
          <Text style={styles.emptyText}>Loading activity...</Text>
        ) : transfersUnavailable ? (
          <Text style={styles.emptyText}>Activity could not be loaded. Tap refresh to try again.</Text>
        ) : transfers.length === 0 ? (
          <Text style={styles.emptyText}>No BRC transfers yet.</Text>
        ) : (
          transfers.map(transfer => <TransferRow key={String(transfer.id)} transfer={transfer} />)
        )}
        {status.is_verified && transfers.length > 0 ? (
          <SecondaryButton label="View all transfers" icon="chevron-right" onPress={openTransfer} style={styles.fullButton} />
        ) : null}
      </GlassCard>

      <Pressable accessibilityRole="button" onPress={openBlockchainWallet} style={styles.blockchainLink}>
        <View style={styles.blockchainIcon}><Ionicons name="globe-outline" size={20} color={theme.colors.textMuted} /></View>
        <View style={styles.blockchainCopy}>
          <Text style={styles.blockchainTitle}>Blockchain wallet</Text>
          <Text style={styles.blockchainSubtitle}>External networks and crypto assets</Text>
        </View>
        <Ionicons name="chevron-forward" size={18} color={theme.colors.textSoft} />
      </Pressable>
    </WalletModeLayout>
  );
};

const TransferRow = ({transfer}: {transfer: BrcTransfer}) => {
  const isSent = transfer.direction === 'sent';
  const counterparty = isSent ? transfer.recipient_username : transfer.sender_username;
  const amount = isSent ? transfer.total_debit ?? transfer.amount : transfer.amount;

  return (
    <View style={styles.transferRow}>
      <View style={[styles.transferIcon, {backgroundColor: alpha(isSent ? theme.colors.accentWarm : theme.colors.primary, 0.13)}]}>
        <Ionicons name={isSent ? 'arrow-up-outline' : 'arrow-down-outline'} size={17} color={isSent ? theme.colors.accentWarm : theme.colors.primary} />
      </View>
      <View style={styles.transferCopy}>
        <Text style={styles.transferTitle} numberOfLines={1}>{isSent ? 'To' : 'From'} @{counterparty || 'Blockrium member'}</Text>
        <Text style={styles.transferMeta}>{transfer.created_at ? new Date(transfer.created_at).toLocaleDateString() : transfer.status}</Text>
        {getBrcTransferFeeSummary(transfer, isSent) ? (
          <Text style={styles.transferMeta}>
            {getBrcTransferFeeSummary(transfer, isSent)}
          </Text>
        ) : null}
      </View>
      <Text style={[styles.transferAmount, {color: isSent ? theme.colors.accentWarm : theme.colors.primary}]} numberOfLines={1}>
        {isSent ? '−' : '+'}{formatBrcAmount(amount)}
      </Text>
    </View>
  );
};

const styles = StyleSheet.create({
  content: {paddingTop: 4, paddingBottom: 132, gap: 14},
  stateCard: {paddingVertical: 22},
  stateText: {color: theme.colors.textMuted, fontSize: 14, fontFamily: theme.typography.body},
  balanceCard: {padding: 20, borderColor: alpha(theme.colors.primary, 0.28)},
  balanceTopline: {flexDirection: 'row', alignItems: 'center', gap: 10},
  coinMark: {width: 34, height: 34, borderRadius: 12, alignItems: 'center', justifyContent: 'center', backgroundColor: alpha(theme.colors.primary, 0.16)},
  coinMarkText: {color: theme.colors.primary, fontSize: 19, fontFamily: theme.typography.heading},
  accountLabel: {color: theme.colors.primary, fontSize: 10, letterSpacing: 1.4, fontFamily: theme.typography.bodyBold},
  balanceLabel: {marginTop: 22, color: theme.colors.textMuted, fontSize: 13, fontFamily: theme.typography.body},
  balanceValue: {marginTop: 4, color: theme.colors.text, fontSize: 34, fontFamily: theme.typography.display},
  currency: {color: theme.colors.textMuted, fontSize: 19, fontFamily: theme.typography.heading},
  balanceDivider: {height: 1, marginTop: 18, backgroundColor: theme.colors.border},
  handleRow: {minHeight: 56, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12},
  handleLabel: {color: theme.colors.textSoft, fontSize: 11, fontFamily: theme.typography.body},
  handleValue: {marginTop: 3, color: theme.colors.text, fontSize: 16, fontFamily: theme.typography.bodyBold},
  mutedText: {marginTop: 4, color: theme.colors.textMuted, fontSize: 12, lineHeight: 18, fontFamily: theme.typography.body},
  actionsRow: {flexDirection: 'row', gap: 12},
  actionCard: {flex: 1, minHeight: 126, padding: 15, borderRadius: theme.radii.lg, borderWidth: 1, borderColor: theme.colors.border, backgroundColor: theme.colors.surfaceGlass, justifyContent: 'center'},
  actionIcon: {width: 42, height: 42, borderRadius: 15, alignItems: 'center', justifyContent: 'center', backgroundColor: theme.colors.primary},
  receiveIcon: {backgroundColor: alpha(theme.colors.primary, 0.14), borderWidth: 1, borderColor: alpha(theme.colors.primary, 0.32)},
  actionTitle: {marginTop: 11, color: theme.colors.text, fontSize: 15, fontFamily: theme.typography.bodyBold},
  actionHint: {marginTop: 3, color: theme.colors.textMuted, fontSize: 11, fontFamily: theme.typography.body},
  lockedCard: {padding: 17},
  lockedHeader: {flexDirection: 'row', alignItems: 'center', gap: 12},
  lockIcon: {width: 40, height: 40, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: alpha(theme.colors.primary, 0.13)},
  lockedCopy: {flex: 1},
  cardTitle: {color: theme.colors.text, fontSize: 16, fontFamily: theme.typography.heading},
  fullButton: {marginTop: 13},
  historyCard: {padding: 17},
  sectionHeader: {flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12, marginBottom: 10},
  refreshButton: {width: 38, height: 38, borderRadius: 13, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: alpha(theme.colors.primary, 0.32)},
  emptyText: {paddingVertical: 14, color: theme.colors.textMuted, fontSize: 13, lineHeight: 19, fontFamily: theme.typography.body},
  transferRow: {minHeight: 62, flexDirection: 'row', alignItems: 'center', gap: 10, borderTopWidth: 1, borderTopColor: alpha(theme.colors.border, 0.8)},
  transferIcon: {width: 34, height: 34, borderRadius: 17, alignItems: 'center', justifyContent: 'center'},
  transferCopy: {flex: 1},
  transferTitle: {color: theme.colors.text, fontSize: 13, fontFamily: theme.typography.bodyMedium},
  transferMeta: {marginTop: 3, color: theme.colors.textSoft, fontSize: 10, fontFamily: theme.typography.body},
  transferAmount: {maxWidth: '38%', fontSize: 12, fontFamily: theme.typography.bodyBold},
  blockchainLink: {minHeight: 72, flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 15, borderRadius: theme.radii.lg, borderWidth: 1, borderColor: theme.colors.border, backgroundColor: theme.colors.surfaceGlass},
  blockchainIcon: {width: 38, height: 38, borderRadius: 13, alignItems: 'center', justifyContent: 'center', backgroundColor: alpha(theme.colors.textMuted, 0.1)},
  blockchainCopy: {flex: 1},
  blockchainTitle: {color: theme.colors.text, fontSize: 14, fontFamily: theme.typography.bodyBold},
  blockchainSubtitle: {marginTop: 3, color: theme.colors.textMuted, fontSize: 11, fontFamily: theme.typography.body},
});

export default BrcWalletHomeScreen;
