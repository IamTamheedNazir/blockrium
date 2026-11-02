import 'react-native-get-random-values';
import '@ethersproject/shims';
import {ethers} from 'ethers';
import * as Keychain from 'react-native-keychain';
import CryptoJS from 'crypto-js';
import {getStorageToken, setStorageToken} from '../utils/mmkvStorage';

export type WalletNetworkKey =
  | 'ethereum'
  | 'polygon'
  | 'bsc'
  | 'arbitrum'
  | 'optimism'
  | 'base'
  | 'avalanche'
  | 'sepolia'
  | 'amoy';

export type WalletNetworkFilter = 'all' | WalletNetworkKey;

export type WalletMode = 'blockrium' | 'wallet';

export type StoredWeb3Wallet = {
  name: string;
  address: string;
  createdAt: string;
  source: 'created' | 'imported';
  backupConfirmedAt?: string;
};

export type WalletPreferences = {
  selectedNetworkKey: WalletNetworkFilter;
  includeTestnets: boolean;
};

export type WalletSecuritySettings = {
  notificationsEnabled: boolean;
  passcodeEnabled: boolean;
  biometricEnabled: boolean;
  autoLockOnBackground: boolean;
};

export type WalletNetwork = {
  key: WalletNetworkKey;
  chainId: number;
  name: string;
  shortName: string;
  symbol: string;
  nativeName: string;
  rpcUrl: string;
  explorerUrl: string;
  accent: string;
  isTestnet: boolean;
  coingeckoId?: string;
};

export type TokenCatalogItem = {
  id: string;
  type: 'native' | 'erc20';
  networkKey: WalletNetworkKey;
  symbol: string;
  name: string;
  decimals: number;
  address?: string;
  isPopular?: boolean;
  isCustom?: boolean;
  coingeckoId?: string;
  accent?: string;
  logoUrl?: string;
};

export type WalletAsset = TokenCatalogItem & {
  balance: string;
  rawBalance: string;
  usdValue: number;
};

export type WalletActivity = {
  id: string;
  type: 'wallet_created' | 'wallet_imported' | 'sent' | 'token_added';
  title: string;
  subtitle: string;
  createdAt: string;
  networkKey?: WalletNetworkKey;
  symbol?: string;
  amount?: string;
  hash?: string;
};

export type SendAssetParams = {
  wallet: StoredWeb3Wallet;
  asset: WalletAsset;
  to: string;
  amount: string;
};

const KEYCHAIN_SERVICE = 'blockrium.wallet.mnemonic.v2';
const KEYCHAIN_PASSCODE_SERVICE = 'blockrium.wallet.passcode.v1';
const KEYCHAIN_BIOMETRIC_SERVICE = 'blockrium.wallet.biometric.v1';
const WALLET_META_KEY = 'blockrium.wallet.meta.v2';
const WALLET_PREFS_KEY = 'blockrium.wallet.preferences.v2';
const WALLET_CUSTOM_TOKENS_KEY = 'blockrium.wallet.customTokens.v2';
const WALLET_ACTIVITY_KEY = 'blockrium.wallet.activity.v2';
const WALLET_VISIBLE_TOKENS_KEY = 'blockrium.wallet.visibleTokens.v2';
const WALLET_SECURITY_SETTINGS_KEY = 'blockrium.wallet.security.v1';
const RPC_TIMEOUT_MS = 12000;
const PRICE_CACHE_MS = 5 * 60 * 1000;
const LEGACY_PASSCODE_HASH_ITERATIONS = 30000;

type StoredPasscodeHash = {
  version: 2 | 3;
  salt: string;
  iterations?: number;
  hash: string;
};

const ERC20_ABI = [
  'function balanceOf(address owner) view returns (uint256)',
  'function decimals() view returns (uint8)',
  'function symbol() view returns (string)',
  'function name() view returns (string)',
  'function transfer(address to, uint256 value) returns (bool)',
];

const ERC20_BYTES32_ABI = [
  'function symbol() view returns (bytes32)',
  'function name() view returns (bytes32)',
];

const keychainDeviceOnlyOptions = {
  accessible: Keychain.ACCESSIBLE.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
  storage: Keychain.STORAGE_TYPE.AES_GCM_NO_AUTH,
};

const walletSecretOptions = {
  service: KEYCHAIN_SERVICE,
  ...keychainDeviceOnlyOptions,
};

const walletSecretReadOptions = {
  service: KEYCHAIN_SERVICE,
  authenticationPrompt: {
    title: 'Unlock Blockrium Wallet',
    subtitle: 'Use device security to access the wallet secret.',
    description: 'Authenticate before signing or revealing the recovery phrase.',
    cancel: 'Cancel',
  },
};

const hashLegacyPasscode = (
  passcode: string,
  salt: string,
  iterations = LEGACY_PASSCODE_HASH_ITERATIONS,
) =>
  CryptoJS.PBKDF2(passcode, salt, {
    keySize: 256 / 32,
    iterations,
    hasher: CryptoJS.algo.SHA256,
  }).toString(CryptoJS.enc.Hex);

const hashPasscode = (passcode: string, salt: string) =>
  CryptoJS.SHA256(`${salt}:${passcode}`).toString(CryptoJS.enc.Hex);

const yieldToEventLoop = () =>
  new Promise(resolve => {
    setTimeout(resolve, 0);
  });

const createStoredPasscodeHash = (passcode: string) => {
  const salt = ethers.hexlify(ethers.randomBytes(16));
  const payload: StoredPasscodeHash = {
    version: 3,
    salt,
    hash: hashPasscode(passcode, salt),
  };

  return JSON.stringify(payload);
};

const verifyStoredPasscodeHash = (storedValue: string, passcode: string) => {
  try {
    const payload = JSON.parse(storedValue) as Partial<StoredPasscodeHash>;

    if (
      payload.version === 3 &&
      typeof payload.salt === 'string' &&
      typeof payload.hash === 'string'
    ) {
      return hashPasscode(passcode, payload.salt) === payload.hash;
    }

    if (
      payload.version === 2 &&
      typeof payload.salt === 'string' &&
      typeof payload.hash === 'string'
    ) {
      const iterations =
        typeof payload.iterations === 'number'
          ? payload.iterations
          : LEGACY_PASSCODE_HASH_ITERATIONS;
      return hashLegacyPasscode(passcode, payload.salt, iterations) === payload.hash;
    }
  } catch {
    // Legacy builds stored the raw passcode in Keychain. Keep one-time migration safe.
  }

  return storedValue === passcode;
};

