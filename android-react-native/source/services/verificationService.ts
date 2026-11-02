import {getApi, postApi, postFormApi} from '../storage/server';
import {
  BrcTransfer,
  VerificationActionPayload,
  VerificationStatus,
} from '../types/verification';

export const BRC_WALLET_REQUEST_TIMEOUT_MS = 8000;

const unwrap = <T>(response: any): T | undefined => {
  if (!response?.success || response?.data?.status === 0) {
    return undefined;
  }

  return response?.data?.data ?? response?.data;
};

export const getVerificationStatus = async () => {
  const response = await getApi('auth/verification/status', {
    timeout: BRC_WALLET_REQUEST_TIMEOUT_MS,
  });
  return {
    response,
    data: unwrap<VerificationStatus>(response),
  };
};

export const joinVerificationWaitlist = async (
  countryCode: string,
  countryName: string,
) => {
  const response = await postApi('auth/verification/waitlist', {
    country_code: countryCode,
    country_name: countryName,
  });

  return {
    response,
    data: unwrap<VerificationStatus['waitlist']>(response),
  };
};

export const startVerification = async () => {
  const response = await postApi('auth/verification/start', {});
  return {
    response,
    data: unwrap<VerificationActionPayload>(response),
  };
};

export const retryVerification = async () => {
  const response = await postApi('auth/verification/retry', {});
  return {
    response,
    data: unwrap<VerificationActionPayload>(response),
  };
};

export const submitPaymentSupport = async (payload: {
  payment_id?: number;
  provider_reference?: string;
  provider_account_email?: string;
  message?: string;
  receipt?: {uri: string; name?: string; type?: string} | null;
}) => {
  let response;
  if (payload.receipt) {
    const form = new FormData();
    if (payload.payment_id) form.append('payment_id', String(payload.payment_id));
    if (payload.provider_reference) form.append('provider_reference', payload.provider_reference);
    if (payload.provider_account_email) form.append('provider_account_email', payload.provider_account_email);
    if (payload.message) form.append('message', payload.message);
    form.append('receipt', {
      uri: payload.receipt.uri,
      name: payload.receipt.name || 'google-play-receipt.jpg',
      type: payload.receipt.type || 'image/jpeg',
    } as any);
    response = await postFormApi('auth/verification/payment/support', form);
  } else {
    const {receipt: _receipt, ...fields} = payload;
    response = await postApi('auth/verification/payment/support', fields);
  }
  return {
    response,
    data: unwrap<any>(response),
  };
};

export const replyToPaymentSupport = async (caseId: number, message: string) => {
  const response = await postApi(`auth/verification/payment/support/${caseId}/messages`, {message});
  return {
    response,
    data: unwrap<any>(response),
  };
};

export const createBrcUsername = async (username: string) => {
  const response = await postApi('auth/verification/username', {username});
  return {
    response,
    data: unwrap<VerificationStatus>(response),
  };
};

export const checkBrcUsernameAvailability = async (username: string) => {
  const response = await postApi('auth/verification/username/availability', {
    username,
  });

  return {
    response,
    data: Boolean(response.success && response.data?.status === 1 && response.data?.available),
  };
};

export const transferBrc = async (payload: {
  recipient_username: string;
  amount: string;
  idempotency_key: string;
  note?: string;
}) => {
  const response = await postApi('auth/brc/transfers', payload);
  return {
    response,
    data: unwrap<BrcTransfer>(response),
  };
};

export const getBrcTransfers = async (
  limit = 25,
  options: {signal?: AbortSignal; timeout?: number} = {},
) => {
  const response = await getApi(
    `auth/brc/transfers?limit=${Math.min(100, Math.max(1, limit))}`,
    {
      signal: options.signal,
      timeout: options.timeout ?? BRC_WALLET_REQUEST_TIMEOUT_MS,
    },
  );
  const data = unwrap<unknown>(response);
  return {
    response,
    data: Array.isArray(data) ? (data as BrcTransfer[]) : [],
  };
};
