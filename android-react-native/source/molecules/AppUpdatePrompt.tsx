import React, {useEffect, useMemo, useState} from 'react';
import {
  AppState,
  Linking,
  Modal,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import DeviceInfo from 'react-native-device-info';
import MaterialCommunityIcons from 'react-native-vector-icons/MaterialCommunityIcons';
import theme, {alpha} from '../design/theme';
import {BASE_URL} from '../utils/constants';

const DEFAULT_PLAY_STORE_URL =
  'https://play.google.com/store/apps/details?id=com.blockrium';

type AppVersionRecord = {
  title?: string;
  message?: string;
  description?: string;
  release_notes?: string;
  releaseNotes?: string;
  app_version?: string | number;
  version?: string | number;
  latest_version?: string | number;
  latestVersion?: string | number;
  version_name?: string | number;
  versionName?: string | number;
  version_code?: string | number;
  versionCode?: string | number;
  latest_version_code?: string | number;
  latestVersionCode?: string | number;
  build_code?: string | number;
  buildCode?: string | number;
  build_number?: string | number;
  buildNumber?: string | number;
  minimum_version?: string | number;
  minimumVersion?: string | number;
  min_version?: string | number;
  minVersion?: string | number;
  minimum_version_code?: string | number;
  minimumVersionCode?: string | number;
  min_version_code?: string | number;
  minVersionCode?: string | number;
  minimum_build?: string | number;
  minimumBuild?: string | number;
  min_build?: string | number;
  minBuild?: string | number;
  force_update?: boolean | number | string;
  forceUpdate?: boolean | number | string;
  is_force_update?: boolean | number | string;
  isForceUpdate?: boolean | number | string;
  mandatory_update?: boolean | number | string;
  mandatoryUpdate?: boolean | number | string;
  required?: boolean | number | string;
  is_required?: boolean | number | string;
  isRequired?: boolean | number | string;
  update_available?: boolean | number | string;
  updateAvailable?: boolean | number | string;
  status?: boolean | number | string;
  is_active?: boolean | number | string;
  isActive?: boolean | number | string;
  active?: boolean | number | string;
  platform?: string;
  os?: string;
  device?: string;
  app_link?: string;
  appLink?: string;
  android_link?: string;
  androidLink?: string;
  ios_link?: string;
  iosLink?: string;
  play_store_url?: string;
  playStoreUrl?: string;
  store_url?: string;
  storeUrl?: string;
  link?: string;
};

type UpdateState = {
  latestBuild: number;
  currentBuild: number;
  latestLabel: string;
  currentLabel: string;
  title: string;
  message: string;
  appLink: string;
  isForced: boolean;
};

const normalizeVersionNumber = (value: unknown) => {
  if (typeof value === 'string' && value.includes('.')) {
    return 0;
  }

  const normalized = Number(value);
  return Number.isFinite(normalized) ? normalized : 0;
};

const getRecordBuild = (record: AppVersionRecord) => {
  return normalizeVersionNumber(
    record.latest_version_code ??
      record.latestVersionCode ??
      record.version_code ??
      record.versionCode ??
      record.build_code ??
      record.buildCode ??
      record.build_number ??
      record.buildNumber ??
      record.app_version ??
      record.version_code ??
      record.version,
  );
};

const getMinimumBuild = (record: AppVersionRecord) => {
  return normalizeVersionNumber(
    record.minimum_version_code ??
      record.minimumVersionCode ??
      record.min_version_code ??
      record.minVersionCode ??
      record.minimum_build ??
      record.minimumBuild ??
      record.min_build ??
      record.minBuild,
  );
};

const normalizeVersionName = (value: unknown) => {
  if (value === null || value === undefined) {
    return '';
  }

  const raw = String(value).trim();
  return raw.includes('.') ? raw : '';
};

const getLatestVersionName = (record: AppVersionRecord) => {
  return (
    normalizeVersionName(record.version_name) ||
    normalizeVersionName(record.versionName) ||
    normalizeVersionName(record.latest_version) ||
    normalizeVersionName(record.latestVersion) ||
    normalizeVersionName(record.app_version) ||
    normalizeVersionName(record.version)
  );
};

const getMinimumVersionName = (record: AppVersionRecord) => {
  return (
    normalizeVersionName(record.minimum_version) ||
    normalizeVersionName(record.minimumVersion) ||
    normalizeVersionName(record.min_version) ||
    normalizeVersionName(record.minVersion)
  );
};

const compareVersionNames = (left: string, right: string) => {
  const leftParts = left
    .split(/[^\d]+/)
    .filter(Boolean)
    .map(Number);
  const rightParts = right
    .split(/[^\d]+/)
    .filter(Boolean)
    .map(Number);
  const length = Math.max(leftParts.length, rightParts.length);

  for (let index = 0; index < length; index += 1) {
    const leftValue = leftParts[index] ?? 0;
    const rightValue = rightParts[index] ?? 0;

    if (leftValue > rightValue) {
      return 1;
    }

    if (leftValue < rightValue) {
      return -1;
    }
  }

  return 0;
};

const getInstalledBuildNumber = () => {
  const buildGetter = DeviceInfo.getBuildNumber;

  return typeof buildGetter === 'function' ? buildGetter() : '0';
};

const getInstalledVersionName = () => {
  const versionGetter = DeviceInfo.getVersion;

  return typeof versionGetter === 'function' ? versionGetter() : '0.0.0';
};

const coerceBoolean = (value: unknown) => {
  if (typeof value === 'boolean') {
    return value;
  }

  if (typeof value === 'number') {
    return value === 1;
  }

  if (typeof value === 'string') {
    return ['1', 'true', 'yes', 'required', 'force', 'forced'].includes(
      value.trim().toLowerCase(),
    );
  }

  return false;
};

const isRecordActive = (record: AppVersionRecord) => {
  const status =
    record.status ?? record.is_active ?? record.isActive ?? record.active;

  if (status === undefined || status === null || status === '') {
    return true;
  }

  if (typeof status === 'boolean') {
    return status;
  }

  if (typeof status === 'number') {
    return status !== 0;
  }

  return !['0', 'false', 'inactive', 'disabled', 'draft', 'deleted'].includes(
    status.trim().toLowerCase(),
  );
};

const isRecordForCurrentPlatform = (record: AppVersionRecord) => {
  const platform = String(record.platform ?? record.os ?? record.device ?? '')
    .trim()
    .toLowerCase();

  return (
    !platform ||
    platform === 'all' ||
    platform === 'mobile' ||
    platform === Platform.OS
  );
};

const isForceUpdate = (record: AppVersionRecord) => {
  return [
    record.force_update,
    record.forceUpdate,
    record.is_force_update,
    record.isForceUpdate,
    record.mandatory_update,
    record.mandatoryUpdate,
    record.required,
    record.is_required,
    record.isRequired,
  ].some(coerceBoolean);
};

const getRecordLink = (record: AppVersionRecord) => {
  const platformLink =
    Platform.OS === 'android'
      ? record.android_link ?? record.androidLink
      : record.ios_link ?? record.iosLink;

  const link = String(
    platformLink ??
      record.link ??
      record.play_store_url ??
      record.playStoreUrl ??
      record.store_url ??
      record.storeUrl ??
      record.app_link ??
      record.appLink ??
      DEFAULT_PLAY_STORE_URL,
  ).trim();

  return link || DEFAULT_PLAY_STORE_URL;
};

const getVersionRecords = (payload: any): AppVersionRecord[] => {
  const list =
    payload?.data?.app_version ??
    payload?.data?.appUpdate ??
    payload?.data?.app_update ??
    payload?.data?.version_control ??
    payload?.data?.versionControl ??
    payload?.data?.latest_version ??
    payload?.data?.latestVersion ??
    payload?.data?.data ??
    payload?.data ??
    payload?.app_version ??
    payload?.appUpdate ??
    payload?.app_update ??
    payload?.version_control ??
    payload?.versionControl ??
    payload?.latest_version ??
    payload?.latestVersion ??
    payload;

  if (Array.isArray(list)) {
    return list.filter(Boolean);
  }

  return list ? [list] : [];
};

const fetchAppVersionPayload = async (currentBuild: number) => {
  const endpoint = `${BASE_URL}app-version?platform=${encodeURIComponent(
    Platform.OS,
  )}&current_version_code=${encodeURIComponent(String(currentBuild || 0))}`;

  try {
    const response = await fetch(endpoint, {
      method: 'GET',
      headers: {
        Accept: 'application/json',
      },
    });

    if (!response.ok) {
      if (__DEV__) {
        console.log('App update check returned HTTP status', response.status);
      }

      return null;
    }

    const text = await response.text();

    if (!text || !/^[\s\n\r]*[{[]/.test(text)) {
      return null;
    }

    return JSON.parse(text);
  } catch (error) {
    if (__DEV__) {
      console.log('App update check failed', error);
    }

    return null;
  }
};

const isUpdateAvailable = (record: AppVersionRecord, currentBuild: number) => {
  const latestBuild = getRecordBuild(record);

  return (
    coerceBoolean(record.update_available ?? record.updateAvailable) ||
    (latestBuild > 0 && latestBuild > currentBuild)
  );
};

const pickLatestVersion = (records: AppVersionRecord[]) => {
  return records.reduce<AppVersionRecord | null>((latest, record) => {
    if (!latest) {
      return record;
    }

    const recordBuild = getRecordBuild(record);
    const latestBuild = getRecordBuild(latest);

    if (recordBuild > latestBuild) {
      return record;
    }

    if (
      recordBuild === latestBuild &&
      compareVersionNames(
        getLatestVersionName(record),
        getLatestVersionName(latest),
      ) > 0
    ) {
      return record;
    }

    return latest;
  }, null);
};

const openPlayStore = async (url: string) => {
  const targetUrl = url || DEFAULT_PLAY_STORE_URL;

  try {
    await Linking.openURL(targetUrl);
  } catch (error) {
    await Linking.openURL(DEFAULT_PLAY_STORE_URL);
  }
};

const AppUpdatePrompt = () => {
  const [updateState, setUpdateState] = useState<UpdateState | null>(null);
  const [dismissedVersion, setDismissedVersion] = useState<number | null>(null);

  const isVisible = Boolean(
    updateState && updateState.latestBuild !== dismissedVersion,
  );

  const message = useMemo(() => {
    if (!updateState) {
      return '';
    }

    return (
      updateState.message ||
      'A new Blockrium update is live. Update from Play Store to get the latest rewards, boosts, and performance improvements.'
    );
  }, [updateState]);

  useEffect(() => {
    let isMounted = true;

    const checkForUpdate = async () => {
      try {
        const currentBuild = normalizeVersionNumber(getInstalledBuildNumber());
        const currentVersionName = getInstalledVersionName();
        const response = await fetchAppVersionPayload(currentBuild);

        if (!response) {
          return;
        }

        const latestRecord = pickLatestVersion(
          getVersionRecords(response)
            .filter(isRecordActive)
            .filter(isRecordForCurrentPlatform),
        );

        if (!latestRecord) {
          return;
        }

        const latestBuild = getRecordBuild(latestRecord);
        const minimumBuild = getMinimumBuild(latestRecord);
        const latestVersionName = getLatestVersionName(latestRecord);
        const minimumVersionName = getMinimumVersionName(latestRecord);
        const isVersionNameUpdate = Boolean(
          latestVersionName &&
            compareVersionNames(latestVersionName, currentVersionName) > 0,
        );
        const isMinimumBuildBlocked =
          minimumBuild > 0 && currentBuild < minimumBuild;
        const isMinimumVersionBlocked = Boolean(
          minimumVersionName &&
            compareVersionNames(minimumVersionName, currentVersionName) > 0,
        );
        const isForced =
          isForceUpdate(latestRecord) ||
          isMinimumBuildBlocked ||
          isMinimumVersionBlocked;
        const updateAvailable =
          isUpdateAvailable(latestRecord, currentBuild) || isVersionNameUpdate;

        if (
          (updateAvailable ||
            isMinimumBuildBlocked ||
            isMinimumVersionBlocked) &&
          isMounted
        ) {
          setUpdateState({
            latestBuild: latestBuild || minimumBuild,
            currentBuild,
            latestLabel:
              latestVersionName || String(latestBuild || minimumBuild),
            currentLabel: `${currentVersionName} (${currentBuild})`,
            title: String(latestRecord.title ?? '').trim(),
            message: String(
              latestRecord.release_notes ??
                latestRecord.releaseNotes ??
                latestRecord.message ??
                latestRecord.description ??
                latestRecord.title ??
                '',
            ).trim(),
            appLink: getRecordLink(latestRecord),
            isForced,
          });
        }
      } catch (error) {
        if (__DEV__) {
          console.log('App update check failed', error);
        }
      }
    };

    checkForUpdate();

    const subscription = AppState.addEventListener('change', nextState => {
      if (nextState === 'active') {
        checkForUpdate();
      }
    });

    return () => {
      isMounted = false;
      subscription.remove();
    };
  }, []);

  if (!updateState) {
    return null;
  }

  return (
    <Modal
      transparent
      animationType="fade"
      visible={isVisible}
      onRequestClose={() => {
        if (!updateState.isForced) {
          setDismissedVersion(updateState.latestBuild);
        }
      }}>
      <View style={styles.overlay}>
        <View style={styles.card}>
          <View style={styles.iconShell}>
            <MaterialCommunityIcons
              name="google-play"
              size={32}
              color={theme.colors.background}
            />
          </View>
          <Text style={styles.title}>
            {updateState.title ||
              (updateState.isForced ? 'Update required' : 'Update Blockrium')}
          </Text>
          <Text style={styles.message}>{message}</Text>
          {updateState.isForced ? (
            <Text style={styles.requiredText}>
              This version is required to continue using Blockrium safely.
            </Text>
          ) : null}
          <Text style={styles.versionText}>
            Installed {updateState.currentLabel} | Latest{' '}
            {updateState.latestLabel}
          </Text>

          <Pressable
            accessibilityRole="button"
            style={styles.primaryButton}
            onPress={() => openPlayStore(updateState.appLink)}>
            <MaterialCommunityIcons
              name="open-in-new"
              size={18}
              color={theme.colors.background}
            />
            <Text style={styles.primaryButtonText}>Update from Play Store</Text>
          </Pressable>

          {!updateState.isForced ? (
            <Pressable
              accessibilityRole="button"
              style={styles.secondaryButton}
              onPress={() => setDismissedVersion(updateState.latestBuild)}>
              <Text style={styles.secondaryButtonText}>Later</Text>
            </Pressable>
          ) : null}
        </View>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    padding: 24,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(4, 13, 28, 0.82)',
  },
  card: {
    width: '100%',
    maxWidth: 380,
    borderRadius: 24,
    padding: 22,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
  },
  iconShell: {
    width: 62,
    height: 62,
    borderRadius: 31,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.primary,
  },
  title: {
    marginTop: 16,
    color: theme.colors.text,
    fontSize: 24,
    fontFamily: theme.typography.display,
    textAlign: 'center',
  },
  message: {
    marginTop: 10,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 21,
    fontFamily: theme.typography.body,
    textAlign: 'center',
  },
  requiredText: {
    marginTop: 10,
    color: theme.colors.primary,
    fontSize: 13,
    lineHeight: 19,
    fontFamily: theme.typography.bodyBold,
    textAlign: 'center',
  },
  versionText: {
    marginTop: 12,
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.body,
    textAlign: 'center',
  },
  primaryButton: {
    width: '100%',
    minHeight: 52,
    marginTop: 20,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
    backgroundColor: theme.colors.primary,
  },
  primaryButtonText: {
    color: theme.colors.background,
    fontSize: 15,
    fontFamily: theme.typography.bodyBold,
  },
  secondaryButton: {
    marginTop: 10,
    minHeight: 42,
    paddingHorizontal: 18,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.5),
  },
  secondaryButtonText: {
    color: theme.colors.text,
    fontSize: 14,
    fontFamily: theme.typography.bodyBold,
  },
});

export default AppUpdatePrompt;
