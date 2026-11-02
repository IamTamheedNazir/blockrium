import React from 'react';
import renderer, {act} from 'react-test-renderer';
import WalletStack from '../source/navigators/WalletStack';

const mockWalletProvider = jest.fn(({children}: any) => <>{children}</>);
const mockNavigatorProps = jest.fn();

jest.mock('@react-navigation/native-stack', () => {
  const ReactModule = require('react');
  const {View} = require('react-native');

  return {
    createNativeStackNavigator: () => ({
      Navigator: ({children, ...props}: any) => {
        mockNavigatorProps(props);
        return ReactModule.createElement(View, null, children);
      },
      Screen: ({name}: any) => ReactModule.createElement(View, {testID: `route-${name}`}),
    }),
  };
});

jest.mock('../source/pages/WalletFlow/WalletProvider', () => ({
  WalletProvider: (props: any) => mockWalletProvider(props),
}));

jest.mock('../source/pages/WalletFlow/BrcWalletHomeScreen', () => () => null);
jest.mock('../source/pages/WalletFlow/WalletBrcTransferScreen', () => () => null);

describe('Wallet stack separation', () => {
  beforeEach(() => {
    mockWalletProvider.mockClear();
    mockNavigatorProps.mockClear();
  });

  it('opens on the internal BRC wallet without mounting the Web3 provider', () => {
    let tree!: renderer.ReactTestRenderer;

    act(() => {
      tree = renderer.create(<WalletStack />);
    });

    expect(mockNavigatorProps).toHaveBeenCalledWith(
      expect.objectContaining({initialRouteName: 'BrcWalletHome'}),
    );
    expect(tree.root.findByProps({testID: 'route-BlockchainWallet'})).toBeTruthy();
    expect(mockWalletProvider).not.toHaveBeenCalled();
    act(() => tree.unmount());
  });
});
