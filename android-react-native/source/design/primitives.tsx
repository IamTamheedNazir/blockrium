import React from 'react';
import {
  ActivityIndicator,
  Animated,
  Easing,
  Pressable,
  RefreshControl,
  ScrollView,
  StatusBar,
  StyleProp,
  StyleSheet,
  Text,
  TextInput,
  TextInputProps,
  useWindowDimensions,
  View,
  ViewStyle,
} from 'react-native';
import {SafeAreaView} from 'react-native-safe-area-context';
import LinearGradient from 'react-native-linear-gradient';
import Ionicons from 'react-native-vector-icons/Ionicons';
import MaterialCommunityIcons from 'react-native-vector-icons/MaterialCommunityIcons';
import theme, {alpha} from './theme';
import {useAppTheme} from '../providers/ThemeProvider';

type AppScreenProps = {
  children: React.ReactNode;
  stickyHeader?: React.ReactNode;
  scrollable?: boolean;
  contentContainerStyle?: StyleProp<ViewStyle>;
  refreshing?: boolean;
  onRefresh?: () => void;
};

type ScreenHeaderProps = {
  title: string;
  subtitle?: string;
  showBack?: boolean;
  onBackPress?: () => void;
  rightIcon?: string;
  onRightPress?: () => void;
  rightTone?: string;
};

type GlassCardProps = {
  children: React.ReactNode;
  style?: StyleProp<ViewStyle>;
};

type PrimaryButtonProps = {
  label: string;
  onPress?: () => void | Promise<void>;
  icon?: string;
  style?: StyleProp<ViewStyle>;
  disabled?: boolean;
  loading?: boolean;
  testID?: string;
  accessibilityLabel?: string;
};

type SecondaryButtonProps = {
  label: string;
  onPress?: () => void | Promise<void>;
  icon?: string;
  style?: StyleProp<ViewStyle>;
  disabled?: boolean;
  loading?: boolean;
  testID?: string;
  accessibilityLabel?: string;
};

type MetricChipProps = {
  label: string;
  value: string;
  accent?: string;
};

type AppTextFieldProps = TextInputProps & {
  label: string;
  leadingIcon?: string;
};

type ScreenLoaderProps = {
  title?: string;
  subtitle?: string;
};

const runSafePress = (handler?: () => void | Promise<void>) => {
  if (!handler) {
    return undefined;
  }

  return () => {
    try {
      const result = handler();

      if (result && typeof (result as Promise<void>).catch === 'function') {
        (result as Promise<void>).catch(error => {
          console.error('Button action failed', error);
        });
      }

      return result;
    } catch (error) {
      console.error('Button action failed', error);
    }
  };
};

export const AppScreen: React.FC<AppScreenProps> = ({
  children,
  stickyHeader,
  scrollable = false,
  contentContainerStyle,
  refreshing = false,
  onRefresh,
}) => {
  const {theme: appTheme, resolvedMode} = useAppTheme();
  const content = scrollable ? (
    <ScrollView
      showsVerticalScrollIndicator={false}
      keyboardShouldPersistTaps="handled"
      keyboardDismissMode="on-drag"
      contentInsetAdjustmentBehavior="never"
      refreshControl={
        onRefresh ? (
          <RefreshControl
            refreshing={refreshing}
            onRefresh={onRefresh}
            tintColor={appTheme.colors.primary}
            colors={[appTheme.colors.primary]}
            progressBackgroundColor={appTheme.colors.surface}
          />
        ) : undefined
      }
      contentContainerStyle={[styles.scrollContent, contentContainerStyle]}>
      {children}
    </ScrollView>
  ) : (
    <View style={[styles.screenContent, contentContainerStyle]}>
      {children}
    </View>
  );

  return (
    <SafeAreaView
      style={[styles.safeArea, {backgroundColor: appTheme.colors.background}]}>
      <StatusBar
        translucent
        backgroundColor="transparent"
        barStyle={resolvedMode === 'light' ? 'dark-content' : 'light-content'}
      />
      <LinearGradient
        colors={appTheme.gradients.screen}
        style={[styles.screen, {backgroundColor: appTheme.colors.background}]}>
        {stickyHeader}
        {content}
      </LinearGradient>
    </SafeAreaView>
  );
};

