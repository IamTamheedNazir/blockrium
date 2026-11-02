import React from 'react';
import renderer, {act} from 'react-test-renderer';
import {Text} from 'react-native';
import LeaderBoardOrg from '../source/organisms/LeaderBoardOrg';

jest.mock('../source/design/primitives', () => ({
  AppScreen: ({children}: any) => {
    const ReactInstance = require('react');
    return ReactInstance.createElement(ReactInstance.Fragment, null, children);
  },
  GlassCard: ({children}: any) => {
    const ReactInstance = require('react');
    return ReactInstance.createElement(ReactInstance.Fragment, null, children);
  },
  SectionEyebrow: ({text}: any) => {
    const ReactInstance = require('react');
    const {Text: NativeText} = require('react-native');
    return ReactInstance.createElement(NativeText, null, text);
  },
  SecondaryButton: ({label, onPress}: any) => {
    const ReactInstance = require('react');
    const {Pressable, Text: NativeText} = require('react-native');
    return ReactInstance.createElement(
      Pressable,
      {accessibilityRole: 'button', onPress},
      ReactInstance.createElement(NativeText, null, label),
    );
  },
}));

jest.mock('react-native-vector-icons/Ionicons', () => () => null);
jest.mock('../source/design/theme', () => ({
  __esModule: true,
  default: {
    colors: {
      accentWarm: '#f90',
      primary: '#0aa',
      accent: '#0f0',
      text: '#fff',
      textMuted: '#aaa',
      textSoft: '#888',
      border: '#333',
    },
    typography: {heading: 'heading', body: 'body', bodyBold: 'bodyBold'},
  },
  alpha: () => '#123',
}));

describe('LeaderBoardOrg error state', () => {
  it('shows a retry action instead of presenting an API failure as an empty ranking', () => {
    const onRetry = jest.fn();
    const tree = renderer.create(
      <LeaderBoardOrg
        topMiners={[]}
        authMiner={null}
        errorMessage="Ranking is temporarily unavailable."
        onRetry={onRetry}
      />,
    );

    expect(tree.root.findAllByType(Text).map(node => node.props.children)).toContain(
      'Ranking is temporarily unavailable.',
    );
    expect(tree.root.findAllByType(Text).map(node => node.props.children)).toContain(
      'Retry',
    );

    act(() => tree.root.findByProps({accessibilityRole: 'button'}).props.onPress());
    expect(onRetry).toHaveBeenCalledTimes(1);
  });
});