const isCurrentPasscodeHash = (storedValue: string) => {
  try {
    const payload = JSON.parse(storedValue) as Partial<StoredPasscodeHash>;
    return (
      payload.version === 3 &&
      typeof payload.salt === 'string' &&
      typeof payload.hash === 'string'
    );
  } catch {
    return false;
  }
};

export const WALLET_NETWORKS: Record<WalletNetworkKey, WalletNetwork> = {
  ethereum: {
    key: 'ethereum',
    chainId: 1,
    name: 'Ethereum Mainnet',
    shortName: 'Ethereum',
    symbol: 'ETH',
    nativeName: 'Ether',
    rpcUrl: 'https://ethereum-rpc.publicnode.com',
    explorerUrl: 'https://etherscan.io',
    accent: '#627EEA',
    isTestnet: false,
    coingeckoId: 'ethereum',
  },
  polygon: {
    key: 'polygon',
    chainId: 137,
    name: 'Polygon PoS',
    shortName: 'Polygon',
    symbol: 'POL',
    nativeName: 'Polygon',
    rpcUrl: 'https://polygon-bor-rpc.publicnode.com',
    explorerUrl: 'https://polygonscan.com',
    accent: '#8247E5',
    isTestnet: false,
    coingeckoId: 'matic-network',
  },
  bsc: {
    key: 'bsc',
    chainId: 56,
    name: 'BNB Smart Chain',
    shortName: 'BNB Chain',
    symbol: 'BNB',
    nativeName: 'BNB',
    rpcUrl: 'https://bsc-rpc.publicnode.com',
    explorerUrl: 'https://bscscan.com',
    accent: '#F0B90B',
    isTestnet: false,
    coingeckoId: 'binancecoin',
  },
  arbitrum: {
    key: 'arbitrum',
    chainId: 42161,
    name: 'Arbitrum One',
    shortName: 'Arbitrum',
    symbol: 'ETH',
    nativeName: 'Ether',
    rpcUrl: 'https://arbitrum-one-rpc.publicnode.com',
    explorerUrl: 'https://arbiscan.io',
    accent: '#28A0F0',
    isTestnet: false,
    coingeckoId: 'ethereum',
  },
  optimism: {
    key: 'optimism',
    chainId: 10,
    name: 'Optimism',
    shortName: 'Optimism',
    symbol: 'ETH',
    nativeName: 'Ether',
    rpcUrl: 'https://optimism-rpc.publicnode.com',
    explorerUrl: 'https://optimistic.etherscan.io',
    accent: '#FF0420',
    isTestnet: false,
    coingeckoId: 'ethereum',
  },
  base: {
    key: 'base',
    chainId: 8453,
    name: 'Base',
    shortName: 'Base',
    symbol: 'ETH',
    nativeName: 'Ether',
    rpcUrl: 'https://base-rpc.publicnode.com',
    explorerUrl: 'https://basescan.org',
    accent: '#0052FF',
    isTestnet: false,
    coingeckoId: 'ethereum',
  },
  avalanche: {
    key: 'avalanche',
    chainId: 43114,
    name: 'Avalanche C-Chain',
    shortName: 'Avalanche',
    symbol: 'AVAX',
    nativeName: 'Avalanche',
    rpcUrl: 'https://avalanche-c-chain-rpc.publicnode.com',
    explorerUrl: 'https://snowtrace.io',
    accent: '#E84142',
    isTestnet: false,
    coingeckoId: 'avalanche-2',
  },
  sepolia: {
    key: 'sepolia',
    chainId: 11155111,
    name: 'Ethereum Sepolia',
    shortName: 'Sepolia',
    symbol: 'ETH',
    nativeName: 'Sepolia Ether',
    rpcUrl: 'https://ethereum-sepolia-rpc.publicnode.com',
    explorerUrl: 'https://sepolia.etherscan.io',
    accent: '#6C6FF0',
    isTestnet: true,
    coingeckoId: 'ethereum',
  },
  amoy: {
    key: 'amoy',
    chainId: 80002,
    name: 'Polygon Amoy',
    shortName: 'Amoy',
    symbol: 'POL',
    nativeName: 'Amoy POL',
    rpcUrl: 'https://polygon-amoy-bor-rpc.publicnode.com',
    explorerUrl: 'https://amoy.polygonscan.com',
    accent: '#4E82FF',
    isTestnet: true,
    coingeckoId: 'matic-network',
  },
};

export const MAINNET_NETWORK_KEYS = (
  Object.keys(WALLET_NETWORKS) as WalletNetworkKey[]
).filter(key => !WALLET_NETWORKS[key].isTestnet);

export const TESTNET_NETWORK_KEYS = (
  Object.keys(WALLET_NETWORKS) as WalletNetworkKey[]
).filter(key => WALLET_NETWORKS[key].isTestnet);

export const DEFAULT_WALLET_PREFERENCES: WalletPreferences = {
  selectedNetworkKey: 'all',
  includeTestnets: false,
};

export const DEFAULT_WALLET_SECURITY_SETTINGS: WalletSecuritySettings = {
  notificationsEnabled: false,
  passcodeEnabled: false,
  biometricEnabled: false,
  autoLockOnBackground: true,
};

const TRUST_WALLET_ASSETS_BASE =
  'https://raw.githubusercontent.com/trustwallet/assets/master';

const TRUST_WALLET_NETWORK_PATHS: Partial<Record<WalletNetworkKey, string>> = {
  ethereum: 'ethereum',
  polygon: 'polygon',
  bsc: 'smartchain',
  arbitrum: 'arbitrum',
  optimism: 'optimism',
  base: 'base',
  avalanche: 'avalanchec',
  sepolia: 'ethereum',
  amoy: 'polygon',
};

const getNativeLogoUrl = (networkKey: WalletNetworkKey) => {
  const path = TRUST_WALLET_NETWORK_PATHS[networkKey];

  if (!path) {
    return undefined;
  }

  return `${TRUST_WALLET_ASSETS_BASE}/blockchains/${path}/info/logo.png`;
};

