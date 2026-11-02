import React, {useEffect, useMemo, useState} from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import {useQuery} from 'react-query';
import Ionicons from 'react-native-vector-icons/Ionicons';
import {
  AppScreen,
  GlassCard,
  PrimaryButton,
  ScreenHeader,
  ScreenLoader,
  SecondaryButton,
  SectionEyebrow,
} from '../design/primitives';
import theme from '../design/theme';
import {callQuery} from '../hooks';
import {useThisAlert} from '../hooks/AlertContext';
import {
  fetchNotificationsWithFallback,
  markNotificationAsRead,
} from '../services/notificationInboxService';
import {navigate as appNavigate} from '../utils/helper';
import {
  formatNotificationAction,
  formatNotificationCoin,
  formatNotificationDate,
  formatNotificationType,
  getNotificationAction,
  getNotificationBucket,
  getNotificationIconName,
  normalizeNotificationCount,
  normalizeNotificationList,
} from '../utils/notifications';

const inboxFilters = [
  'All',
  'Reward',
  'Mining',
  'Tasks',
  'Squad',
  'Streak',
  'Boost',
  'Referral',
  'System',
];

const Notifications = () => {
  const navigation = useNavigation<any>();
  const {
    data,
    error,
    isLoading,
    isFetching,
    refetch: refetchNotifications,
  }: any = useQuery(
    'blockrium-notification-inbox',
    fetchNotificationsWithFallback,
    {
      retry: 0,
      staleTime: 30000,
    },
  );
  const {data: countData, refetch: refetchCount}: any = callQuery(
    'auth/count-notification',
    {
      retry: 0,
      staleTime: 30000,
    },
  );
  const {showAlert} = useThisAlert();
  const [optimisticNotifications, setOptimisticNotifications] = useState<
    any[] | null
  >(null);
  const [filter, setFilter] = useState('All');
  const [isMarkingRead, setIsMarkingRead] = useState(false);
  const handleBackPress = () => {
    if (navigation.canGoBack()) {
      navigation.goBack();
      return;
    }

    appNavigate('HomeFlow');
  };

  const backendNotifications = useMemo(
    () => normalizeNotificationList(data),
    [data],
  );
  const notifications = optimisticNotifications ?? backendNotifications;
  const visibleNotifications = useMemo(
    () =>
      filter === 'All'
        ? notifications
        : notifications.filter(
            item =>
              getNotificationBucket(item?.notification_type, item?.message) ===
              filter,
          ),
    [filter, notifications],
  );
  const filterCounts = useMemo(
    () =>
      inboxFilters.reduce<Record<string, number>>((result, item) => {
        result[item] =
          item === 'All'
            ? notifications.length
            : notifications.filter(
                notification =>
                  getNotificationBucket(
                    notification?.notification_type,
                    notification?.message,
                  ) === item,
              ).length;
        return result;
      }, {}),
    [notifications],
  );
  const unreadCount = Math.max(
    normalizeNotificationCount(countData),
    notifications.length,
  );

  useEffect(() => {
    if (error) {
      showAlert('An error occurred while fetching notifications.', 'ERROR');
    } else if (data && !data.success) {
      showAlert(data?.error || 'Something went wrong', 'ERROR');
    } else if (data) {
      setOptimisticNotifications(null);
    }
  }, [data, error, showAlert]);

  const refreshNotifications = () => {
    refetchNotifications?.();
    refetchCount?.();
  };

  const handleMarkAllRead = async () => {
    if (unreadCount === 0 || isMarkingRead) {
      return;
    }

    setIsMarkingRead(true);

    try {
      const response = await markNotificationAsRead();

      if (!response?.success || Number(response?.data?.status ?? 1) === 0) {
        showAlert(
          response?.error ||
            response?.data?.message ||
            'Could not mark notifications as read.',
          'ERROR',
        );
        return;
      }

      setOptimisticNotifications([]);
      await Promise.all([refetchNotifications?.(), refetchCount?.()]);
      showAlert(
        response?.data?.message || 'Notifications marked as read.',
        'SUCCESS',
      );
    } catch (markReadError) {
      console.error('Mark notifications read error', markReadError);
      showAlert('Could not mark notifications as read.', 'ERROR');
    } finally {
      setIsMarkingRead(false);
    }
  };

  const handleOpenNotification = async (item: any) => {
    const action = getNotificationAction(
      item?.notification_type,
      item,
      item?.action,
    );

    setOptimisticNotifications(currentNotifications =>
      (currentNotifications ?? notifications).filter(
        notification => notification !== item,
      ),
    );

    markNotificationAsRead(item)
      .then(() => Promise.all([refetchNotifications?.(), refetchCount?.()]))
      .catch(markReadError => {
        console.log('Notification read marker warning', markReadError);
      });

    appNavigate(
      action.route,
      action.route === 'WalletFlow'
        ? {
            openedFromRewardNotification: true,
            rewardNotificationAction: item?.action,
            refreshWalletBalanceAt: Date.now(),
          }
        : undefined,
    );
  };

  if (isLoading) {
    return (
      <ScreenLoader
        title="Loading notifications"
        subtitle="Checking your latest system alerts and activity."
      />
    );
  }

  return (
    <AppScreen
      scrollable
      refreshing={Boolean(isFetching)}
      onRefresh={refreshNotifications}
      contentContainerStyle={styles.contentContainer}>
      <ScreenHeader
        title="Alerts"
        subtitle="System alerts, app notices, and account activity."
        showBack
        onBackPress={handleBackPress}
      />

      <GlassCard>
        <SectionEyebrow text="Inbox" />
        <Text style={styles.heroTitle}>
          {unreadCount > 0
            ? `${unreadCount} unread alert${unreadCount === 1 ? '' : 's'}`
            : 'All clear'}
        </Text>
        <Text style={styles.emptyText}>
          Mining complete, streak ready, task ready, squad invites, squad
          missions, and boost alerts.
        </Text>
        <View style={styles.filterRow}>
          {inboxFilters.map(item => (
            <Pressable
              key={item}
              onPress={() => setFilter(item)}
              style={[
                styles.filterChip,
                filter === item && styles.filterChipActive,
              ]}>
              <Text
                style={[
                  styles.filterText,
                  filter === item && styles.filterTextActive,
                ]}>
                {item}
              </Text>
              <Text style={styles.filterCount}>{filterCounts[item] ?? 0}</Text>
            </Pressable>
          ))}
        </View>
        <View style={styles.buttonRow}>
          <PrimaryButton
            label={isMarkingRead ? 'Marking...' : 'Mark all read'}
            icon="checkmark-done-outline"
            onPress={handleMarkAllRead}
            disabled={unreadCount === 0 || isMarkingRead}
            loading={isMarkingRead}
            style={styles.buttonItem}
          />
          <SecondaryButton
            label="Open Home"
            icon="home-outline"
            onPress={() => appNavigate('HomeFlow')}
            style={styles.buttonItem}
          />
        </View>
      </GlassCard>

      {notifications.length === 0 ? (
        <GlassCard>
          <Text style={styles.heroTitle}>Nothing new yet</Text>
          <Text style={styles.emptyText}>
            Notifications will appear here once the backend sends them.
          </Text>
        </GlassCard>
      ) : null}

      {visibleNotifications.length === 0 && notifications.length > 0 ? (
        <GlassCard>
          <Text style={styles.heroTitle}>No {filter.toLowerCase()} alerts</Text>
          <Text style={styles.emptyText}>
            Choose another alert type or pull down to refresh.
          </Text>
        </GlassCard>
      ) : null}

      {visibleNotifications.map((item, index) => {
        const bucket = getNotificationBucket(
          item?.notification_type,
          item?.message,
        );
        const action = getNotificationAction(
          item?.notification_type,
          item,
          item?.action,
        );
        const coinLabel = formatNotificationCoin(item?.coin);
        const actionLabel = formatNotificationAction(item?.action);

        return (
          <GlassCard
            key={`${index}-${item?.message ?? 'notification'}`}
            style={styles.rowCard}>
            <View style={styles.iconShell}>
              <Ionicons
                name={getNotificationIconName(item?.notification_type) as any}
                size={20}
                color={theme.colors.primary}
              />
            </View>
            <View style={styles.messageShell}>
              <View style={styles.rowTopLine}>
                <Text style={styles.bucketPill}>{bucket}</Text>
                <Text style={styles.meta}>
                  {formatNotificationDate(item?.date)}
                </Text>
              </View>
              {item?.title ? (
                <Text style={styles.notificationTitle}>{item.title}</Text>
              ) : null}
              <Text style={styles.message}>
                {item?.message ?? 'Blockrium notification'}
              </Text>
              <View style={styles.fieldRow}>
                <Text style={styles.meta}>
                  {formatNotificationType(item?.notification_type)}
                </Text>
                {coinLabel ? (
                  <Text style={styles.meta}>{coinLabel}</Text>
                ) : null}
                {actionLabel ? (
                  <Text style={styles.meta}>{actionLabel}</Text>
                ) : null}
              </View>
              <Pressable
                onPress={() => handleOpenNotification(item)}
                style={styles.notificationAction}>
                <Text style={styles.notificationActionText}>
                  {action.label}
                </Text>
                <Ionicons
                  name="arrow-forward"
                  size={14}
                  color={theme.colors.primary}
                />
              </Pressable>
            </View>
          </GlassCard>
        );
      })}
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {
    paddingTop: 8,
    paddingBottom: 120,
  },
  heroTitle: {
    color: theme.colors.text,
    fontSize: 24,
    fontFamily: theme.typography.heading,
  },
  emptyText: {
    marginTop: 8,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  buttonRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 16,
  },
  buttonItem: {
    flex: 1,
  },
  filterRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 14,
  },
  filterChip: {
    minHeight: 34,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(8, 20, 34, 0.72)',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 10,
  },
  filterChipActive: {
    borderColor: 'rgba(0, 226, 183, 0.38)',
    backgroundColor: 'rgba(0, 226, 183, 0.14)',
  },
  filterText: {
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  filterTextActive: {
    color: theme.colors.primary,
  },
  filterCount: {
    overflow: 'hidden',
    minWidth: 20,
    textAlign: 'center',
    borderRadius: 10,
    paddingHorizontal: 6,
    paddingVertical: 2,
    color: theme.colors.text,
    fontSize: 10,
    fontFamily: theme.typography.bodyBold,
    backgroundColor: 'rgba(147, 163, 185, 0.14)',
  },
  rowCard: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  iconShell: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: 'rgba(47, 224, 196, 0.12)',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 14,
  },
  messageShell: {
    flex: 1,
    minWidth: 0,
  },
  rowTopLine: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
    marginBottom: 6,
  },
  bucketPill: {
    overflow: 'hidden',
    borderRadius: 999,
    paddingHorizontal: 8,
    paddingVertical: 3,
    color: theme.colors.primary,
    fontSize: 10,
    fontFamily: theme.typography.bodyBold,
    backgroundColor: 'rgba(0, 226, 183, 0.12)',
  },
  message: {
    color: theme.colors.text,
    fontSize: 14,
    lineHeight: 20,
    fontFamily: theme.typography.body,
  },
  notificationTitle: {
    color: theme.colors.text,
    fontSize: 15,
    lineHeight: 20,
    fontFamily: theme.typography.bodyBold,
    marginBottom: 4,
  },
  fieldRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    columnGap: 10,
  },
  meta: {
    marginTop: 5,
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.bodyMedium,
  },
  notificationAction: {
    alignSelf: 'flex-start',
    minHeight: 34,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: 'rgba(0, 226, 183, 0.22)',
    backgroundColor: 'rgba(0, 226, 183, 0.10)',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 12,
    marginTop: 10,
  },
  notificationActionText: {
    color: theme.colors.primary,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
});

export default Notifications;
