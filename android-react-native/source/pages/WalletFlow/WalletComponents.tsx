import React from 'react';
import {
  Pressable,
  ScrollView,
  StyleProp,
  StyleSheet,
  Text,
  TextInput,
  View,
  ViewStyle,
} from 'react-native';
import FastImage from 'react-native-fast-image';
import Ionicons from 'react-native-vector-icons/Ionicons';
import MaterialCommunityIcons from 'react-native-vector-icons/MaterialCommunityIcons';
import QRCode from 'react-native-qrcode-svg';
import {images} from '../../../assets/images';
import {AppScreen, GlassCard, PrimaryButton, SecondaryButton} from '../../design/primitives';
import theme, {alpha} from '../../design/theme';
import {copyToClipboard} from '../../utils/helper';
import {
  WalletAsset,
  WalletNetworkFilter,
  WALLET_NETWORKS,
} from '../../services/web3WalletService';

export const formatUsd = (value: number) =>
  `$${Number.isFinite(value) ? value.toLocaleString(undefined, {
    minimumFractionDigits: value >= 1000 ? 0 : 2,
    maximumFractionDigits: 2,
  }) : '0.00'}`;

export const formatBalance = (value: string, symbol?: string) =>
  `${Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 5,
  })}${symbol ? ` ${symbol}` : ''}`;

export const WalletModeLayout: React.FC<{
  children: React.ReactNode;
  title?: string;
  subtitle?: string;
  scrollable?: boolean;
  contentContainerStyle?: StyleProp<ViewStyle>;
  rightAction?: React.ReactNode;
  showBack?: boolean;
  onBackPress?: () => void;
  showModeSwitcher?: boolean;
}> = ({
  children,
  title,
  subtitle,
  scrollable = true,
  contentContainerStyle,
  rightAction,
  showBack = false,
  onBackPress,
}) => {
  const header = (
    <>
      {title ? (
        <View style={styles.headerRow}>
          <View style={styles.headerMain}>
            {showBack ? (
              <Pressable style={styles.backButton} onPress={onBackPress}>
                <Ionicons name="arrow-back" size={18} color={theme.colors.text} />
              </Pressable>
            ) : null}
            <View style={styles.headerCopy}>
              <Text style={styles.headerTitle}>{title}</Text>
              {subtitle ? <Text style={styles.headerSubtitle}>{subtitle}</Text> : null}
            </View>
          </View>
          {rightAction}
        </View>
      ) : null}
    </>
  );

  return (
    <AppScreen scrollable={false} contentContainerStyle={styles.layoutFrame}>
      <View style={styles.nonScrollFill}>
        <View style={styles.layoutHeader}>{header}</View>
        {scrollable ? (
          <ScrollView
            showsVerticalScrollIndicator={false}
            keyboardShouldPersistTaps="handled"
            keyboardDismissMode="on-drag"
            contentContainerStyle={[styles.layoutContent, contentContainerStyle]}>
            {children}
          </ScrollView>
        ) : (
          <View style={[styles.layoutBody, contentContainerStyle]}>{children}</View>
        )}
      </View>
    </AppScreen>
  );
};

export const WalletBrandHero: React.FC<{
  title: string;
  subtitle: string;
  actionLabel?: string;
  onAction?: () => void;
}> = ({title, subtitle, actionLabel, onAction}) => (
  <GlassCard style={styles.heroCard}>
    <View style={styles.logoBadge}>
      <FastImage source={images.logo} style={styles.logoImage} resizeMode="contain" />
    </View>
    <Text style={styles.heroTitle}>{title}</Text>
    <Text style={styles.heroSubtitle}>{subtitle}</Text>
    {actionLabel && onAction ? (
      <PrimaryButton label={actionLabel} icon="wallet-outline" onPress={onAction} style={styles.heroButton} />
    ) : null}
  </GlassCard>
);

export const WalletActionPill: React.FC<{
  label: string;
  icon: string;
  onPress: () => void;
}> = ({label, icon, onPress}) => (
  <Pressable
    accessibilityRole="button"
    accessibilityLabel={label}
    style={({pressed}) => [styles.actionPill, pressed && styles.pressedScale]}
    onPress={onPress}>
    <View style={styles.actionPillCircle}>
      <MaterialCommunityIcons
        name={icon}
        size={20}
        color={theme.colors.background}
      />
    </View>
    <Text style={styles.actionPillText} numberOfLines={2}>
      {label}
    </Text>
  </Pressable>
);