const getTokenLogoUrl = (networkKey: WalletNetworkKey, address?: string) => {
  const path = TRUST_WALLET_NETWORK_PATHS[networkKey];

  if (!path) {
    return undefined;
  }

  if (!address) {
    return getNativeLogoUrl(networkKey);
  }

  try {
    const checksumAddress = ethers.getAddress(address.trim());
    return `${TRUST_WALLET_ASSETS_BASE}/blockchains/${path}/assets/${checksumAddress}/logo.png`;
  } catch (error) {
    return undefined;
  }
};

const DEFAULT_TOKEN_CATALOG_ITEMS: TokenCatalogItem[] = [
  {
    id: 'ethereum-usdt',
    type: 'erc20',
    networkKey: 'ethereum',
    symbol: 'USDT',
    name: 'Tether USD',
    decimals: 6,
    address: '0xdAC17F958D2ee523a2206206994597C13D831ec7',
    isPopular: true,
    coingeckoId: 'tether',
    accent: '#26A17B',
  },
  {
    id: 'ethereum-usdc',
    type: 'erc20',
    networkKey: 'ethereum',
    symbol: 'USDC',
    name: 'USD Coin',
    decimals: 6,
    address: '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
    isPopular: true,
    coingeckoId: 'usd-coin',
    accent: '#2775CA',
  },
  {
    id: 'ethereum-dai',
    type: 'erc20',
    networkKey: 'ethereum',
    symbol: 'DAI',
    name: 'Dai',
    decimals: 18,
    address: '0x6B175474E89094C44Da98b954EedeAC495271d0F',
    isPopular: true,
    coingeckoId: 'dai',
    accent: '#F4B731',
  },
  {
    id: 'ethereum-wbtc',
    type: 'erc20',
    networkKey: 'ethereum',
    symbol: 'WBTC',
    name: 'Wrapped Bitcoin',
    decimals: 8,
    address: '0x2260FAC5E5542a773Aa44fBCfeDf7C193bc2C599',
    isPopular: true,
    coingeckoId: 'wrapped-bitcoin',
    accent: '#F7931A',
  },
  {
    id: 'ethereum-link',
    type: 'erc20',
    networkKey: 'ethereum',
    symbol: 'LINK',
    name: 'Chainlink',
    decimals: 18,
    address: '0x514910771AF9Ca656af840dff83E8264EcF986CA',
    isPopular: true,
    coingeckoId: 'chainlink',
    accent: '#2A5ADA',
  },
  {
    id: 'polygon-usdt',
    type: 'erc20',
    networkKey: 'polygon',
    symbol: 'USDT',
    name: 'Tether USD',
    decimals: 6,
    address: '0xc2132D05D31c914a87C6611C10748AaCbC5320BB',
    isPopular: true,
    coingeckoId: 'tether',
    accent: '#26A17B',
  },
  {
    id: 'polygon-usdc',
    type: 'erc20',
    networkKey: 'polygon',
    symbol: 'USDC',
    name: 'USD Coin',
    decimals: 6,
    address: '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359',
    isPopular: true,
    coingeckoId: 'usd-coin',
    accent: '#2775CA',
  },
  {
    id: 'polygon-dai',
    type: 'erc20',
    networkKey: 'polygon',
    symbol: 'DAI',
    name: 'Dai',
    decimals: 18,
    address: '0x8f3Cf7ad23Cd3CaDbD9735AFf958023239c6A063',
    isPopular: true,
    coingeckoId: 'dai',
    accent: '#F4B731',
  },
  {
    id: 'polygon-weth',
    type: 'erc20',
    networkKey: 'polygon',
    symbol: 'WETH',
    name: 'Wrapped Ether',
    decimals: 18,
    address: '0x7ceB23fD6bC0adD59E62ac25578270cFf1b9f619',
    isPopular: true,
    coingeckoId: 'weth',
    accent: '#627EEA',
  },
  {
    id: 'bsc-usdt',
    type: 'erc20',
    networkKey: 'bsc',
    symbol: 'USDT',
    name: 'Tether USD',
    decimals: 18,
    address: '0x55d398326f99059fF775485246999027B3197955',
    isPopular: true,
    coingeckoId: 'tether',
    accent: '#26A17B',
  },
  {
    id: 'bsc-usdc',
    type: 'erc20',
    networkKey: 'bsc',
    symbol: 'USDC',
    name: 'USD Coin',
    decimals: 18,
    address: '0x8ac76a51cc950d9822d68b83fe1ad97b32cd580d',
    isPopular: true,
    coingeckoId: 'usd-coin',
    accent: '#2775CA',
  },
  {
    id: 'bsc-eth',
    type: 'erc20',
    networkKey: 'bsc',
    symbol: 'ETH',
    name: 'Ethereum Token',
    decimals: 18,
    address: '0x2170Ed0880ac9A755fd29B2688956BD959F933F8',
    isPopular: true,
    coingeckoId: 'ethereum',
    accent: '#627EEA',
  },
  {
    id: 'bsc-btcb',
    type: 'erc20',
    networkKey: 'bsc',
    symbol: 'BTCB',
    name: 'Bitcoin BEP2',
    decimals: 18,
    address: '0x7130D2A12B9BCbFAe4f2634d864A1Ee1Ce3Ead9c',
    isPopular: true,
    coingeckoId: 'bitcoin',
    accent: '#F7931A',
  },
  {
    id: 'arbitrum-usdc',
    type: 'erc20',
    networkKey: 'arbitrum',
    symbol: 'USDC',
    name: 'USD Coin',
    decimals: 6,
    address: '0xaf88d065e77c8cC2239327C5EDb3A432268e5831',
    isPopular: true,
    coingeckoId: 'usd-coin',
    accent: '#2775CA',
  },
  {
    id: 'arbitrum-usdt',
    type: 'erc20',
    networkKey: 'arbitrum',
    symbol: 'USDT',
    name: 'Tether USD',
    decimals: 6,
    address: '0xfd086bc7cd5c481dcc9c85ebe478a1c0b69fcbb9',
    isPopular: true,
    coingeckoId: 'tether',
    accent: '#26A17B',
  },
  {
    id: 'arbitrum-arb',
    type: 'erc20',
    networkKey: 'arbitrum',
    symbol: 'ARB',
    name: 'Arbitrum',
    decimals: 18,
    address: '0x912CE59144191C1204E64559FE8253a0e49E6548',
    isPopular: true,
    coingeckoId: 'arbitrum',
    accent: '#28A0F0',
  },
  {
    id: 'optimism-usdc',
    type: 'erc20',
    networkKey: 'optimism',
    symbol: 'USDC',
    name: 'USD Coin',
    decimals: 6,
    address: '0x0b2C639c533813f4Aa9D7837CAF62653d097Ff85',
    isPopular: true,
    coingeckoId: 'usd-coin',
    accent: '#2775CA',
  },
  {
    id: 'optimism-usdt',
    type: 'erc20',
    networkKey: 'optimism',
    symbol: 'USDT',
    name: 'Tether USD',
    decimals: 6,
    address: '0x94b008aA00579c1307B0EF2c499ad98a8ce58e58',
    isPopular: true,
    coingeckoId: 'tether',
    accent: '#26A17B',
  },
  {
    id: 'optimism-op',
    type: 'erc20',
    networkKey: 'optimism',
    symbol: 'OP',
    name: 'Optimism',
    decimals: 18,
    address: '0x4200000000000000000000000000000000000042',
    isPopular: true,
    coingeckoId: 'optimism',
    accent: '#FF0420',
  },
  {
    id: 'base-usdc',
    type: 'erc20',
    networkKey: 'base',
    symbol: 'USDC',
    name: 'USD Coin',
    decimals: 6,
    address: '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
    isPopular: true,
    coingeckoId: 'usd-coin',
    accent: '#2775CA',
  },
  {
    id: 'base-dai',
    type: 'erc20',
    networkKey: 'base',
    symbol: 'DAI',
    name: 'Dai',
    decimals: 18,
    address: '0x50c5725949A6F0c72E6C4a641F24049A917DB0Cb',
    isPopular: true,
    coingeckoId: 'dai',
    accent: '#F4B731',
  },
  {
    id: 'base-weth',
    type: 'erc20',
    networkKey: 'base',
    symbol: 'WETH',
    name: 'Wrapped Ether',
    decimals: 18,
    address: '0x4200000000000000000000000000000000000006',
    isPopular: true,
    coingeckoId: 'weth',
    accent: '#627EEA',
  },
  {
    id: 'avalanche-usdc',
    type: 'erc20',
    networkKey: 'avalanche',
    symbol: 'USDC',
    name: 'USD Coin',
    decimals: 6,
    address: '0xB97EF9Ef8734C71904D8002F8b6Bc66Dd9c48a6E',
    isPopular: true,
    coingeckoId: 'usd-coin',
    accent: '#2775CA',
  },
  {
    id: 'avalanche-usdt',
    type: 'erc20',
    networkKey: 'avalanche',
    symbol: 'USDT',
    name: 'Tether USD',
    decimals: 6,
    address: '0x9702230A8Ea53601f5cD2dc00fDBc13d4dF4A8c7',
    isPopular: true,
    coingeckoId: 'tether',
    accent: '#26A17B',
  },
  {
    id: 'avalanche-dai',
    type: 'erc20',
    networkKey: 'avalanche',
    symbol: 'DAI',
    name: 'Dai',
    decimals: 18,
    address: '0xd586E7F844cEa2F87f50152665BCbc2C279D8d70',
    isPopular: true,
    coingeckoId: 'dai',
    accent: '#F4B731',
  },
];

