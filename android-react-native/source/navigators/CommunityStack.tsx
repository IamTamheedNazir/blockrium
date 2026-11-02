import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';

const Stack = createNativeStackNavigator();

const CommunityStack = () => (
  <Stack.Navigator
    initialRouteName="CommunityFlow"
    screenOptions={{
      headerShown: false,
      animation: 'slide_from_right',
      gestureEnabled: true,
    }}>
    <Stack.Screen
      name="CommunityFlow"
      getComponent={() => require('../organisms/HomeOrg/Community').default}
    />
    <Stack.Screen
      name="LeaderBoardFlow"
      getComponent={() => require('../pages/LeaderBoardFlow').default}
    />
    <Stack.Screen
      name="Squads"
      getComponent={() => require('../pages/Squads').default}
    />
  </Stack.Navigator>
);

export default CommunityStack;
