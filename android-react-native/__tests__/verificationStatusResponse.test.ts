import {parseVerificationStatusResponse} from '../source/utils/verificationStatusResponse';

describe('verification status response parsing', () => {
  it('returns an error for API failures that were returned instead of thrown', () => {
    expect(parseVerificationStatusResponse({success: false, error: 'Service unavailable'})).toEqual({
      status: null,
      error: 'Service unavailable',
    });
  });

  it('unwraps the Laravel response envelope', () => {
    const status = {is_verified: true, kyc_status: 'approved', payment_status: 'paid'};

    expect(parseVerificationStatusResponse({
      success: true,
      data: {success: true, data: status},
    })).toEqual({status, error: null});
  });

  it('rejects malformed status payloads rather than treating them as an unverified zero-balance account', () => {
    expect(parseVerificationStatusResponse({success: true, data: {success: true, data: {}}})).toEqual({
      status: null,
      error: 'Wallet status is unavailable. Please retry.',
    });
  });
});
