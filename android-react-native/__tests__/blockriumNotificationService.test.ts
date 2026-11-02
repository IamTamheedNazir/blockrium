jest.mock('../source/utils/helper', () => ({
  navigate: jest.fn(),
  navigationRef: {current: {isReady: jest.fn(() => true)}},
}));

import {
  BLOCKRIUM_NOTIFICATION_EVENTS,
  openBlockriumNotificationFromPayload,
} from '../source/services/blockriumNotificationService';
const {navigate: mockNavigate} = require('../source/utils/helper');

describe('Blockrium notification payload routing', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('routes Firebase app_message_type verification invitations to KYC', () => {
    openBlockriumNotificationFromPayload({
      app_message_type: 'verification_invitation',
    });

    expect(mockNavigate).toHaveBeenCalledWith('VerifiedMember');
  });

  it('describes the separate membership payment without calling it a KYC fee', () => {
    const reminder = BLOCKRIUM_NOTIFICATION_EVENTS.kyc_payment_reminder;

    expect(reminder.title).toContain('membership payment pending');
    expect(reminder.message).toContain('Your KYC is complete');
    expect(reminder.message).not.toContain('KYC fee');
  });
});
