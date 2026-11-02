import fonts from '../../assets/fonts';

export type ThemeMode = 'system' | 'dark' | 'light';

export type AppTheme = {
  mode: 'dark' | 'light';
  colors: typeof darkColors;
  gradients: typeof darkGradients;
  spacing: typeof spacing;
  radii: typeof radii;
  typography: typeof typography;
  shadow: typeof shadow;
};

const spacing = {
  xs: 6,
  sm: 10,
  md: 14,
  lg: 18,
  xl: 24,
  xxl: 32,
};

const radii = {
  sm: 8,
  md: 12,
  lg: 18,
  xl: 24,
  pill: 999,
};

const typography = {
  display: fonts.display,
  heading: fonts.heading,
  body: fonts.body,
  bodyMedium: fonts.bodyMedium,
  bodyBold: fonts.bodyBold,
};

const shadow = {
  shadowColor: '#000000',
  shadowOffset: {width: 0, height: 10},
  shadowOpacity: 0.24,
  shadowRadius: 18,
  elevation: 7,
};

const darkColors = {
  background: '#05070A',
  backgroundElevated: '#0A0F16',
  surface: '#0E151F',
  surfaceMuted: '#121B27',
  surfaceStrong: '#192435',
  surfaceGlass: '#111A26',
  primary: '#00D6B0',
  primaryDeep: '#008F7C',
  primarySoft: '#57F2D6',
  accent: '#2F7CFF',
  accentDeep: '#1D4ED8',
  accentWarm: '#F0B90B',
  success: '#18C683',
  warning: '#F0B90B',
  danger: '#FF5A6E',
  text: '#F7FAFF',
  textMuted: '#9BAECD',
  textSoft: '#667892',
  border: 'rgba(151, 166, 195, 0.12)',
  borderStrong: 'rgba(151, 166, 195, 0.22)',
  overlay: 'rgba(5, 7, 10, 0.82)',
  white: '#FFFFFF',
  black: '#000000',
};

const lightColors: typeof darkColors = {
  background: '#F4F8FC',
  backgroundElevated: '#FFFFFF',
  surface: '#FFFFFF',
  surfaceMuted: '#EEF5F7',
  surfaceStrong: '#E3EDF2',
  surfaceGlass: '#F8FBFC',
  primary: '#008E79',
  primaryDeep: '#006B5B',
  primarySoft: '#007865',
  accent: '#2563D9',
  accentDeep: '#1D4ED8',
  accentWarm: '#A96B00',
  success: '#087A50',
  warning: '#A96B00',
  danger: '#C9334A',
  text: '#102338',
  textMuted: '#50647A',
  textSoft: '#708297',
  border: 'rgba(35, 64, 87, 0.14)',
  borderStrong: 'rgba(35, 64, 87, 0.24)',
  overlay: 'rgba(16, 35, 56, 0.42)',
  white: '#FFFFFF',
  black: '#000000',
};

const darkGradients = {
  screen: ['#05070A', '#071019', '#0A1420'],
  accent: ['#00D6B0', '#35C7FF', '#2F7CFF'],
  brand: ['#00E0BC', '#00A58F', '#063F3A'],
  surface: ['rgba(21, 31, 45, 0.98)', 'rgba(10, 15, 22, 0.99)'],
  surfaceRaised: ['rgba(23, 35, 51, 0.98)', 'rgba(12, 18, 27, 0.99)'],
  subtle: ['rgba(0, 214, 176, 0.14)', 'rgba(47, 124, 255, 0.07)'],
};

const lightGradients = {
  screen: ['#F4F8FC', '#F7FBFC', '#EDF5F6'],
  accent: ['#00A98C', '#2389D5', '#2563D9'],
  brand: ['#00B99A', '#008E79', '#075E56'],
  surface: ['#FFFFFF', '#F5FAFB'],
  surfaceRaised: ['#FFFFFF', '#F2F8F9'],
  subtle: ['rgba(0, 142, 121, 0.12)', 'rgba(37, 99, 217, 0.08)'],
};

export const createTheme = (mode: 'dark' | 'light'): AppTheme => ({
  mode,
  colors: mode === 'light' ? lightColors : darkColors,
  gradients: mode === 'light' ? lightGradients : darkGradients,
  spacing,
  radii,
  typography,
  shadow,
});

export const theme = createTheme('dark');

/*
 * Keep this default export stable for the existing component library. New or
 * updated screens should use useAppTheme so a user can change appearance
 * without restarting the app.
 */
export default theme;

/*
 * Legacy shape retained below through the exported dark theme above. This
 * comment intentionally marks the compatibility boundary for older screens.
 */
/*
  colors: {
    background: '#05070A',
    backgroundElevated: '#0A0F16',
    surface: '#0E151F',
    surfaceMuted: '#121B27',
    surfaceStrong: '#192435',
    surfaceGlass: '#111A26',
    primary: '#00D6B0',
    primaryDeep: '#008F7C',
    primarySoft: '#57F2D6',
    accent: '#2F7CFF',
    accentDeep: '#1D4ED8',
    accentWarm: '#F0B90B',
    success: '#18C683',
    warning: '#F0B90B',
    danger: '#FF5A6E',
    text: '#F7FAFF',
    textMuted: '#9BAECD',
    textSoft: '#667892',
    border: 'rgba(151, 166, 195, 0.12)',
    borderStrong: 'rgba(151, 166, 195, 0.22)',
    overlay: 'rgba(5, 7, 10, 0.82)',
    white: '#FFFFFF',
    black: '#000000',
  },
  gradients: {
    screen: ['#05070A', '#071019', '#0A1420'],
    accent: ['#00D6B0', '#35C7FF', '#2F7CFF'],
    brand: ['#00E0BC', '#00A58F', '#063F3A'],
    surface: ['rgba(21, 31, 45, 0.98)', 'rgba(10, 15, 22, 0.99)'],
    surfaceRaised: ['rgba(23, 35, 51, 0.98)', 'rgba(12, 18, 27, 0.99)'],
    subtle: ['rgba(0, 214, 176, 0.14)', 'rgba(47, 124, 255, 0.07)'],
  },
  spacing: {
    xs: 6,
    sm: 10,
    md: 14,
    lg: 18,
    xl: 24,
    xxl: 32,
  },
  radii: {
    sm: 8,
    md: 12,
    lg: 18,
    xl: 24,
    pill: 999,
  },
  typography: {
    display: fonts.display,
    heading: fonts.heading,
    body: fonts.body,
    bodyMedium: fonts.bodyMedium,
    bodyBold: fonts.bodyBold,
  },
  shadow: {
    shadowColor: '#000000',
    shadowOffset: {width: 0, height: 10},
    shadowOpacity: 0.24,
    shadowRadius: 18,
    elevation: 7,
  },
};
*/
export const alpha = (hex: string, opacity: number) => {
  const sanitized = hex.replace('#', '');
  const safeHex =
    sanitized.length === 3
      ? sanitized
          .split('')
          .map(char => `${char}${char}`)
          .join('')
      : sanitized;

  const alphaValue = Math.round(Math.min(Math.max(opacity, 0), 1) * 255)
    .toString(16)
    .padStart(2, '0');

  return `#${safeHex}${alphaValue}`;
};
