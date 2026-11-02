import React from 'react';
import {createBottomTabNavigator} from '@react-navigation/bottom-tabs';
import type {WalletRewardRouteParams} from '../pages/WalletFlow/WalletScreens';
import {
  WalletBrowserScreen,
  WalletHomeScreen,
  WalletModeSettingsScreen,
  WalletPayScreen,
  WalletSwapScreen,
} from '../pages/WalletFlow/WalletScreens';

export type WalletMainTabsParamList = {
  WalletHome: WalletRewardRouteParams | undefined;
  WalletSwap: undefined;
  WalletPay: undefined;
  WalletBrowser: undefined;
  WalletModeSettings: undefined;
};

const Tab = createBottomTabNavigator<WalletMainTabsParamList>();

const WalletMainTabs = ({route}: any) => {
  const walletHomeParams = route?.params?.params ?? route?.params;

  return (
    <Tab.Navigator
      initialRouteName="WalletHome"
      tabBar={() => null}
      screenOptions={{
        headerShown: false,
        lazy: true,
      }}>
      <Tab.Screen
        name="WalletHome"
        component={WalletHomeScreen}
        initialParams={walletHomeParams}
        options={{unmountOnBlur: false}}
      />
      <Tab.Screen
        name="WalletSwap"
        component={WalletSwapScreen}
        options={{unmountOnBlur: true}}
      />
      <Tab.Screen
        name="WalletPay"
        component={WalletPayScreen}
        options={{unmountOnBlur: true}}
      />
      <Tab.Screen
        name="WalletBrowser"
        component={WalletBrowserScreen}
        options={{unmountOnBlur: true}}
      />
      <Tab.Screen
        name="WalletModeSettings"
        component={WalletModeSettingsScreen}
        options={{unmountOnBlur: true}}
      />
    </Tab.Navigator>
  );
};

export default WalletMainTabs;
