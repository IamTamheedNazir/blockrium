import React, {useEffect, useRef, useState} from 'react';
import {DarkTheme, NavigationContainer} from '@react-navigation/native';
import {StatusBar, StyleSheet, View} from 'react-native';
import {QueryClient, QueryClientProvider} from 'react-query';
import {GestureHandlerRootView} from 'react-native-gesture-handler';
import {SheetProvider} from 'react-native-actions-sheet';
import {SafeAreaProvider} from 'react-native-safe-area-context';
import MainStack from './source/navigators/MainStack';
import CustomAlert from './source/molecules/CustomAlert';
import OfflineBanner from './source/molecules/OfflineBanner';
import ConsentAlert, {hasDataConsent} from './source/molecules/ConsentAlert';
import {AlertProvider} from './source/hooks/AlertContext';
import {useAlert} from './source/hooks';
import useAlertStore from './source/storage/client/useCustomAlert/alertStore';
import RootErrorBoundary from './source/molecules/RootErrorBoundary';
import {navigationRef} from './source/utils/navigationRef';
import colors from './source/utils/colors';
import {NavigationProgressBar, ScreenLoader} from './source/design/primitives';
import {identifyAdUser, initializeAds} from './source/services/adService';
import {initializeFirebaseMessagingSafely} from './source/services/firebaseMessagingService';
import {
  identifyBlockriumNotificationUser,
  initializeBlockriumNotifications,
} from './source/services/blockriumNotificationService';
import {getStorageId} from './source/utils/mmkvStorage';
import {getCachedUserProfile, hasProfileIdentity} from './source/utils/profile';
import ThemeProvider, {useAppTheme} from './source/providers/ThemeProvider';

const linking = {
  prefixes: ['blockrium://', 'https://blockrium.com'],
  config: {
    screens: {
      BottomStack: {
        screens: {
          Home: {
            screens: {
              HomeFlow: '',
              Tasks: 'tasks',
              Referral: 'referral',
              LearnEarn: 'learn',
              TrustCenter: 'trust-center',
            },
          },
          Mining: {
            screens: {
              MiningFlow: 'mining',
            },
          },
          Wallet: {
            screens: {
              BrcWalletHome: 'wallet',
              BlockchainWallet: {
                path: 'wallet/blockchain',
                screens: {
                  WalletFlow: '',
                  WalletSend: 'send',
                  WalletReceive: 'receive',
                  WalletHistory: 'history',
                },
              },
            },
          },
          Community: {
            screens: {
              CommunityFlow: 'community',
              LeaderBoardFlow: 'community/leaderboard',
              Squads: 'community/squads',
            },
          },
          Profile: {
            screens: {
              ProfileFlow: 'profile',
            },
          },
        },
      },
      AppNotifications: 'notifications',
      AppSearch: 'search',
      AppSettings: 'settings',
      Squads: 'squad/:inviteCode',
    },
  },
};

const DeferredAppUpdatePrompt = () => {
  const [Component, setComponent] = useState<React.ComponentType | null>(null);

  useEffect(() => {
    const timeout = setTimeout(() => {
      try {
        const LoadedComponent =
          require('./source/molecules/AppUpdatePrompt').default;
        setComponent(() => LoadedComponent);
      } catch (error) {
        console.log('App update prompt unavailable', error);
      }
    }, 900);

    return () => clearTimeout(timeout);
  }, []);

  return Component ? <Component /> : null;
};

const DeferredNetworkLogDebugModal = () => {
  const [Component, setComponent] = useState<React.ComponentType | null>(null);

  useEffect(() => {
    if (!__DEV__) {
      return;
    }

    try {
      const LoadedComponent =
        require('./source/molecules/NetworkLogDebugModal').default;
      setComponent(() => LoadedComponent);
    } catch (error) {
      console.log('Network logger modal unavailable', error);
    }
  }, []);

  return Component ? <Component /> : null;
};

