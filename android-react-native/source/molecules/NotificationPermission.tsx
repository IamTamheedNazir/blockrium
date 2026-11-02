import {useEffect} from 'react';
import {Alert} from 'react-native';
import {requestBlockriumNotificationPermission} from '../services/blockriumNotificationService';

export const NotificationPermission = ({onDone}: {onDone: () => void}) => {
  useEffect(() => {
    const askNotificationPermission = async () => {
      try {
        const granted = await requestBlockriumNotificationPermission();
        if (!granted) {
          Alert.alert('Permission needed', 'Notifications are required.');
        }
      } catch (error) {
        console.log('OneSignal notification permission warning', error);
      }

      onDone();
    };

    askNotificationPermission();
  }, [onDone]);

  return null;
};
