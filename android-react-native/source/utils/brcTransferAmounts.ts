const SCALE = 100000000;
const MAX_UNITS = 100000000000000;
const MIN_TRANSFER_UNITS = 50;

export type BrcTransferQuote = {
  amount: string;
  fee: string;
  totalDebit: string;
};

const toUnits = (value: string): number | null => {
  const match = /^(0|[1-9]\d*)(?:\.(\d{1,8}))?$/.exec(value.trim());
  if (!match) {
    return null;
  }

  const units =
    Number(match[1]) * SCALE + Number((match[2] ?? '').padEnd(8, '0'));
  return Number.isSafeInteger(units) && units >= MIN_TRANSFER_UNITS && units <= MAX_UNITS
    ? units
    : null;
};

const fromUnits = (units: number) => {
  const whole = Math.floor(units / SCALE);
  const fraction = String(units % SCALE).padStart(8, '0');
  return `${whole}.${fraction}`;
};

export const calculateBrcTransferQuote = (
  amount: string,
): BrcTransferQuote | null => {
  const amountUnits = toUnits(amount);
  if (amountUnits === null) {
    return null;
  }
  if (amountUnits % 50 !== 0) {
    return null;
  }

  const feeUnits = amountUnits / 50;
  return {
    amount: fromUnits(amountUnits),
    fee: fromUnits(feeUnits),
    totalDebit: fromUnits(amountUnits + feeUnits),
  };
};

export const canCoverBrcTransfer = (
  balance: string,
  quote: BrcTransferQuote,
): boolean => {
  const balanceUnits = toUnits(balance);
  const debitUnits = toUnits(quote.totalDebit);
  return balanceUnits !== null && debitUnits !== null && balanceUnits >= debitUnits;
};