export const ScreenHeader: React.FC<ScreenHeaderProps> = ({
  title,
  subtitle,
  showBack = false,
  onBackPress,
  rightIcon,
  onRightPress,
  rightTone,
}) => {
  const {theme: appTheme} = useAppTheme();
  return (
    <View style={styles.headerRow}>
      <View style={styles.headerMain}>
        {showBack ? (
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Go back"
            onPress={onBackPress ?? onRightPress}
            style={styles.iconButton}>
            <Ionicons
              name="arrow-back"
              size={20}
              color={appTheme.colors.text}
            />
          </Pressable>
        ) : (
          <View />
        )}
        <View style={styles.headerCopy}>
          <Text style={[styles.headerTitle, {color: appTheme.colors.text}]}>
            {title}
          </Text>
          {subtitle ? (
            <Text
              style={[
                styles.headerSubtitle,
                {color: appTheme.colors.textMuted},
              ]}>
              {subtitle}
            </Text>
          ) : null}
        </View>
      </View>
      {rightIcon ? (
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Open page action"
          onPress={onRightPress}
          style={styles.iconButton}>
          <Ionicons
            name={rightIcon}
            size={20}
            color={rightTone ?? appTheme.colors.text}
          />
        </Pressable>
      ) : null}
    </View>
  );
};

export const AppTopNav: React.FC<{
  title: string;
  subtitle?: string;
  notificationCount?: number;
  onNotificationsPress?: () => void;
  onSearchPress?: () => void;
  onSettingsPress?: () => void;
}> = ({
  title,
  subtitle,
  notificationCount = 0,
  onNotificationsPress,
  onSearchPress,
  onSettingsPress,
}) => {
  const {theme: appTheme} = useAppTheme();
  const {width} = useWindowDimensions();
  const isCompact = width < 390;
  const showNotificationBadge = notificationCount > 0;
  const notificationBadgeText =
    notificationCount > 99 ? '99+' : String(notificationCount);

  return (
    <View style={[styles.appTopNav, isCompact && styles.appTopNavCompact]}>
      <View style={styles.appTopNavCopy}>
        <Text
          style={[styles.appTopNavTitle, {color: appTheme.colors.text}]}
          numberOfLines={1}>
          {title}
        </Text>
        {subtitle ? (
          <Text
            style={[
              styles.appTopNavSubtitle,
              {color: appTheme.colors.textMuted},
            ]}
            numberOfLines={2}>
            {subtitle}
          </Text>
        ) : null}
      </View>
      <View style={styles.appTopNavActions}>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Open notifications"
          hitSlop={8}
          onPress={onNotificationsPress}
          style={styles.appTopNavButton}>
          <Ionicons
            name="notifications-outline"
            size={18}
            color={appTheme.colors.text}
          />
          {showNotificationBadge ? (
            <View style={styles.appTopNavBadge}>
              <Text style={styles.appTopNavBadgeText}>
                {notificationBadgeText}
              </Text>
            </View>
          ) : null}
        </Pressable>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Open search"
          hitSlop={8}
          onPress={onSearchPress}
          style={styles.appTopNavButton}>
          <Ionicons
            name="search-outline"
            size={18}
            color={appTheme.colors.text}
          />
        </Pressable>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Open settings"
          hitSlop={8}
          onPress={onSettingsPress}
          style={styles.appTopNavButton}>
          <Ionicons
            name="settings-outline"
            size={18}
            color={appTheme.colors.text}
          />
        </Pressable>
      </View>
    </View>
  );
};

export const GlassCard: React.FC<GlassCardProps> = ({children, style}) => {
  const {theme: appTheme} = useAppTheme();

  return (
    <LinearGradient
      colors={appTheme.gradients.surfaceRaised}
      style={[
        styles.card,
        {borderColor: appTheme.colors.border},
        appTheme.mode === 'light' && styles.lightShadow,
        style,
      ]}>
      {children}
    </LinearGradient>
  );
};

