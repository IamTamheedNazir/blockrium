import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';

const Stack = createNativeStackNavigator();

const AuthStack = () => {
  return (
    <Stack.Navigator
      screenOptions={{
        headerShown: false,
        animation: 'slide_from_right',
        gestureEnabled: true,
      }}>
      <Stack.Screen
        name="Login"
        getComponent={() => require('../pages/AuthFlow/Login').default}
      />
      <Stack.Screen
        name="SignUp"
        getComponent={() => require('../pages/AuthFlow/SignUp').default}
      />
      <Stack.Screen
        name="ForgotPassword"
        getComponent={() => require('../pages/AuthFlow/ForgotPassword').default}
      />
    </Stack.Navigator>
  );
};

export default AuthStack;
