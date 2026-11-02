import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';

const Stack = createNativeStackNavigator();

const ProfileStack = () => {
  return (
    <Stack.Navigator
      initialRouteName="ProfileFlow"
      screenOptions={{
        headerShown: false,
        animation: 'slide_from_right',
        gestureEnabled: true,
      }}>
      <Stack.Screen
        name="ProfileFlow"
        getComponent={() => require('../pages/ProfileFlow').default}
      />
      <Stack.Screen
        name="EditScreen"
        getComponent={() => require('../organisms/ProfileOrg/EditScreen').default}
      />
    </Stack.Navigator>
  );
};

export default ProfileStack;
