export const formatBrcAmount = (value: string | number): string => {
  const raw = String(value).trim();
  const match = /^(-?)(\d+)(?:\.(\d{1,8}))?$/.exec(raw);

  if (!match) {
    const numeric = Number(value);
    return Number.isFinite(numeric)
      ? numeric.toLocaleString(undefined, {maximumFractionDigits: 8})
      : raw;
  }

  const sign = match[1];
  const whole = match[2].replace(/^0+(?=\d)/, '');
  const groupedWhole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  const fraction = (match[3] ?? '').replace(/0+$/, '');

  return `${sign}${groupedWhole}${fraction ? `.${fraction}` : ''}`;
};
