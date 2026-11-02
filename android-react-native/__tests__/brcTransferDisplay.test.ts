import {getBrcTransferFeeSummary} from '../source/utils/brcTransferDisplay';

const transfer = {
  id: 1,
  amount: '10.00000000',
  fee_amount: '0.20000000',
  total_debit: '10.20000000',
  status: 'completed',
};

describe('BRC transfer activity fee summary', () => {
  it('explains the recipient amount and fee for outgoing transfers', () => {
    expect(getBrcTransferFeeSummary(transfer, true)).toBe(
      '10 BRC to member · 0.2 BRC fee',
    );
  });

  it('does not show sender fees on incoming transfers', () => {
    expect(getBrcTransferFeeSummary(transfer, false)).toBeNull();
  });

  it('hides fee details for legacy zero-fee transfers', () => {
    expect(
      getBrcTransferFeeSummary({...transfer, fee_amount: '0.00000000'}, true),
    ).toBeNull();
  });
});
