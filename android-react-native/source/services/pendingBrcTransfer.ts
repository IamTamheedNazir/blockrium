import AsyncStorage from '@react-native-async-storage/async-storage';

const storageKey = (userId: number) =>
  `blockrium.brc-transfer.pending.v1.${userId}`;

export type PendingBrcTransfer = {
  recipient_username: string;
  amount: string;
  idempotency_key: string;
  note?: string;
  user_id: number;
};

export const savePendingBrcTransfer = async (intent: PendingBrcTransfer) => {
  await AsyncStorage.setItem(storageKey(intent.user_id), JSON.stringify(intent));
};

export const getPendingBrcTransfer = async (
  userId: number,
): Promise<PendingBrcTransfer | null> => {
  const raw = await AsyncStorage.getItem(storageKey(userId));
  if (!raw) {
    return null;
  }

  try {
    const intent = JSON.parse(raw) as PendingBrcTransfer;
    if (
      intent.user_id !== userId ||
      typeof intent.recipient_username !== 'string' ||
      typeof intent.amount !== 'string' ||
      typeof intent.idempotency_key !== 'string' ||
      !intent.idempotency_key
    ) {
      return null;
    }
    return intent;
  } catch {
    return null;
  }
};

export const clearPendingBrcTransfer = async (
  userId: number,
  idempotencyKey?: string,
) => {
  if (idempotencyKey) {
    const current = await getPendingBrcTransfer(userId);
    if (current?.idempotency_key !== idempotencyKey) {
      return;
    }
  }
  await AsyncStorage.removeItem(storageKey(userId));
};

export const isDefinitiveTransferRejection = (status?: number) =>
  typeof status === 'number' &&
  status >= 400 &&
  status < 500 &&
  status !== 408 &&
  status !== 409 &&
  status !== 429;
