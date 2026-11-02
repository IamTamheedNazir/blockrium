import React from 'react';
import renderer, {act} from 'react-test-renderer';
import CustomBottomTabs from '../source/molecules/CustomBottomTabs';

const mockDispatch = jest.fn();
const mockNavigate = jest.fn();
const mockEmit = jest.fn().mockReturnValue({defaultPrevented: false});

jest.mock('@react-navigation/native', () => ({
  StackActions: {popToTop: () => ({type: 'POP_TO_TOP'})},
}));

jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({bottom: 0}),
}));

jest.mock('react-native-linear-gradient', () => {
  const {View} = require('react-native');
  return ({children}: any) => <View>{children}</View>;
});

jest.mock('../source/atoms/NavigationIcon', () => {
  const {Pressable, Text} = require('react-native');
  return ({route, onPress}: any) => (
    <Pressable testID={`tab-${route}`} onPress={onPress}>
      <Text>{route}</Text>
    </Pressable>
  );
});

describe('Custom bottom tabs', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockEmit.mockReturnValue({defaultPrevented: false});
  });

  it('pops the selected tab stack to its root when its tab is tapped', () => {
    const state = {
      index: 0,
      routes: [
        {key: 'home-tab', name: 'Home', state: {key: 'home-stack'}},
        {key: 'mining-tab', name: 'Mining', state: {key: 'mining-stack'}},
      ],
    };
    const navigation = {
      dispatch: mockDispatch,
      emit: mockEmit,
      navigate: mockNavigate,
    };
    let tree!: renderer.ReactTestRenderer;

    act(() => {
      tree = renderer.create(<CustomBottomTabs state={state} navigation={navigation} />);
    });
    act(() => {
      tree.root.findByProps({testID: 'tab-Home'}).props.onPress();
    });

    expect(mockDispatch).toHaveBeenCalledWith({
      type: 'POP_TO_TOP',
      target: 'home-stack',
    });
    expect(mockNavigate).toHaveBeenCalledWith('Home', {
      screen: 'HomeFlow',
      merge: true,
    });
    act(() => tree.unmount());
  });

  it('opens the internal BRC wallet as the Wallet tab root', () => {
    const state = {
      index: 0,
      routes: [
        {key: 'wallet-tab', name: 'Wallet', state: {key: 'wallet-stack'}},
      ],
    };
    const navigation = {
      dispatch: mockDispatch,
      emit: mockEmit,
      navigate: mockNavigate,
    };
    let tree!: renderer.ReactTestRenderer;

    act(() => {
      tree = renderer.create(<CustomBottomTabs state={state} navigation={navigation} />);
    });
    act(() => {
      tree.root.findByProps({testID: 'tab-Wallet'}).props.onPress();
    });

    expect(mockNavigate).toHaveBeenCalledWith('Wallet', {
      screen: 'BrcWalletHome',
      merge: true,
    });
    act(() => tree.unmount());
  });
});
