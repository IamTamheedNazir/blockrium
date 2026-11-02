import {formatBrcAmount} from '../source/utils/formatBrcAmount';

describe('formatBrcAmount', () => {
  it('preserves small fractions on large transfer amounts', () => {
    expect(formatBrcAmount('123456789.00000001')).toBe('123,456,789.00000001');
  });

  it('groups whole numbers and trims insignificant decimal zeros', () => {
    expect(formatBrcAmount('12000.50000000')).toBe('12,000.5');
  });

  it('formats number inputs and leaves invalid values readable', () => {
    expect(formatBrcAmount(1200.5)).toBe('1,200.5');
    expect(formatBrcAmount('not-an-amount')).toBe('not-an-amount');
  });
});