export const DEFAULT_TOKEN_CATALOG: TokenCatalogItem[] = DEFAULT_TOKEN_CATALOG_ITEMS.map(token => ({
  ...token,
  logoUrl: getTokenLogoUrl(token.networkKey, token.address),
}));

const DEFAULT_VISIBLE_TOKEN_IDS = DEFAULT_TOKEN_CATALOG.filter(token => token.isPopular)
  .map(token => token.id);

const priceCache = new Map<string, {usd: number; expiresAt: number}>();
const remoteTokenCache = new Map<WalletNetworkKey, TokenCatalogItem[]>();

const normalizeMnemonic = (mnemonic: string) =>
  mnemonic
    .trim()
    .toLowerCase()
    .split(/\s+/)
    .filter(Boolean)
    .join(' ');

const normalizeAddress = (address: string) => ethers.getAddress(address.trim());

const getProvider = (networkKey: WalletNetworkKey) =>
  new ethers.JsonRpcProvider(
    WALLET_NETWORKS[networkKey].rpcUrl,
    WALLET_NETWORKS[networkKey].chainId,
  );

const withTimeout = async <T,>(promise: Promise<T>, message: string) => {
  let timer: ReturnType<typeof setTimeout> | undefined;

  const timeout = new Promise<T>((_, reject) => {
    timer = setTimeout(() => reject(new Error(message)), RPC_TIMEOUT_MS);
  });

  try {
    return await Promise.race([promise, timeout]);
  } finally {
    if (timer) {
      clearTimeout(timer);
    }
  }
};

const safeParse = <T,>(value: string | undefined, fallback: T) => {
  if (!value) {
    return fallback;
  }

  try {
    return JSON.parse(value) as T;
  } catch (error) {
    console.log('Unable to parse wallet storage payload', error);
    return fallback;
  }
};

const decodeBytes32String = (value: string) => {
  try {
    return ethers.decodeBytes32String(value).trim();
  } catch (error) {
    return '';
  }
};

const withWalletErrorMessage = (error: any, fallback: string) => {
  const message = String(error?.message ?? '').trim();

  if (!message) {
    return fallback;
  }

  if (
    message.includes('CALL_EXCEPTION') ||
    message.includes('missing revert data') ||
    message.includes('execution reverted')
  ) {
    return fallback;
  }

  if (
    message.includes('timed out') ||
    message.includes('timeout') ||
    message.includes('network changed')
  ) {
    return 'The selected network RPC is taking too long. Try again in a moment.';
  }

  return message;
};

