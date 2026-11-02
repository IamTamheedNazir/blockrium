import {VerificationStatus} from '../types/verification';

export const hasUnpaidApprovedVerification = (
  status?: Partial<VerificationStatus> | null,
) =>
  Boolean(
    status &&
      !status.is_verified &&
      status.kyc_status === 'approved' &&
      status.payment_status !== 'paid',
  );