export const SectionEyebrow: React.FC<{text: string}> = ({text}) => {
  const {theme: appTheme} = useAppTheme();
  return (
    <Text style={[styles.eyebrow, {color: appTheme.colors.primary}]}>
      {text}
    </Text>
  );
};

export const MetricChip: React.FC<MetricChipProps> = ({
  label,
  value,
  accent,
}) => <MetricChipView label={label} value={value} accent={accent} />;

const MetricChipView: React.FC<MetricChipProps> = ({label, value, accent}) => {
  const {theme: appTheme} = useAppTheme();
  return (
    <View
      style={[
        styles.metricChip,
        {
          backgroundColor: alpha(accent ?? appTheme.colors.surfaceStrong, 0.22),
          borderColor: appTheme.colors.border,
        },
      ]}>
      <Text style={[styles.metricLabel, {color: appTheme.colors.textSoft}]}>
        {label}
      </Text>
      <Text style={[styles.metricValue, {color: appTheme.colors.text}]}>
        {value}
      </Text>
    </View>
  );
};

export const PrimaryButton: React.FC<PrimaryButtonProps> = ({
  label,
  onPress,
  icon,
  style,
  disabled = false,
  loading = false,
  testID,
  accessibilityLabel,
}) => {
  const {theme: appTheme} = useAppTheme();
  return (
    <Pressable
      testID={testID}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? label}
      disabled={disabled || loading}
      onPress={runSafePress(onPress)}
      style={({pressed}) => [
        style,
        pressed && !disabled && !loading ? styles.buttonPressed : null,
      ]}>
      <LinearGradient
        colors={
          disabled || loading
            ? [
                alpha(appTheme.colors.textSoft, 0.5),
                alpha(appTheme.colors.textSoft, 0.2),
              ]
            : appTheme.gradients.accent
        }
        style={[
          styles.primaryButton,
          (disabled || loading) && styles.primaryButtonDisabled,
        ]}>
        {loading ? (
          <ActivityIndicator size="small" color={appTheme.colors.background} />
        ) : icon ? (
          <MaterialCommunityIcons
            name={icon}
            size={18}
            color={appTheme.colors.background}
          />
        ) : null}
        <Text
          style={[
            styles.primaryButtonText,
            {color: appTheme.colors.background},
          ]}>
          {label}
        </Text>
      </LinearGradient>
    </Pressable>
  );
};

export const SecondaryButton: React.FC<SecondaryButtonProps> = ({
  label,
  onPress,
  icon,
  style,
  disabled = false,
  loading = false,
  testID,
  accessibilityLabel,
}) => {
  const {theme: appTheme} = useAppTheme();
  return (
    <Pressable
      testID={testID}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? label}
      disabled={disabled || loading}
      onPress={runSafePress(onPress)}
      style={({pressed}) => [
        [
          styles.secondaryButton,
          {
            borderColor: appTheme.colors.borderStrong,
            backgroundColor: alpha(appTheme.colors.surfaceStrong, 0.5),
          },
        ],
        (disabled || loading) && styles.secondaryButtonDisabled,
        pressed && !disabled && !loading ? styles.buttonPressed : null,
        style,
      ]}>
      {loading ? (
        <ActivityIndicator size="small" color={appTheme.colors.text} />
      ) : icon ? (
        <MaterialCommunityIcons
          name={icon}
          size={18}
          color={appTheme.colors.text}
        />
      ) : null}
      <Text style={[styles.secondaryButtonText, {color: appTheme.colors.text}]}>
        {label}
      </Text>
    </Pressable>
  );
};