const fetchPriceMap = async (ids: string[]) => {
  const uniqueIds = [...new Set(ids.filter(Boolean))];
  const now = Date.now();
  const result: Record<string, number> = {};
  const missingIds: string[] = [];

  uniqueIds.forEach(id => {
    const cached = priceCache.get(id);
    if (cached && cached.expiresAt > now) {
      result[id] = cached.usd;
    } else {
      missingIds.push(id);
    }
  });

  if (!missingIds.length) {
    return result;
  }

  const priceRequest = fetch(
    `https://api.coingecko.com/api/v3/simple/price?ids=${encodeURIComponent(
      missingIds.join(','),
    )}&vs_currencies=usd`,
  ).then(response => response.json());

  try {
    const data = await withTimeout(priceRequest, 'Price service timed out.');
    missingIds.forEach(id => {
      const usd = Number(data?.[id]?.usd ?? 0);
      priceCache.set(id, {usd, expiresAt: now + PRICE_CACHE_MS});
      result[id] = usd;
    });
  } catch (error) {
    console.log('Unable to fetch wallet prices', error);
    missingIds.forEach(id => {
      result[id] = 0;
    });
  }

  return result;
};

const createNativeToken = (networkKey: WalletNetworkKey): TokenCatalogItem => {
  const network = WALLET_NETWORKS[networkKey];

  return {
    id: `${networkKey}-native`,
    type: 'native',
    networkKey,
    symbol: network.symbol,
    name: network.nativeName,
    decimals: 18,
    isPopular: true,
    coingeckoId: network.coingeckoId,
    accent: network.accent,
    logoUrl: getNativeLogoUrl(networkKey),
  };
};

export const validateMnemonic = (mnemonic: string) =>
  ethers.Mnemonic.isValidMnemonic(normalizeMnemonic(mnemonic));

export const generateMnemonic = () => {
  const phrase = ethers.Wallet.createRandom().mnemonic?.phrase;

  if (!phrase) {
    throw new Error('Unable to generate a recovery phrase.');
  }

  return phrase;
};

export const deriveAddress = (mnemonic: string) =>
  ethers.getAddress(ethers.Wallet.fromPhrase(normalizeMnemonic(mnemonic)).address);

export const saveWalletPreferences = async (preferences: WalletPreferences) => {
  await setStorageToken(WALLET_PREFS_KEY, JSON.stringify(preferences));
  return preferences;
};

export const loadWalletPreferences = async () => {
  const stored = await getStorageToken(WALLET_PREFS_KEY);
  const nextPreferences = {
    ...DEFAULT_WALLET_PREFERENCES,
    ...safeParse<Partial<WalletPreferences>>(stored, {}),
  };

  if (
    nextPreferences.selectedNetworkKey !== 'all' &&
    !WALLET_NETWORKS[nextPreferences.selectedNetworkKey]
  ) {
    nextPreferences.selectedNetworkKey = 'all';
  }

  return nextPreferences;
};

export const loadWalletSecuritySettings = async () => {
  const stored = await getStorageToken(WALLET_SECURITY_SETTINGS_KEY);
  return {
    ...DEFAULT_WALLET_SECURITY_SETTINGS,
    ...safeParse<Partial<WalletSecuritySettings>>(stored, {}),
  };
};

export const saveWalletSecuritySettings = async (
  settings: WalletSecuritySettings,
) => {
  await setStorageToken(WALLET_SECURITY_SETTINGS_KEY, JSON.stringify(settings));
  return settings;
};

export const loadWallet = async () => {
  const storedMeta = await getStorageToken(WALLET_META_KEY);
  return safeParse<StoredWeb3Wallet | undefined>(storedMeta, undefined);
};

export const hasStoredWallet = async () => {
  const wallet = await loadWallet();
  return Boolean(wallet?.address);
};

export const saveWallet = async ({
  mnemonic,
  name,
  source,
  backupConfirmed,
}: {
  mnemonic: string;
  name?: string;
  source: 'created' | 'imported';
  backupConfirmed?: boolean;
}) => {
  const normalizedMnemonic = normalizeMnemonic(mnemonic);

  if (!validateMnemonic(normalizedMnemonic)) {
    throw new Error('Invalid recovery phrase. Check the words and order.');
  }

  const address = deriveAddress(normalizedMnemonic);

  const storedSecret = await Keychain.setGenericPassword(
    'blockrium-wallet',
    normalizedMnemonic,
    walletSecretOptions,
  );

  if (!storedSecret) {
    throw new Error('Secure wallet storage is unavailable on this device.');
  }

  const nextWallet: StoredWeb3Wallet = {
    name: name?.trim() || 'Blockrium Wallet',
    address,
    createdAt: new Date().toISOString(),
    source,
    backupConfirmedAt: backupConfirmed ? new Date().toISOString() : undefined,
  };

  await Promise.all([
    setStorageToken(WALLET_META_KEY, JSON.stringify(nextWallet)),
    setStorageToken(
      WALLET_VISIBLE_TOKENS_KEY,
      JSON.stringify(DEFAULT_VISIBLE_TOKEN_IDS),
    ),
  ]);

  await appendWalletActivity({
    id: `${source}-${Date.now()}`,
    type: source === 'created' ? 'wallet_created' : 'wallet_imported',
    title: source === 'created' ? 'Wallet created' : 'Wallet imported',
    subtitle:
      source === 'created'
        ? 'Recovery phrase saved to secure storage.'
        : 'Imported phrase restored this wallet.',
    createdAt: new Date().toISOString(),
  });

  return nextWallet;
};

export const getWalletMnemonic = async () => {
  const credentials = await Keychain.getGenericPassword({
    ...walletSecretReadOptions,
  });

  if (!credentials || !credentials.password) {
    throw new Error('Wallet secret is missing. Re-import the wallet phrase.');
  }

  return credentials.password;
};

export const saveWalletPasscode = async (passcode: string) => {
  const normalizedPasscode = passcode.trim();

  if (!/^\d{4,6}$/.test(normalizedPasscode)) {
    throw new Error('Use a 4 to 6 digit wallet passcode.');
  }

  await yieldToEventLoop();
  const passcodeHash = createStoredPasscodeHash(normalizedPasscode);

  const storedPasscode = await Keychain.setGenericPassword(
    'blockrium-wallet-passcode',
    passcodeHash,
    {
      service: KEYCHAIN_PASSCODE_SERVICE,
      ...keychainDeviceOnlyOptions,
    },
  );

  if (!storedPasscode) {
    throw new Error('Wallet passcode could not be secured on this device.');
  }

  return true;
};

export const getWalletBiometryType = async () => {
  try {
    return await Keychain.getSupportedBiometryType();
  } catch (error) {
    console.log('Unable to detect supported wallet biometry type', error);
    return null;
  }
};

export const isWalletBiometricAvailable = async () => {
  const biometryType = await getWalletBiometryType();
  return Boolean(biometryType);
};

