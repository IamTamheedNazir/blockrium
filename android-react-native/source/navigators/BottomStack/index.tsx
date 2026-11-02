import React from 'react';
import {createBottomTabNavigator} from '@react-navigation/bottom-tabs';
import colors from '../../utils/colors';
import CustomBottomTabs from '../../molecules/CustomBottomTabs';

const Bottom = createBottomTabNavigator();

const BottomStack = () => {
  return (
    <Bottom.Navigator
      tabBar={props => <CustomBottomTabs {...props} />}
      detachInactiveScreens
      screenOptions={{
        lazy: true,
        unmountOnBlur: false,
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.grey,
        headerShown: false,
        tabBarStyle: {
          position: 'absolute',
          backgroundColor: 'transparent',
          borderTopWidth: 0,
          elevation: 0,
        },
      }}
      initialRouteName="Home">
      <Bottom.Screen
        name="Home"
        getComponent={() => require('../HomeStack').default}
      />
      <Bottom.Screen
        name="Mining"
        getComponent={() => require('../MiningStack').default}
      />
      <Bottom.Screen
        name="Wallet"
        getComponent={() => require('../WalletStack').default}
      />
      <Bottom.Screen
        name="Community"
        getComponent={() => require('../CommunityStack').default}
      />
      <Bottom.Screen
        name="Profile"
        getComponent={() => require('../ProfileStack').default}
      />
    </Bottom.Navigator>
  );
};

export default BottomStack;
