const mockMessagingInstance: any = {
  getToken: jest.fn().mockResolvedValue('fcm-token'),
  getInitialNotification: jest.fn().mockResolvedValue(null),
  onNotificationOpenedApp: jest.fn(listener => {
    mockMessagingInstance.openedHandler = listener;
    return jest.fn();
  }),
  onMessage: jest.fn(listener => {
    mockMessagingInstance.foregroundHandler = listener;
    return jest.fn();
  }),
  openedHandler: undefined,
  foregroundHandler: undefined,
};

jest.mock('@react-native-firebase/messaging', () => ({
  __esModule: true,
  default: jest.fn(() => mockMessagingInstance),
}));

jest.mock('../source/utils/brcRewardNotifications', () => ({
  normalizeBrcRewardNotification: jest.fn(() => null),
  shouldHandleBrcRewardNotification: jest.fn(),
  showBrcRewardDialog: jest.fn(),
  openBrcRewardWallet: jest.fn(),
}));

jest.mock('../source/services/blockriumNotificationService', () => ({
  openBlockriumNotificationFromPayload: jest.fn(),
}));

import {initializeFirebaseMessagingSafely} from '../source/services/firebaseMessagingService';

describe('Firebase notification routing', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockMessagingInstance.openedHandler = undefined;
    mockMessagingInstance.foregroundHandler = undefined;
  });

  it('routes KYC invitation taps using the FCM data payload', async () => {
    initializeFirebaseMessagingSafely();
    const payload = {
      data: {
        app_message_type: 'verification_invitation',
      },
    };

    await mockMessagingInstance.openedHandler(payload);

    const {openBlockriumNotificationFromPayload} = require('../source/services/blockriumNotificationService');
    expect(openBlockriumNotificationFromPayload).toHaveBeenCalledWith(payload.data);
  });
});
