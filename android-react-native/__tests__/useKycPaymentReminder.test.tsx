import React from 'react';
import {AppState, Text} from 'react-native';
import renderer, {act} from 'react-test-renderer';
import {useKycPaymentReminder} from '../source/hooks/useKycPaymentReminder';

describe('useKycPaymentReminder', () => {
  let appStateListener: ((state: any) => void) | undefined;
  let removeListener: jest.Mock;
  let refreshStatus: jest.Mock<Promise<boolean>>;

  beforeEach(() => {
    appStateListener = undefined;
    removeListener = jest.fn();
    refreshStatus = jest.fn().mockResolvedValue(true);
    jest
      .spyOn(AppState, 'addEventListener')
      .mockImplementation((event, listener) => {
        if (event === 'change') {
          appStateListener = listener;
        }
        return {remove: removeListener} as any;
      });
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  it('shows again after resume when a dismissed KYC payment is still pending', async () => {
    let reminder!: ReturnType<typeof useKycPaymentReminder>;
    const Probe = () => {
      reminder = useKycPaymentReminder(true, refreshStatus);
      return <Text>{reminder.visible ? 'visible' : 'hidden'}</Text>;
    };
    let tree!: renderer.ReactTestRenderer;

    act(() => {
      tree = renderer.create(<Probe />);
    });
    expect(tree.root.findByType(Text).props.children).toBe('visible');

    act(() => reminder.dismiss());
    expect(tree.root.findByType(Text).props.children).toBe('hidden');

    act(() => appStateListener?.('background'));
    expect(tree.root.findByType(Text).props.children).toBe('hidden');

    act(() => appStateListener?.('active'));
    expect(tree.root.findByType(Text).props.children).toBe('hidden');
    await act(async () => {
      await Promise.resolve();
    });
    expect(tree.root.findByType(Text).props.children).toBe('visible');

    act(() => tree.unmount());
    expect(removeListener).toHaveBeenCalledTimes(1);
  });

  it('does not show the reminder when the refreshed status says payment is complete', async () => {
    refreshStatus.mockResolvedValue(false);
    let setPending!: (value: boolean) => void;
    let reminder!: ReturnType<typeof useKycPaymentReminder>;
    const Probe = () => {
      const [pending, updatePending] = React.useState(true);
      setPending = updatePending;
      reminder = useKycPaymentReminder(pending, refreshStatus);
      return <Text>{reminder.visible ? 'visible' : 'hidden'}</Text>;
    };
    let tree!: renderer.ReactTestRenderer;

    act(() => {
      tree = renderer.create(<Probe />);
    });
    act(() => reminder.dismiss());
    act(() => setPending(false));
    act(() => appStateListener?.('background'));
    await act(async () => {
      appStateListener?.('active');
      await Promise.resolve();
    });

    expect(tree.root.findByType(Text).props.children).toBe('hidden');
    act(() => tree.unmount());
  });
});
