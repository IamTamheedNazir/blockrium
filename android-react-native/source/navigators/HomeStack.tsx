import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';

const Stack = createNativeStackNavigator();

const HomeStack = () => {
  return (
    <Stack.Navigator
      initialRouteName="HomeFlow"
      screenOptions={{
        headerShown: false,
        animation: 'slide_from_right',
        gestureEnabled: true,
      }}>
      <Stack.Screen
        name="HomeFlow"
        getComponent={() => require('../pages/HomeFlow').default}
      />
      <Stack.Screen
        name="Tasks"
        getComponent={() => require('../organisms/HomeOrg/Tasks').default}
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
        name="BoostStore"
        getComponent={() => require('../organisms/HomeOrg/BoostStore').default}
      />
      <Stack.Screen
        name="PromoCodeRedeem"
        getComponent={() => require('../pages/PromoCodeRedeem').default}
      />
      <Stack.Screen
        name="Community"
        getComponent={() => require('../organisms/HomeOrg/Community').default}
      />
      <Stack.Screen
        name="Referral"
        getComponent={() => require('../organisms/HomeOrg/Referral').default}
      />
      <Stack.Screen
        name="Notifications"
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
    </Stack.Navigator>
  );
};

export default HomeStack;
