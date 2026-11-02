/* eslint-env jest */

jest.mock('native-base', () => ({
  NativeBaseProvider: ({children}) => {
    const React = require('react');
    const {View} = require('react-native');
    return React.createElement(View, null, children);
  },
  Box: ({children}) => {
    const React = require('react');
    const {View} = require('react-native');
    return React.createElement(View, null, children);
  },
  HStack: ({children}) => {
    const React = require('react');
    const {View} = require('react-native');
    return React.createElement(View, null, children);
  },
  VStack: ({children}) => {
    const React = require('react');
    const {View} = require('react-native');
    return React.createElement(View, null, children);
  },
  FlatList: require('react-native').FlatList,
  Text: ({children}) => {
    const React = require('react');
    const {Text} = require('react-native');
    return React.createElement(Text, null, children);
  },
  Skeleton: ({children}) => {
    const React = require('react');
    const {View} = require('react-native');
    return React.createElement(View, null, children);
  },
}));

jest.mock('react-native-linear-gradient', () => 'LinearGradient');
jest.mock('react-native-fast-image', () => 'FastImage');
jest.mock('react-native-webview', () => ({
  WebView: 'WebView',
}));
jest.mock('react-native-qrcode-svg', () => 'QRCode');
jest.mock('react-native-share', () => ({
  open: jest.fn(),
}));
jest.mock('react-native-inappbrowser-reborn', () => ({
  open: jest.fn(),
  close: jest.fn(),
  openAuth: jest.fn(),
  isAvailable: jest.fn().mockResolvedValue(true),
}));
jest.mock('react-native-push-notification', () => ({
  configure: jest.fn(),
  localNotification: jest.fn(),
  cancelAllLocalNotifications: jest.fn(),
  createChannel: jest.fn(),
}));
jest.mock('@react-native-async-storage/async-storage', () => ({
  setItem: jest.fn().mockResolvedValue(undefined),
  getItem: jest.fn().mockResolvedValue(null),
  removeItem: jest.fn().mockResolvedValue(undefined),
}));
jest.mock('react-native-permissions', () => ({
  check: jest.fn().mockResolvedValue('granted'),
  request: jest.fn().mockResolvedValue('granted'),
  PERMISSIONS: {},
  RESULTS: {
    GRANTED: 'granted',
    DENIED: 'denied',
  },
}));
jest.mock('react-native-responsive-fontsize', () => ({
  RFValue: value => value,
}));
jest.mock('react-native-responsive-screen', () => ({
  widthPercentageToDP: value => value,
  heightPercentageToDP: value => value,
}));
jest.mock('ky', () => ({
  create: () => ({
    extend: jest.fn(),
    get: jest.fn(() => ({json: jest.fn()})),
    post: jest.fn(() => ({json: jest.fn()})),
    put: jest.fn(() => ({json: jest.fn()})),
    delete: jest.fn(() => ({json: jest.fn()})),
  }),
}));
jest.mock('react-native-actions-sheet', () => {
  const React = require('react');
  const {View} = require('react-native');
  const SheetProvider = ({children}) => React.createElement(View, null, children);
  return {
    __esModule: true,
    default: ({children}) => React.createElement(View, null, children),
    SheetProvider,
    useSheetRef: () => ({current: {show: jest.fn(), hide: jest.fn()}}),
  };
});

jest.mock('react-native-network-logger', () => {
  const React = require('react');
  const {View} = require('react-native');
  return {
    __esModule: true,
    default: () => React.createElement(View, null),
    startNetworkLogging: jest.fn(),
  };
});

jest.mock('@react-native-community/netinfo', () => {
  const netInfo = {
    addEventListener: jest.fn(callback => {
      callback({isConnected: true});
      return jest.fn();
    }),
  };

  return {
    __esModule: true,
    default: netInfo,
    ...netInfo,
  };
});

jest.mock('@react-native-firebase/remote-config', () => () => ({
  setDefaults: jest.fn().mockResolvedValue(undefined),
  fetchAndActivate: jest.fn().mockResolvedValue(true),
  getValue: jest.fn(() => ({asString: () => '1.0'})),
}));

jest.mock('@react-native-firebase/messaging', () => () => ({
  getToken: jest.fn().mockResolvedValue('mock-device-token'),
}));

jest.mock('react-native-device-info', () => ({
  getVersion: jest.fn(() => '1.0'),
}));

jest.mock('react-native-purchases', () => ({
  __esModule: true,
  default: {
    addCustomerInfoUpdateListener: jest.fn(),
    configure: jest.fn(),
    logIn: jest.fn().mockResolvedValue({customerInfo: {}}),
    logOut: jest.fn().mockResolvedValue({}),
    getCustomerInfo: jest.fn().mockResolvedValue({entitlements: {active: {}}}),
    getOfferings: jest.fn().mockResolvedValue({current: null, all: {}}),
    purchasePackage: jest.fn().mockResolvedValue({customerInfo: {}}),
    removeCustomerInfoUpdateListener: jest.fn(),
    restorePurchases: jest.fn().mockResolvedValue({entitlements: {active: {}}}),
    setLogLevel: jest.fn().mockResolvedValue(undefined),
  },
  LOG_LEVEL: {
    DEBUG: 'DEBUG',
    ERROR: 'ERROR',
    INFO: 'INFO',
  },
  PURCHASES_ERROR_CODE: {},
}));

jest.mock('react-native-purchases-ui', () => ({
  __esModule: true,
  default: {
    presentPaywall: jest.fn().mockResolvedValue('NOT_PRESENTED'),
    presentPaywallIfNeeded: jest.fn().mockResolvedValue('NOT_PRESENTED'),
    presentCustomerCenter: jest.fn().mockResolvedValue(undefined),
  },
  PAYWALL_RESULT: {
    NOT_PRESENTED: 'NOT_PRESENTED',
    PURCHASED: 'PURCHASED',
    RESTORED: 'RESTORED',
    CANCELLED: 'CANCELLED',
    ERROR: 'ERROR',
  },
}));