const AppContent = () => {
  const {theme: appTheme, resolvedMode} = useAppTheme();
  const navigationTheme = {
    ...DarkTheme,
    colors: {
      ...DarkTheme.colors,
      background: appTheme.colors.background,
      card: appTheme.colors.surface,
      primary: appTheme.colors.primary,
      text: appTheme.colors.text,
      border: appTheme.colors.border,
    },
  };
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 45000,
            cacheTime: 5 * 60 * 1000,
            refetchOnWindowFocus: false,
            refetchOnReconnect: true,
            retry: 1,
          },
          mutations: {
            retry: 0,
          },
        },
      }),
  );
  const [isOffline, setIsOffline] = useState(false);
  const [isAppReady, setIsAppReady] = useState(false);
  const [hasAcceptedDataConsent, setHasAcceptedDataConsent] = useState(false);
  const [isNavigating, setIsNavigating] = useState(false);
  const navigationTimerRef = useRef<ReturnType<typeof setTimeout>>();
  const {
    selectSnack,
    snackTitle,
    timer,
    onCloseSnack,
    setSnackTitle,
    setSnackMode,
    setTimer,
  } = useAlertStore();

  useAlert(
    selectSnack,
    snackTitle,
    timer,
    onCloseSnack,
    setSnackTitle,
    setSnackMode,
    setTimer,
  );

  useEffect(() => {
    return () => {
      if (navigationTimerRef.current) {
        clearTimeout(navigationTimerRef.current);
      }
    };
  }, []);

  useEffect(() => {
    let isMounted = true;

    const bootstrapApp = async () => {
      try {
        const [consentGranted] = await Promise.all([
          hasDataConsent(),
          new Promise(resolve => setTimeout(resolve, 350)),
        ]);

        if (isMounted) {
          setHasAcceptedDataConsent(consentGranted);
        }
      } catch (error) {
        console.log('App bootstrap warning', error);
      } finally {
        if (isMounted) {
          setIsAppReady(true);
        }
      }
    };

    bootstrapApp();

    return () => {
      isMounted = false;
    };
  }, []);

  useEffect(() => {
    const unsubscribe = initializeFirebaseMessagingSafely();

    return unsubscribe;
  }, []);

  useEffect(() => {
    if (!hasAcceptedDataConsent) {
      return;
    }

    const timeout = setTimeout(async () => {
      try {
        initializeBlockriumNotifications();

        const cachedProfile = await getCachedUserProfile();
        if (hasProfileIdentity(cachedProfile)) {
          await identifyBlockriumNotificationUser(cachedProfile);
        }

        identifyAdUser(await getStorageId('userId'));
        await initializeAds();
      } catch (error) {
        if (__DEV__) {
          console.log('Ad preload unavailable', error);
        }
      }
    }, 700);

    return () => clearTimeout(timeout);
  }, [hasAcceptedDataConsent]);

  useEffect(() => {
    if (!__DEV__) {
      return;
    }

    try {
      const {startNetworkLogging} = require('react-native-network-logger');
      startNetworkLogging();
    } catch (error) {
      console.log('Network logging unavailable', error);
    }
  }, []);

  useEffect(() => {
    let unsubscribe: undefined | (() => void);

    try {
      const NetInfo = require('@react-native-community/netinfo').default;
      unsubscribe = NetInfo.addEventListener(
        (state: {isConnected?: boolean | null}) => {
          setIsOffline(state.isConnected === false);
        },
      );
    } catch (error) {
      console.log('NetInfo unavailable', error);
    }

    return () => {
      unsubscribe?.();
    };
  }, []);

  return (
    <QueryClientProvider client={queryClient}>
      <GestureHandlerRootView
        style={[styles.root, {backgroundColor: appTheme.colors.background}]}>
        <SafeAreaProvider>
          <SheetProvider context="global">
            <AlertProvider>
              <StatusBar
                animated
                translucent
                backgroundColor="transparent"
                barStyle={
                  resolvedMode === 'light' ? 'dark-content' : 'light-content'
                }
              />
              <ConsentAlert
                onConsentGranted={() => setHasAcceptedDataConsent(true)}
              />
              {isAppReady ? (
                <NavigationContainer
                  ref={navigationRef}
                  theme={navigationTheme}
                  linking={linking}
                  onStateChange={() => {
                    if (navigationTimerRef.current) {
                      clearTimeout(navigationTimerRef.current);
                    }
                    setIsNavigating(true);
                    navigationTimerRef.current = setTimeout(
                      () => setIsNavigating(false),
                      320,
                    );
                  }}>
                  <View style={styles.navigationShell}>
                    <NavigationProgressBar visible={isNavigating} />
                    <MainStack />
                  </View>
                </NavigationContainer>
              ) : (
                <ScreenLoader
                  title="Loading Blockrium"
                  subtitle="Securing the session and warming up your dashboard."
                />
              )}
              <DeferredAppUpdatePrompt />
              {__DEV__ ? <DeferredNetworkLogDebugModal /> : null}
              <CustomAlert />
            </AlertProvider>
            {isOffline ? (
              <View style={styles.offlineContainer}>
                <OfflineBanner isOffline={isOffline} />
              </View>
            ) : null}
          </SheetProvider>
        </SafeAreaProvider>
      </GestureHandlerRootView>
    </QueryClientProvider>
  );
};

const App = () => (
  <RootErrorBoundary>
    <ThemeProvider>
      <AppContent />
    </ThemeProvider>
  </RootErrorBoundary>
);

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: colors.background,
  },
  offlineContainer: {
    position: 'absolute',
    zIndex: 500,
    bottom: 0,
    left: 0,
    width: '100%',
  },
  navigationShell: {
    flex: 1,
  },
});

export default App;
