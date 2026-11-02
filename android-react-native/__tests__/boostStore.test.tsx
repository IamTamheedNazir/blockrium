import React from 'react';
import {Alert, Platform, Text} from 'react-native';
import renderer, {act} from 'react-test-renderer';
import BoostStore from '../source/organisms/HomeOrg/BoostStore';

const originalPlatformOS = Platform.OS;

const mockNavigation = {
  canGoBack: jest.fn(),
  dispatch: jest.fn(),
  getParent: jest.fn(),
  navigate: jest.fn(),
};
const mockTabNavigation = {navigate: jest.fn()};
const mockCallQuery = jest.fn();
const mockRefetchPlans = jest.fn();
const mockRefetchBoost = jest.fn();
const mockPlansQuery = {
  data: undefined,
  isLoading: true,
  isFetching: true,
  refetch: mockRefetchPlans,
};

jest.mock('@react-navigation/native', () => ({
  StackActions: {popToTop: () => ({type: 'POP_TO_TOP'})},
  useIsFocused: () => true,
  useNavigation: () => mockNavigation,
}));

jest.mock('../source/hooks', () => ({
  callQuery: (...args: any[]) => mockCallQuery(...args),
}));

jest.mock('../source/services/googlePlayPurchaseService', () => ({
  clearPendingGooglePlayIntent: jest.fn(),
  createAndLaunchGooglePlayPurchase: jest.fn(),
  getPendingGooglePlayIntent: jest.fn().mockResolvedValue(null),
  getGooglePlayProduct: jest.fn().mockResolvedValue(null),
  restorePendingGooglePlayPurchase: jest.fn().mockResolvedValue({found: false}),
  subscribeToGooglePlayPurchases: () => ({remove: jest.fn()}),
  verifyGooglePlayPurchase: jest.fn(),
}));

jest.mock('react-native-vector-icons/MaterialCommunityIcons', () => () => null);
jest.mock('react-native-vector-icons/Ionicons', () => () => null);

jest.mock('../source/design/primitives', () => {
  const MockReact = require('react');
  const Native = require('react-native');
  const MockButton = ({label, onPress, testID}: any) =>
    MockReact.createElement(
      Native.Pressable,
      {onPress, testID},
      MockReact.createElement(Native.Text, null, label),
    );

  return {
    AppScreen: ({children, stickyHeader, onRefresh}: any) =>
      MockReact.createElement(
        Native.View,
        null,
        stickyHeader,
        MockReact.createElement(Native.Pressable, {
          onPress: onRefresh,
          testID: 'boost-store-refresh',
        }),
        children,
      ),
    GlassCard: ({children}: any) =>
      MockReact.createElement(Native.View, null, children),
    InlineLoader: ({label}: any) =>
      MockReact.createElement(Native.Text, null, label),
    PrimaryButton: MockButton,
    ScreenState: ({title, message, actionLabel, onActionPress}: any) =>
      MockReact.createElement(
        Native.View,
        null,
        MockReact.createElement(Native.Text, null, title),
        MockReact.createElement(Native.Text, null, message),
        MockButton({label: actionLabel, onPress: onActionPress, testID: 'boost-state-retry'}),
      ),
    SecondaryButton: MockButton,
    SectionEyebrow: ({text: label}: any) =>
      MockReact.createElement(Native.Text, null, label),
  };
});

const renderScreen = () => renderer.create(<BoostStore />);

