import {getBrcMemberAccessCopy} from '../source/utils/verifiedMemberAccessCopy';

describe('BRC member access copy', () => {
  it('does not ask a suspended paid member to pay again', () => {
    expect(getBrcMemberAccessCopy(true)).toEqual({
      title: 'Verified-member access suspended',
      message:
        'Your KYC approval and payment remain recorded. Contact Blockrium Support for assistance.',
    });
  });

  it('explains the separate KYC and one-time membership payment steps for other locked accounts', () => {
    expect(getBrcMemberAccessCopy(false)).toEqual({
      title: 'Verified members only',
      message:
        'Complete KYC first. After approval, the one-time Blockrium Verified membership payment unlocks BRC transfers.',
    });
  });
});
