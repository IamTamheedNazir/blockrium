import {VerificationStatus} from '../types/verification';

type VerificationStatusResult = {
  status: VerificationStatus | null;
  error: string | null;
};

const readError = (value: any): string | null => {
  const message = value?.error ?? value?.message ?? value?.msg;
  return typeof message === 'string' && message.trim() ? message.trim() : null;
};

export const parseVerificationStatusResponse = (response: any): VerificationStatusResult => {
  if (response?.success === false) {
    return {
      status: null,
      error: readError(response) ?? readError(response?.data) ?? 'Wallet status is unavailable. Please retry.',
    };
  }

  const envelope = response?.data ?? response;
  if (envelope?.success === false) {
    return {
      status: null,
      error: readError(envelope) ?? readError(envelope?.data) ?? 'Wallet status is unavailable. Please retry.',
    };
  }

  const payload = envelope?.data ?? envelope;
  if (!payload || typeof payload !== 'object' || typeof payload.is_verified !== 'boolean') {
    return {status: null, error: 'Wallet status is unavailable. Please retry.'};
  }

  return {status: payload as VerificationStatus, error: null};
};