jest.mock('react-native-mmkv', () => ({
  MMKV: jest.fn().mockImplementation(() => ({
    set: jest.fn(),
    getString: jest.fn(),
    getNumber: jest.fn(),
  })),
}), {virtual: true});

jest.mock('react-native-appodeal', () => {
  const appodeal = {
    addEventListener: jest.fn((event, callback) => {
      if (event === 'initialized') {
        callback();
      }
    }),
    cache: jest.fn(),
    canShow: jest.fn(() => false),
    initialize: jest.fn(),
    isLoaded: jest.fn(() => false),
    setAutoCache: jest.fn(),
    setBannerAnimation: jest.fn(),
    setChildDirectedTreatment: jest.fn(),
    setLogLevel: jest.fn(),
    setSmartBanners: jest.fn(),
    setTabletBanners: jest.fn(),
    setTesting: jest.fn(),
    setUserId: jest.fn(),
    show: jest.fn(),
  };

  return {
    __esModule: true,
    default: appodeal,
    Appodeal: appodeal,
    AppodealAdType: {
      INTERSTITIAL: 1,
      BANNER: 2,
      REWARDED_VIDEO: 4,
    },
    AppodealBanner: 'AppodealBanner',
    AppodealBannerEvents: {
      LOADED: 'bannerLoaded',
    },
    AppodealInterstitialEvents: {
      LOADED: 'interstitialLoaded',
      FAILED_TO_LOAD: 'interstitialFailedToLoad',
      SHOWN: 'interstitialShown',
      CLICKED: 'interstitialClicked',
      CLOSED: 'interstitialClosed',
      EXPIRED: 'interstitialExpired',
      FAILED_TO_SHOW: 'interstitialFailedToShow',
    },
    AppodealLogLevel: {
      NONE: 0,
      VERBOSE: 1,
    },
    AppodealRewardedEvents: {
      LOADED: 'rewardedLoaded',
      FAILED_TO_LOAD: 'rewardedFailedToLoad',
      SHOWN: 'rewardedShown',
      REWARD: 'rewardedReward',
      CLICKED: 'rewardedClicked',
      CLOSED: 'rewardedClosed',
      EXPIRED: 'rewardedExpired',
      FAILED_TO_SHOW: 'rewardedFailedToShow',
    },
    AppodealSdkEvents: {
      INITIALIZED: 'initialized',
    },
  };
});

jest.mock('react-native-gesture-handler', () => {
  const React = require('react');
  const {View} = require('react-native');
  const actual = jest.requireActual('react-native-gesture-handler/jestSetup');
  return {
    ...actual,
    GestureHandlerRootView: ({children}) => React.createElement(View, null, children),
  };
});

jest.mock('react-native-reanimated', () => {
  const Reanimated = require('react-native-reanimated/mock');
  Reanimated.default.call = () => {};
  return Reanimated;
});

jest.mock('react-native-purchases', () => ({
  __esModule: true,
  default: {
    configure: jest.fn(),
    setLogLevel: jest.fn().mockResolvedValue(undefined),
    addCustomerInfoUpdateListener: jest.fn(),
    removeCustomerInfoUpdateListener: jest.fn(),
    getCustomerInfo: jest.fn().mockResolvedValue({
      entitlements: {active: {}, all: {}},
      activeSubscriptions: [],
      allPurchasedProductIdentifiers: [],
      originalAppUserId: 'test-user',
    }),
    getOfferings: jest.fn().mockResolvedValue({
      current: null,
      all: {},
    }),
    purchasePackage: jest.fn(),
    restorePurchases: jest.fn().mockResolvedValue({
      entitlements: {active: {}, all: {}},
      activeSubscriptions: [],
      allPurchasedProductIdentifiers: [],
      originalAppUserId: 'test-user',
    }),
    logIn: jest.fn().mockResolvedValue({
      customerInfo: {
        entitlements: {active: {}, all: {}},
        activeSubscriptions: [],
        allPurchasedProductIdentifiers: [],
        originalAppUserId: 'test-user',
      },
      created: false,
    }),
    logOut: jest.fn().mockResolvedValue({
      entitlements: {active: {}, all: {}},
      activeSubscriptions: [],
      allPurchasedProductIdentifiers: [],
      originalAppUserId: 'test-user',
    }),
  },
  LOG_LEVEL: {
    DEBUG: 'DEBUG',
    INFO: 'INFO',
  },
}));

jest.mock('react-native-purchases-ui', () => ({
  __esModule: true,
  default: {
    presentPaywall: jest.fn().mockResolvedValue('CANCELLED'),
    presentPaywallIfNeeded: jest.fn().mockResolvedValue('NOT_PRESENTED'),
    presentCustomerCenter: jest.fn().mockResolvedValue(undefined),
  },
  PAYWALL_RESULT: {
    NOT_PRESENTED: 'NOT_PRESENTED',
    ERROR: 'ERROR',
    CANCELLED: 'CANCELLED',
    PURCHASED: 'PURCHASED',
    RESTORED: 'RESTORED',
  },
}));

jest.mock('react-native-vector-icons/Ionicons', () => 'Ionicons');
jest.mock('react-native-vector-icons/MaterialCommunityIcons', () => 'MaterialCommunityIcons');
jest.mock('react-native-vector-icons/Feather', () => 'Feather');
jest.mock('react-native-vector-icons/MaterialIcons', () => 'MaterialIcons');
