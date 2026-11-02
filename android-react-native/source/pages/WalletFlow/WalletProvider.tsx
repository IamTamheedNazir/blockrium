import React, {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import {AppState, AppStateStatus} from 'react-native';
import {
  DEFAULT_WALLET_PREFERENCES,
  DEFAULT_WALLET_SECURITY_SETTINGS,
  MAINNET_NETWORK_KEYS,
  TESTNET_NETWORK_KEYS,
  TokenCatalogItem,
  StoredWeb3Wallet,
  WalletActivity,
  WalletAsset,
  WalletNetworkFilter,
  WalletNetworkKey,
  WalletPreferences,
  WalletSecuritySettings,
  WALLET_NETWORKS,
  enableWalletToken,
  enableWalletBiometricUnlock,
  generateMnemonic,
  getAllKnownTokens,
  getWalletBiometryType,
  getTokenMetadata,
  getWalletAssetsForNetwork,
  isWalletBiometricAvailable,
  loadCustomTokens,
  loadVisibleTokenIds,
  loadWallet,
  loadWalletActivity,
  loadWalletPreferences,
  loadWalletSecuritySettings,
  revealWalletMnemonic,
  saveCustomToken as importCustomTokenByAddress,
  saveWallet,
  saveWalletPasscode,
  saveWalletPreferences,
  saveWalletSecuritySettings,
  searchSuggestedTokens,
  sendWalletAsset,
  disableWalletBiometricUnlock,
  validateMnemonic,
  verifyWalletBiometricUnlock,
  verifyWalletPasscode,
} from '../../services/web3WalletService';
import {loadWalletNetworksIndependently} from '../../utils/walletNetworkRefresh';

type DraftWallet = {
  mnemonic: string;
  words: string[];
  confirmIndexes: number[];
};

type WalletContextValue = {
  isHydrating: boolean;
  isRefreshing: boolean;
  wallet?: StoredWeb3Wallet;
  preferences: WalletPreferences;
  securitySettings: WalletSecuritySettings;
  isLocked: boolean;
  assetsByNetwork: Partial<Record<WalletNetworkKey, WalletAsset[]>>;
  activity: WalletActivity[];
  visibleTokenIds: string[];
  customTokens: TokenCatalogItem[];
  createDraft?: DraftWallet;
  currentAssets: WalletAsset[];
  totalUsd: number;
  availableNetworks: WalletNetworkFilter[];
  hydrateWalletModule: () => Promise<void>;
  beginCreateWallet: () => Promise<DraftWallet>;
  stageImportWallet: (mnemonic: string) => Promise<void>;
  discardSetupDrafts: () => void;
  completeWalletSetup: (params: {
    passcode: string;
    notificationsEnabled?: boolean;
  }) => Promise<StoredWeb3Wallet>;
  revealSecretPhrase: (passcode: string) => Promise<string>;
  lockWallet: () => void;
  unlockWallet: (passcode: string) => Promise<boolean>;
  refreshSelectedNetwork: () => Promise<void>;
  selectNetwork: (networkKey: WalletNetworkFilter) => Promise<void>;
  toggleTestnets: (nextValue: boolean) => Promise<void>;
  getSuggestions: (
    query: string,
    networkKey?: WalletNetworkKey,
  ) => Promise<TokenCatalogItem[]>;
  addSuggestedToken: (token: TokenCatalogItem) => Promise<void>;
  addCustomToken: (
    address: string,
    networkKey?: WalletNetworkKey,
  ) => Promise<TokenCatalogItem>;
  biometricType: string | null;
  sendAsset: (params: {
    asset: WalletAsset;
    to: string;
    amount: string;
    passcode: string;
  }) => Promise<void>;
  reloadActivity: () => Promise<void>;
  setBiometricUnlockEnabled: (enabled: boolean) => Promise<boolean>;
  setAutoLockOnBackground: (enabled: boolean) => Promise<void>;
  unlockWalletWithBiometric: () => Promise<boolean>;
};

const WalletContext = createContext<WalletContextValue | undefined>(undefined);

const pickConfirmIndexes = () => {
  const pool = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];
  const picks: number[] = [];

  while (picks.length < 3) {
    const randomIndex = Math.floor(Math.random() * pool.length);
    picks.push(pool.splice(randomIndex, 1)[0]);
  }

  return picks.sort((left, right) => left - right);
};

