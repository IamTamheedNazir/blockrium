import {BrcTransfer} from '../types/verification';
import {formatBrcAmount} from './formatBrcAmount';

export const getBrcTransferFeeSummary = (
  transfer: BrcTransfer,
  isSent: boolean,
): string | null => {
  const fee = Number(transfer.fee_amount ?? 0);
  if (!isSent || !Number.isFinite(fee) || fee <= 0) {
    return null;
  }

  return `${formatBrcAmount(transfer.amount)} BRC to member · ${formatBrcAmount(
    fee,
  )} BRC fee`;
};