export const WalletNetworkChip: React.FC<{
  networkKey: WalletNetworkFilter;
  isActive: boolean;
  onPress: () => void;
}> = ({networkKey, isActive, onPress}) => {
  const network =
    networkKey === 'all'
      ? {
          shortName: 'All chains',
          accent: theme.colors.primary,
        }
      : WALLET_NETWORKS[networkKey];
  return (
    <Pressable
      onPress={onPress}
      style={({pressed}) => [
        styles.networkChip,
        isActive && {
          borderColor: network.accent,
          backgroundColor: alpha(network.accent, 0.14),
        },
        pressed && styles.pressedScale,
      ]}>
      <Text style={[styles.networkChipText, isActive && styles.networkChipTextActive]}>
        {network.shortName}
      </Text>
    </Pressable>
  );
};

export const WalletPortfolioHero: React.FC<{
  walletName: string;
  address?: string;
  networkLabel: string;
  totalUsd: number;
  availableBalance?: string;
  pendingBalance?: string;
  totalMined?: string;
  onCopy: () => void;
  onNetworksPress?: () => void;
}> = ({
  walletName,
  address,
  networkLabel,
  totalUsd,
  availableBalance,
  pendingBalance,
  totalMined,
  onCopy,
  onNetworksPress,
}) => (
  <GlassCard style={styles.portfolioCard}>
    <View style={styles.portfolioTop}>
      <View>
        <Text style={styles.walletLabel}>{walletName}</Text>
        <Pressable style={styles.addressRow} onPress={onCopy}>
          <Text style={styles.addressText}>
            {address ? `${address.slice(0, 6)}...${address.slice(-4)}` : 'No address yet'}
          </Text>
          <Ionicons name="copy-outline" size={14} color={theme.colors.textSoft} />
        </Pressable>
      </View>
      {onNetworksPress ? (
        <Pressable
          style={styles.networkButton}
          onPress={onNetworksPress}
          accessibilityRole="button"
          accessibilityLabel={`Manage ${networkLabel}`}>
          <Text style={styles.networkButtonText}>{networkLabel}</Text>
          <Ionicons name="chevron-down" size={15} color={theme.colors.text} />
        </Pressable>
      ) : (
        <View style={styles.networkButton}>
          <Text style={styles.networkButtonText}>{networkLabel}</Text>
        </View>
      )}
    </View>

    <Text style={styles.balanceCaption}>On-chain portfolio</Text>
    <Text style={styles.balanceValue}>{formatUsd(totalUsd)}</Text>
    <Text style={styles.balanceHint}>
      Mined BRC is listed separately below.
    </Text>
    <View style={styles.portfolioMetricRow}>
      <View style={styles.portfolioMetric}>
        <Text style={styles.portfolioMetricLabel}>Available</Text>
        <Text style={styles.portfolioMetricValue}>{availableBalance ?? '0 BRC'}</Text>
      </View>
      <View style={styles.portfolioMetric}>
        <Text style={styles.portfolioMetricLabel}>Pending</Text>
        <Text style={styles.portfolioMetricValue}>{pendingBalance ?? '0 BRC'}</Text>
      </View>
      <View style={styles.portfolioMetric}>
        <Text style={styles.portfolioMetricLabel}>Total mined</Text>
        <Text style={styles.portfolioMetricValue}>{totalMined ?? '0 BRC'}</Text>
      </View>
    </View>
  </GlassCard>
);

export const WalletListSection: React.FC<{
  title: string;
  actionLabel?: string;
  onActionPress?: () => void;
  children: React.ReactNode;
}> = ({title, actionLabel, onActionPress, children}) => (
  <View style={styles.sectionWrap}>
    <View style={styles.sectionHeader}>
      <Text style={styles.sectionTitle}>{title}</Text>
      {actionLabel && onActionPress ? (
        <Pressable onPress={onActionPress}>
          <Text style={styles.sectionAction}>{actionLabel}</Text>
        </Pressable>
      ) : null}
    </View>
    <GlassCard style={styles.sectionCard}>{children}</GlassCard>
  </View>
);

