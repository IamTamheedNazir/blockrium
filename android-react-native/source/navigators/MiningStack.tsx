import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';

const Stack = createNativeStackNavigator();

const MiningStack = () => {
  return (
    <Stack.Navigator
      initialRouteName="MiningFlow"
      screenOptions={{
        headerShown: false,
        animation: 'slide_from_right',
        gestureEnabled: true,
      }}>
      <Stack.Screen
        name="MiningFlow"
        getComponent={() => require('../pages/MiningFlow').default}
      />
    </Stack.Navigator>
  );
};

export default MiningStack;
