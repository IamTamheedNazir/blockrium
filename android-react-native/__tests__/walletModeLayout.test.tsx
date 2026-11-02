import React from 'react';
import {ScrollView, Text} from 'react-native';
import renderer, {act} from 'react-test-renderer';
import {ThemeProvider} from '../source/providers/ThemeProvider';
import {WalletModeLayout} from '../source/pages/WalletFlow/WalletComponents';

describe('WalletModeLayout', () => {
  it('keeps wallet form actions tappable and dismisses the keyboard on drag', () => {
    let tree!: renderer.ReactTestRenderer;

    act(() => {
      tree = renderer.create(
        <ThemeProvider>
          <WalletModeLayout title="BRC transfer">
            <Text>Transfer form</Text>
          </WalletModeLayout>
        </ThemeProvider>,
      );
    });

    const scrollView = tree.root.findByType(ScrollView);
    expect(scrollView.props.keyboardShouldPersistTaps).toBe('handled');
    expect(scrollView.props.keyboardDismissMode).toBe('on-drag');

    act(() => tree.unmount());
  });
});