export const WalletAssetRow: React.FC<{
  asset: WalletAsset;
  onPress: () => void;
}> = ({asset, onPress}) => (
  <Pressable
    style={({pressed}) => [styles.assetRow, pressed && styles.pressedSurface]}
    onPress={onPress}>
    <View
      style={[
        styles.assetAvatar,
        {backgroundColor: alpha(asset.accent ?? theme.colors.primary, 0.18)},
      ]}>
      {asset.logoUrl ? (
        <FastImage
          source={{uri: asset.logoUrl}}
          style={styles.assetAvatarImage}
          resizeMode={FastImage.resizeMode.contain}
        />
      ) : (
        <Text
          style={[
            styles.assetAvatarText,
            {color: asset.accent ?? theme.colors.primary},
          ]}>
          {asset.symbol.slice(0, 1)}
        </Text>
      )}
    </View>
    <View style={styles.assetMeta}>
      <Text style={styles.assetName}>{asset.name}</Text>
      <Text style={styles.assetSub}>
        {asset.symbol} | {WALLET_NETWORKS[asset.networkKey].shortName}
      </Text>
    </View>
    <View style={styles.assetRight}>
      <Text style={styles.assetBalance}>{formatBalance(asset.balance, asset.symbol)}</Text>
      <Text style={styles.assetUsd}>{formatUsd(asset.usdValue)}</Text>
    </View>
  </Pressable>
);

export const WalletInputGroup: React.FC<{
  label: string;
  value: string;
  onChangeText: (text: string) => void;
  placeholder: string;
  multiline?: boolean;
  keyboardType?: 'default' | 'decimal-pad';
  autoCapitalize?: 'none' | 'sentences' | 'characters' | 'words';
  secureTextEntry?: boolean;
  maxLength?: number;
}> = ({
  label,
  value,
  onChangeText,
  placeholder,
  multiline = false,
  keyboardType = 'default',
  autoCapitalize = 'sentences',
  secureTextEntry = false,
  maxLength,
}) => (
  <View style={styles.inputWrap}>
    <Text style={styles.inputLabel}>{label}</Text>
    <TextInput
      value={value}
      onChangeText={onChangeText}
      placeholder={placeholder}
      placeholderTextColor={theme.colors.textSoft}
      multiline={multiline}
      keyboardType={keyboardType}
      autoCapitalize={autoCapitalize}
      secureTextEntry={secureTextEntry}
      maxLength={maxLength}
      style={[styles.input, multiline && styles.inputLarge]}
    />
  </View>
);

export const WalletReceiveCard: React.FC<{
  address: string;
}> = ({address}) => (
  <GlassCard style={styles.receiveCard}>
    <View style={styles.qrShell}>
      <QRCode value={address} size={176} color={theme.colors.background} backgroundColor={theme.colors.white} />
    </View>
    <Text style={styles.receiveAddress}>{address}</Text>
    <Text style={styles.receiveHint}>
      Use this EVM address for supported mainnets and testnets in Blockrium Wallet.
    </Text>
    <SecondaryButton
      label="Copy address"
      icon="content-copy"
      onPress={() => copyToClipboard(address)}
      style={styles.receiveButton}
    />
  </GlassCard>
);

export const WalletWordsGrid: React.FC<{
  words: string[];
}> = ({words}) => (
  <View style={styles.wordsGrid}>
    {words.map((word, index) => (
      <View key={`${word}-${index}`} style={styles.wordChip}>
        <Text style={styles.wordIndex}>{index + 1}</Text>
        <Text style={styles.wordText}>{word}</Text>
      </View>
    ))}
  </View>
);

export const WalletConfirmPrompt: React.FC<{
  promptIndexes: number[];
  values: string[];
  onChange: (index: number, value: string) => void;
}> = ({promptIndexes, values, onChange}) => (
  <View style={styles.confirmGrid}>
    {promptIndexes.map((wordIndex, index) => (
      <WalletInputGroup
        key={`${wordIndex}-${index}`}
        label={`Word ${wordIndex + 1}`}
        value={values[index] ?? ''}
        onChangeText={value => onChange(index, value)}
        placeholder={`Enter word ${wordIndex + 1}`}
        autoCapitalize="none"
      />
    ))}
  </View>
);

export const WalletInfoNotice: React.FC<{
  icon?: string;
  tone?: string;
  title: string;
  text: string;
}> = ({icon = 'shield-checkmark-outline', tone = theme.colors.primary, title, text}) => (
  <View style={[styles.noticeBox, {borderColor: alpha(tone, 0.26), backgroundColor: alpha(tone, 0.11)}]}>
    <Ionicons name={icon as any} size={18} color={tone} />
    <View style={styles.noticeCopy}>
      <Text style={[styles.noticeTitle, {color: tone}]}>{title}</Text>
      <Text style={styles.noticeText}>{text}</Text>
    </View>
  </View>
);