export const AppTextField: React.FC<AppTextFieldProps> = ({
  label,
  leadingIcon,
  style,
  placeholderTextColor = theme.colors.textSoft,
  ...props
}) => {
  const {theme: appTheme} = useAppTheme();
  return (
    <View style={styles.fieldGroup}>
      <Text style={[styles.fieldLabel, {color: appTheme.colors.textMuted}]}>
        {label}
      </Text>
      <View
        style={[
          styles.fieldShell,
          {
            borderColor: appTheme.colors.border,
            backgroundColor: alpha(appTheme.colors.surfaceStrong, 0.54),
          },
        ]}>
        {leadingIcon ? (
          <Ionicons
            name={leadingIcon}
            size={18}
            color={appTheme.colors.textSoft}
            style={styles.fieldIcon}
          />
        ) : null}
        <TextInput
          placeholderTextColor={
            placeholderTextColor ?? appTheme.colors.textSoft
          }
          style={[styles.fieldInput, {color: appTheme.colors.text}, style]}
          {...props}
        />
      </View>
    </View>
  );
};

export const InlineLoader: React.FC<{label?: string}> = ({label}) => {
  const {theme: appTheme} = useAppTheme();
  return (
    <View style={styles.inlineLoader}>
      <ActivityIndicator size="small" color={appTheme.colors.primary} />
      {label ? (
        <Text
          style={[styles.inlineLoaderText, {color: appTheme.colors.textMuted}]}>
          {label}
        </Text>
      ) : null}
    </View>
  );
};

export const ScreenState: React.FC<{
  icon?: string;
  title: string;
  message: string;
  actionLabel?: string;
  onActionPress?: () => void;
}> = ({
  icon = 'alert-circle-outline',
  title,
  message,
  actionLabel,
  onActionPress,
}) => {
  const {theme: appTheme} = useAppTheme();
  return (
    <GlassCard style={styles.stateCard}>
      <View style={styles.stateIcon}>
        <Ionicons
          name={icon as any}
          size={22}
          color={appTheme.colors.primary}
        />
      </View>
      <Text style={[styles.stateTitle, {color: appTheme.colors.text}]}>
        {title}
      </Text>
      <Text style={[styles.stateMessage, {color: appTheme.colors.textMuted}]}>
        {message}
      </Text>
      {actionLabel && onActionPress ? (
        <SecondaryButton
          label={actionLabel}
          icon="refresh"
          onPress={onActionPress}
          style={styles.stateButton}
        />
      ) : null}
    </GlassCard>
  );
};

export const CardEntrance: React.FC<{
  children: React.ReactNode;
  delay?: number;
  style?: StyleProp<ViewStyle>;
}> = ({children, delay = 0, style}) => {
  const progress = React.useRef(new Animated.Value(0)).current;

  React.useEffect(() => {
    Animated.timing(progress, {
      toValue: 1,
      duration: 280,
      delay,
      easing: Easing.out(Easing.cubic),
      useNativeDriver: true,
    }).start();
  }, [delay, progress]);

  return (
    <Animated.View
      style={[
        style,
        {
          opacity: progress,
          transform: [
            {
              translateY: progress.interpolate({
                inputRange: [0, 1],
                outputRange: [10, 0],
              }),
            },
          ],
        },
      ]}>
      {children}
    </Animated.View>
  );
};

export const NavigationProgressBar: React.FC<{visible: boolean}> = ({
  visible,
}) => {
  const progress = React.useRef(new Animated.Value(0)).current;

  React.useEffect(() => {
    if (visible) {
      progress.setValue(0);
      Animated.timing(progress, {
        toValue: 1,
        duration: 420,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: false,
      }).start();
      return;
    }

    Animated.timing(progress, {
      toValue: 0,
      duration: 160,
      easing: Easing.out(Easing.cubic),
      useNativeDriver: false,
    }).start();
  }, [progress, visible]);

  const width = progress.interpolate({
    inputRange: [0, 1],
    outputRange: ['0%', '100%'],
  });

  return (
    <Animated.View
      pointerEvents="none"
      style={[
        styles.navigationProgress,
        {
          opacity: progress,
          width,
        },
      ]}
    />
  );
};