export const enableWalletBiometricUnlock = async () => {
  const biometryType = await getWalletBiometryType();

  if (!biometryType) {
    throw new Error('Biometric unlock is not available on this device.');
  }

  const storedPasscode = await Keychain.getGenericPassword({
    service: KEYCHAIN_PASSCODE_SERVICE,
  });

  if (!storedPasscode || !storedPasscode.password) {
    throw new Error('Set a wallet passcode before enabling biometric unlock.');
  }

  await Keychain.setGenericPassword('blockrium-wallet-biometric', 'enabled', {
    service: KEYCHAIN_BIOMETRIC_SERVICE,
    accessControl: Keychain.ACCESS_CONTROL.BIOMETRY_ANY_OR_DEVICE_PASSCODE,
    accessible: Keychain.ACCESSIBLE.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
    authenticationPrompt: {
      title: 'Enable biometric unlock',
      subtitle: 'Use your device security for Blockrium Wallet',
      description: 'Authenticate once to secure wallet unlock on this device.',
      cancel: 'Cancel',
    },
  });

  return biometryType;
};

export const disableWalletBiometricUnlock = async () => {
  await Keychain.resetGenericPassword({service: KEYCHAIN_BIOMETRIC_SERVICE});
  return true;
};

export const verifyWalletBiometricUnlock = async () => {
  const credentials = await Keychain.getGenericPassword({
    service: KEYCHAIN_BIOMETRIC_SERVICE,
    authenticationPrompt: {
      title: 'Unlock Blockrium Wallet',
      subtitle: 'Use biometric or device passcode',
      description: 'Authenticate to re-open your wallet on this device.',
      cancel: 'Cancel',
    },
  });

  return Boolean(credentials && credentials.password);
};

export const verifyWalletPasscode = async (passcode: string) => {
  const credentials = await Keychain.getGenericPassword({
    service: KEYCHAIN_PASSCODE_SERVICE,
  });

  if (!credentials || !credentials.password) {
    throw new Error('Wallet passcode is not set yet.');
  }

  const normalizedPasscode = passcode.trim();
  const isValid = verifyStoredPasscodeHash(credentials.password, normalizedPasscode);

  if (isValid && !isCurrentPasscodeHash(credentials.password)) {
    await Keychain.setGenericPassword(
      'blockrium-wallet-passcode',
      createStoredPasscodeHash(normalizedPasscode),
      {
        service: KEYCHAIN_PASSCODE_SERVICE,
        ...keychainDeviceOnlyOptions,
      },
    );
  }

  return isValid;
};

export const revealWalletMnemonic = async (passcode: string) => {
  const isValid = await verifyWalletPasscode(passcode);

  if (!isValid) {
    throw new Error('Wrong wallet passcode. Try again.');
  }

  return getWalletMnemonic();
};

export const clearWallet = async () => {
  await Promise.all([
    Keychain.resetGenericPassword({service: KEYCHAIN_SERVICE}),
    Keychain.resetGenericPassword({service: KEYCHAIN_PASSCODE_SERVICE}),
    Keychain.resetGenericPassword({service: KEYCHAIN_BIOMETRIC_SERVICE}),
    setStorageToken(WALLET_META_KEY, ''),
    setStorageToken(WALLET_CUSTOM_TOKENS_KEY, '[]'),
    setStorageToken(WALLET_ACTIVITY_KEY, '[]'),
    setStorageToken(WALLET_VISIBLE_TOKENS_KEY, '[]'),
    setStorageToken(WALLET_PREFS_KEY, JSON.stringify(DEFAULT_WALLET_PREFERENCES)),
    setStorageToken(
      WALLET_SECURITY_SETTINGS_KEY,
      JSON.stringify(DEFAULT_WALLET_SECURITY_SETTINGS),
    ),
  ]);
};

export const loadCustomTokens = async () => {
  const stored = await getStorageToken(WALLET_CUSTOM_TOKENS_KEY);
  return safeParse<TokenCatalogItem[]>(stored, []);
};

export const loadVisibleTokenIds = async () => {
  const stored = await getStorageToken(WALLET_VISIBLE_TOKENS_KEY);
  const ids = safeParse<string[]>(stored, DEFAULT_VISIBLE_TOKEN_IDS);
  return ids.length ? ids : DEFAULT_VISIBLE_TOKEN_IDS;
};

export const saveVisibleTokenIds = async (ids: string[]) => {
  const uniqueIds = [...new Set(ids)];
  await setStorageToken(WALLET_VISIBLE_TOKENS_KEY, JSON.stringify(uniqueIds));
  return uniqueIds;
};

export const enableWalletToken = async (tokenId: string) => {
  const currentIds = await loadVisibleTokenIds();
  if (currentIds.includes(tokenId)) {
    return currentIds;
  }

  const nextIds = [...currentIds, tokenId];
  await saveVisibleTokenIds(nextIds);
  return nextIds;
};

export const saveCustomToken = async (token: TokenCatalogItem) => {
  const customTokens = await loadCustomTokens();
  const normalizedAddress = token.address ? normalizeAddress(token.address) : undefined;
  const normalizedToken: TokenCatalogItem = {
    ...token,
    id: normalizedAddress
      ? `${token.networkKey}-${normalizedAddress.toLowerCase()}`
      : token.id,
    address: normalizedAddress,
    isCustom: true,
  };

  const nextTokens = [
    ...customTokens.filter(
      item =>
        !(
          item.networkKey === normalizedToken.networkKey &&
          item.address?.toLowerCase() === normalizedToken.address?.toLowerCase()
        ),
    ),
    normalizedToken,
  ];

  await Promise.all([
    setStorageToken(WALLET_CUSTOM_TOKENS_KEY, JSON.stringify(nextTokens)),
    enableWalletToken(normalizedToken.id),
    appendWalletActivity({
      id: `token-${normalizedToken.id}-${Date.now()}`,
      type: 'token_added',
      title: `${normalizedToken.symbol} added`,
      subtitle: `${WALLET_NETWORKS[normalizedToken.networkKey].shortName} token imported into the wallet.`,
      createdAt: new Date().toISOString(),
      networkKey: normalizedToken.networkKey,
      symbol: normalizedToken.symbol,
    }),
  ]);

  return normalizedToken;
};

