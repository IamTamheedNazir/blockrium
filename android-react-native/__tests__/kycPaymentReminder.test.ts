import {hasUnpaidApprovedVerification} from '../source/utils/verification';

describe('hasUnpaidApprovedVerification', () => {
  it('shows the payment prompt only after approval and before payment', () => {
    expect(
      hasUnpaidApprovedVerification({
        is_verified: false,
        kyc_status: 'approved',
        payment_status: 'pending',
      }),
    ).toBe(true);
    expect(
      hasUnpaidApprovedVerification({
        is_verified: false,
        kyc_status: 'approved',
        payment_status: 'failed',
      }),
    ).toBe(true);
  });

  it('never shows for unapproved, paid, or already verified users', () => {
    expect(
      hasUnpaidApprovedVerification({
        is_verified: false,
        kyc_status: 'in_review',
        payment_status: 'pending',
      }),
    ).toBe(false);
    expect(
      hasUnpaidApprovedVerification({
        is_verified: false,
        kyc_status: 'approved',
        payment_status: 'paid',
      }),
    ).toBe(false);
    expect(
      hasUnpaidApprovedVerification({
        is_verified: true,
        kyc_status: 'approved',
        payment_status: 'pending',
      }),
    ).toBe(false);
    expect(hasUnpaidApprovedVerification(null)).toBe(false);
  });
});
