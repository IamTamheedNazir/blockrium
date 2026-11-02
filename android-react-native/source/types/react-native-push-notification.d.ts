declare module 'react-native-push-notification' {
  type NotificationPermission = 'granted' | 'denied' | 'never_ask_again';

  type LocalNotificationPayload = {
    id?: string;
    channelId?: string;
    date?: Date;
    allowWhileIdle?: boolean;
    title?: string;
    message: string;
    userInfo?: Record<string, unknown>;
  };

  type PushNotificationObject = {
    data?: Record<string, unknown>;
    userInfo?: Record<string, unknown>;
  };

  const PushNotification: {
    configure(options: {
      onNotification?: (notification: PushNotificationObject) => void;
      requestPermissions?: boolean;
      popInitialNotification?: boolean;
    }): void;
    createChannel(
      options: {
        channelId: string;
        channelName: string;
        channelDescription?: string;
        importance?: number;
        vibrate?: boolean;
        playSound?: boolean;
        soundName?: string;
      },
      callback?: (created: boolean) => void,
    ): void;
    cancelLocalNotifications(options: {id: string}): void;
    localNotificationSchedule(options: LocalNotificationPayload): void;
    requestPermissions(): Promise<Record<string, NotificationPermission>> | void;
  };

  export default PushNotification;
}
