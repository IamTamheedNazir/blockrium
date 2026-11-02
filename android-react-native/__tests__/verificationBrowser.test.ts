import {Linking} from 'react-native';
import InAppBrowser from 'react-native-inappbrowser-reborn';
import {openHostedVerification} from '../source/services/verificationBrowser';

jest.mock('react-native-inappbrowser-reborn', () => ({
  isAvailable: jest.fn(),
  open: jest.fn(),
}));

describe('openHostedVerification', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    jest.spyOn(Linking, 'openURL').mockResolvedValue(undefined as never);
    (InAppBrowser.isAvailable as jest.Mock).mockResolvedValue(true);
    (InAppBrowser.open as jest.Mock).mockResolvedValue({type: 'dismiss'});
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  it('opens Android hosted KYC in the system browser for camera handoff', async () => {
    await openHostedVerification('https://verify.example/session', 'android');

    expect(Linking.openURL).toHaveBeenCalledWith('https://verify.example/session');
    expect(InAppBrowser.open).not.toHaveBeenCalled();
  });

  it('retains the in-app browser flow on iOS when available', async () => {
    await openHostedVerification('https://verify.example/session', 'ios');

    expect(InAppBrowser.open).toHaveBeenCalledWith(
      'https://verify.example/session',
      expect.objectContaining({showTitle: true}),
    );
    expect(Linking.openURL).not.toHaveBeenCalled();
  });
});
