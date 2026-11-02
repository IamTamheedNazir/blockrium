/**
 * @format
 */

import 'react-native-get-random-values';
import '@ethersproject/shims';
import { AppRegistry } from 'react-native';
import App from './App';
import { name as appName } from './app.json';
import {logAppIssue} from './source/services/appIssueLogger';

const isRecoverableNotificationStartupError = error => {
  const message = error?.message ?? String(error ?? '');
  return (
    /getInitialNotification|onNotificationOpenedApp|onMessage|getToken/i.test(message) &&
    /null|undefined|not available/i.test(message)
  );
};

const installGlobalIssueLogger = () => {
  const errorUtils = global.ErrorUtils;

  if (!errorUtils?.getGlobalHandler || !errorUtils?.setGlobalHandler) {
    return;
  }

  const previousHandler = errorUtils.getGlobalHandler();

  errorUtils.setGlobalHandler((error, isFatal) => {
    if (isRecoverableNotificationStartupError(error)) {
      console.log('Recoverable notification startup warning', error?.message);
      return;
    }

    logAppIssue({
      severity: isFatal ? 'fatal' : 'error',
      source: 'javascript',
      action: 'global_error_handler',
      errorCode: error?.name,
      message: error?.message || 'Unhandled JavaScript error',
      metadata: {
        isFatal: Boolean(isFatal),
        stack: error?.stack,
      },
    }).catch(logError => {
      console.log('Unable to send global issue log', logError);
    });

    previousHandler?.(error, isFatal);
  });
};

installGlobalIssueLogger();

if (__DEV__) {
    import('react-native').then(RN => {
      RN.DevSettings.setIsDebuggingRemotely(false);
    });
  }

AppRegistry.registerComponent(appName, () => App);