const isNetworkKey = (value: WalletNetworkFilter): value is WalletNetworkKey =>
  value !== 'all';

const sortAssets = (assets: WalletAsset[]) =>
  [...assets].sort((left, right) => {
    const usdDelta = right.usdValue - left.usdValue;
    if (usdDelta !== 0) {
      return usdDelta;
    }

    return Number(right.balance) - Number(left.balance);
  });

const SAFE_BOOTSTRAP_NETWORK_KEYS: WalletNetworkKey[] = [
  'ethereum',
  'polygon',
  'bsc',
];

const withTimeout = async <T,>(
  promise: Promise<T>,
  timeoutMs: number,
  message: string,
) => {
  let timeoutId: ReturnType<typeof setTimeout> | undefined;

  try {
    return await Promise.race([
      promise,
      new Promise<T>((_, reject) => {
        timeoutId = setTimeout(() => reject(new Error(message)), timeoutMs);
      }),
    ]);
  } finally {
    if (timeoutId) {
      clearTimeout(timeoutId);
    }
  }
};

export const WalletProvider: React.FC<{children: React.ReactNode}> = ({
  children,
}) => {
  const [isHydrating, setIsHydrating] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [wallet, setWallet] = useState<StoredWeb3Wallet | undefined>();
  const [preferences, setPreferences] = useState<WalletPreferences>(
    DEFAULT_WALLET_PREFERENCES,
  );
  const [securitySettings, setSecuritySettings] =
    useState<WalletSecuritySettings>(DEFAULT_WALLET_SECURITY_SETTINGS);
  const [isLocked, setIsLocked] = useState(false);
  const [biometricType, setBiometricType] = useState<string | null>(null);
  const [assetsByNetwork, setAssetsByNetwork] = useState<
    Partial<Record<WalletNetworkKey, WalletAsset[]>>
  >({});
  const [activity, setActivity] = useState<WalletActivity[]>([]);
  const [visibleTokenIds, setVisibleTokenIds] = useState<string[]>([]);
  const [customTokens, setCustomTokens] = useState<TokenCatalogItem[]>([]);
  const [createDraft, setCreateDraft] = useState<DraftWallet | undefined>();
  const [importDraftMnemonic, setImportDraftMnemonic] = useState<
    string | undefined
  >();
  const appStateRef = useRef<AppStateStatus>(AppState.currentState);
  const walletSetupPromiseRef = useRef<Promise<StoredWeb3Wallet> | null>(null);

  const networkKeys = useMemo(
    () =>
      preferences.includeTestnets
        ? [...MAINNET_NETWORK_KEYS, ...TESTNET_NETWORK_KEYS]
        : MAINNET_NETWORK_KEYS,
    [preferences.includeTestnets],
  );

  const availableNetworks = useMemo<WalletNetworkFilter[]>(
    () => ['all', ...networkKeys],
    [networkKeys],
  );

  const currentAssets = useMemo(() => {
    if (isNetworkKey(preferences.selectedNetworkKey)) {
      return assetsByNetwork[preferences.selectedNetworkKey] ?? [];
    }

    return sortAssets(
      networkKeys.flatMap(networkKey => assetsByNetwork[networkKey] ?? []),
    );
  }, [assetsByNetwork, networkKeys, preferences.selectedNetworkKey]);

  const totalUsd = useMemo(
    () =>
      currentAssets.reduce(
        (portfolioTotal, asset) => portfolioTotal + (asset.usdValue ?? 0),
        0,
      ),
    [currentAssets],
  );

  const reloadActivity = async () => {
    const nextActivity = await loadWalletActivity();
    setActivity(nextActivity);
  };

  const refreshNetworkAssets = async (
    walletArg: StoredWeb3Wallet,
    networkKey: WalletNetworkKey,
  ) => {
    const assets = await getWalletAssetsForNetwork({
      address: walletArg.address,
      networkKey,
    });

    setAssetsByNetwork(current => ({
      ...current,
      [networkKey]: assets,
    }));
  };

  const refreshNetworks = async (
    walletArg: StoredWeb3Wallet,
    targets: WalletNetworkKey[],
  ) => {
    await loadWalletNetworksIndependently(
      targets,
      networkKey =>
        getWalletAssetsForNetwork({
          address: walletArg.address,
          networkKey,
        }),
      (networkKey, assets) =>
        setAssetsByNetwork(current => ({
          ...current,
          [networkKey]: assets,
        })),
      (networkKey, error) =>
        console.log(`Wallet refresh warning (${networkKey})`, error),
    );
  };

  const hydrateWalletModule = async () => {
    setIsHydrating(true);

    try {
      const [
        walletMeta,
        nextPreferences,
        nextActivity,
        nextVisibleTokenIds,
        nextCustomTokens,
        nextSecuritySettings,
        supportedBiometricType,
      ] = await Promise.all([
        loadWallet(),
        loadWalletPreferences(),
        loadWalletActivity(),
        loadVisibleTokenIds(),
        loadCustomTokens(),
        loadWalletSecuritySettings(),
        getWalletBiometryType(),
      ]);

      setWallet(walletMeta);
      setPreferences(nextPreferences);
      setSecuritySettings(nextSecuritySettings);
      setBiometricType(supportedBiometricType);
      setIsLocked(
        Boolean(walletMeta?.address && nextSecuritySettings.passcodeEnabled),
      );
      setActivity(nextActivity);
      setVisibleTokenIds(nextVisibleTokenIds);
      setCustomTokens(nextCustomTokens);
      setAssetsByNetwork({});
      setIsHydrating(false);

      if (walletMeta?.address) {
        const initialTargets: WalletNetworkKey[] = isNetworkKey(
          nextPreferences.selectedNetworkKey,
        )
          ? [nextPreferences.selectedNetworkKey]
          : nextPreferences.includeTestnets
          ? [...SAFE_BOOTSTRAP_NETWORK_KEYS, 'sepolia', 'amoy']
          : SAFE_BOOTSTRAP_NETWORK_KEYS;

        refreshNetworks(walletMeta, initialTargets).catch(error => {
          console.log('Wallet bootstrap sync warning', error);
        });
      }
    } catch (error) {
      console.log('Wallet hydrate error', error);
      setWallet(undefined);
      setPreferences(DEFAULT_WALLET_PREFERENCES);
      setSecuritySettings(DEFAULT_WALLET_SECURITY_SETTINGS);
      setBiometricType(null);
      setIsLocked(false);
      setActivity([]);
      setVisibleTokenIds([]);
      setCustomTokens([]);
      setAssetsByNetwork({});
      setCreateDraft(undefined);
      setImportDraftMnemonic(undefined);
    } finally {
      setIsHydrating(false);
    }
  };

  useEffect(() => {
    hydrateWalletModule();
    // Wallet hydration intentionally runs once when the provider mounts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const refreshSelectedNetwork = async () => {
    if (!wallet?.address) {
      return;
    }

    setIsRefreshing(true);

    try {
      if (isNetworkKey(preferences.selectedNetworkKey)) {
        await refreshNetworkAssets(wallet, preferences.selectedNetworkKey);
      } else {
        await refreshNetworks(
          wallet,
          preferences.includeTestnets
            ? [...SAFE_BOOTSTRAP_NETWORK_KEYS, 'sepolia', 'amoy']
            : SAFE_BOOTSTRAP_NETWORK_KEYS,
        );
      }
      await reloadActivity();
    } catch (error) {
      console.log('Wallet selected refresh warning', error);
    } finally {
      setIsRefreshing(false);
    }
  };

  const selectNetwork = async (networkKey: WalletNetworkFilter) => {
    const nextPreferences = {
      ...preferences,
      selectedNetworkKey: networkKey,
    };

    setPreferences(nextPreferences);
    await saveWalletPreferences(nextPreferences);

    if (!wallet?.address) {
      return;
    }

    if (isNetworkKey(networkKey)) {
      try {
        await refreshNetworkAssets(wallet, networkKey);
      } catch (error) {
        console.log(`Wallet network switch warning (${networkKey})`, error);
      }
      return;
    }

    await refreshNetworks(
      wallet,
      nextPreferences.includeTestnets
        ? [...SAFE_BOOTSTRAP_NETWORK_KEYS, 'sepolia', 'amoy']
        : SAFE_BOOTSTRAP_NETWORK_KEYS,
    );
  };

  const toggleTestnets = async (nextValue: boolean) => {
    const selectedNetworkKey =
      nextValue ||
      preferences.selectedNetworkKey === 'all' ||
      !WALLET_NETWORKS[preferences.selectedNetworkKey].isTestnet
        ? preferences.selectedNetworkKey
        : 'all';

    const nextPreferences: WalletPreferences = {
      selectedNetworkKey,
      includeTestnets: nextValue,
    };

    setPreferences(nextPreferences);
    await saveWalletPreferences(nextPreferences);

    if (!wallet?.address) {
      return;
    }

    if (isNetworkKey(nextPreferences.selectedNetworkKey)) {
      try {
        await refreshNetworkAssets(wallet, nextPreferences.selectedNetworkKey);
      } catch (error) {
        console.log(
          `Wallet testnet toggle warning (${nextPreferences.selectedNetworkKey})`,
          error,
        );
      }
      return;
    }

    await refreshNetworks(
      wallet,
      nextValue
        ? [...SAFE_BOOTSTRAP_NETWORK_KEYS, 'sepolia', 'amoy']
        : SAFE_BOOTSTRAP_NETWORK_KEYS,
    );
  };

  const beginCreateWallet = async () => {
    await new Promise(resolve => {
      setTimeout(resolve, 48);
    });

    const mnemonic = generateMnemonic();
    const draft = {
      mnemonic,
      words: mnemonic.split(' '),
      confirmIndexes: pickConfirmIndexes(),
    };

    setImportDraftMnemonic(undefined);
    setCreateDraft(draft);
    return draft;
  };

  const stageImportWallet = async (mnemonic: string) => {
    const normalizedMnemonic = mnemonic
      .trim()
      .toLowerCase()
      .replace(/\s+/g, ' ');

    if (!validateMnemonic(normalizedMnemonic)) {
      throw new Error('Invalid recovery phrase. Check the words and order.');
    }

    setCreateDraft(undefined);
    setImportDraftMnemonic(normalizedMnemonic);
  };

  const discardSetupDrafts = () => {
    setCreateDraft(undefined);
    setImportDraftMnemonic(undefined);
  };

  const completeWalletSetup = async ({
    passcode,
    notificationsEnabled = false,
  }: {
    passcode: string;
    notificationsEnabled?: boolean;
  }) => {
    if (walletSetupPromiseRef.current) {
      return walletSetupPromiseRef.current;
    }

    const draftMnemonic = createDraft?.mnemonic ?? importDraftMnemonic;
    const source = createDraft?.mnemonic ? 'created' : 'imported';

    if (!draftMnemonic) {
      throw new Error(
        'Wallet setup expired. Start the create or import flow again.',
      );
    }

    const setupPromise = (async () => {
      await withTimeout(
        saveWalletPasscode(passcode),
        30000,
        'Wallet passcode storage took too long. Keep the app open and try again.',
      );

      const nextWallet = await withTimeout(
        saveWallet({
          mnemonic: draftMnemonic,
          source,
          backupConfirmed: true,
        }),
        30000,
        'Wallet secure storage took too long. Keep the app open and try again.',
      );

      const nextSecuritySettings = await withTimeout(
        saveWalletSecuritySettings({
          ...securitySettings,
          notificationsEnabled,
          passcodeEnabled: true,
        }),
        15000,
        'Wallet settings could not be saved. Please try again.',
      );

      setWallet(nextWallet);
      setSecuritySettings(nextSecuritySettings);
      setIsLocked(false);
      setCreateDraft(undefined);
      setImportDraftMnemonic(undefined);

      (async () => {
        try {
          await reloadActivity();

          if (isNetworkKey(preferences.selectedNetworkKey)) {
            await refreshNetworkAssets(
              nextWallet,
              preferences.selectedNetworkKey,
            );
          } else {
            await refreshNetworks(nextWallet, SAFE_BOOTSTRAP_NETWORK_KEYS);
          }
        } catch (error) {
          console.log('Wallet setup background refresh warning', error);
        }
      })();

      return nextWallet;
    })();

    walletSetupPromiseRef.current = setupPromise;

    try {
      return await setupPromise;
    } finally {
      if (walletSetupPromiseRef.current === setupPromise) {
        walletSetupPromiseRef.current = null;
      }
    }
  };

  const getSuggestions = async (
    query: string,
    networkKey?: WalletNetworkKey,
  ) => {
    const targetNetwork =
      networkKey ??
      (isNetworkKey(preferences.selectedNetworkKey)
        ? preferences.selectedNetworkKey
        : 'ethereum');

    return searchSuggestedTokens(targetNetwork, query);
  };

  const addSuggestedToken = async (token: TokenCatalogItem) => {
    const knownTokens = await getAllKnownTokens();
    const alreadyKnown = knownTokens.find(
      item =>
        item.id === token.id ||
        (item.address?.toLowerCase() === token.address?.toLowerCase() &&
          item.networkKey === token.networkKey),
    );

    if (alreadyKnown) {
      await enableWalletToken(alreadyKnown.id);
    } else if (token.address) {
      await importCustomTokenByAddress(token);
    } else {
      await enableWalletToken(token.id);
    }

    const [nextCustomTokens, nextVisibleTokenIds] = await Promise.all([
      loadCustomTokens(),
      loadVisibleTokenIds(),
    ]);

    setCustomTokens(nextCustomTokens);
    setVisibleTokenIds(nextVisibleTokenIds);

    if (wallet?.address) {
      await refreshNetworkAssets(wallet, token.networkKey);
    }
  };

  const addCustomToken = async (
    address: string,
    networkKey?: WalletNetworkKey,
  ) => {
    const targetNetwork =
      networkKey ??
      (isNetworkKey(preferences.selectedNetworkKey)
        ? preferences.selectedNetworkKey
        : 'ethereum');
    const metadata = await getTokenMetadata(targetNetwork, address);
    const token = await importCustomTokenByAddress(metadata);
    const [nextCustomTokens, nextVisibleTokenIds] = await Promise.all([
      loadCustomTokens(),
      loadVisibleTokenIds(),
    ]);

    setCustomTokens(nextCustomTokens);
    setVisibleTokenIds(nextVisibleTokenIds);

    if (wallet?.address) {
      await refreshNetworkAssets(wallet, targetNetwork);
    }

    return token;
  };

  const sendAsset = async ({
    asset,
    to,
    amount,
    passcode,
  }: {
    asset: WalletAsset;
    to: string;
    amount: string;
    passcode: string;
  }) => {
    if (!wallet) {
      throw new Error('Create or import a wallet first.');
    }

    const authorized = await verifyWalletPasscode(passcode);

    if (!authorized) {
      throw new Error('Wrong wallet passcode. Transaction was not signed.');
    }

    await sendWalletAsset({
      wallet,
      asset,
      to,
      amount,
    });

    await Promise.all([
      reloadActivity(),
      refreshNetworkAssets(wallet, asset.networkKey),
    ]);
  };

  const revealSecretPhrase = async (passcode: string) =>
    revealWalletMnemonic(passcode);

  const lockWallet = () => {
    if (
      wallet?.address &&
      securitySettings.passcodeEnabled &&
      securitySettings.autoLockOnBackground
    ) {
      setIsLocked(true);
    }
  };

  const unlockWallet = async (passcode: string) => {
    const isValid = await verifyWalletPasscode(passcode);

    if (isValid) {
      setIsLocked(false);
    }

    return isValid;
  };

  const unlockWalletWithBiometric = async () => {
    const unlocked = await verifyWalletBiometricUnlock();

    if (unlocked) {
      setIsLocked(false);
    }

    return unlocked;
  };

  const setBiometricUnlockEnabled = async (enabled: boolean) => {
    if (enabled) {
      const available = await isWalletBiometricAvailable();

      if (!available) {
        throw new Error('Biometric unlock is not available on this device.');
      }

      const nextBiometryType = await enableWalletBiometricUnlock();
      const nextSettings = await saveWalletSecuritySettings({
        ...securitySettings,
        biometricEnabled: true,
      });

      setBiometricType(nextBiometryType);
      setSecuritySettings(nextSettings);
      return true;
    }

    await disableWalletBiometricUnlock();

    const nextSettings = await saveWalletSecuritySettings({
      ...securitySettings,
      biometricEnabled: false,
    });

    setSecuritySettings(nextSettings);
    return false;
  };

  const setAutoLockOnBackground = async (enabled: boolean) => {
    const nextSettings = await saveWalletSecuritySettings({
      ...securitySettings,
      autoLockOnBackground: enabled,
    });

    setSecuritySettings(nextSettings);
  };

  useEffect(() => {
    const subscription = AppState.addEventListener('change', nextState => {
      const wasActive = appStateRef.current === 'active';
      const isBackgrounding = nextState.match(/inactive|background/);

      if (wasActive && isBackgrounding) {
        if (
          wallet?.address &&
          securitySettings.passcodeEnabled &&
          securitySettings.autoLockOnBackground
        ) {
          setIsLocked(true);
        }
      }

      appStateRef.current = nextState;
    });

    return () => {
      subscription.remove();
    };
  }, [
    securitySettings.autoLockOnBackground,
    securitySettings.passcodeEnabled,
    wallet?.address,
  ]);

  const value: WalletContextValue = {
    isHydrating,
    isRefreshing,
    wallet,
    preferences,
    securitySettings,
    isLocked,
    assetsByNetwork,
    activity,
    visibleTokenIds,
    customTokens,
    createDraft,
    currentAssets,
    totalUsd,
    availableNetworks,
    hydrateWalletModule,
    beginCreateWallet,
    stageImportWallet,
    discardSetupDrafts,
    completeWalletSetup,
    revealSecretPhrase,
    lockWallet,
    unlockWallet,
    refreshSelectedNetwork,
    selectNetwork,
    toggleTestnets,
    getSuggestions,
    addSuggestedToken,
    addCustomToken,
    biometricType,
    sendAsset,
    reloadActivity,
    setBiometricUnlockEnabled,
    setAutoLockOnBackground,
    unlockWalletWithBiometric,
  };

  return (
    <WalletContext.Provider value={value}>{children}</WalletContext.Provider>
  );
};

export const useWalletModule = () => {
  const context = useContext(WalletContext);

  if (!context) {
    throw new Error('WalletProvider is missing in the wallet module tree.');
  }

  return context;
};
