import {Linking, Platform} from 'react-native';
import InAppBrowser from 'react-native-inappbrowser-reborn';
import theme from '../design/theme';

export const openHostedVerification = async (
  url: string,
  platform: string = Platform.OS,
) => {
  // Keep Android's camera/document picker in the browser task that owns the hosted KYC page.
  if (platform === 'android') {
    await Linking.openURL(url);
    return;
  }

  if (await InAppBrowser.isAvailable()) {
    await InAppBrowser.open(url, {
      toolbarColor: theme.colors.background,
      secondaryToolbarColor: theme.colors.surface,
      enableUrlBarHiding: true,
      showTitle: true,
      enableDefaultShare: false,
      forceCloseOnRedirection: false,
    });
    return;
  }

  await Linking.openURL(url);
};
