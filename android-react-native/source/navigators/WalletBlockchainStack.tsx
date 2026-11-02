import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import {WalletProvider} from '../pages/WalletFlow/WalletProvider';
import WalletMainTabs from './WalletMainTabs';
import {
  WalletAddTokenScreen,
  WalletBackupPhraseScreen,
  WalletConfirmPhraseScreen,
  WalletFlowScreen,
  WalletHistoryScreen,
  WalletBrowserWebScreen,
  WalletImportScreen,
  WalletNetworksScreen,
  WalletNotificationsScreen,
  WalletOnboardingScreen,
  WalletPasscodeScreen,
  WalletReceiveScreen,
  WalletReadyScreen,
  WalletSendScreen,
  WalletSecretPhraseScreen,
  WalletSettingsScreen,
  WalletTokenDetailsScreen,
  WalletUnlockScreen,
  WalletStackParamList,
} from '../pages/WalletFlow/WalletScreens';

const Stack = createNativeStackNavigator<WalletStackParamList>();

const WalletBlockchainStack = () => (
  <WalletProvider>
    <Stack.Navigator
      initialRouteName="WalletFlow"
      screenOptions={{
        headerShown: false,
        animation: 'slide_from_right',
        gestureEnabled: true,
      }}>
      <Stack.Screen name="WalletFlow" component={WalletFlowScreen} />
      <Stack.Screen name="WalletUnlock" component={WalletUnlockScreen} />
      <Stack.Screen name="WalletOnboarding" component={WalletOnboardingScreen} />
      <Stack.Screen name="WalletBackupPhrase" component={WalletBackupPhraseScreen} />
      <Stack.Screen name="WalletConfirmPhrase" component={WalletConfirmPhraseScreen} />
      <Stack.Screen name="WalletImport" component={WalletImportScreen} />
      <Stack.Screen name="WalletPasscode" component={WalletPasscodeScreen} />
      <Stack.Screen name="WalletNotifications" component={WalletNotificationsScreen} />
      <Stack.Screen name="WalletReady" component={WalletReadyScreen} />
      <Stack.Screen name="WalletMainTabs" component={WalletMainTabs} />
      <Stack.Screen name="WalletBrowserWeb" component={WalletBrowserWebScreen} />
      <Stack.Screen name="WalletTokenDetails" component={WalletTokenDetailsScreen} />
      <Stack.Screen name="WalletSend" component={WalletSendScreen} />
      <Stack.Screen name="WalletReceive" component={WalletReceiveScreen} />
      <Stack.Screen name="WalletAddToken" component={WalletAddTokenScreen} />
      <Stack.Screen name="WalletNetworks" component={WalletNetworksScreen} />
      <Stack.Screen name="WalletSettings" component={WalletSettingsScreen} />
      <Stack.Screen name="WalletSecretPhrase" component={WalletSecretPhraseScreen} />
      <Stack.Screen name="WalletHistory" component={WalletHistoryScreen} />
    </Stack.Navigator>
  </WalletProvider>
);

export default WalletBlockchainStack;