export const WalletSearchBar: React.FC<{
  value: string;
  onChangeText: (text: string) => void;
}> = ({value, onChangeText}) => (
  <View style={styles.searchBar}>
    <Ionicons name="search" size={16} color={theme.colors.textSoft} />
    <TextInput
      value={value}
      onChangeText={onChangeText}
      placeholder="Search tokens"
      placeholderTextColor={theme.colors.textSoft}
      style={styles.searchInput}
      autoCapitalize="none"
    />
  </View>
);

export const WalletChipScroller: React.FC<{
  networkKeys: WalletNetworkFilter[];
  activeKey: WalletNetworkFilter;
  onSelect: (key: WalletNetworkFilter) => void;
}> = ({networkKeys, activeKey, onSelect}) => (
  <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.networkRow}>
    {networkKeys.map(networkKey => (
      <WalletNetworkChip
        key={networkKey}
        networkKey={networkKey}
        isActive={activeKey === networkKey}
        onPress={() => onSelect(networkKey)}
      />
    ))}
  </ScrollView>
);

const styles = StyleSheet.create({
  layoutFrame: {
    flex: 1,
    paddingTop: 0,
  },
  nonScrollFill: {
    flex: 1,
  },
  layoutHeader: {
    paddingBottom: 6,
  },
  layoutContent: {
    flexGrow: 1,
    paddingBottom: 124,
  },
  layoutBody: {
    flex: 1,
  },
  pressedScale: {
    opacity: 0.92,
    transform: [{scale: 0.985}],
  },
  pressedSurface: {
    backgroundColor: alpha(theme.colors.primary, 0.06),
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  headerMain: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
  },
  backButton: {
    width: 34,
    height: 34,
    borderRadius: 17,
    marginRight: 10,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.58),
  },
  headerCopy: {
    flex: 1,
    marginRight: 12,
  },
  headerTitle: {
    color: theme.colors.text,
    fontSize: 23,
    lineHeight: 27,
    fontFamily: theme.typography.display,
  },
  headerSubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
  heroCard: {
    alignItems: 'center',
    paddingVertical: 14,
  },
  logoBadge: {
    width: 60,
    height: 60,
    borderRadius: 18,
    backgroundColor: alpha(theme.colors.primary, 0.12),
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 10,
  },
  logoImage: {
    width: 44,
    height: 44,
  },
  heroTitle: {
    color: theme.colors.text,
    fontSize: 22,
    textAlign: 'center',
    fontFamily: theme.typography.display,
  },
  heroSubtitle: {
    marginTop: 6,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    textAlign: 'center',
    fontFamily: theme.typography.body,
  },
  heroButton: {
    width: '100%',
    marginTop: 14,
  },
  portfolioCard: {
    marginBottom: 12,
    paddingTop: 13,
    paddingBottom: 13,
    borderColor: alpha(theme.colors.primary, 0.15),
  },
  portfolioTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  walletLabel: {
    color: theme.colors.text,
    fontSize: 16,
    fontFamily: theme.typography.heading,
  },
  addressRow: {
    marginTop: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  addressText: {
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  networkButton: {
    minHeight: 34,
    borderRadius: theme.radii.pill,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: alpha('#111723', 0.88),
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  networkButtonText: {
    color: theme.colors.text,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  balanceCaption: {
    marginTop: 12,
    color: theme.colors.textSoft,
    fontSize: 12,
    letterSpacing: 1,
    textTransform: 'uppercase',
    fontFamily: theme.typography.bodyBold,
  },
  balanceValue: {
    marginTop: 5,
    color: theme.colors.text,
    fontSize: 28,
    lineHeight: 34,
    fontFamily: theme.typography.display,
  },
  balanceHint: {
    marginTop: 6,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  portfolioMetricRow: {
    flexDirection: 'row',
    gap: 7,
    marginTop: 14,
  },
  portfolioMetric: {
    flex: 1,
    borderRadius: theme.radii.md,
    borderWidth: 1,
    borderColor: alpha(theme.colors.primary, 0.12),
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.44),
    paddingHorizontal: 9,
    paddingVertical: 9,
  },
  portfolioMetricLabel: {
    color: theme.colors.textSoft,
    fontSize: 11,
    fontFamily: theme.typography.body,
  },
  portfolioMetricValue: {
    marginTop: 5,
    color: theme.colors.text,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
  },
  actionPill: {
    flex: 1,
    alignItems: 'center',
  },
  actionPillCircle: {
    width: 46,
    height: 46,
    borderRadius: 16,
    backgroundColor: theme.colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionPillText: {
    width: '100%',
    marginTop: 7,
    color: theme.colors.text,
    fontSize: 12,
    textAlign: 'center',
    fontFamily: theme.typography.bodyBold,
  },
  networkRow: {
    gap: 8,
    paddingBottom: 8,
  },
  networkChip: {
    minHeight: 38,
    paddingHorizontal: 14,
    borderRadius: theme.radii.pill,
    borderWidth: 1,
    borderColor: theme.colors.border,
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.42),
  },
  networkChipText: {
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  networkChipTextActive: {
    color: theme.colors.text,
  },
  sectionWrap: {
    marginTop: 4,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  sectionTitle: {
    color: theme.colors.text,
    fontSize: 18,
    fontFamily: theme.typography.heading,
  },
  sectionAction: {
    color: theme.colors.primary,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
  },
  sectionCard: {
    paddingVertical: 4,
    borderColor: alpha(theme.colors.primary, 0.1),
  },
  assetRow: {
    flexDirection: 'row',
    alignItems: 'center',
    minHeight: 68,
    borderBottomWidth: 1,
    borderBottomColor: alpha(theme.colors.border, 0.24),
  },
  assetAvatar: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  assetAvatarImage: {
    width: 28,
    height: 28,
    borderRadius: 14,
  },
  assetAvatarText: {
    fontSize: 18,
    fontFamily: theme.typography.bodyBold,
  },
  assetMeta: {
    flex: 1,
  },
  assetName: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  assetSub: {
    marginTop: 3,
    color: theme.colors.textMuted,
    fontSize: 11,
    fontFamily: theme.typography.body,
  },
  assetRight: {
    alignItems: 'flex-end',
    marginLeft: 10,
  },
  assetBalance: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.bodyBold,
  },
  assetUsd: {
    marginTop: 4,
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  inputWrap: {
    marginBottom: 14,
  },
  inputLabel: {
    marginBottom: 7,
    color: theme.colors.textMuted,
    fontSize: 12,
    letterSpacing: 0.8,
    textTransform: 'uppercase',
    fontFamily: theme.typography.bodyBold,
  },
  input: {
    minHeight: 54,
    borderRadius: theme.radii.md,
    paddingHorizontal: 16,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.56),
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.bodyMedium,
  },
  inputLarge: {
    minHeight: 120,
    paddingTop: 16,
    textAlignVertical: 'top',
  },
  receiveCard: {
    alignItems: 'center',
  },
  qrShell: {
    padding: 14,
    backgroundColor: theme.colors.white,
    borderRadius: 28,
  },
  receiveAddress: {
    marginTop: 18,
    color: theme.colors.text,
    fontSize: 13,
    textAlign: 'center',
    fontFamily: theme.typography.bodyBold,
  },
  receiveHint: {
    marginTop: 10,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    textAlign: 'center',
    fontFamily: theme.typography.body,
  },
  receiveButton: {
    width: '100%',
    marginTop: 18,
  },
  wordsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  wordChip: {
    width: '48.5%',
    minHeight: 54,
    marginBottom: 10,
    borderRadius: theme.radii.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.52),
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
  },
  wordIndex: {
    width: 26,
    color: theme.colors.primary,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  wordText: {
    color: theme.colors.text,
    fontSize: 14,
    fontFamily: theme.typography.bodyMedium,
  },
  confirmGrid: {
    marginTop: 12,
  },
  noticeBox: {
    flexDirection: 'row',
    gap: 12,
    borderRadius: theme.radii.md,
    borderWidth: 1,
    padding: 14,
    marginBottom: 16,
  },
  noticeCopy: {
    flex: 1,
  },
  noticeTitle: {
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
  },
  noticeText: {
    marginTop: 3,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  searchBar: {
    minHeight: 50,
    borderRadius: theme.radii.pill,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.5),
    paddingHorizontal: 16,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  searchInput: {
    flex: 1,
    color: theme.colors.text,
    fontSize: 14,
    fontFamily: theme.typography.bodyMedium,
    paddingVertical: 12,
  },
});
