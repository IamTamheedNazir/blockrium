import React from 'react';
import renderer, {act} from 'react-test-renderer';
import {describe, expect, it, jest} from '@jest/globals';
import {Pressable, Text} from 'react-native';
import {logAppIssue} from '../source/services/appIssueLogger';
import RootErrorBoundary from '../source/molecules/RootErrorBoundary';

jest.mock('react-native-restart', () => ({
  __esModule: true,
  default: {
    Restart: jest.fn(() => {
      throw new Error('Native restart unavailable');
    }),
  },
}));

jest.mock('../source/services/appIssueLogger', () => ({
  logAppIssue: jest.fn<typeof logAppIssue>().mockResolvedValue(false),
}));

const renderBrokenScreen = jest.fn(() => {
  throw new Error('Synthetic startup failure');
});

const BrokenScreen = () => renderBrokenScreen();

describe('RootErrorBoundary recovery', () => {
  it('records the React component stack with root render failures', () => {
    const issueLogger = logAppIssue as jest.MockedFunction<typeof logAppIssue>;
    const consoleError = jest
      .spyOn(console, 'error')
      .mockImplementation(() => {});
    const consoleLog = jest.spyOn(console, 'log').mockImplementation(() => {});
    let tree: renderer.ReactTestRenderer | undefined;

    try {
      act(() => {
        tree = renderer.create(
          <RootErrorBoundary>
            <BrokenScreen />
          </RootErrorBoundary>,
        );
      });

      expect(issueLogger).toHaveBeenCalledWith(
        expect.objectContaining({
          severity: 'fatal',
          action: 'root_error_boundary',
          metadata: expect.objectContaining({
            componentStack: expect.stringContaining('BrokenScreen'),
          }),
        }),
      );
      expect(tree!.root.findAllByType(Text).map(node => node.props.children).join(' '))
        .toContain('Session needs a quick refresh');
    } finally {
      tree?.unmount();
      consoleError.mockRestore();
      consoleLog.mockRestore();
    }
  });

  it('keeps a stable recovery screen when the native restart hook fails', () => {
    const restartModule = jest.requireMock('react-native-restart') as {
      default: {Restart: jest.Mock};
    };
    const restart = restartModule.default.Restart;
    const consoleError = jest
      .spyOn(console, 'error')
      .mockImplementation(() => {});
    const consoleLog = jest.spyOn(console, 'log').mockImplementation(() => {});
    let tree: renderer.ReactTestRenderer | undefined;

    try {
      act(() => {
        tree = renderer.create(
          <RootErrorBoundary>
            <BrokenScreen />
          </RootErrorBoundary>,
        );
      });

      const renderAttemptsBeforeRestart = renderBrokenScreen.mock.calls.length;
      const restartButton = tree!.root.findByType(Pressable);
      act(() => restartButton.props.onPress());

      const visibleText = tree!.root
        .findAllByType(Text)
        .map(node => node.props.children)
        .join(' ');

      expect(restart).toHaveBeenCalledTimes(1);
      expect(visibleText).toContain('Automatic restart is unavailable');
      expect(visibleText).toContain('Close and reopen Blockrium');
      expect(tree!.root.findByType(Pressable).props.disabled).toBe(true);
      expect(renderBrokenScreen).toHaveBeenCalledTimes(
        renderAttemptsBeforeRestart,
      );
    } finally {
      tree?.unmount();
      consoleError.mockRestore();
      consoleLog.mockRestore();
    }
  });
});