export const getAllKnownTokens = async () => [
  ...DEFAULT_TOKEN_CATALOG,
  ...(await loadCustomTokens()),
];

const fetchRemoteTokens = async (networkKey: WalletNetworkKey) => {
  if (WALLET_NETWORKS[networkKey].isTestnet) {
    return [] as TokenCatalogItem[];
  }

  const cached = remoteTokenCache.get(networkKey);
  if (cached?.length) {
    return cached;
  }

  try {
    const request = fetch(
      `https://tokens.1inch.io/v1.2/${WALLET_NETWORKS[networkKey].chainId}`,
    ).then(response => response.json());
    const payload = await withTimeout(
      request,
      'Token list lookup timed out for this network.',
    );
    const tokenMap = payload?.tokens ?? payload ?? {};
    const tokens = Object.values(tokenMap)
      .map((item: any) => {
        try {
          const address = normalizeAddress(String(item?.address ?? ''));

          return {
            id: `${networkKey}-${address.toLowerCase()}`,
            type: 'erc20' as const,
            networkKey,
            symbol: String(item?.symbol ?? '').slice(0, 12),
            name: String(item?.name ?? '').slice(0, 40),
            decimals: Number(item?.decimals ?? 18),
            address,
            isPopular: true,
            accent: WALLET_NETWORKS[networkKey].accent,
            logoUrl: getTokenLogoUrl(networkKey, address),
          };
        } catch (error) {
          return undefined;
        }
      })
      .filter(Boolean)
      .filter(
        (item: any) =>
          item?.symbol &&
          item?.name &&
          Number.isFinite(item?.decimals) &&
          item.decimals >= 0 &&
          item.decimals <= 36,
      )
      .slice(0, 260) as TokenCatalogItem[];

    remoteTokenCache.set(networkKey, tokens);
    return tokens;
  } catch (error) {
    console.log('Remote wallet token list warning', error);
    return [] as TokenCatalogItem[];
  }
};

export const getTokensForNetwork = async (networkKey: WalletNetworkKey) => {
  const tokens = await getAllKnownTokens();
  return [
    createNativeToken(networkKey),
    ...tokens.filter(token => token.networkKey === networkKey),
  ];
};

export const searchSuggestedTokens = async (
  networkKey: WalletNetworkKey,
  query: string,
) => {
  const [tokens, remoteTokens] = await Promise.all([
    getTokensForNetwork(networkKey),
    fetchRemoteTokens(networkKey),
  ]);
  const merged = [
    ...tokens,
    ...remoteTokens.filter(
      remoteToken =>
        !tokens.some(
          token =>
            token.id === remoteToken.id ||
            token.address?.toLowerCase() === remoteToken.address?.toLowerCase(),
        ),
    ),
  ];
  const normalizedQuery = query.trim().toLowerCase();

  if (!normalizedQuery) {
    return merged
      .filter(token => token.type === 'erc20')
      .slice(0, 240);
  }

  return merged.filter(token => {
    const haystack = `${token.symbol} ${token.name} ${token.address ?? ''}`.toLowerCase();
    return haystack.includes(normalizedQuery);
  });
};

export const getTokenMetadata = async (
  networkKey: WalletNetworkKey,
  address: string,
) => {
  if (!ethers.isAddress(address.trim())) {
    throw new Error('Enter a valid token contract address.');
  }

  const checksumAddress = normalizeAddress(address);
  const provider = getProvider(networkKey);
  const code = await withTimeout(
    provider.getCode(checksumAddress),
    'Contract lookup timed out on this network.',
  );

  if (!code || code === '0x') {
    throw new Error('No ERC-20 contract was found on the selected network.');
  }

  const contract = new ethers.Contract(checksumAddress, ERC20_ABI, provider);
  const bytes32Contract = new ethers.Contract(
    checksumAddress,
    ERC20_BYTES32_ABI,
    provider,
  );

  try {
    const [decimals, standardNameResult, standardSymbolResult] = await Promise.all([
      withTimeout(contract.decimals(), 'Token decimals lookup timed out.'),
      withTimeout(contract.name(), 'Token name lookup timed out.').catch(() => ''),
      withTimeout(contract.symbol(), 'Token symbol lookup timed out.').catch(() => ''),
    ]);

    const [bytes32NameResult, bytes32SymbolResult] = await Promise.all([
      withTimeout(bytes32Contract.name(), 'Token name lookup timed out.').catch(() => ''),
      withTimeout(bytes32Contract.symbol(), 'Token symbol lookup timed out.').catch(() => ''),
    ]);

    const resolvedName =
      String(standardNameResult || '').trim() ||
      decodeBytes32String(String(bytes32NameResult || ''));
    const resolvedSymbol =
      String(standardSymbolResult || '').trim() ||
      decodeBytes32String(String(bytes32SymbolResult || ''));
    const resolvedDecimals = Number(decimals);

    if (!resolvedName || !resolvedSymbol || !Number.isFinite(resolvedDecimals)) {
      throw new Error(
        'This contract does not expose standard ERC-20 metadata on the selected network.',
      );
    }

    return {
      id: `${networkKey}-${checksumAddress.toLowerCase()}`,
      type: 'erc20' as const,
      networkKey,
      symbol: resolvedSymbol.slice(0, 12),
      name: resolvedName.slice(0, 40),
      decimals: resolvedDecimals,
      address: checksumAddress,
      isCustom: true,
      accent: WALLET_NETWORKS[networkKey].accent,
      logoUrl: getTokenLogoUrl(networkKey, checksumAddress),
    };
  } catch (error: any) {
    throw new Error(
      withWalletErrorMessage(
        error,
        'This contract does not expose standard ERC-20 metadata on the selected network.',
      ),
    );
  }
};

const toWalletAsset = (
  token: TokenCatalogItem,
  rawBalance: bigint,
  usdPrice: number,
) => {
  const normalizedBalance =
    token.type === 'native'
      ? ethers.formatEther(rawBalance)
      : ethers.formatUnits(rawBalance, token.decimals);
  const numericBalance = Number(normalizedBalance || 0);

  return {
    ...token,
    balance: numericBalance.toFixed(token.decimals <= 6 ? 2 : 5),
    rawBalance: rawBalance.toString(),
    usdValue: Number.isFinite(numericBalance * usdPrice)
      ? numericBalance * usdPrice
      : 0,
  };
};

