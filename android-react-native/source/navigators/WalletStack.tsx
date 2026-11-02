import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import type {WalletStackParamList} from '../pages/WalletFlow/WalletScreens';
import WalletBrcTransferScreen from '../pages/WalletFlow/WalletBrcTransferScreen';
import BrcWalletHomeScreen from '../pages/WalletFlow/BrcWalletHomeScreen';

const Stack = createNativeStackNavigator<WalletStackParamList>();

const WalletStack = () => {
  return (
    <Stack.Navigator
      initialRouteName="BrcWalletHome"
      screenOptions={{
        headerShown: false,
        animation: 'slide_from_right',
        gestureEnabled: true,
      }}>
      <Stack.Screen name="BrcWalletHome" component={BrcWalletHomeScreen} />
      <Stack.Screen name="WalletBrcTransfer" component={WalletBrcTransferScreen} />
      <Stack.Screen
        name="BlockchainWallet"
        getComponent={() => require('./WalletBlockchainStack').default}
      />
    </Stack.Navigator>
  );
};

export default WalletStack;
