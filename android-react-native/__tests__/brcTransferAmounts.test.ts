import {
  calculateBrcTransferQuote,
  canCoverBrcTransfer,
} from '../source/utils/brcTransferAmounts';

describe('BRC transfer fee quotes', () => {
  it('adds 2% on top and leaves the recipient amount unchanged', () => {
    expect(calculateBrcTransferQuote('10')).toEqual({
      amount: '10.00000000',
      fee: '0.20000000',
      totalDebit: '10.20000000',
    });
  });

  it('requires amounts in 0.00000050 BRC steps so the fee is exactly 2%', () => {
    expect(calculateBrcTransferQuote('0.00001234')).toBeNull();
    expect(calculateBrcTransferQuote('0.00001250')).toEqual({
      amount: '0.00001250',
      fee: '0.00000025',
      totalDebit: '0.00001275',
    });
  });

  it('rejects amounts that cannot represent an exact 2% fee', () => {
    expect(calculateBrcTransferQuote('0.00000075')).toBeNull();
  });

  it('charges a representable 2% fee at the minimum transfer amount', () => {
    expect(calculateBrcTransferQuote('0.00000050')).toEqual({
      amount: '0.00000050',
      fee: '0.00000001',
      totalDebit: '0.00000051',
    });
  });

  it('rejects amounts below the minimum transfer amount', () => {
    expect(calculateBrcTransferQuote('0.00000049')).toBeNull();
    expect(calculateBrcTransferQuote('0.00000001')).toBeNull();
  });

  it('rejects malformed, zero, and over-limit amounts', () => {
    expect(calculateBrcTransferQuote('0')).toBeNull();
    expect(calculateBrcTransferQuote('1.000000001')).toBeNull();
    expect(calculateBrcTransferQuote('1000000.00000001')).toBeNull();
  });

  it('checks the full debit, not just the recipient amount', () => {
    const quote = calculateBrcTransferQuote('10');
    expect(quote).not.toBeNull();
    expect(canCoverBrcTransfer('10.19999999', quote!)).toBe(false);
    expect(canCoverBrcTransfer('10.20000000', quote!)).toBe(true);
  });
});