const readNativeAsset = async (
  address: string,
  networkKey: WalletNetworkKey,
  usdPrice: number,
) => {
  const provider = getProvider(networkKey);
  const rawBalance = await withTimeout<bigint>(
    provider.getBalance(address),
    `Balance lookup timed out on ${WALLET_NETWORKS[networkKey].shortName}.`,
  );

  return toWalletAsset(createNativeToken(networkKey), rawBalance, usdPrice);
};

const readTokenAsset = async (
  address: string,
  token: TokenCatalogItem,
  usdPrice: number,
) => {
  if (!token.address) {
    return toWalletAsset(token, 0n, usdPrice);
  }

  const provider = getProvider(token.networkKey);
  const contract = new ethers.Contract(token.address, ERC20_ABI, provider);

  try {
    const rawBalance = await withTimeout<bigint>(
      contract.balanceOf(address),
      `${token.symbol} balance lookup timed out.`,
    );

    return toWalletAsset(token, rawBalance, usdPrice);
  } catch (error: any) {
    if (String(error?.message ?? '').includes('CALL_EXCEPTION')) {
      return toWalletAsset(token, 0n, usdPrice);
    }

    throw error;
  }
};

export const getWalletAssetsForNetwork = async ({
  address,
  networkKey,
}: {
  address: string;
  networkKey: WalletNetworkKey;
}) => {
  const [visibleTokenIds, tokens] = await Promise.all([
    loadVisibleTokenIds(),
    getTokensForNetwork(networkKey),
  ]);
  const activeTokens = tokens.filter(
    token => token.type === 'native' || visibleTokenIds.includes(token.id),
  );
  const priceIds = activeTokens
    .map(token => token.coingeckoId)
    .filter(Boolean) as string[];
  const priceMap = await fetchPriceMap(priceIds);

  const assetResults = await Promise.allSettled(
    activeTokens.map(token => {
      const usdPrice = token.coingeckoId ? priceMap[token.coingeckoId] ?? 0 : 0;
      return token.type === 'native'
        ? readNativeAsset(address, token.networkKey, usdPrice)
        : readTokenAsset(address, token, usdPrice);
    }),
  );

  const assets = assetResults
    .map((result, index) => {
      if (result.status === 'fulfilled') {
        return result.value;
      }

      console.log(
        `Wallet asset load warning (${activeTokens[index]?.id ?? 'unknown-token'})`,
        result.reason,
      );
      return undefined;
    })
    .filter(Boolean) as WalletAsset[];

  return assets.sort((left, right) => {
    const usdDelta = right.usdValue - left.usdValue;
    if (usdDelta !== 0) {
      return usdDelta;
    }

    return Number(right.balance) - Number(left.balance);
  });
};

export const getWalletAssetsForNetworks = async ({
  address,
  networkKeys,
}: {
  address: string;
  networkKeys: WalletNetworkKey[];
}) => {
  const result = await Promise.all(
    networkKeys.map(async networkKey => ({
      networkKey,
      assets: await getWalletAssetsForNetwork({address, networkKey}),
    })),
  );

  return result.reduce<Record<WalletNetworkKey, WalletAsset[]>>((accumulator, item) => {
    accumulator[item.networkKey] = item.assets;
    return accumulator;
  }, {} as Record<WalletNetworkKey, WalletAsset[]>);
};

export const loadWalletActivity = async () => {
  const stored = await getStorageToken(WALLET_ACTIVITY_KEY);
  return safeParse<WalletActivity[]>(stored, []).sort(
    (left, right) =>
      new Date(right.createdAt).getTime() - new Date(left.createdAt).getTime(),
  );
};

export const appendWalletActivity = async (activity: WalletActivity) => {
  const current = await loadWalletActivity();
  const next = [activity, ...current].slice(0, 50);
  await setStorageToken(WALLET_ACTIVITY_KEY, JSON.stringify(next));
  return next;
};

export const getExplorerTransactionUrl = (
  networkKey: WalletNetworkKey,
  hash: string,
) => `${WALLET_NETWORKS[networkKey].explorerUrl}/tx/${hash}`;

export const sendWalletAsset = async ({
  asset,
  to,
  amount,
}: SendAssetParams) => {
  if (!ethers.isAddress(to.trim())) {
    throw new Error('Enter a valid destination address.');
  }

  const parsedAmount = Number(amount.trim());
  if (!Number.isFinite(parsedAmount) || parsedAmount <= 0) {
    throw new Error('Enter a valid amount to send.');
  }

  const mnemonic = await getWalletMnemonic();
  const signer = ethers.Wallet.fromPhrase(mnemonic).connect(
    getProvider(asset.networkKey),
  );
  const checksumTo = normalizeAddress(to);

  if (asset.type === 'erc20' && asset.address) {
    const contract = new ethers.Contract(asset.address, ERC20_ABI, signer);
    const tx = await contract.transfer(
      checksumTo,
      ethers.parseUnits(amount.trim(), asset.decimals),
    );

    await appendWalletActivity({
      id: `${tx.hash}-${Date.now()}`,
      type: 'sent',
      title: `Sent ${asset.symbol}`,
      subtitle: `${asset.balance} available on ${WALLET_NETWORKS[asset.networkKey].shortName}.`,
      createdAt: new Date().toISOString(),
      networkKey: asset.networkKey,
      symbol: asset.symbol,
      amount: amount.trim(),
      hash: tx.hash,
    });

    return {
      hash: tx.hash,
      network: WALLET_NETWORKS[asset.networkKey],
      symbol: asset.symbol,
    };
  }

  const tx = await signer.sendTransaction({
    to: checksumTo,
    value: ethers.parseEther(amount.trim()),
  });

  await appendWalletActivity({
    id: `${tx.hash}-${Date.now()}`,
    type: 'sent',
    title: `Sent ${asset.symbol}`,
    subtitle: `${amount.trim()} ${asset.symbol} submitted on ${WALLET_NETWORKS[asset.networkKey].shortName}.`,
    createdAt: new Date().toISOString(),
    networkKey: asset.networkKey,
    symbol: asset.symbol,
    amount: amount.trim(),
    hash: tx.hash,
  });

  return {
    hash: tx.hash,
    network: WALLET_NETWORKS[asset.networkKey],
    symbol: asset.symbol,
  };
};
