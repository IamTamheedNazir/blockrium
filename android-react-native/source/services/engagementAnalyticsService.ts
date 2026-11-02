import {Platform} from 'react-native';
import DeviceInfo from 'react-native-device-info';
import {BASE_URL} from '../utils/constants';
import {getStorageId, getStorageToken} from '../utils/mmkvStorage';

type EngagementStatus = 'viewed' | 'started' | 'attempted' | 'succeeded' | 'failed' | 'dismissed' | 'info';

export type EngagementEventPayload = {
  eventName: string;
  area?: string;
  status?: EngagementStatus;
  route?: string;
  endpoint?: string;
  durationMs?: number;
  metadata?: Record<string, unknown>;
};

const LOG_ENDPOINT = 'app-engagement-event';
const BLOCKED_KEYS = ['password', 'token', 'authToken', 'authorization', 'jwt', 'secret'];

const sanitizeMetadata = (metadata: Record<string, unknown> = {}) =>
  Object.keys(metadata).reduce<Record<string, unknown>>((result, key) => {
    if (BLOCKED_KEYS.includes(key)) {
      result[key] = '[redacted]';
      return result;
    }

    const value = metadata[key];
    if (Array.isArray(value)) {
      result[key] = value.slice(0, 20).map(item =>
        item && typeof item === 'object'
          ? sanitizeMetadata(item as Record<string, unknown>)
          : item,
      );
      return result;
    }

    if (value && typeof value === 'object') {
      result[key] = sanitizeMetadata(value as Record<string, unknown>);
      return result;
    }

    result[key] = typeof value === 'string' ? value.slice(0, 1000) : value;
    return result;
  }, {});

const getDeviceSnapshot = () => {
  try {
    return {
      app_version: DeviceInfo.getVersion?.(),
      build_number: DeviceInfo.getBuildNumber?.(),
      platform: Platform.OS,
      device_model: DeviceInfo.getModel?.(),
      os_version: `${Platform.OS} ${DeviceInfo.getSystemVersion?.() ?? Platform.Version}`,
    };
  } catch {
    return {
      platform: Platform.OS,
      os_version: `${Platform.OS} ${Platform.Version}`,
    };
  }
};

export const trackEngagementEvent = async (payload: EngagementEventPayload) => {
  try {
    const [storedUserId, token] = await Promise.all([
      getStorageId('userId'),
      getStorageToken('authToken'),
    ]);
    const body = {
      event_name: payload.eventName,
      area: payload.area,
      status: payload.status ?? 'info',
      source: 'app',
      route: payload.route,
      endpoint: payload.endpoint,
      duration_ms: payload.durationMs,
      metadata: sanitizeMetadata(payload.metadata),
      occurred_at: new Date().toISOString(),
      user_id: storedUserId,
      ...getDeviceSnapshot(),
    };

    const response = await fetch(`${BASE_URL}${LOG_ENDPOINT}`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(token ? {Authorization: `Bearer ${token}`} : {}),
      },
      body: JSON.stringify(body),
    });

    return response.ok;
  } catch (error) {
    if (__DEV__) {
      console.log('Unable to send engagement event', error);
    }
    return false;
  }
};

export const queueEngagementEvent = (payload: EngagementEventPayload) => {
  trackEngagementEvent(payload).catch(error => {
    if (__DEV__) {
      console.log('Unable to queue engagement event', error);
    }
  });
};
