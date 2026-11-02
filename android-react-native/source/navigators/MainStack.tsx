import React, {useEffect, useRef, useState} from 'react';
import {Alert, AppState, AppStateStatus, LogBox} from 'react-native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import {getStorageToken} from '../utils/mmkvStorage';
import {attachLeadsTokenInHeader} from '../storage/server';
import {getApi} from '../storage/server';
import {ScreenLoader} from '../design/primitives';

LogBox.ignoreLogs(['Setting a timer']);

const Stack = createNativeStackNavigator();

const screenOptions = {
  headerShown: false,
  animation: 'slide_from_right' as const,
  gestureEnabled: true,
};

const MainStack = () => {
  const [token, setToken] = useState<string | undefined>();
  const [isBooting, setIsBooting] = useState(true);
  const appStateRef = useRef<AppStateStatus>(AppState.currentState);
  const maintenanceShownRef = useRef(false);

  useEffect(() => {
    let isMounted = true;

    const hydrateToken = async () => {
      try {
        const storedToken = await getStorageToken('authToken');
        if (isMounted) {
          setToken(storedToken);
        }
      } catch (error) {
        console.log('Token bootstrap error', error);
      } finally {
        if (isMounted) {
          setIsBooting(false);
        }
      }
    };

    hydrateToken();

    return () => {
      isMounted = false;
    };
  }, []);

  useEffect(() => {
    if (token) {
      attachLeadsTokenInHeader(token);
    }
  }, [token]);

  useEffect(() => {
    const checkBackendStatus = async () => {
      try {
        const [maintenanceResponse] = await Promise.all([
          getApi('app-maintenance'),
        ]);

        if (
          maintenanceResponse?.success &&
          maintenanceResponse?.data?.app_status
        ) {
          const maintenanceDescription =
            maintenanceResponse?.data?.app_maintenance?.description?.trim?.() ||
            'Blockrium is temporarily under maintenance. Please try again shortly.';

          if (!maintenanceShownRef.current) {
            maintenanceShownRef.current = true;
            Alert.alert('Maintenance', maintenanceDescription, [{text: 'OK'}], {
              cancelable: false,
            });
          }
        } else {
          maintenanceShownRef.current = false;
        }
      } catch (backendError) {
        console.log('Backend status check error', backendError);
      }
    };

    let unsubscribeNetInfo: undefined | (() => void);

    try {
      const NetInfo = require('@react-native-community/netinfo').default;
      unsubscribeNetInfo = NetInfo.addEventListener(
        (state: {isConnected?: boolean | null}) => {
          if (state.isConnected) {
            checkBackendStatus();
          }
        },
      );
    } catch (error) {
      console.log('NetInfo backend watcher unavailable', error);
    }

    const appStateSubscription = AppState.addEventListener(
      'change',
      nextState => {
        if (
          appStateRef.current.match(/inactive|background/) &&
          nextState === 'active'
        ) {
          checkBackendStatus();
        }

        appStateRef.current = nextState;
      },
    );

    checkBackendStatus();

    return () => {
      unsubscribeNetInfo?.();
      appStateSubscription.remove();
    };
  }, []);

  if (isBooting) {
    return (
      <ScreenLoader
        title="Restoring your session"
        subtitle="Checking token state and preparing the navigation stack."
      />
    );
  }

  return (
    <Stack.Navigator
      initialRouteName={token ? 'BottomStack' : 'Splash'}
      screenOptions={screenOptions}>
      <Stack.Screen
        name="BottomStack"
        getComponent={() => require('./BottomStack').default}
      />
      <Stack.Screen
        name="AuthStack"
        getComponent={() => require('./AuthStack').default}
      />
      <Stack.Screen
        name="MiningStack"
        getComponent={() => require('./MiningStack').default}
      />
      <Stack.Screen
        name="AppNotifications"
        getComponent={() => require('../molecules/Notifications').default}
      />
      <Stack.Screen
        name="AppSearch"
        getComponent={() => require('../pages/AppSearch').default}
      />
      <Stack.Screen
        name="AppSettings"
        getComponent={() => require('../pages/AppSettings').default}
      />
      <Stack.Screen
        name="HelpCenter"
        getComponent={() => require('../pages/HelpCenter').default}
      />
      <Stack.Screen
        name="LearnEarn"
        getComponent={() => require('../pages/LearnEarn').default}
      />
      <Stack.Screen
        name="TrustCenter"
        getComponent={() => require('../pages/TrustCenter').default}
      />
      <Stack.Screen
        name="VerifiedMember"
        getComponent={() => require('../pages/VerifiedMember').default}
      />
      <Stack.Screen
        name="PromoCodeRedeem"
        getComponent={() => require('../pages/PromoCodeRedeem').default}
      />
      <Stack.Screen
        name="Squads"
        getComponent={() => require('../pages/Squads').default}
      />
      <Stack.Screen
        name="Splash"
        getComponent={() => require('../organisms/SplashOrg').default}
      />
    </Stack.Navigator>
  );
};

export default MainStack;
