import React from 'react';
import {AppState, Text} from 'react-native';
import renderer, {act} from 'react-test-renderer';
import {useAppResumeRefresh} from '../source/hooks/useAppResumeRefresh';

let appStateChangeHandler: Parameters<typeof AppState.addEventListener>[1] | undefined;
const removeListener = jest.fn();

const TestComponent = ({enabled, refresh}: {enabled: boolean; refresh: () => void}) => {
  useAppResumeRefresh(enabled, refresh);
  return <Text>verified member</Text>;
};

describe('useAppResumeRefresh', () => {
  beforeEach(() => {
    appStateChangeHandler = undefined;
    jest.spyOn(AppState, 'addEventListener').mockImplementation(((
      _event: Parameters<typeof AppState.addEventListener>[0],
      handler: Parameters<typeof AppState.addEventListener>[1],
    ) => {
      appStateChangeHandler = handler;
      return {remove: removeListener};
    }) as typeof AppState.addEventListener);
  });

  afterEach(() => {
    jest.restoreAllMocks();
    removeListener.mockClear();
  });

  it('refreshes KYC status when the focused screen resumes after camera/browser handoff', () => {
    const refresh = jest.fn();
    let tree: renderer.ReactTestRenderer;

    act(() => {
      tree = renderer.create(<TestComponent enabled refresh={refresh} />);
    });
    act(() => appStateChangeHandler?.('background'));
    act(() => appStateChangeHandler?.('active'));

    expect(refresh).toHaveBeenCalledTimes(1);
    act(() => tree!.unmount());
    expect(removeListener).toHaveBeenCalledTimes(1);
  });

  it('does not refresh when the screen is not focused or state stays active', () => {
    const refresh = jest.fn();
    let tree: renderer.ReactTestRenderer;

    act(() => {
      tree = renderer.create(<TestComponent enabled={false} refresh={refresh} />);
    });
    act(() => appStateChangeHandler?.('active'));
    act(() => appStateChangeHandler?.('background'));
    act(() => appStateChangeHandler?.('active'));

    expect(refresh).not.toHaveBeenCalled();
    act(() => tree!.unmount());
  });
});