describe('Mining boost store', () => {
  beforeEach(() => {
    jest.useFakeTimers();
    jest.clearAllMocks();
    mockNavigation.canGoBack.mockReturnValue(true);
    mockNavigation.getParent.mockReturnValue(mockTabNavigation);
    mockCallQuery.mockImplementation((endpoint: string) =>
      endpoint === 'auth/boost-plans'
        ? mockPlansQuery
        : {data: {data: {}}, isLoading: false, refetch: mockRefetchBoost},
    );
  });

  afterEach(() => {
    Object.defineProperty(Platform, 'OS', {configurable: true, value: originalPlatformOS});
    jest.useRealTimers();
  });

  it('replaces a stuck plans spinner with a retry state', () => {
    let tree!: renderer.ReactTestRenderer;
    act(() => {
      tree = renderScreen();
    });

    expect(tree.root.findAllByType(Text).some(node => node.props.children === 'Loading boost plans...')).toBe(true);

    act(() => {
      jest.advanceTimersByTime(12000);
    });

    const text = tree.root
      .findAllByType(Text)
      .map(node => node.props.children)
      .flat(Infinity)
      .join(' ');
    expect(text).toContain('Boost plans are taking longer than expected');

    act(() => {
      tree.root.findByProps({testID: 'boost-state-retry'}).props.onPress();
    });
    expect(mockRefetchPlans).toHaveBeenCalledTimes(1);

    act(() => {
      jest.advanceTimersByTime(12000);
    });
    expect(
      tree.root
        .findAllByType(Text)
        .some(node => node.props.children === 'Boost plans are taking longer than expected'),
    ).toBe(true);
    act(() => tree.unmount());
  });

  it('keeps Home and Mining navigation actions visible outside the scroll content', () => {
    let tree!: renderer.ReactTestRenderer;
    act(() => {
      tree = renderScreen();
    });

    act(() => {
      tree.root.findByProps({testID: 'boost-store-home'}).props.onPress();
      tree.root.findByProps({testID: 'boost-store-mining'}).props.onPress();
    });

    expect(mockNavigation.dispatch).toHaveBeenCalledWith({type: 'POP_TO_TOP'});
    expect(mockTabNavigation.navigate).toHaveBeenCalledWith('Mining', {
      screen: 'MiningFlow',
    });
    act(() => tree.unmount());
  });

  it('shows the nested server error instead of labeling a failed request as paused', () => {
    mockCallQuery.mockImplementation((endpoint: string) =>
      endpoint === 'auth/boost-plans'
        ? {
            data: {
              success: true,
              data: {
                status: 0,
                message: 'Boost plans are not installed yet.',
                data: [],
              },
            },
            isLoading: false,
            isFetching: false,
            refetch: mockRefetchPlans,
          }
        : {data: {data: {}}, isLoading: false, refetch: jest.fn()},
    );
    let tree!: renderer.ReactTestRenderer;
    act(() => {
      tree = renderScreen();
    });

    const text = tree.root
      .findAllByType(Text)
      .map(node => node.props.children)
      .flat(Infinity)
      .join(' ');
    expect(text).toContain('Boost plans could not be loaded');
    expect(text).toContain('Boost plans are not installed yet.');
    expect(text).not.toContain('Boost purchases are paused');
    act(() => tree.unmount());
  });

  it('shows plan cards with their benefits while checkout is paused', () => {
    mockCallQuery.mockImplementation((endpoint: string) =>
      endpoint === 'auth/boost-plans'
        ? {
            data: {
              success: true,
              data: {
                store_enabled: true,
                checkout_enabled: false,
                data: [
                  {
                    id: 11,
                    name: 'Blockrium Basic Mining Boost',
                    boost_percent: 10,
                    duration_days: 30,
                    purchase_ready: false,
                  },
                ],
              },
            },
            isLoading: false,
            isFetching: false,
            refetch: mockRefetchPlans,
          }
        : {data: {data: {}}, isLoading: false, refetch: jest.fn()},
    );

    let tree!: renderer.ReactTestRenderer;
    act(() => {
      tree = renderScreen();
    });

    const text = tree.root
      .findAllByType(Text)
      .map(node => node.props.children)
      .flat(Infinity)
      .join(' ');
    expect(text).toContain('Blockrium Basic Mining Boost');
    expect(text).toContain('+ 10 % mining rate');
    expect(text).toContain('30  days');
    expect(text).toContain('Purchases paused');
    act(() => tree.unmount());
  });

  it('explains where to enable plan visibility when the store is hidden', () => {
    mockCallQuery.mockImplementation((endpoint: string) =>
      endpoint === 'auth/boost-plans'
        ? {
            data: {
              success: true,
              data: {
                store_enabled: false,
                checkout_enabled: false,
                data: [],
              },
            },
            isLoading: false,
            isFetching: false,
            refetch: mockRefetchPlans,
          }
        : {data: {data: {}}, isLoading: false, refetch: jest.fn()},
    );

    let tree!: renderer.ReactTestRenderer;
    act(() => {
      tree = renderScreen();
    });

    const text = tree.root
      .findAllByType(Text)
      .map(node => node.props.children)
      .flat(Infinity)
      .join(' ');
    expect(text).toContain('Boost plans are hidden');
    expect(text).toContain('Admin > Verified Members > Settings');
    expect(text).toContain('Google Play checkout is enabled');
    act(() => tree.unmount());
  });

  it('labels an available Google Play plan action Boost Now', async () => {
    Object.defineProperty(Platform, 'OS', {configurable: true, value: 'android'});
    const planResponse = {
      data: {
        success: true,
        data: {
          store_enabled: true,
          checkout_enabled: true,
          data: [
            {
              id: 13,
              name: 'Blockrium Basic Mining Boost',
              boost_percent: 10,
              duration_days: 30,
              purchase_ready: true,
              google_play_product_id: 'brc_boost_basic',
              google_play_base_plan_id: 'basic-monthly',
            },
          ],
        },
      },
      isLoading: false,
      isFetching: false,
      refetch: mockRefetchPlans,
    };
    mockCallQuery.mockImplementation((endpoint: string) =>
      endpoint === 'auth/boost-plans'
        ? planResponse
        : {data: {data: {}}, isLoading: false, refetch: jest.fn()},
    );
    const {getGooglePlayProduct} = jest.requireMock('../source/services/googlePlayPurchaseService');
    getGooglePlayProduct.mockResolvedValue({
      productId: 'brc_boost_basic',
      title: 'Basic boost',
      description: 'Monthly boost',
      formattedPrice: '$5.00',
      basePlanId: 'basic-monthly',
    });

    let tree!: renderer.ReactTestRenderer;
    await act(async () => {
      tree = renderScreen();
      await Promise.resolve();
      await Promise.resolve();
    });

    const text = tree.root
      .findAllByType(Text)
      .map(node => node.props.children)
      .flat(Infinity)
      .join(' ');
    expect(text).toContain('Boost Now');
    expect(text).not.toContain('Continue with Google Play');
    act(() => tree.unmount());
  });

  it('does not leave a plan price in a checking state when native Play billing is unavailable', async () => {
    mockCallQuery.mockImplementation((endpoint: string) =>
      endpoint === 'auth/boost-plans'
        ? {
            data: {
              success: true,
              data: {
                store_enabled: true,
                checkout_enabled: true,
                data: [
                  {
                    id: 12,
                    name: 'Blockrium Pro Mining Boost',
                    boost_percent: 25,
                    duration_days: 30,
                    purchase_ready: true,
                    google_play_product_id: 'brc_boost_basic',
                    google_play_base_plan_id: 'pro-monthly',
                  },
                ],
              },
            },
            isLoading: false,
            isFetching: false,
            refetch: mockRefetchPlans,
          }
        : {data: {data: {}}, isLoading: false, refetch: jest.fn()},
    );

    let tree!: renderer.ReactTestRenderer;
    await act(async () => {
      tree = renderScreen();
      await Promise.resolve();
    });

    const text = tree.root
      .findAllByType(Text)
      .map(node => node.props.children)
      .flat(Infinity)
      .join(' ');
    expect(text).toContain('Blockrium Pro Mining Boost');
    expect(text).toContain('Unavailable');
    expect(text).not.toContain('Checking');
    expect(tree.root.findByProps({testID: 'boost-store-home'})).toBeTruthy();
    act(() => tree.unmount());
  });

  it('reports Play status refresh failures without leaking a rejected promise', async () => {
    Object.defineProperty(Platform, 'OS', {configurable: true, value: 'android'});
    const {restorePendingGooglePlayPurchase} = jest.requireMock('../source/services/googlePlayPurchaseService');
    restorePendingGooglePlayPurchase.mockRejectedValue(new Error('Billing service unavailable.'));
    const alertSpy = jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);

    let tree!: renderer.ReactTestRenderer;
    await act(async () => {
      tree = renderScreen();
      await Promise.resolve();
      await Promise.resolve();
    });

    await act(async () => {
      tree.root.findByProps({testID: 'boost-store-refresh'}).props.onPress();
      await Promise.resolve();
      await Promise.resolve();
    });

    expect(alertSpy).toHaveBeenCalledWith(
      'Could not refresh payment status',
      'Billing service unavailable.',
    );
    act(() => tree.unmount());
    alertSpy.mockRestore();
  });

  it('refreshes boost status only once while recovering a pending Play purchase', async () => {
    Object.defineProperty(Platform, 'OS', {configurable: true, value: 'android'});
    const {restorePendingGooglePlayPurchase} = jest.requireMock('../source/services/googlePlayPurchaseService');
    restorePendingGooglePlayPurchase.mockResolvedValue({found: false});

    let tree!: renderer.ReactTestRenderer;
    await act(async () => {
      tree = renderScreen();
      await Promise.resolve();
    });

    await act(async () => {
      tree.root.findByProps({testID: 'boost-store-refresh'}).props.onPress();
      await Promise.resolve();
      await Promise.resolve();
      await Promise.resolve();
    });

    expect(mockRefetchPlans).toHaveBeenCalledTimes(1);
    expect(mockRefetchBoost).toHaveBeenCalledTimes(1);
    act(() => tree.unmount());
  });
});
