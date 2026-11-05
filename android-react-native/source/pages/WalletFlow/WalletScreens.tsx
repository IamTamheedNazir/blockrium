import React, {
  useCallback,
  useDeferredValue,
  useEffect,
  useRef,
  useState,
} from 'react';
import {
  ActivityIndicator,
  Alert,
  BackHandler,
  Linking,
  Pressable,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from 'react-native';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import {SafeAreaView} from 'react-native-safe-area-context';
import Ionicons from 'react-native-vector-icons/Ionicons';
import {WebView} from 'react-native-webview';
import {
  GlassCard,
  PrimaryButton,
  ScreenLoader,
  SecondaryButton,
} from '../../design/primitives';
import AppodealBannerSlot from '../../molecules/AppodealBannerSlot';
import theme, {alpha} from '../../design/theme';
import {callQuery} from '../../hooks';
import {useThisAlert} from '../../hooks/AlertContext';
import {
  WalletActivity,
  WalletAsset,
  WalletNetworkKey,
  WALLET_NETWORKS,
  getExplorerTransactionUrl,
} from '../../services/web3WalletService';
import {
  requestBlockriumNotificationPermission,
  setBlockriumWalletNotificationPreference,
} from '../../services/blockriumNotificationService';
import {copyToClipboard, navigate as appNavigate} from '../../utils/helper';
import {normalizeNotificationCount} from '../../utils/notifications';
import {
  WalletActionPill,
  WalletAssetRow,
  WalletChipScroller,
  WalletConfirmPrompt,
  WalletInfoNotice,
  WalletInputGroup,
  WalletListSection,
  WalletModeLayout,
  WalletPortfolioHero,
  WalletReceiveCard,
  WalletSearchBar,
  WalletWordsGrid,
  formatBalance,
  formatUsd,
} from './WalletComponents';
import {useWalletModule} from './WalletProvider';

export type WalletStackParamList = {
  BrcWalletHome: WalletRewardRouteParams | undefined;
  WalletFlow: WalletRewardRouteParams | undefined;
  WalletUnlock: WalletRewardRouteParams | undefined;
  WalletOnboarding: undefined;
  WalletBackupPhrase: undefined;
  WalletConfirmPhrase: undefined;
  WalletImport: undefined;
  WalletPasscode: undefined;
  WalletNotifications: {passcode?: string} | undefined;
  WalletReady: undefined;
  WalletMainTabs:
    | WalletRewardRouteParams
    | {screen?: 'WalletHome'; params?: WalletRewardRouteParams}
    | undefined;
  WalletHome: WalletRewardRouteParams | undefined;
  WalletSwap: undefined;
  WalletPay: undefined;
  WalletBrowser: undefined;
  WalletBrowserWeb: {url: string; title?: string};
  WalletModeSettings: undefined;
  WalletTokenDetails: {assetId: string};
  WalletSend: {assetId?: string} | undefined;
  WalletReceive: undefined;
  WalletAddToken: undefined;
  WalletNetworks: undefined;
  WalletSettings: undefined;
  WalletSecretPhrase: undefined;
  WalletHistory: undefined;
  WalletBrcTransfer: undefined;
  BlockchainWallet: {screen?: string; params?: Record<string, unknown>} | undefined;
};

export type WalletRewardRouteParams = {
  openedFromRewardNotification?: boolean;
  rewardNotificationAction?: string;
  rewardNotificationId?: string;
  refreshWalletBalanceAt?: number;
};

const pause = (timeout = 90) =>
  new Promise(resolve => {
    setTimeout(resolve, timeout);
  });

const findAssetById = (
  assetsByNetwork: Partial<Record<WalletNetworkKey, WalletAsset[]>>,
  assetId?: string,
) => {
  if (!assetId) {
    return undefined;
  }

  return Object.values(assetsByNetwork)
    .flatMap(networkAssets => networkAssets ?? [])
    .find(asset => asset.id === assetId);
};

const formatMinedTokenValue = (value: unknown) => {
  const amount = Number(value ?? 0);

  if (!Number.isFinite(amount)) {
    return '0 BRC';
  }

  return `${amount.toLocaleString(undefined, {
    maximumFractionDigits: 2,
  })} BRC`;
};

const WalletHistoryRow: React.FC<{item: WalletActivity}> = React.memo(
  ({item}) => (
    <Pressable
      style={styles.historyRow}
      onPress={() => {
        if (item.hash && item.networkKey) {
          Linking.openURL(
            getExplorerTransactionUrl(item.networkKey, item.hash),
          );
        }
      }}>
      <View style={styles.historyIcon}>
        <Ionicons
          name={item.type === 'sent' ? 'arrow-up-outline' : 'ellipse-outline'}
          size={18}
          color={theme.colors.primary}
        />
      </View>
      <View style={styles.historyMeta}>
        <Text style={styles.historyTitle}>{item.title}</Text>
        <Text style={styles.historySub}>{item.subtitle}</Text>
      </View>
      <Text style={styles.historyDate}>
        {new Date(item.createdAt).toLocaleDateString()}
      </Text>
    </Pressable>
  ),
);

const WalletMainHeader: React.FC<{
  title: string;
  subtitle?: string;
  navigation: any;
  compact?: boolean;
  showSettings?: boolean;
}> = ({title, subtitle, navigation}) => (
  <View style={styles.walletMainHeader}>
    <View style={styles.walletMainHeaderTop}>
      <View style={styles.walletMainHeaderCopy}>
        <Text style={styles.walletMainHeaderEyebrow}>Blockrium Wallet</Text>
        <Text style={styles.walletMainHeaderTitle}>{title}</Text>
        {subtitle ? (
          <Text style={styles.walletMainHeaderSubtitle}>{subtitle}</Text>
        ) : null}
      </View>
      <Pressable
        style={styles.headerIconButton}
        onPress={() => navigation.navigate('WalletModeSettings')}>
        <Ionicons name="settings-outline" size={18} color={theme.colors.text} />
      </Pressable>
    </View>
  </View>
);

const WalletHomeHeader: React.FC<{
  navigation: any;
  notificationCount?: number;
}> = React.memo(({navigation, notificationCount = 0}) => {
  const notificationBadgeText =
    notificationCount > 99 ? '99+' : String(notificationCount);

  return (
    <View style={styles.walletHomeHeader}>
      <View style={styles.walletHomeIdentity}>
        <Text style={styles.walletHomeEyebrow}>Blockrium Wallet</Text>
        <Text style={styles.walletHomeName}>Portfolio</Text>
      </View>
      <View style={styles.walletHomeHeaderActions}>
        <Pressable
          style={styles.headerIconButton}
          onPress={() => appNavigate('Notifications')}>
          <Ionicons
            name="notifications-outline"
            size={18}
            color={theme.colors.text}
          />
          {notificationCount > 0 ? (
            <View style={styles.walletNotificationBadge}>
              <Text style={styles.walletNotificationBadgeText}>
                {notificationBadgeText}
              </Text>
            </View>
          ) : null}
        </Pressable>
        <Pressable
          style={styles.headerIconButton}
          onPress={() => appNavigate('AppSearch')}>
          <Ionicons name="search-outline" size={18} color={theme.colors.text} />
        </Pressable>
        <Pressable
          style={styles.headerIconButton}
          onPress={() => navigation.navigate('WalletModeSettings')}>
          <Ionicons
            name="settings-outline"
            size={18}
            color={theme.colors.text}
          />
        </Pressable>
      </View>
    </View>
  );
});

const WalletHeaderNotice: React.FC<{
  icon?: string;
  title: string;
  text: string;
}> = ({icon = 'shield-checkmark-outline', title, text}) => (
  <View style={styles.walletHeaderNotice}>
    <View style={styles.walletHeaderNoticeIcon}>
      <Ionicons name={icon as any} size={18} color={theme.colors.primary} />
    </View>
    <View style={styles.walletHeaderNoticeCopy}>
      <Text style={styles.walletHeaderNoticeTitle}>{title}</Text>
      <Text style={styles.walletHeaderNoticeText}>{text}</Text>
    </View>
  </View>
);

const WalletFlowScreen = ({
  navigation,
  route,
}: NativeStackScreenProps<WalletStackParamList, 'WalletFlow'>) => {
  const {isHydrating, isLocked, wallet} = useWalletModule();

  useEffect(() => {
    if (isHydrating) {
      return;
    }

    if (!wallet?.address) {
      navigation.replace('WalletOnboarding');
      return;
    }

    if (isLocked) {
      navigation.replace('WalletUnlock', route.params);
      return;
    }

    navigation.replace('WalletMainTabs', {
      screen: 'WalletHome',
      params: route.params,
    });
  }, [isHydrating, isLocked, navigation, route.params, wallet?.address]);

  return (
    <ScreenLoader
      title="Opening Wallet"
      subtitle="Checking secure storage, restoring wallet state, and preparing your blockchain session."
    />
  );
};

const WalletUnlockScreen = ({
  navigation,
  route,
}: NativeStackScreenProps<WalletStackParamList, 'WalletUnlock'>) => {
  const {
    biometricType,
    securitySettings,
    unlockWallet,
    unlockWalletWithBiometric,
    wallet,
  } = useWalletModule();
  const {showAlert} = useThisAlert();
  const [passcode, setPasscode] = useState('');
  const [isUnlocking, setIsUnlocking] = useState(false);
  const [isUnlockingWithBiometric, setIsUnlockingWithBiometric] =
    useState(false);

  const handleBiometricUnlock = async () => {
    setIsUnlockingWithBiometric(true);

    try {
      const unlocked = await unlockWalletWithBiometric();

      if (!unlocked) {
        showAlert('Biometric unlock was cancelled or unavailable.', 'INFO');
        return;
      }

      navigation.replace('WalletMainTabs', {
        screen: 'WalletHome',
        params: route.params,
      });
    } catch (error: any) {
      showAlert(error?.message || 'Unable to unlock with biometrics.', 'ERROR');
    } finally {
      setIsUnlockingWithBiometric(false);
    }
  };

  useEffect(() => {
    if (
      securitySettings.biometricEnabled &&
      biometricType &&
      !isUnlockingWithBiometric
    ) {
      handleBiometricUnlock();
    }
    // We only want the first secure prompt when this screen opens.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <WalletModeLayout
      title="Unlock wallet"
      subtitle="Wallet mode is locked when you leave the app or switch back to Blockrium."
      scrollable={false}>
      <GlassCard style={styles.readyCard}>
        <View style={styles.readyOrb}>
          <Ionicons
            name="lock-closed-outline"
            size={36}
            color={theme.colors.primary}
          />
        </View>
        <Text style={styles.readyTitle}>Wallet locked</Text>
        <Text style={styles.readyCopy}>
          Re-enter your wallet passcode to open{' '}
          {wallet?.name ?? 'Blockrium Wallet'} on this device.
        </Text>
        <WalletInputGroup
          label="Wallet passcode"
          value={passcode}
          onChangeText={setPasscode}
          placeholder="Enter passcode"
          keyboardType="decimal-pad"
          autoCapitalize="none"
        />
        <PrimaryButton
          label={isUnlocking ? 'Unlocking wallet...' : 'Unlock wallet'}
          icon="shield-check-outline"
          loading={isUnlocking}
          style={styles.primaryCta}
          onPress={async () => {
            setIsUnlocking(true);

            try {
              const unlocked = await unlockWallet(passcode);

              if (!unlocked) {
                showAlert('Wrong wallet passcode.', 'ERROR');
                return;
              }

              navigation.replace('WalletMainTabs', {
                screen: 'WalletHome',
                params: route.params,
              });
            } catch (error: any) {
              showAlert(error?.message || 'Unable to unlock wallet.', 'ERROR');
            } finally {
              setIsUnlocking(false);
            }
          }}
        />
        {securitySettings.biometricEnabled && biometricType ? (
          <SecondaryButton
            label={
              isUnlockingWithBiometric
                ? `Opening ${biometricType}...`
                : `Use ${biometricType}`
            }
            icon="fingerprint"
            loading={isUnlockingWithBiometric}
            style={styles.secondaryCta}
            onPress={handleBiometricUnlock}
          />
        ) : null}
      </GlassCard>
    </WalletModeLayout>
  );
};

const WalletOnboardingScreen = ({
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletOnboarding'>) => {
  const {beginCreateWallet} = useWalletModule();
  const [isStartingCreate, setIsStartingCreate] = useState(false);
  const [isStartingImport, setIsStartingImport] = useState(false);

  return (
    <WalletModeLayout
      title="Wallet"
      subtitle="Create or import a self-custody Web3 wallet for supported EVM chains.">
      <GlassCard style={styles.onboardingHero}>
        <View style={styles.logoOrb}>
          <Ionicons
            name="wallet-outline"
            size={36}
            color={theme.colors.primary}
          />
        </View>
        <Text style={styles.onboardingTitle}>Blockrium Wallet</Text>
        <Text style={styles.onboardingCopy}>
          Passcode-protected wallet mode with receive QR, token balances, send
          flow, and network controls.
        </Text>
        <PrimaryButton
          label={isStartingCreate ? 'Preparing wallet...' : 'Create wallet'}
          icon="wallet-plus-outline"
          loading={isStartingCreate}
          style={styles.primaryCta}
          onPress={async () => {
            setIsStartingCreate(true);
            try {
              await pause(40);
              await beginCreateWallet();
              navigation.navigate('WalletBackupPhrase');
            } finally {
              setIsStartingCreate(false);
            }
          }}
        />
        <SecondaryButton
          label={isStartingImport ? 'Opening import...' : 'Import wallet'}
          icon="key-outline"
          loading={isStartingImport}
          style={styles.secondaryCta}
          onPress={async () => {
            setIsStartingImport(true);
            await pause();
            navigation.navigate('WalletImport');
            setIsStartingImport(false);
          }}
        />
      </GlassCard>

      <WalletInfoNotice
        title="Security first"
        text="Your recovery phrase is encrypted on this device after setup. Blockrium cannot recover it if it is lost."
      />

      <WalletListSection title="Ready">
        {[
          'Ethereum, Polygon, BNB Chain, Base, Arbitrum, Optimism, Avalanche',
          'Send, receive QR, network switch, and activity history',
          'Popular tokens, search, and custom token import',
        ].map(item => (
          <View key={item} style={styles.checkRow}>
            <Ionicons
              name="checkmark-circle"
              size={18}
              color={theme.colors.primary}
            />
            <Text style={styles.checkText}>{item}</Text>
          </View>
        ))}
      </WalletListSection>
    </WalletModeLayout>
  );
};

const WalletBackupPhraseScreen = ({
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletBackupPhrase'>) => {
  const {createDraft, beginCreateWallet} = useWalletModule();
  const {showAlert} = useThisAlert();

  useEffect(() => {
    if (!createDraft) {
      beginCreateWallet().catch(error => {
        console.log('Wallet draft bootstrap warning', error);
      });
    }
  }, [beginCreateWallet, createDraft]);

  if (!createDraft) {
    return (
      <ScreenLoader
        title="Preparing phrase"
        subtitle="Generating a secure recovery phrase for your wallet setup."
      />
    );
  }

  return (
    <WalletModeLayout
      title="Backup phrase"
      subtitle="Write these 12 words offline. Anyone with this phrase can control the wallet."
      showBack
      onBackPress={() => navigation.goBack()}>
      <WalletInfoNotice
        icon="warning-outline"
        tone={theme.colors.accentWarm}
        title="Never share your recovery phrase"
        text="Blockrium support, ads, and mining never require this phrase. Keep it offline and private."
      />

      <GlassCard>
        <WalletWordsGrid words={createDraft.words} />
        <SecondaryButton
          label="Copy phrase"
          icon="content-copy"
          style={styles.secondaryCta}
          onPress={() => {
            copyToClipboard(createDraft.mnemonic);
            showAlert(
              'Recovery phrase copied. Save it offline now.',
              'SUCCESS',
            );
          }}
        />
        <PrimaryButton
          label="I saved it securely"
          icon="shield-check-outline"
          style={styles.primaryCta}
          onPress={async () => {
            await pause();
            navigation.navigate('WalletConfirmPhrase');
          }}
        />
      </GlassCard>
    </WalletModeLayout>
  );
};

const WalletConfirmPhraseScreen = ({
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletConfirmPhrase'>) => {
  const {createDraft} = useWalletModule();
  const {showAlert} = useThisAlert();
  const [answers, setAnswers] = useState<string[]>(['', '', '']);

  if (!createDraft) {
    return (
      <WalletModeLayout
        title="Confirm phrase"
        subtitle="This step needs a fresh draft. Generate a new phrase to continue.">
        <PrimaryButton
          label="Back to onboarding"
          icon="arrow-left"
          onPress={() => navigation.replace('WalletOnboarding')}
        />
      </WalletModeLayout>
    );
  }

  return (
    <WalletModeLayout
      title="Confirm phrase"
      subtitle="Enter the requested words to verify that your backup was saved correctly."
      showBack
      onBackPress={() => navigation.goBack()}>
      <WalletInfoNotice
        title="Recovery check"
        text="This keeps new wallets from being created without a usable backup."
      />

      <GlassCard>
        <WalletConfirmPrompt
          promptIndexes={createDraft.confirmIndexes}
          values={answers}
          onChange={(index, value) => {
            const next = [...answers];
            next[index] = value.trim().toLowerCase();
            setAnswers(next);
          }}
        />
        <PrimaryButton
          label="Continue to security"
          icon="wallet-outline"
          style={styles.primaryCta}
          onPress={async () => {
            const matches = createDraft.confirmIndexes.every(
              (wordIndex, index) =>
                createDraft.words[wordIndex].toLowerCase() ===
                answers[index]?.trim().toLowerCase(),
            );

            if (!matches) {
              showAlert(
                'Recovery phrase confirmation does not match.',
                'ERROR',
              );
              return;
            }

            await pause();
            navigation.navigate('WalletPasscode');
          }}
        />
      </GlassCard>
    </WalletModeLayout>
  );
};

const WalletImportScreen = ({
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletImport'>) => {
  const {stageImportWallet} = useWalletModule();
  const {showAlert} = useThisAlert();
  const [mnemonic, setMnemonic] = useState('');
  const [isImporting, setIsImporting] = useState(false);

  return (
    <WalletModeLayout
      title="Import wallet"
      subtitle="Paste the 12-word recovery phrase to restore an existing EVM wallet."
      showBack
      onBackPress={() => navigation.goBack()}>
      <WalletInfoNotice
        title="Import from phrase"
        text="Imported wallet keeps the same EVM address across supported networks in this module."
      />
      <GlassCard>
        <WalletInputGroup
          label="Recovery phrase"
          value={mnemonic}
          onChangeText={setMnemonic}
          placeholder="twelve words separated by spaces"
          multiline
          autoCapitalize="none"
        />
        <PrimaryButton
          label={isImporting ? 'Checking phrase...' : 'Import wallet'}
          icon="key-outline"
          loading={isImporting}
          style={styles.primaryCta}
          onPress={async () => {
            setIsImporting(true);

            try {
              await stageImportWallet(mnemonic);
              await pause();
              navigation.navigate('WalletPasscode');
            } catch (error: any) {
              showAlert(error?.message || 'Unable to import wallet.', 'ERROR');
            } finally {
              setIsImporting(false);
            }
          }}
        />
      </GlassCard>
    </WalletModeLayout>
  );
};

const WalletPasscodeScreen = ({
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletPasscode'>) => {
  const {completeWalletSetup, createDraft} = useWalletModule();
  const {showAlert} = useThisAlert();
  const [passcode, setPasscode] = useState('');
  const [confirmPasscode, setConfirmPasscode] = useState('');
  const [isFinishing, setIsFinishing] = useState(false);

  const finishWalletSetup = async () => {
    if (isFinishing) {
      return;
    }

    if (!/^\d{4,6}$/.test(passcode.trim())) {
      showAlert('Use a 4 to 6 digit wallet passcode.', 'ERROR');
      return;
    }

    if (passcode.trim() !== confirmPasscode.trim()) {
      showAlert('Passcodes do not match.', 'ERROR');
      return;
    }

    setIsFinishing(true);

    try {
      await pause(80);
      await completeWalletSetup({
        passcode: passcode.trim(),
        notificationsEnabled: true,
      });
      setBlockriumWalletNotificationPreference(true);
      requestBlockriumNotificationPermission().catch(permissionError => {
        console.log('Wallet notification permission warning', permissionError);
      });

      navigation.reset({
        index: 0,
        routes: [{name: 'WalletReady'}],
      });
    } catch (error: any) {
      showAlert(error?.message || 'Unable to finish wallet setup.', 'ERROR');
    } finally {
      setIsFinishing(false);
    }
  };

  return (
    <WalletModeLayout
      title="Wallet security"
      subtitle="Set a wallet passcode before finishing setup. You will use it to reveal the phrase inside security settings."
      showBack
      onBackPress={() => navigation.goBack()}>
      <WalletInfoNotice
        title="Passcode protection"
        text="Use 4 to 6 digits. This protects wallet access on this device and gates secret phrase reveal."
      />
      <GlassCard>
        <WalletInputGroup
          label="Wallet passcode"
          value={passcode}
          onChangeText={text =>
            setPasscode(text.replace(/\D/g, '').slice(0, 6))
          }
          placeholder="4 to 6 digits"
          keyboardType="decimal-pad"
          autoCapitalize="none"
          secureTextEntry
          maxLength={6}
        />
        <WalletInputGroup
          label="Confirm passcode"
          value={confirmPasscode}
          onChangeText={text =>
            setConfirmPasscode(text.replace(/\D/g, '').slice(0, 6))
          }
          placeholder="Repeat passcode"
          keyboardType="decimal-pad"
          autoCapitalize="none"
          secureTextEntry
          maxLength={6}
        />
        <PrimaryButton
          label={isFinishing ? 'Securing wallet...' : 'Finish setup'}
          icon="shield-check-outline"
          loading={isFinishing}
          disabled={isFinishing}
          style={styles.primaryCta}
          onPress={finishWalletSetup}
        />
        {!createDraft ? (
          <Text style={styles.setupHintText}>
            Import flow also uses this passcode before the wallet finishes
            setup.
          </Text>
        ) : null}
      </GlassCard>
    </WalletModeLayout>
  );
};

const WalletNotificationsScreen = ({
  route,
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletNotifications'>) => {
  const {biometricType, completeWalletSetup, setBiometricUnlockEnabled} =
    useWalletModule();
  const {showAlert} = useThisAlert();
  const [isFinishing, setIsFinishing] = useState(false);
  const [biometricEnabled, setBiometricEnabled] = useState(
    Boolean(biometricType),
  );
  const setupPasscode = route.params?.passcode;

  const leaveNotificationStep = () => {
    if (navigation.canGoBack()) {
      navigation.goBack();
      return;
    }

    navigation.replace('WalletPasscode');
  };

  useEffect(() => {
    const subscription = BackHandler.addEventListener(
      'hardwareBackPress',
      () => {
        leaveNotificationStep();
        return true;
      },
    );

    return () => subscription.remove();
  });

  const requestNotificationPermission = async () => {
    await Promise.race([
      requestBlockriumNotificationPermission(),
      new Promise(resolve => {
        setTimeout(resolve, 3500);
      }),
    ]);
  };

  const finishSetup = async (notificationsEnabled: boolean) => {
    if (isFinishing) {
      return;
    }

    setIsFinishing(true);

    try {
      if (!setupPasscode) {
        showAlert('Enter your wallet passcode again to finish setup.', 'ERROR');
        navigation.replace('WalletPasscode');
        return;
      }

      await completeWalletSetup({
        passcode: setupPasscode,
        notificationsEnabled,
      });

      setBlockriumWalletNotificationPreference(notificationsEnabled);

      if (notificationsEnabled) {
        requestNotificationPermission().catch(permissionError => {
          console.log(
            'Wallet notification permission warning',
            permissionError,
          );
        });
      }

      if (biometricEnabled && biometricType) {
        setBiometricUnlockEnabled(true).catch(biometricError => {
          console.log('Wallet biometric setup warning', biometricError);
        });
      }

      navigation.reset({
        index: 0,
        routes: [{name: 'WalletReady'}],
      });
    } catch (error: any) {
      showAlert(error?.message || 'Unable to finish wallet setup.', 'ERROR');
    } finally {
      setIsFinishing(false);
    }
  };

  return (
    <WalletModeLayout
      title="Notifications"
      subtitle="Choose whether Blockrium Wallet can notify you about wallet activity on this device."
      showBack
      onBackPress={leaveNotificationStep}>
      <GlassCard>
        <WalletInfoNotice
          title="One more step"
          text="Your wallet passcode is ready. Choose whether wallet notifications should be enabled on this device."
        />
        {biometricType ? (
          <View style={styles.switchRow}>
            <View style={styles.switchCopy}>
              <Text style={styles.switchTitle}>Use {biometricType}</Text>
              <Text style={styles.switchSub}>
                Unlock wallet mode with device biometrics or secure device
                passcode after the app is closed.
              </Text>
            </View>
            <Switch
              value={biometricEnabled}
              onValueChange={setBiometricEnabled}
              trackColor={{
                false: alpha(theme.colors.textSoft, 0.24),
                true: alpha(theme.colors.primary, 0.38),
              }}
              thumbColor={
                biometricEnabled ? theme.colors.primary : theme.colors.text
              }
            />
          </View>
        ) : null}
        <PrimaryButton
          label={isFinishing ? 'Finishing wallet...' : 'Enable notifications'}
          icon="bell-outline"
          loading={isFinishing}
          style={styles.primaryCta}
          onPress={() => finishSetup(true)}
        />
        <SecondaryButton
          label={isFinishing ? 'Finishing wallet...' : 'Skip for now'}
          icon="arrow-right"
          disabled={isFinishing}
          style={styles.secondaryCta}
          onPress={() => finishSetup(false)}
        />
      </GlassCard>
    </WalletModeLayout>
  );
};

const WalletReadyScreen = ({
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletReady'>) => {
  return (
    <WalletModeLayout
      title="Wallet ready"
      subtitle="Your self-custody wallet is now secured on this device and ready for funding."
      scrollable={false}>
      <GlassCard style={styles.readyCard}>
        <View style={styles.readyOrb}>
          <Ionicons
            name="checkmark-done"
            size={42}
            color={theme.colors.primary}
          />
        </View>
        <Text style={styles.readyTitle}>Your wallet is ready</Text>
        <Text style={styles.readyCopy}>
          Blockrium Shield is active, your passcode is set, and your wallet can
          now receive assets across supported chains.
        </Text>
        <PrimaryButton
          label="Show wallet"
          icon="wallet-outline"
          style={styles.primaryCta}
          onPress={() =>
            navigation.reset({
              index: 0,
              routes: [{name: 'WalletMainTabs'}],
            })
          }
        />
        <SecondaryButton
          label="Fund your wallet"
          icon="qrcode"
          style={styles.secondaryCta}
          onPress={() => navigation.navigate('WalletReceive')}
        />
      </GlassCard>
    </WalletModeLayout>
  );
};

const WalletHomeScreen = ({
  navigation,
  route,
}: NativeStackScreenProps<WalletStackParamList, 'WalletHome'>) => {
  const {
    wallet,
    preferences,
    currentAssets,
    totalUsd,
    activity,
    availableNetworks,
    isRefreshing,
    refreshSelectedNetwork,
    selectNetwork,
  } = useWalletModule();
  const {showAlert} = useThisAlert();
  const {
    data: minedWalletData,
    isFetching: isMinedWalletFetching,
    refetch: refetchMinedWallet,
  }: any = callQuery('auth/wallet', {
    requestTimeoutMs: 8000,
    retry: 0,
    staleTime: 60000,
  });
  const {data: notificationCountData, refetch: refetchNotificationCount}: any =
    callQuery('auth/count-notification', {
      retry: 0,
      staleTime: 30000,
    });
  const minedWallet =
    minedWalletData?.data?.data ?? minedWalletData?.data ?? {};
  const rewardRefreshHandledRef = useRef<number | undefined>();
  const refreshWalletHome = useCallback(async () => {
    await Promise.all([
      refreshSelectedNetwork(),
      refetchMinedWallet?.(),
      refetchNotificationCount?.(),
    ]);
  }, [refreshSelectedNetwork, refetchMinedWallet, refetchNotificationCount]);

  useEffect(() => {
    const rewardRefreshMarker = route.params?.refreshWalletBalanceAt;

    if (
      !route.params?.openedFromRewardNotification ||
      !rewardRefreshMarker ||
      rewardRefreshHandledRef.current === rewardRefreshMarker
    ) {
      return;
    }

    rewardRefreshHandledRef.current = rewardRefreshMarker;
    refreshWalletHome().catch(error => {
      console.log('Reward wallet refresh warning', error);
    });
  }, [
    refreshWalletHome,
    route.params?.openedFromRewardNotification,
    route.params?.refreshWalletBalanceAt,
  ]);
  const notificationCount = normalizeNotificationCount(notificationCountData);
  const selectedNetworkLabel =
    preferences.selectedNetworkKey === 'all'
      ? 'All chains'
      : WALLET_NETWORKS[preferences.selectedNetworkKey].shortName;
  return (
    <WalletModeLayout
      contentContainerStyle={styles.walletSceneContent}
      showModeSwitcher={false}>
      <WalletHomeHeader
        navigation={navigation}
        notificationCount={notificationCount}
      />
      <WalletPortfolioHero
        walletName={wallet?.name ?? 'Blockrium Wallet'}
        address={wallet?.address}
        networkLabel={selectedNetworkLabel}
        totalUsd={totalUsd}
        availableBalance={formatMinedTokenValue(minedWallet?.wallet_balance)}
        pendingBalance={formatMinedTokenValue(minedWallet?.unVerifiedBalance)}
        totalMined={formatMinedTokenValue(
          Number(minedWallet?.wallet_balance ?? 0) +
            Number(minedWallet?.unVerifiedBalance ?? 0),
        )}
        onCopy={() => {
          if (wallet?.address) {
            copyToClipboard(wallet.address);
            showAlert('Wallet address copied.', 'SUCCESS');
          }
        }}
      />

      <View style={styles.actionRow}>
        <WalletActionPill
          label="Send"
          icon="send-outline"
          onPress={() => navigation.navigate('WalletSend')}
        />
        <WalletActionPill
          label="Receive"
          icon="qrcode"
          onPress={() => navigation.navigate('WalletReceive')}
        />
        <WalletActionPill
          label="History"
          icon="history"
          onPress={() => navigation.navigate('WalletHistory')}
        />
      </View>

      <WalletChipScroller
        networkKeys={availableNetworks}
        activeKey={preferences.selectedNetworkKey}
        onSelect={selectNetwork}
      />

      <AppodealBannerSlot />

      <WalletListSection
        title="Assets"
        actionLabel="+ Add token"
        onActionPress={() => navigation.navigate('WalletAddToken')}>
        <View style={styles.assetsSectionHeader}>
          <Text style={styles.assetsSectionMeta}>
            {currentAssets.length} asset{currentAssets.length === 1 ? '' : 's'}
          </Text>
          <Pressable
            disabled={isRefreshing || isMinedWalletFetching}
            onPress={refreshWalletHome}
            style={({pressed}) => [
              styles.assetsRefreshChip,
              (pressed || isRefreshing || isMinedWalletFetching) &&
                styles.pressedSurface,
            ]}>
            {isRefreshing || isMinedWalletFetching ? (
              <ActivityIndicator size="small" color={theme.colors.primary} />
            ) : (
              <>
                <Ionicons
                  name="refresh-outline"
                  size={14}
                  color={theme.colors.primary}
                />
                <Text style={styles.assetsRefreshText}>Refresh</Text>
              </>
            )}
          </Pressable>
        </View>
        {currentAssets.length ? (
          currentAssets.map(asset => (
            <WalletAssetRow
              key={asset.id}
              asset={asset}
              onPress={() =>
                navigation.navigate('WalletTokenDetails', {assetId: asset.id})
              }
            />
          ))
        ) : (
          <WalletInfoNotice
            title="No visible assets"
            text="No assets found on this network. Refresh or switch networks."
            tone={theme.colors.accent}
          />
        )}
      </WalletListSection>

      <WalletListSection
        title="Recent activity"
        actionLabel="View all"
        onActionPress={() => navigation.navigate('WalletHistory')}>
        {activity.length ? (
          activity
            .slice(0, 3)
            .map(item => <WalletHistoryRow key={item.id} item={item} />)
        ) : (
          <Text style={styles.emptyText}>
            Transactions, token imports, and wallet actions will appear here.
          </Text>
        )}
      </WalletListSection>
    </WalletModeLayout>
  );
};

const WalletSwapScreen = ({
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletSwap'>) => {
  return (
    <WalletModeLayout
      contentContainerStyle={styles.walletSceneContent}
      showModeSwitcher={false}>
      <WalletMainHeader title="Swap" subtitle="Coming soon" navigation={navigation} />
      <WalletHeaderNotice
        icon="swap-horizontal-outline"
        title="Swap unavailable"
        text="Token swaps are not available yet."
      />
      <View style={styles.actionRow}>
        <WalletActionPill
          label="Send"
          icon="send-outline"
          onPress={() => navigation.navigate('WalletSend')}
        />
        <WalletActionPill
          label="Receive"
          icon="qrcode"
          onPress={() => navigation.navigate('WalletReceive')}
        />
      </View>
    </WalletModeLayout>
  );
};

const WalletPayScreen = ({
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletPay'>) => {
  return (
    <WalletModeLayout
      contentContainerStyle={styles.walletSceneContent}
      showModeSwitcher={false}>
      <WalletMainHeader
        title="Pay"
        subtitle="Send and receive assets."
        navigation={navigation}
      />
      <View style={styles.actionRow}>
        <WalletActionPill
          label="Send"
          icon="send-outline"
          onPress={() => navigation.navigate('WalletSend')}
        />
        <WalletActionPill
          label="Receive"
          icon="qrcode"
          onPress={() => navigation.navigate('WalletReceive')}
        />
        <WalletActionPill
          label="Add token"
          icon="plus-circle-outline"
          onPress={() => navigation.navigate('WalletAddToken')}
        />
        <WalletActionPill
          label="History"
          icon="history"
          onPress={() => navigation.navigate('WalletHistory')}
        />
      </View>
    </WalletModeLayout>
  );
};

const walletBrowserShortcuts = [
  {
    label: 'Uniswap',
    subtitle: 'DEX and token swaps',
    url: 'https://app.uniswap.org/',
  },
  {
    label: 'OpenSea',
    subtitle: 'NFT marketplace',
    url: 'https://opensea.io/',
  },
  {
    label: 'Polygon',
    subtitle: 'Network and ecosystem',
    url: 'https://polygon.technology/',
  },
  {
    label: 'Base',
    subtitle: 'Layer 2 ecosystem',
    url: 'https://base.org/',
  },
];

const walletBrowserCategories = [
  {label: 'DeFi', url: 'https://app.uniswap.org/'},
  {label: 'NFTs', url: 'https://opensea.io/'},
  {label: 'Security', url: 'https://trustwallet.com/blog'},
  {label: 'Learning', url: 'https://ethereum.org/'},
];

const walletBrowserHighlights = [
  {
    label: 'Ethereum',
    subtitle: 'Foundation guides, apps, and network resources.',
    url: 'https://ethereum.org/',
  },
  {
    label: 'CoinGecko',
    subtitle: 'Track token prices, categories, and market leaders.',
    url: 'https://www.coingecko.com/',
  },
];

const walletBrowserNews = [
  {
    title: 'Ethereum ecosystem overview',
    subtitle: 'Open the official Ethereum portal and network resources.',
    url: 'https://ethereum.org/',
  },
  {
    title: 'Polygon developer updates',
    subtitle: 'Read network updates and ecosystem launches.',
    url: 'https://polygon.technology/blog',
  },
  {
    title: 'Wallet security guides',
    subtitle: 'Review self-custody and transaction safety best practices.',
    url: 'https://trustwallet.com/blog',
  },
];

const normalizeBrowserUrl = (value: string) => {
  const trimmedValue = value.trim();
  const withScheme = /^[a-z][a-z0-9+.-]*:\/\//i.test(trimmedValue)
    ? trimmedValue
    : `https://${trimmedValue}`;

  if (!/^https:\/\//i.test(withScheme)) {
    throw new Error(
      'Only secure HTTPS websites can open inside Blockrium Wallet.',
    );
  }

  return withScheme;
};

const isAllowedWalletBrowserUrl = (value: string) => {
  try {
    const parsedUrl = new URL(value);
    return parsedUrl.protocol === 'https:';
  } catch {
    return false;
  }
};

const WalletBrowserScreen = ({
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletBrowser'>) => {
  const [browserUrl, setBrowserUrl] = useState('');
  const {showAlert} = useThisAlert();

  const openBrowserPage = (rawUrl?: string, title?: string) => {
    const trimmedUrl = (rawUrl ?? browserUrl).trim();

    if (!trimmedUrl) {
      showAlert('Enter a website URL first.', 'INFO');
      return;
    }

    try {
      const nextUrl = normalizeBrowserUrl(trimmedUrl);
      navigation.navigate('WalletBrowserWeb', {
        url: nextUrl,
        title,
      });
    } catch (error: any) {
      showAlert(error?.message || 'Enter a secure HTTPS website.', 'ERROR');
    }
  };

  return (
    <WalletModeLayout
      contentContainerStyle={styles.walletSceneContent}
      showModeSwitcher={false}>
      <WalletMainHeader
        title="Discover"
        subtitle="Trusted dApps, chain resources, and Web3 news."
        navigation={navigation}
      />
      <GlassCard style={styles.browserToolbarCard}>
        <WalletInputGroup
          label="Search or enter URL"
          value={browserUrl}
          onChangeText={setBrowserUrl}
          placeholder="Enter a dApp or website URL"
          autoCapitalize="none"
        />
        <PrimaryButton
          label="Open browser"
          icon="web"
          onPress={() => openBrowserPage()}
        />
      </GlassCard>

      <WalletListSection title="Featured dApps">
        <View style={styles.browserDiscoveryGrid}>
          {walletBrowserShortcuts.map(item => (
            <Pressable
              key={item.url}
              style={styles.browserDiscoveryCard}
              onPress={() => openBrowserPage(item.url, item.label)}>
              <View style={styles.browserDiscoveryIcon}>
                <Ionicons
                  name="globe-outline"
                  size={18}
                  color={theme.colors.primary}
                />
              </View>
              <Text style={styles.browserDiscoveryTitle}>{item.label}</Text>
              <Text style={styles.browserDiscoverySub}>{item.subtitle}</Text>
            </Pressable>
          ))}
        </View>
      </WalletListSection>

      <WalletListSection title="Explore by topic">
        <View style={styles.browserQuickRow}>
          {walletBrowserCategories.map(item => (
            <Pressable
              key={item.label}
              style={styles.browserQuickChip}
              onPress={() => openBrowserPage(item.url, item.label)}>
              <Text style={styles.browserQuickChipText}>{item.label}</Text>
            </Pressable>
          ))}
        </View>
      </WalletListSection>

      <View style={styles.browserDiscoveryGrid}>
        {walletBrowserHighlights.map(item => (
          <Pressable
            key={item.url}
            style={styles.browserDiscoveryCard}
            onPress={() => openBrowserPage(item.url, item.label)}>
            <View style={styles.browserDiscoveryIcon}>
              <Ionicons
                name="globe-outline"
                size={18}
                color={theme.colors.primary}
              />
            </View>
            <Text style={styles.browserDiscoveryTitle}>{item.label}</Text>
            <Text style={styles.browserDiscoverySub}>{item.subtitle}</Text>
          </Pressable>
        ))}
      </View>
      <WalletListSection title="Latest from Web3">
        {walletBrowserNews.map(item => (
          <Pressable
            key={item.url}
            style={styles.quickLinkRow}
            onPress={() => openBrowserPage(item.url, item.title)}>
            <View style={styles.quickLinkCopy}>
              <Text style={styles.quickLinkTitle}>{item.title}</Text>
              <Text style={styles.quickLinkSub}>{item.subtitle}</Text>
            </View>
            <Ionicons
              name="chevron-forward"
              size={18}
              color={theme.colors.textSoft}
            />
          </Pressable>
        ))}
      </WalletListSection>
    </WalletModeLayout>
  );
};

const WalletBrowserWebScreen = ({
  navigation,
  route,
}: NativeStackScreenProps<WalletStackParamList, 'WalletBrowserWeb'>) => {
  const {showAlert} = useThisAlert();
  const initialUrl = (() => {
    try {
      return normalizeBrowserUrl(route.params.url);
    } catch {
      return 'https://blockrium.com';
    }
  })();
  const webViewRef = useRef<WebView>(null);
  const [currentUrl, setCurrentUrl] = useState(initialUrl);
  const [addressBarValue, setAddressBarValue] = useState(initialUrl);
  const [pageTitle, setPageTitle] = useState(route.params.title ?? 'Browser');
  const [canGoBack, setCanGoBack] = useState(false);
  const [canGoForward, setCanGoForward] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [hasError, setHasError] = useState(false);

  const submitUrl = (rawValue?: string) => {
    try {
      const nextValue = normalizeBrowserUrl(rawValue ?? addressBarValue);
      setAddressBarValue(nextValue);
      setCurrentUrl(nextValue);
      setHasError(false);
      setIsLoading(true);
    } catch (error: any) {
      showAlert(error?.message || 'Enter a secure HTTPS website.', 'ERROR');
    }
  };

  return (
    <SafeAreaView style={styles.browserScreen}>
      <View style={styles.browserHeader}>
        <Pressable
          onPress={() => {
            if (canGoBack) {
              webViewRef.current?.goBack();
              return;
            }

            navigation.goBack();
          }}
          style={({pressed}) => [
            styles.browserNavButton,
            pressed && styles.pressedSurface,
          ]}>
          <Ionicons name="arrow-back" size={18} color={theme.colors.text} />
        </Pressable>
        <View style={styles.browserAddressShell}>
          <Ionicons
            name="shield-checkmark-outline"
            size={15}
            color={theme.colors.primary}
          />
          <TextInput
            value={addressBarValue}
            onChangeText={setAddressBarValue}
            onSubmitEditing={() => submitUrl()}
            placeholder="Search or enter URL"
            placeholderTextColor={theme.colors.textMuted}
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="url"
            returnKeyType="go"
            style={styles.browserAddressInput}
          />
        </View>
        <Pressable
          onPress={() => webViewRef.current?.reload()}
          style={({pressed}) => [
            styles.browserNavButton,
            pressed && styles.pressedSurface,
          ]}>
          <Ionicons
            name="refresh-outline"
            size={18}
            color={theme.colors.text}
          />
        </Pressable>
      </View>

      <View style={styles.browserControlsRow}>
        <View style={styles.browserPageMeta}>
          <Text numberOfLines={1} style={styles.browserPageTitle}>
            {pageTitle}
          </Text>
          <Text numberOfLines={1} style={styles.browserPageUrl}>
            {currentUrl}
          </Text>
        </View>
        <View style={styles.browserInlineActions}>
          <Pressable
            disabled={!canGoForward}
            onPress={() => webViewRef.current?.goForward()}
            style={({pressed}) => [
              styles.browserMiniButton,
              !canGoForward && styles.browserMiniButtonDisabled,
              pressed && canGoForward && styles.pressedSurface,
            ]}>
            <Ionicons
              name="arrow-forward"
              size={16}
              color={canGoForward ? theme.colors.text : theme.colors.textMuted}
            />
          </Pressable>
          <Pressable
            onPress={() => Linking.openURL(currentUrl)}
            style={({pressed}) => [
              styles.browserMiniButton,
              pressed && styles.pressedSurface,
            ]}>
            <Ionicons name="open-outline" size={16} color={theme.colors.text} />
          </Pressable>
        </View>
      </View>

      <View style={styles.browserWebViewContainerFull}>
        <WebView
          ref={webViewRef}
          source={{uri: currentUrl}}
          style={styles.browserWebViewFull}
          originWhitelist={['https://*']}
          javaScriptEnabled
          domStorageEnabled
          sharedCookiesEnabled={false}
          thirdPartyCookiesEnabled={false}
          incognito
          mixedContentMode="never"
          setSupportMultipleWindows={false}
          allowsBackForwardNavigationGestures
          startInLoadingState
          onShouldStartLoadWithRequest={request => {
            if (!isAllowedWalletBrowserUrl(request.url)) {
              showAlert('Blocked a non-HTTPS wallet browser request.', 'ERROR');
              return false;
            }

            return true;
          }}
          onLoadStart={() => {
            setIsLoading(true);
            setHasError(false);
          }}
          onLoadEnd={() => {
            setIsLoading(false);
          }}
          onError={() => {
            setIsLoading(false);
            setHasError(true);
          }}
          onNavigationStateChange={navState => {
            setCanGoBack(navState.canGoBack);
            setCanGoForward(navState.canGoForward);
            setCurrentUrl(navState.url);
            setAddressBarValue(navState.url);
            if (navState.title) {
              setPageTitle(navState.title);
            }
          }}
          renderLoading={() => (
            <View style={styles.browserLoadingState}>
              <ActivityIndicator size="large" color={theme.colors.primary} />
              <Text style={styles.browserLoadingText}>
                Loading browser page...
              </Text>
            </View>
          )}
          renderError={() => (
            <View style={styles.browserErrorState}>
              <Ionicons
                name="alert-circle-outline"
                size={24}
                color={theme.colors.primary}
              />
              <Text style={styles.browserErrorTitle}>Page could not load</Text>
              <Text style={styles.browserErrorText}>
                Check the URL or try reloading this dApp page.
              </Text>
              <PrimaryButton
                label="Reload page"
                icon="refresh-outline"
                onPress={() => {
                  setHasError(false);
                  setIsLoading(true);
                  webViewRef.current?.reload();
                }}
                style={styles.browserErrorButton}
              />
            </View>
          )}
        />
        {isLoading && !hasError ? (
          <View pointerEvents="none" style={styles.browserLoadingOverlay}>
            <ActivityIndicator size="small" color={theme.colors.primary} />
          </View>
        ) : null}
      </View>
    </SafeAreaView>
  );
};

const WalletModeSettingsScreen = ({
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletModeSettings'>) => {
  const {wallet} = useWalletModule();

  return (
    <WalletModeLayout
      contentContainerStyle={styles.walletSceneContent}
      showModeSwitcher={false}>
      <WalletMainHeader
        title="Settings"
        subtitle="Security, recovery access, networks, and wallet tools."
        navigation={navigation}
      />
      <GlassCard style={styles.settingsProfileCard}>
        <View style={styles.settingsProfileOrb}>
          <Ionicons
            name="shield-checkmark-outline"
            size={20}
            color={theme.colors.primary}
          />
        </View>
        <View style={styles.settingsProfileCopy}>
          <Text style={styles.settingsProfileTitle}>
            {wallet?.name ?? 'Blockrium Wallet'}
          </Text>
          <Text style={styles.settingsProfileSub}>
            {wallet?.address
              ? `${wallet.address.slice(0, 8)}...${wallet.address.slice(-6)}`
              : 'Wallet address not ready'}
          </Text>
        </View>
      </GlassCard>
      <WalletListSection title="Quick controls">
        <Pressable
          style={styles.quickLinkRow}
          onPress={() => navigation.navigate('WalletSettings')}>
          <View style={styles.quickLinkCopy}>
            <Text style={styles.quickLinkTitle}>Security and settings</Text>
            <Text style={styles.quickLinkSub}>
              Passcode, biometrics, auto-lock, and recovery phrase access.
            </Text>
          </View>
          <Ionicons
            name="chevron-forward"
            size={18}
            color={theme.colors.textSoft}
          />
        </Pressable>
        <Pressable
          style={styles.quickLinkRow}
          onPress={() => navigation.navigate('WalletHistory')}>
          <View style={styles.quickLinkCopy}>
            <Text style={styles.quickLinkTitle}>Transaction history</Text>
            <Text style={styles.quickLinkSub}>
              Review sent transactions, wallet setup actions, and token imports.
            </Text>
          </View>
          <Ionicons
            name="chevron-forward"
            size={18}
            color={theme.colors.textSoft}
          />
        </Pressable>
        <Pressable
          style={styles.quickLinkRow}
          onPress={() => navigation.navigate('WalletNetworks')}>
          <View style={styles.quickLinkCopy}>
            <Text style={styles.quickLinkTitle}>Networks</Text>
            <Text style={styles.quickLinkSub}>
              Switch active networks and manage testnet visibility.
            </Text>
          </View>
          <Ionicons
            name="chevron-forward"
            size={18}
            color={theme.colors.textSoft}
          />
        </Pressable>
        <Pressable
          style={styles.quickLinkRow}
          onPress={() => navigation.navigate('WalletAddToken')}>
          <View style={styles.quickLinkCopy}>
            <Text style={styles.quickLinkTitle}>Add token</Text>
            <Text style={styles.quickLinkSub}>
              Search popular tokens or add a verified custom contract.
            </Text>
          </View>
          <Ionicons
            name="chevron-forward"
            size={18}
            color={theme.colors.textSoft}
          />
        </Pressable>
      </WalletListSection>
    </WalletModeLayout>
  );
};

const WalletTokenDetailsScreen = ({
  navigation,
  route,
}: NativeStackScreenProps<WalletStackParamList, 'WalletTokenDetails'>) => {
  const {assetsByNetwork} = useWalletModule();
  const asset = findAssetById(assetsByNetwork, route.params.assetId);

  if (!asset) {
    return (
      <WalletModeLayout
        title="Asset"
        subtitle="This token is no longer available in the current wallet state.">
        <PrimaryButton
          label="Back to wallet"
          icon="arrow-left"
          onPress={() => navigation.goBack()}
        />
      </WalletModeLayout>
    );
  }

  return (
    <WalletModeLayout
      title={asset.symbol}
      subtitle={`${asset.name} on ${
        WALLET_NETWORKS[asset.networkKey].shortName
      }`}
      showBack
      onBackPress={() => navigation.goBack()}>
      <GlassCard>
        <Text style={styles.detailBalance}>
          {formatBalance(asset.balance, asset.symbol)}
        </Text>
        <Text style={styles.detailUsd}>{formatUsd(asset.usdValue)}</Text>
        <View style={styles.detailMetaWrap}>
          <Text style={styles.detailMetaLabel}>Contract</Text>
          <Text style={styles.detailMetaValue}>
            {asset.address ?? 'Native asset'}
          </Text>
        </View>
        <View style={styles.detailMetaWrap}>
          <Text style={styles.detailMetaLabel}>Network</Text>
          <Text style={styles.detailMetaValue}>
            {WALLET_NETWORKS[asset.networkKey].name}
          </Text>
        </View>
        <PrimaryButton
          label={`Send ${asset.symbol}`}
          icon="send-outline"
          style={styles.primaryCta}
          onPress={() => navigation.navigate('WalletSend', {assetId: asset.id})}
        />
        <SecondaryButton
          label="Receive"
          icon="qrcode"
          style={styles.secondaryCta}
          onPress={() => navigation.navigate('WalletReceive')}
        />
      </GlassCard>
    </WalletModeLayout>
  );
};

const WalletSendScreen = ({
  navigation,
  route,
}: NativeStackScreenProps<WalletStackParamList, 'WalletSend'>) => {
  const {assetsByNetwork, currentAssets, sendAsset} = useWalletModule();
  const {showAlert} = useThisAlert();
  const initialAsset =
    findAssetById(assetsByNetwork, route.params?.assetId) ?? currentAssets[0];
  const [assetId, setAssetId] = useState(initialAsset?.id ?? '');
  const [to, setTo] = useState('');
  const [amount, setAmount] = useState('');
  const [passcode, setPasscode] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const selectedAsset =
    currentAssets.find(asset => asset.id === assetId) ??
    findAssetById(assetsByNetwork, assetId) ??
    initialAsset;

  const submitTransaction = async () => {
    if (!selectedAsset) {
      showAlert('Select an asset to send.', 'ERROR');
      return;
    }

    setIsSubmitting(true);

    try {
      await sendAsset({asset: selectedAsset, to, amount, passcode});
      showAlert(`${selectedAsset.symbol} transaction submitted.`, 'SUCCESS');
      setPasscode('');
      navigation.goBack();
    } catch (error: any) {
      showAlert(error?.message || 'Unable to send transaction.', 'ERROR');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <WalletModeLayout
      title="Send"
      subtitle="Send native assets or ERC-20 tokens on the selected network with proper validation."
      showBack
      onBackPress={() => navigation.goBack()}>
      <WalletListSection title="Asset">
        <View style={styles.assetPickerWrap}>
          {currentAssets.slice(0, 6).map(asset => {
            const isActive = asset.id === selectedAsset?.id;
            return (
              <Pressable
                key={asset.id}
                onPress={() => setAssetId(asset.id)}
                style={[
                  styles.assetPickerChip,
                  isActive && styles.assetPickerChipActive,
                ]}>
                <Text
                  style={[
                    styles.assetPickerText,
                    isActive && styles.assetPickerTextActive,
                  ]}>
                  {asset.symbol}
                </Text>
              </Pressable>
            );
          })}
        </View>
      </WalletListSection>

      <GlassCard>
        <WalletInputGroup
          label="Recipient address"
          value={to}
          onChangeText={setTo}
          placeholder="0x..."
          autoCapitalize="none"
        />
        <WalletInputGroup
          label="Amount"
          value={amount}
          onChangeText={setAmount}
          placeholder={`0.00 ${selectedAsset?.symbol ?? ''}`}
          keyboardType="decimal-pad"
          autoCapitalize="none"
        />
        <WalletInputGroup
          label="Wallet passcode"
          value={passcode}
          onChangeText={setPasscode}
          placeholder="Confirm with passcode"
          keyboardType="decimal-pad"
          autoCapitalize="none"
          secureTextEntry
        />
        <WalletInfoNotice
          title="Before you send"
          text="This wallet supports real EVM transactions. The app will ask for one final confirmation before signing."
        />
        <PrimaryButton
          label={
            isSubmitting ? 'Submitting transaction...' : 'Send transaction'
          }
          icon="send-outline"
          loading={isSubmitting}
          style={styles.primaryCta}
          onPress={() => {
            if (!selectedAsset) {
              showAlert('Select an asset to send.', 'ERROR');
              return;
            }

            if (!to.trim() || !amount.trim()) {
              showAlert('Enter the recipient address and amount.', 'ERROR');
              return;
            }

            if (!/^\d{4,6}$/.test(passcode.trim())) {
              showAlert('Enter your 4 to 6 digit wallet passcode.', 'ERROR');
              return;
            }

            Alert.alert(
              'Confirm transaction',
              `Send ${amount.trim()} ${selectedAsset.symbol} on ${
                WALLET_NETWORKS[selectedAsset.networkKey].shortName
              } to ${to.trim()}?`,
              [
                {text: 'Cancel', style: 'cancel'},
                {
                  text: 'Send',
                  style: 'destructive',
                  onPress: submitTransaction,
                },
              ],
            );
          }}
        />
      </GlassCard>
    </WalletModeLayout>
  );
};

const WalletReceiveScreen = ({
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletReceive'>) => {
  const {wallet} = useWalletModule();

  return (
    <WalletModeLayout
      title="Receive"
      subtitle="Show your wallet address as QR and copy it for supported EVM networks."
      showBack
      onBackPress={() => navigation.goBack()}>
      {wallet?.address ? (
        <WalletReceiveCard address={wallet.address} />
      ) : (
        <GlassCard>
          <Text style={styles.emptyText}>
            Create or import a wallet to receive assets.
          </Text>
        </GlassCard>
      )}
    </WalletModeLayout>
  );
};

const WalletAddTokenScreen = ({
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletAddToken'>) => {
  const {preferences, getSuggestions, addSuggestedToken, addCustomToken} =
    useWalletModule();
  const {showAlert} = useThisAlert();
  const [query, setQuery] = useState('');
  const [contractAddress, setContractAddress] = useState('');
  const [targetNetwork, setTargetNetwork] = useState<WalletNetworkKey>(
    preferences.selectedNetworkKey === 'all'
      ? 'ethereum'
      : preferences.selectedNetworkKey,
  );
  const [results, setResults] = useState<any[]>([]);
  const [loadingResults, setLoadingResults] = useState(false);
  const [isAddingCustom, setIsAddingCustom] = useState(false);
  const deferredQuery = useDeferredValue(query);

  useEffect(() => {
    let isMounted = true;
    setLoadingResults(true);

    getSuggestions(deferredQuery, targetNetwork)
      .then(items => {
        if (isMounted) {
          setResults(items);
        }
      })
      .catch(error => {
        if (isMounted) {
          console.log('Wallet token search warning', error);
        }
      })
      .finally(() => {
        if (isMounted) {
          setLoadingResults(false);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [deferredQuery, getSuggestions, targetNetwork]);

  return (
    <WalletModeLayout
      title="Add token"
      subtitle={`Search popular ${WALLET_NETWORKS[targetNetwork].shortName} tokens or import a custom ERC-20 contract.`}
      showBack
      onBackPress={() => navigation.goBack()}>
      <WalletChipScroller
        networkKeys={
          preferences.includeTestnets
            ? [
                'ethereum',
                'polygon',
                'bsc',
                'arbitrum',
                'optimism',
                'base',
                'avalanche',
                'sepolia',
                'amoy',
              ]
            : [
                'ethereum',
                'polygon',
                'bsc',
                'arbitrum',
                'optimism',
                'base',
                'avalanche',
              ]
        }
        activeKey={targetNetwork}
        onSelect={key => {
          if (key !== 'all') {
            setTargetNetwork(key);
          }
        }}
      />

      <WalletSearchBar value={query} onChangeText={setQuery} />

      <WalletListSection title="Popular tokens">
        {loadingResults ? (
          <View style={styles.loaderInline}>
            <ActivityIndicator size="small" color={theme.colors.primary} />
          </View>
        ) : !results.length ? (
          <Text style={styles.emptyText}>
            No matching tokens were found for this network. Try a symbol, name,
            or contract.
          </Text>
        ) : (
          results.map(token => (
            <Pressable
              key={token.id}
              style={styles.tokenSuggestionRow}
              onPress={async () => {
                try {
                  await addSuggestedToken(token);
                  showAlert(
                    `${token.symbol} added to your wallet view.`,
                    'SUCCESS',
                  );
                } catch (error: any) {
                  showAlert(error?.message || 'Unable to add token.', 'ERROR');
                }
              }}>
              <View style={styles.tokenSuggestionMeta}>
                <Text style={styles.tokenSuggestionName}>{token.name}</Text>
                <Text style={styles.tokenSuggestionSub}>{token.symbol}</Text>
              </View>
              <Ionicons
                name="add-circle-outline"
                size={20}
                color={theme.colors.primary}
              />
            </Pressable>
          ))
        )}
      </WalletListSection>

      <GlassCard>
        <WalletInputGroup
          label="Custom contract"
          value={contractAddress}
          onChangeText={setContractAddress}
          placeholder="0x..."
          autoCapitalize="none"
        />
        <PrimaryButton
          label={isAddingCustom ? 'Importing token...' : 'Import custom token'}
          icon="plus-circle-outline"
          style={styles.primaryCta}
          onPress={async () => {
            setIsAddingCustom(true);

            try {
              const token = await addCustomToken(
                contractAddress,
                targetNetwork,
              );
              setContractAddress('');
              showAlert(`${token.symbol} imported successfully.`, 'SUCCESS');
            } catch (error: any) {
              showAlert(error?.message || 'Unable to import token.', 'ERROR');
            } finally {
              setIsAddingCustom(false);
            }
          }}
        />
      </GlassCard>
    </WalletModeLayout>
  );
};

const WalletNetworksScreen = ({
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletNetworks'>) => {
  const {preferences, availableNetworks, selectNetwork, toggleTestnets} =
    useWalletModule();

  return (
    <WalletModeLayout
      title="Networks"
      subtitle="Switch the active blockchain network and control whether testnets appear inside wallet mode."
      showBack
      onBackPress={() => navigation.goBack()}>
      <GlassCard>
        <View style={styles.switchRow}>
          <View style={styles.switchCopy}>
            <Text style={styles.switchTitle}>Show testnets</Text>
            <Text style={styles.switchSub}>
              Include Sepolia and Amoy alongside your mainnets.
            </Text>
          </View>
          <Switch
            value={preferences.includeTestnets}
            onValueChange={toggleTestnets}
            trackColor={{
              false: theme.colors.surfaceStrong,
              true: alpha(theme.colors.primary, 0.6),
            }}
            thumbColor={theme.colors.white}
          />
        </View>
      </GlassCard>

      <WalletListSection title="Available networks">
        {availableNetworks.map(networkKey => {
          const network =
            networkKey === 'all'
              ? {
                  name: 'All chains',
                  chainId: 0,
                  accent: theme.colors.primary,
                  symbol: 'A',
                  isTestnet: false,
                }
              : WALLET_NETWORKS[networkKey];
          const isActive = networkKey === preferences.selectedNetworkKey;

          return (
            <Pressable
              key={networkKey}
              style={styles.networkRow}
              onPress={async () => {
                await selectNetwork(networkKey);
                navigation.goBack();
              }}>
              <View
                style={[
                  styles.networkOrb,
                  {backgroundColor: alpha(network.accent, 0.18)},
                ]}>
                <Text style={[styles.networkOrbText, {color: network.accent}]}>
                  {network.symbol.slice(0, 1)}
                </Text>
              </View>
              <View style={styles.networkMeta}>
                <Text style={styles.networkName}>{network.name}</Text>
                <Text style={styles.networkSub}>
                  {networkKey === 'all'
                    ? 'Combined balances across your active wallet networks.'
                    : `Chain ID ${network.chainId}${
                        network.isTestnet ? ' | Testnet' : ' | Mainnet'
                      }`}
                </Text>
              </View>
              {isActive ? (
                <Ionicons
                  name="checkmark-circle"
                  size={20}
                  color={theme.colors.primary}
                />
              ) : null}
            </Pressable>
          );
        })}
      </WalletListSection>
    </WalletModeLayout>
  );
};

const WalletSettingsScreen = ({
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletSettings'>) => {
  const {
    biometricType,
    wallet,
    customTokens,
    visibleTokenIds,
    securitySettings,
    setAutoLockOnBackground,
    setBiometricUnlockEnabled,
  } = useWalletModule();
  const {showAlert} = useThisAlert();
  const [isUpdatingBiometric, setIsUpdatingBiometric] = useState(false);

  const handleBiometricToggle = async (enabled: boolean) => {
    setIsUpdatingBiometric(true);

    try {
      const nextValue = await setBiometricUnlockEnabled(enabled);
      showAlert(
        nextValue
          ? 'Biometric unlock is now enabled for wallet mode.'
          : 'Biometric unlock has been turned off.',
        'SUCCESS',
      );
    } catch (error: any) {
      showAlert(
        error?.message || 'Unable to update biometric unlock.',
        'ERROR',
      );
    } finally {
      setIsUpdatingBiometric(false);
    }
  };

  return (
    <WalletModeLayout
      title="Settings"
      subtitle="Wallet identity, secure storage notes, and module-level configuration."
      showBack
      onBackPress={() => navigation.goBack()}>
      <GlassCard>
        <View style={styles.settingsRow}>
          <Text style={styles.settingsLabel}>Wallet name</Text>
          <Text style={styles.settingsValue}>
            {wallet?.name ?? 'Not created'}
          </Text>
        </View>
        <View style={styles.settingsRow}>
          <Text style={styles.settingsLabel}>Address</Text>
          <Text style={styles.settingsValue}>
            {wallet?.address ?? 'Unavailable'}
          </Text>
        </View>
        <View style={styles.settingsRow}>
          <Text style={styles.settingsLabel}>Visible tokens</Text>
          <Text style={styles.settingsValue}>{visibleTokenIds.length}</Text>
        </View>
        <View style={styles.settingsRow}>
          <Text style={styles.settingsLabel}>Custom tokens</Text>
          <Text style={styles.settingsValue}>{customTokens.length}</Text>
        </View>
        <View style={styles.settingsRow}>
          <Text style={styles.settingsLabel}>Notifications</Text>
          <Text style={styles.settingsValue}>
            {securitySettings.notificationsEnabled ? 'Enabled' : 'Skipped'}
          </Text>
        </View>
        <View style={styles.settingsRow}>
          <Text style={styles.settingsLabel}>Wallet passcode</Text>
          <Text style={styles.settingsValue}>
            {securitySettings.passcodeEnabled ? 'Enabled' : 'Not set'}
          </Text>
        </View>
        <View style={styles.settingsRow}>
          <Text style={styles.settingsLabel}>Biometric unlock</Text>
          <Text style={styles.settingsValue}>
            {securitySettings.biometricEnabled && biometricType
              ? `${biometricType} enabled`
              : biometricType
              ? `${biometricType} available`
              : 'Not available'}
          </Text>
        </View>
      </GlassCard>

      <WalletListSection title="Security">
        <View style={styles.switchRow}>
          <View style={styles.switchCopy}>
            <Text style={styles.switchTitle}>Auto-lock wallet mode</Text>
            <Text style={styles.switchSub}>
              Require passcode or biometrics again when you leave the app or
              switch back to Blockrium.
            </Text>
          </View>
          <Switch
            value={securitySettings.autoLockOnBackground}
            onValueChange={value => {
              setAutoLockOnBackground(value).catch(error => {
                showAlert(
                  error?.message || 'Unable to update wallet auto-lock.',
                  'ERROR',
                );
              });
            }}
            trackColor={{
              false: alpha(theme.colors.textSoft, 0.24),
              true: alpha(theme.colors.primary, 0.38),
            }}
            thumbColor={
              securitySettings.autoLockOnBackground
                ? theme.colors.primary
                : theme.colors.text
            }
          />
        </View>
        <View style={styles.switchRow}>
          <View style={styles.switchCopy}>
            <Text style={styles.switchTitle}>Biometric unlock</Text>
            <Text style={styles.switchSub}>
              Use {biometricType ?? 'device security'} to reopen wallet mode
              faster on this device.
            </Text>
          </View>
          <Switch
            value={securitySettings.biometricEnabled}
            disabled={!biometricType || isUpdatingBiometric}
            onValueChange={handleBiometricToggle}
            trackColor={{
              false: alpha(theme.colors.textSoft, 0.24),
              true: alpha(theme.colors.primary, 0.38),
            }}
            thumbColor={
              securitySettings.biometricEnabled
                ? theme.colors.primary
                : theme.colors.text
            }
          />
        </View>
      </WalletListSection>

      <WalletListSection title="Manage wallet">
        <Pressable
          style={styles.settingsActionRow}
          onPress={() => {
            if (wallet?.address) {
              copyToClipboard(wallet.address);
              showAlert('Wallet address copied.', 'SUCCESS');
            } else {
              showAlert('Create or import a wallet first.', 'ERROR');
            }
          }}>
          <View style={styles.settingsActionCopy}>
            <Text style={styles.settingsActionTitle}>Copy address</Text>
            <Text style={styles.settingsActionSub}>
              Quickly copy your active wallet address.
            </Text>
          </View>
          <Ionicons
            name="copy-outline"
            size={18}
            color={theme.colors.primary}
          />
        </Pressable>
        <Pressable
          style={styles.settingsActionRow}
          onPress={() => navigation.navigate('WalletNetworks')}>
          <View style={styles.settingsActionCopy}>
            <Text style={styles.settingsActionTitle}>Networks</Text>
            <Text style={styles.settingsActionSub}>
              Choose the active chain and control testnet visibility.
            </Text>
          </View>
          <Ionicons
            name="chevron-forward"
            size={18}
            color={theme.colors.textSoft}
          />
        </Pressable>
        <Pressable
          style={styles.settingsActionRow}
          onPress={() => navigation.navigate('WalletSecretPhrase')}>
          <View style={styles.settingsActionCopy}>
            <Text style={styles.settingsActionTitle}>Reveal secret phrase</Text>
            <Text style={styles.settingsActionSub}>
              Enter your wallet passcode before the recovery phrase is shown.
            </Text>
          </View>
          <Ionicons
            name="lock-closed-outline"
            size={18}
            color={theme.colors.textSoft}
          />
        </Pressable>
        <Pressable
          style={styles.settingsActionRow}
          onPress={() => navigation.navigate('WalletHistory')}>
          <View style={styles.settingsActionCopy}>
            <Text style={styles.settingsActionTitle}>Wallet history</Text>
            <Text style={styles.settingsActionSub}>
              Review local wallet actions and submitted transactions.
            </Text>
          </View>
          <Ionicons
            name="chevron-forward"
            size={18}
            color={theme.colors.textSoft}
          />
        </Pressable>
        <Pressable
          style={styles.settingsActionRow}
          onPress={() => showAlert('Address book is coming soon.', 'INFO')}>
          <View style={styles.settingsActionCopy}>
            <Text style={styles.settingsActionTitle}>Address book</Text>
            <Text style={styles.settingsActionSub}>
              Save trusted addresses and labels for faster sending.
            </Text>
          </View>
          <Ionicons
            name="bookmark-outline"
            size={18}
            color={theme.colors.textSoft}
          />
        </Pressable>
        <Pressable
          style={styles.settingsActionRow}
          onPress={() => showAlert('WalletConnect is coming soon.', 'INFO')}>
          <View style={styles.settingsActionCopy}>
            <Text style={styles.settingsActionTitle}>WalletConnect</Text>
            <Text style={styles.settingsActionSub}>
              Connect the wallet to dApps from one secure entry point.
            </Text>
          </View>
          <Ionicons
            name="scan-outline"
            size={18}
            color={theme.colors.textSoft}
          />
        </Pressable>
        <Pressable
          style={styles.settingsActionRow}
          onPress={() => appNavigate('HelpCenter')}>
          <View style={styles.settingsActionCopy}>
            <Text style={styles.settingsActionTitle}>Support and about</Text>
            <Text style={styles.settingsActionSub}>
              Get help with your wallet or report an app issue.
            </Text>
          </View>
          <Ionicons
            name="information-circle-outline"
            size={18}
            color={theme.colors.textSoft}
          />
        </Pressable>
      </WalletListSection>

      <WalletInfoNotice
        title="Blockrium Shield active"
        text="Recovery phrase stays in device keychain storage, wallet mode is separate from Blockrium rewards, and every unlock route now flows through wallet security."
      />
    </WalletModeLayout>
  );
};

const WalletSecretPhraseScreen = ({
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletSecretPhrase'>) => {
  const {revealSecretPhrase} = useWalletModule();
  const {showAlert} = useThisAlert();
  const [passcode, setPasscode] = useState('');
  const [secretPhrase, setSecretPhrase] = useState('');
  const [isLoadingPhrase, setIsLoadingPhrase] = useState(false);

  return (
    <WalletModeLayout
      title="Reveal phrase"
      subtitle="Enter your wallet passcode before the recovery phrase is shown on this device."
      showBack
      onBackPress={() => navigation.goBack()}>
      <WalletInfoNotice
        icon="alert-circle-outline"
        tone={theme.colors.accentWarm}
        title="Private information"
        text="Never share your recovery phrase. Blockrium support will never ask for it."
      />
      <GlassCard>
        <WalletInputGroup
          label="Wallet passcode"
          value={passcode}
          onChangeText={setPasscode}
          placeholder="Enter your passcode"
          keyboardType="decimal-pad"
          autoCapitalize="none"
        />
        <PrimaryButton
          label={isLoadingPhrase ? 'Unlocking phrase...' : 'Show phrase'}
          icon="shield-check-outline"
          style={styles.primaryCta}
          onPress={async () => {
            setIsLoadingPhrase(true);

            try {
              const nextPhrase = await revealSecretPhrase(passcode);
              setSecretPhrase(nextPhrase);
            } catch (error: any) {
              showAlert(
                error?.message || 'Unable to reveal the phrase.',
                'ERROR',
              );
            } finally {
              setIsLoadingPhrase(false);
            }
          }}
        />
        {secretPhrase ? (
          <>
            <Text style={styles.secretPhraseText}>{secretPhrase}</Text>
            <SecondaryButton
              label="Copy phrase"
              icon="content-copy"
              style={styles.secondaryCta}
              onPress={() => {
                copyToClipboard(secretPhrase);
                showAlert('Recovery phrase copied.', 'SUCCESS');
              }}
            />
          </>
        ) : null}
      </GlassCard>
    </WalletModeLayout>
  );
};

const WalletHistoryScreen = ({
  navigation,
}: NativeStackScreenProps<WalletStackParamList, 'WalletHistory'>) => {
  const {activity} = useWalletModule();

  return (
    <WalletModeLayout
      title="History"
      subtitle="Wallet actions and submitted transactions recorded by this wallet module."
      showBack
      onBackPress={() => navigation.goBack()}>
      <WalletListSection title="Recent activity">
        {activity.length ? (
          activity.map(item => <WalletHistoryRow key={item.id} item={item} />)
        ) : (
          <Text style={styles.emptyText}>
            Wallet activity will appear here after you create, import, add
            tokens, or send transactions.
          </Text>
        )}
      </WalletListSection>
    </WalletModeLayout>
  );
};

const styles = StyleSheet.create({
  onboardingHero: {
    alignItems: 'center',
    paddingTop: 20,
    paddingBottom: 20,
  },
  logoOrb: {
    width: 64,
    height: 64,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.primary, 0.16),
    marginBottom: 14,
  },
  onboardingTitle: {
    color: theme.colors.text,
    fontSize: 24,
    lineHeight: 29,
    textAlign: 'center',
    fontFamily: theme.typography.display,
  },
  onboardingCopy: {
    marginTop: 8,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    textAlign: 'center',
    fontFamily: theme.typography.body,
  },
  primaryCta: {
    width: '100%',
    marginTop: 14,
  },
  secondaryCta: {
    width: '100%',
    marginTop: 10,
  },
  setupHintText: {
    marginTop: 14,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 17,
    textAlign: 'center',
    fontFamily: theme.typography.body,
  },
  readyCard: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  readyOrb: {
    width: 80,
    height: 80,
    borderRadius: 40,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.primary, 0.16),
    marginBottom: 18,
  },
  readyTitle: {
    color: theme.colors.text,
    fontSize: 25,
    textAlign: 'center',
    fontFamily: theme.typography.display,
  },
  readyCopy: {
    marginTop: 10,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 20,
    textAlign: 'center',
    fontFamily: theme.typography.body,
  },
  checkRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    minHeight: 38,
  },
  checkText: {
    flex: 1,
    color: theme.colors.text,
    fontSize: 13,
    lineHeight: 17,
    fontFamily: theme.typography.bodyMedium,
  },
  headerIconButton: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.62),
  },
  walletNotificationBadge: {
    position: 'absolute',
    top: -4,
    right: -4,
    minWidth: 17,
    height: 17,
    borderRadius: 9,
    paddingHorizontal: 4,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.danger,
    borderWidth: 1,
    borderColor: theme.colors.background,
  },
  walletNotificationBadgeText: {
    color: theme.colors.text,
    fontSize: 9,
    lineHeight: 11,
    fontFamily: theme.typography.bodyBold,
  },
  pressedSurface: {
    opacity: 0.72,
    transform: [{scale: 0.98}],
  },
  actionRow: {
    flexDirection: 'row',
    marginBottom: 12,
    gap: 8,
  },
  assetsSectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
    gap: 12,
  },
  assetsSectionMeta: {
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.bodyMedium,
  },
  assetsRefreshChip: {
    minHeight: 32,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingHorizontal: 12,
    borderRadius: theme.radii.pill,
    borderWidth: 1,
    borderColor: alpha(theme.colors.primary, 0.3),
    backgroundColor: alpha(theme.colors.primary, 0.1),
  },
  assetsRefreshText: {
    color: theme.colors.primary,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  tokensTopRow: {
    marginTop: 4,
    marginBottom: 8,
  },
  walletHomeShell: {
    flex: 1,
    marginTop: 8,
  },
  walletSceneFrame: {
    flex: 1,
    minHeight: 0,
    paddingBottom: 100,
  },
  walletMainHeader: {
    marginBottom: 10,
  },
  walletMainHeaderTop: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  walletMainHeaderCopy: {
    flex: 1,
  },
  walletMainHeaderEyebrow: {
    color: theme.colors.primary,
    fontSize: 10,
    textTransform: 'uppercase',
    letterSpacing: 1,
    fontFamily: theme.typography.bodyBold,
  },
  walletMainHeaderTitle: {
    marginTop: 2,
    color: theme.colors.text,
    fontSize: 18,
    lineHeight: 22,
    fontFamily: theme.typography.bodyBold,
  },
  walletMainHeaderSubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
  walletHomeHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    marginBottom: 12,
  },
  walletHomeIdentity: {
    flex: 1,
  },
  walletHomeEyebrow: {
    color: theme.colors.primary,
    fontSize: 11,
    textTransform: 'uppercase',
    letterSpacing: 1,
    fontFamily: theme.typography.bodyBold,
  },
  walletHomeName: {
    marginTop: 2,
    color: theme.colors.text,
    fontSize: 22,
    lineHeight: 27,
    fontFamily: theme.typography.display,
  },
  walletHomeHeaderActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
  },
  walletHeaderNotice: {
    marginBottom: 12,
    flexDirection: 'row',
    alignItems: 'flex-start',
    borderRadius: theme.radii.lg,
    paddingHorizontal: 14,
    paddingVertical: 13,
    backgroundColor: alpha(theme.colors.primary, 0.09),
    borderWidth: 1,
    borderColor: alpha(theme.colors.primary, 0.18),
  },
  walletHeaderNoticeIcon: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.primary, 0.14),
    marginRight: 12,
  },
  walletHeaderNoticeCopy: {
    flex: 1,
  },
  walletHeaderNoticeTitle: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  walletHeaderNoticeText: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  marketStrip: {
    marginBottom: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  marketStripLabel: {
    color: theme.colors.text,
    fontSize: 18,
    fontFamily: theme.typography.bodyBold,
  },
  marketStripMeta: {
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  walletSceneContent: {
    paddingBottom: 126,
  },
  walletSceneFill: {
    flex: 1,
    minHeight: 0,
    gap: 10,
    paddingBottom: 10,
  },
  walletHomeNavDock: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
  },
  walletHomeNav: {
    paddingVertical: 8,
    marginBottom: 0,
  },
  walletHomeNavRow: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    justifyContent: 'space-between',
    gap: 6,
  },
  walletNavItem: {
    flex: 1,
    alignItems: 'center',
  },
  walletNavItemPrimary: {
    marginTop: -18,
  },
  walletNavItemActive: {
    opacity: 1,
  },
  walletNavIconShell: {
    width: 38,
    height: 38,
    borderRadius: 19,
    borderWidth: 1,
    borderColor: theme.colors.border,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.55),
  },
  walletNavIconShellPrimary: {
    width: 50,
    height: 50,
    borderRadius: 25,
    backgroundColor: theme.colors.primary,
    borderColor: alpha(theme.colors.primary, 0.22),
  },
  walletNavIconShellActive: {
    backgroundColor: alpha(theme.colors.primary, 0.18),
    borderColor: theme.colors.primary,
  },
  walletNavLabel: {
    marginTop: 6,
    color: theme.colors.textSoft,
    fontSize: 10,
    fontFamily: theme.typography.bodyBold,
  },
  walletNavLabelActive: {
    color: theme.colors.text,
  },
  walletNavLabelPrimary: {
    color: theme.colors.text,
  },
  browserQuickRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  browserQuickChip: {
    minHeight: 36,
    paddingHorizontal: 12,
    borderRadius: theme.radii.pill,
    borderWidth: 1,
    borderColor: theme.colors.border,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha('#111723', 0.88),
  },
  browserQuickChipText: {
    color: theme.colors.text,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  browserToolbarCard: {
    marginBottom: 10,
    backgroundColor: alpha('#070A11', 0.92),
  },
  browserSceneCard: {
    flex: 1,
    minHeight: 0,
    padding: 0,
    overflow: 'hidden',
  },
  browserWebView: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  browserLoader: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.background,
  },
  browserLoaderText: {
    marginTop: 10,
    color: theme.colors.textMuted,
    fontSize: 13,
    fontFamily: theme.typography.body,
  },
  browserDiscoveryGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 9,
    marginTop: 2,
    marginBottom: 4,
  },
  browserDiscoveryCard: {
    width: '48.5%',
    minHeight: 100,
    borderRadius: theme.radii.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: 11,
    backgroundColor: alpha('#070A11', 0.92),
  },
  browserDiscoveryIcon: {
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.primary, 0.12),
    marginBottom: 8,
  },
  browserDiscoveryTitle: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.bodyBold,
  },
  browserDiscoverySub: {
    marginTop: 6,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
  browserSafeArea: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  browserNativeScreen: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  browserScreen: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  browserHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 16,
    paddingTop: 10,
    paddingBottom: 10,
    borderBottomWidth: 1,
    borderBottomColor: alpha(theme.colors.border, 0.38),
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.3),
  },
  browserNavButton: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.58),
  },
  browserAddressShell: {
    flex: 1,
    minHeight: 44,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    borderRadius: theme.radii.pill,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.7),
    paddingHorizontal: 14,
  },
  browserControlsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  browserPageMeta: {
    flex: 1,
    marginRight: 12,
  },
  browserPageTitle: {
    color: theme.colors.text,
    fontSize: 14,
    fontFamily: theme.typography.bodyBold,
  },
  browserPageUrl: {
    marginTop: 2,
    color: theme.colors.textMuted,
    fontSize: 11,
    fontFamily: theme.typography.body,
  },
  browserInlineActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  browserMiniButton: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.5),
  },
  browserMiniButtonDisabled: {
    opacity: 0.4,
  },
  browserWebViewContainerFull: {
    flex: 1,
    minHeight: 0,
    backgroundColor: theme.colors.background,
  },
  browserWebViewFull: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  browserLoadingState: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    backgroundColor: theme.colors.background,
  },
  browserLoadingText: {
    color: theme.colors.textMuted,
    fontSize: 13,
    fontFamily: theme.typography.body,
  },
  browserLoadingOverlay: {
    position: 'absolute',
    top: 14,
    right: 16,
    width: 28,
    height: 28,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.72),
  },
  browserErrorButton: {
    minWidth: 168,
  },
  browserOrbPrimary: {
    position: 'absolute',
    top: -120,
    left: -40,
    width: 220,
    height: 220,
    borderRadius: 110,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.2),
  },
  browserOrbAccent: {
    position: 'absolute',
    top: -60,
    right: -34,
    width: 180,
    height: 180,
    borderRadius: 90,
    backgroundColor: alpha(theme.colors.primary, 0.12),
  },
  browserTopShell: {
    paddingHorizontal: 16,
    paddingTop: 10,
    paddingBottom: 12,
  },
  browserChromeBar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 16,
    paddingTop: 8,
    paddingBottom: 10,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.34),
    borderBottomWidth: 1,
    borderBottomColor: alpha(theme.colors.border, 0.4),
  },
  browserChromeButton: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.58),
  },
  browserAddressBar: {
    flex: 1,
    minHeight: 48,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    borderRadius: theme.radii.pill,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.72),
    paddingHorizontal: 14,
  },
  browserAddressInput: {
    flex: 1,
    color: theme.colors.text,
    fontSize: 14,
    fontFamily: theme.typography.bodyMedium,
    paddingVertical: 0,
  },
  browserControlRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 16,
    paddingBottom: 12,
  },
  browserControlButton: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.52),
  },
  browserControlButtonDisabled: {
    opacity: 0.35,
  },
  browserWebViewContainer: {
    flex: 1,
    minHeight: 0,
    overflow: 'hidden',
    backgroundColor: theme.colors.background,
  },
  browserFullWebView: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  browserLoaderOverlay: {
    ...StyleSheet.absoluteFillObject,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    backgroundColor: alpha(theme.colors.background, 0.84),
  },
  browserErrorState: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 28,
    backgroundColor: theme.colors.background,
  },
  browserErrorTitle: {
    marginTop: 12,
    color: theme.colors.text,
    fontSize: 18,
    fontFamily: theme.typography.heading,
  },
  browserErrorText: {
    marginTop: 8,
    marginBottom: 18,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 19,
    textAlign: 'center',
    fontFamily: theme.typography.body,
  },
  quickLinkRow: {
    minHeight: 66,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderBottomWidth: 1,
    borderBottomColor: alpha(theme.colors.border, 0.42),
  },
  quickLinkTitle: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  quickLinkCopy: {
    flex: 1,
    marginRight: 12,
  },
  quickLinkSub: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
  settingsProfileCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
  },
  settingsProfileOrb: {
    width: 50,
    height: 50,
    borderRadius: 25,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.primary, 0.14),
  },
  settingsProfileCopy: {
    flex: 1,
  },
  settingsProfileTitle: {
    color: theme.colors.text,
    fontSize: 17,
    fontFamily: theme.typography.heading,
  },
  settingsProfileSub: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  walletSponsorCard: {
    marginTop: 2,
  },
  walletSponsorHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    marginBottom: 12,
    gap: 12,
  },
  walletSponsorEyebrow: {
    color: theme.colors.primary,
    fontSize: 11,
    letterSpacing: 1,
    textTransform: 'uppercase',
    fontFamily: theme.typography.bodyBold,
  },
  walletSponsorTitle: {
    marginTop: 4,
    color: theme.colors.text,
    fontSize: 17,
    fontFamily: theme.typography.heading,
  },
  walletSponsorText: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  walletSponsorBadge: {
    minWidth: 40,
    minHeight: 28,
    paddingHorizontal: 10,
    borderRadius: theme.radii.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.primary, 0.14),
    borderWidth: 1,
    borderColor: alpha(theme.colors.primary, 0.22),
  },
  walletSponsorBadgeText: {
    color: theme.colors.primary,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  emptyText: {
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  detailBalance: {
    color: theme.colors.text,
    fontSize: 34,
    fontFamily: theme.typography.display,
  },
  detailUsd: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 16,
    fontFamily: theme.typography.bodyMedium,
  },
  detailMetaWrap: {
    marginTop: 16,
  },
  detailMetaLabel: {
    color: theme.colors.textSoft,
    fontSize: 12,
    letterSpacing: 0.8,
    textTransform: 'uppercase',
    fontFamily: theme.typography.bodyBold,
  },
  detailMetaValue: {
    marginTop: 4,
    color: theme.colors.text,
    fontSize: 14,
    lineHeight: 18,
    fontFamily: theme.typography.bodyMedium,
  },
  assetPickerWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  assetPickerChip: {
    minHeight: 38,
    paddingHorizontal: 14,
    borderRadius: theme.radii.pill,
    borderWidth: 1,
    borderColor: theme.colors.border,
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.48),
  },
  assetPickerChipActive: {
    borderColor: theme.colors.primary,
    backgroundColor: alpha(theme.colors.primary, 0.14),
  },
  assetPickerText: {
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  assetPickerTextActive: {
    color: theme.colors.text,
  },
  loaderInline: {
    minHeight: 54,
    alignItems: 'center',
    justifyContent: 'center',
  },
  tokenSuggestionRow: {
    minHeight: 64,
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: alpha(theme.colors.border, 0.42),
  },
  tokenSuggestionMeta: {
    flex: 1,
  },
  tokenSuggestionName: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  tokenSuggestionSub: {
    marginTop: 3,
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  switchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  switchCopy: {
    flex: 1,
    marginRight: 12,
  },
  switchTitle: {
    color: theme.colors.text,
    fontSize: 16,
    fontFamily: theme.typography.heading,
  },
  switchSub: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  networkRow: {
    minHeight: 72,
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: alpha(theme.colors.border, 0.42),
  },
  networkOrb: {
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  networkOrbText: {
    fontSize: 16,
    fontFamily: theme.typography.bodyBold,
  },
  networkMeta: {
    flex: 1,
  },
  networkName: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  networkSub: {
    marginTop: 3,
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  settingsRow: {
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: alpha(theme.colors.border, 0.4),
  },
  settingsLabel: {
    color: theme.colors.textSoft,
    fontSize: 12,
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    fontFamily: theme.typography.bodyBold,
  },
  settingsValue: {
    marginTop: 5,
    color: theme.colors.text,
    fontSize: 14,
    lineHeight: 18,
    fontFamily: theme.typography.bodyMedium,
  },
  settingsActionRow: {
    minHeight: 72,
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: alpha(theme.colors.border, 0.42),
  },
  settingsActionCopy: {
    flex: 1,
    marginRight: 12,
  },
  settingsActionTitle: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  settingsActionSub: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
  secretPhraseText: {
    marginTop: 16,
    color: theme.colors.text,
    fontSize: 14,
    lineHeight: 22,
    fontFamily: theme.typography.bodyBold,
  },
  historyRow: {
    minHeight: 72,
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: alpha(theme.colors.border, 0.42),
  },
  historyIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    marginRight: 12,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.primary, 0.12),
  },
  historyMeta: {
    flex: 1,
  },
  historyTitle: {
    color: theme.colors.text,
    fontSize: 14,
    fontFamily: theme.typography.heading,
  },
  historySub: {
    marginTop: 3,
    color: theme.colors.textMuted,
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
  historyDate: {
    color: theme.colors.textSoft,
    fontSize: 11,
    fontFamily: theme.typography.bodyMedium,
  },
});

export {
  WalletFlowScreen,
  WalletUnlockScreen,
  WalletOnboardingScreen,
  WalletBackupPhraseScreen,
  WalletConfirmPhraseScreen,
  WalletImportScreen,
  WalletPasscodeScreen,
  WalletNotificationsScreen,
  WalletReadyScreen,
  WalletHomeScreen,
  WalletSwapScreen,
  WalletPayScreen,
  WalletBrowserScreen,
  WalletBrowserWebScreen,
  WalletModeSettingsScreen,
  WalletTokenDetailsScreen,
  WalletSendScreen,
  WalletReceiveScreen,
  WalletAddTokenScreen,
  WalletNetworksScreen,
  WalletSettingsScreen,
  WalletSecretPhraseScreen,
  WalletHistoryScreen,
};
