import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import LeaderBoardFlow from '../pages/LeaderBoardFlow';

const LeaderBoardStack = () => {
  const Stack = createNativeStackNavigator();

  return (
    <Stack.Navigator
      screenOptions={{
        headerShown: false,
        animation: 'slide_from_right',
        gestureEnabled: true,
      }}>
      <Stack.Screen
        name="LeaderBoardFlow"
        component={LeaderBoardFlow}
      />
    </Stack.Navigator>
  );
};

export default LeaderBoardStack;
