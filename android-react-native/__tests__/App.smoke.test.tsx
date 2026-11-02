import React from 'react';
import renderer, {act} from 'react-test-renderer';
import {afterEach, beforeEach, describe, expect, it, jest} from '@jest/globals';

jest.mock('@react-navigation/native', () => {
  const React = require('react');
  const {View} = require('react-native');

  return {
    DarkTheme: {
      colors: {
        background: '#000',
        border: '#000',
        card: '#000',
        notification: '#000',
        primary: '#000',
        text: '#fff',
      },
      dark: true,
    },
    NavigationContainer: ({children}: any) =>
      React.createElement(View, null, children),
  };
});
jest.mock('../source/navigators/MainStack', () => 'MainStack');
jest.mock('../source/molecules/CustomAlert', () => 'CustomAlert');
jest.mock('../source/molecules/ConsentAlert', () => ({
  __esModule: true,
  default: 'ConsentAlert',
  hasDataConsent: jest.fn<() => Promise<boolean>>().mockResolvedValue(false),
}));
jest.mock('../source/molecules/OfflineBanner', () => 'OfflineBanner');
jest.mock('../source/molecules/NetworkLogDebugModal', () => 'NetworkLogDebugModal');
jest.mock('../source/hooks/AlertContext', () => ({
  AlertProvider: ({children}: any) => children,
}));
jest.mock('../source/hooks', () => ({
  useAlert: jest.fn(),
}));
jest.mock('../source/storage/client/useCustomAlert/alertStore', () => () => ({
  selectSnack: {called: false},
  snackTitle: '',
  timer: null,
  onCloseSnack: jest.fn(),
  setSnackTitle: jest.fn(),
  setSnackMode: jest.fn(),
  setTimer: jest.fn(),
}));
jest.mock('../source/utils/helper', () => ({
  navigationRef: {current: null},
}));
jest.mock('../source/services/adService', () => ({
  initializeAds: jest.fn(),
}));
jest.mock('../source/services/blockriumNotificationService', () => ({
  initializeBlockriumNotifications: jest.fn(),
}));
jest.mock('../source/design/primitives', () => {
  const React = require('react');
  const {Text, View} = require('react-native');

  return {
    NavigationProgressBar: () => React.createElement(View, null),
    ScreenLoader: ({title}: any) => React.createElement(Text, null, title),
  };
});
jest.mock('../source/providers/RevenueCatProvider', () => ({
  RevenueCatProvider: ({children}: any) => children,
  useRevenueCat: jest.fn(() => ({
    customerInfo: null,
    currentOffering: null,
    packages: [],
    isConfigured: true,
    isLoading: false,
    isPurchasing: false,
    isPro: false,
    proEntitlement: null,
    error: null,
    refresh: jest.fn(),
    purchasePackage: jest.fn(),
    restorePurchases: jest.fn(),
    presentPaywall: jest.fn(),
    presentPaywallIfNeeded: jest.fn(),
    presentCustomerCenter: jest.fn(),
  })),
}));

import App from '../App';

describe('App startup', () => {
  beforeEach(() => {
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.runOnlyPendingTimers();
    jest.useRealTimers();
  });

  it('renders without throwing', async () => {
    let tree: renderer.ReactTestRenderer | undefined;

    await act(async () => {
      expect(() => {
        tree = renderer.create(<App />);
      }).not.toThrow();
      jest.advanceTimersByTime(1000);
      await Promise.resolve();
    });

    await act(async () => {
      tree?.unmount();
    });
  });
});
