import {getProfileVerificationBadges} from '../source/utils/profile';

describe('profile verification badges', () => {
  it('shows only the KYC badge for an approved user with payment pending', () => {
    expect(
      getProfileVerificationBadges({
        kyc_status: 'approved',
        payment_status: 'pending',
        is_verified: false,
      }),
    ).toEqual({kycVerified: true, blockriumVerified: false});
  });

  it('shows both badges for a paid, verified member', () => {
    expect(
      getProfileVerificationBadges({
        kyc_status: 'approved',
        payment_status: 'paid',
        is_verified: true,
      }),
    ).toEqual({kycVerified: true, blockriumVerified: true});
  });

  it('keeps the KYC badge but removes member access when a paid account is suspended', () => {
    expect(
      getProfileVerificationBadges({
        kyc_status: 'approved',
        payment_status: 'paid',
        is_verified: false,
        access_suspended: true,
      }),
    ).toEqual({kycVerified: true, blockriumVerified: false});
  });

  it('never grants a Blockrium badge without approved KYC', () => {
    expect(
      getProfileVerificationBadges({
        kyc_status: 'in_review',
        payment_status: 'paid',
        is_verified: true,
      }),
    ).toEqual({kycVerified: false, blockriumVerified: false});
  });

  it('does not show either badge when the server has no verification status', () => {
    expect(getProfileVerificationBadges(undefined)).toEqual({
      kycVerified: false,
      blockriumVerified: false,
    });
  });
});
