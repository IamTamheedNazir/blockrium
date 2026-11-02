export const getBrcMemberAccessCopy = (accessSuspended: boolean) =>
  accessSuspended
    ? {
        title: 'Verified-member access suspended',
        message:
          'Your KYC approval and payment remain recorded. Contact Blockrium Support for assistance.',
      }
    : {
        title: 'Verified members only',
        message:
          'Complete KYC first. After approval, the one-time Blockrium Verified membership payment unlocks BRC transfers.',
      };
