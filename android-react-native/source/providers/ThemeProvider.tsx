import AsyncStorage from '@react-native-async-storage/async-storage';
import React, {
  createContext,
  useContext,
  useEffect,
  useMemo,
} from 'react';
import {AppTheme, createTheme, ThemeMode} from '../design/theme';

const THEME_MODE_KEY = '@blockrium/theme-mode';

type ThemeContextValue = {
  mode: ThemeMode;
  resolvedMode: 'dark' | 'light';
  theme: AppTheme;
  setMode: (mode: ThemeMode) => Promise<void>;
};

const ThemeContext = createContext<ThemeContextValue | undefined>(undefined);

const DARK_MODE: ThemeMode = 'dark';

export const ThemeProvider: React.FC<{children: React.ReactNode}> = ({
  children,
}) => {
  useEffect(() => {
    let active = true;
    AsyncStorage.getItem(THEME_MODE_KEY)
      .then(() => {
        // Remove stale light/system preferences so older installs cannot
        // re-enable the unfinished light theme on a later launch.
        if (active) {
          return AsyncStorage.setItem(THEME_MODE_KEY, DARK_MODE);
        }

        return undefined;
      })
      .catch(error => {
        if (__DEV__) {
          console.log('Theme preference unavailable', error);
        }
      });

    return () => {
      active = false;
    };
  }, []);

  const resolvedMode = 'dark' as const;
  const value = useMemo<ThemeContextValue>(
    () => ({
      mode: DARK_MODE,
      resolvedMode,
      theme: createTheme(resolvedMode),
      setMode: async () => {
        try {
          await AsyncStorage.setItem(THEME_MODE_KEY, DARK_MODE);
        } catch (error) {
          if (__DEV__) {
            console.log('Unable to save theme preference', error);
          }
        }
      },
    }),
    [resolvedMode],
  );

  return (
    <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>
  );
};

export const useAppTheme = () => {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useAppTheme must be used within ThemeProvider');
  }

  return context;
};

export default ThemeProvider;