export const ScreenLoader: React.FC<ScreenLoaderProps> = ({
  title = 'Preparing your workspace',
  subtitle = 'Fetching live balances, mining state, and account data.',
}) => {
  const {theme: appTheme} = useAppTheme();
  return (
    <AppScreen contentContainerStyle={styles.loaderScreen}>
      <View
        accessible
        accessibilityRole="progressbar"
        accessibilityLabel={`${title}. ${subtitle}`}
        style={[
          styles.loaderCard,
          {
            backgroundColor: alpha(appTheme.colors.surface, 0.92),
            borderColor: appTheme.colors.border,
          },
        ]}>
        <LinearGradient
          colors={appTheme.gradients.subtle}
          style={[
            styles.loaderMark,
            {borderColor: alpha(appTheme.colors.primary, 0.24)},
          ]}>
          <ActivityIndicator size="small" color={appTheme.colors.primary} />
        </LinearGradient>
        <View style={styles.loaderTrack}>
          <View style={styles.loaderTrackFill} />
        </View>
        <Text style={[styles.loaderTitle, {color: appTheme.colors.text}]}>
          {title}
        </Text>
        <Text
          style={[styles.loaderSubtitle, {color: appTheme.colors.textMuted}]}>
          {subtitle}
        </Text>
      </View>
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  screen: {
    flex: 1,
    paddingHorizontal: theme.spacing.md,
  },
  screenContent: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    paddingTop: theme.spacing.sm,
    marginBottom: theme.spacing.lg,
  },
  headerMain: {
    flexDirection: 'row',
    flex: 1,
    alignItems: 'center',
  },
  headerCopy: {
    flex: 1,
  },
  iconButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    marginRight: theme.spacing.md,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.62),
    borderWidth: 1,
    borderColor: theme.colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitle: {
    color: theme.colors.text,
    fontSize: 25,
    lineHeight: 29,
    fontFamily: theme.typography.display,
  },
  headerSubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  appTopNav: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: theme.spacing.md,
    paddingTop: 4,
    marginBottom: theme.spacing.lg,
  },
  appTopNavCompact: {
    flexDirection: 'column',
    gap: theme.spacing.sm,
  },
  appTopNavCopy: {
    flex: 1,
    minWidth: 0,
  },
  appTopNavTitle: {
    color: theme.colors.text,
    fontSize: 26,
    lineHeight: 30,
    fontFamily: theme.typography.display,
  },
  appTopNavSubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  appTopNavActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    flexShrink: 0,
  },
  appTopNavButton: {
    width: 40,
    height: 40,
    borderRadius: theme.radii.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.72),
    borderWidth: 1,
    borderColor: alpha(theme.colors.primary, 0.14),
  },
  appTopNavBadge: {
    position: 'absolute',
    top: -4,
    right: -4,
    minWidth: 17,
    height: 17,
    borderRadius: 9,
    paddingHorizontal: 4,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.danger,
    borderWidth: 1,
    borderColor: theme.colors.background,
  },
  appTopNavBadgeText: {
    color: theme.colors.text,
    fontSize: 9,
    lineHeight: 11,
    fontFamily: theme.typography.bodyBold,
  },
  card: {
    borderRadius: theme.radii.xl,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: 16,
    marginBottom: theme.spacing.md,
    overflow: 'hidden',
    ...theme.shadow,
  },
  lightShadow: {
    shadowColor: '#7D94A5',
  },
  eyebrow: {
    marginBottom: theme.spacing.sm,
    color: theme.colors.primary,
    fontSize: 12,
    letterSpacing: 1.4,
    textTransform: 'uppercase',
    fontFamily: theme.typography.bodyBold,
  },
  metricChip: {
    minWidth: 96,
    borderRadius: theme.radii.md,
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: alpha(theme.colors.surfaceGlass, 0.58),
  },
  metricLabel: {
    color: theme.colors.textSoft,
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  metricValue: {
    marginTop: 4,
    color: theme.colors.text,
    fontSize: 18,
    fontFamily: theme.typography.heading,
  },
  primaryButton: {
    minHeight: 50,
    borderRadius: theme.radii.pill,
    paddingHorizontal: theme.spacing.lg,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: theme.spacing.sm,
  },
  primaryButtonDisabled: {
    opacity: 0.7,
  },
  buttonPressed: {
    opacity: 0.88,
    transform: [{scale: 0.985}],
  },
  primaryButtonText: {
    color: theme.colors.background,
    fontSize: 15,
    fontFamily: theme.typography.bodyBold,
  },
  secondaryButton: {
    minHeight: 48,
    borderRadius: theme.radii.pill,
    paddingHorizontal: theme.spacing.lg,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: theme.spacing.sm,
    borderWidth: 1,
    borderColor: theme.colors.borderStrong,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.5),
  },
  secondaryButtonText: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.bodyBold,
  },
  secondaryButtonDisabled: {
    opacity: 0.55,
  },
  fieldGroup: {
    marginBottom: theme.spacing.lg,
  },
  fieldLabel: {
    marginBottom: theme.spacing.sm,
    color: theme.colors.textMuted,
    fontSize: 13,
    fontFamily: theme.typography.bodyMedium,
    textTransform: 'uppercase',
    letterSpacing: 0.8,
  },
  fieldShell: {
    minHeight: 56,
    borderRadius: theme.radii.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.54),
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: theme.spacing.md,
  },
  fieldIcon: {
    marginRight: theme.spacing.sm,
  },
  fieldInput: {
    flex: 1,
    color: theme.colors.text,
    fontSize: 16,
    fontFamily: theme.typography.bodyMedium,
    paddingVertical: theme.spacing.md,
  },
  inlineLoader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: theme.spacing.sm,
  },
  inlineLoaderText: {
    color: theme.colors.textMuted,
    fontSize: 13,
    fontFamily: theme.typography.bodyMedium,
  },
  stateCard: {
    alignItems: 'center',
    paddingVertical: theme.spacing.xl,
  },
  stateIcon: {
    width: 48,
    height: 48,
    borderRadius: theme.radii.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.primary, 0.12),
    borderWidth: 1,
    borderColor: alpha(theme.colors.primary, 0.22),
    marginBottom: theme.spacing.md,
  },
  stateTitle: {
    color: theme.colors.text,
    fontSize: 20,
    textAlign: 'center',
    fontFamily: theme.typography.heading,
  },
  stateMessage: {
    marginTop: theme.spacing.sm,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 20,
    textAlign: 'center',
    fontFamily: theme.typography.body,
  },
  stateButton: {
    marginTop: theme.spacing.lg,
    alignSelf: 'stretch',
  },
  navigationProgress: {
    position: 'absolute',
    top: 0,
    left: 0,
    height: 2,
    zIndex: 1000,
    backgroundColor: theme.colors.primary,
  },
  loaderScreen: {
    flex: 1,
    justifyContent: 'center',
  },
  loaderCard: {
    borderRadius: theme.radii.xl,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(13, 28, 48, 0.9)',
    paddingHorizontal: theme.spacing.xl,
    paddingVertical: theme.spacing.xxl,
    alignItems: 'center',
    ...theme.shadow,
  },
  loaderMark: {
    width: 58,
    height: 58,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: theme.radii.lg,
    borderWidth: 1,
    borderColor: alpha(theme.colors.primary, 0.24),
  },
  loaderTrack: {
    width: 92,
    height: 4,
    overflow: 'hidden',
    marginTop: theme.spacing.lg,
    borderRadius: theme.radii.pill,
    backgroundColor: alpha(theme.colors.textSoft, 0.18),
  },
  loaderTrackFill: {
    width: '58%',
    height: '100%',
    borderRadius: theme.radii.pill,
    backgroundColor: theme.colors.primary,
  },
  loaderTitle: {
    marginTop: theme.spacing.lg,
    color: theme.colors.text,
    fontSize: 24,
    textAlign: 'center',
    fontFamily: theme.typography.display,
  },
  loaderSubtitle: {
    marginTop: theme.spacing.sm,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 20,
    textAlign: 'center',
    fontFamily: theme.typography.body,
  },
});
