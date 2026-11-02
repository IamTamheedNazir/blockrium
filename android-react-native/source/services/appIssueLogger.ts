import {Platform} from 'react-native';
import DeviceInfo from 'react-native-device-info';
import {BASE_URL} from '../utils/constants';
import {getStorageId, getStorageToken} from '../utils/mmkvStorage';
import {claimSlowApiReport} from '../utils/slowApiTelemetry';

type AppIssueSeverity = 'info' | 'warning' | 'error' | 'fatal';

export type AppIssuePayload = {
  severity?: AppIssueSeverity;
  source?: string;
  action?: string;
  endpoint?: string;
  method?: string;
  httpStatus?: number;
  errorCode?: string;
  message?: string;
  requestDurationMs?: number;
  metadata?: Record<string, unknown>;
};

const LOG_ENDPOINT = 'app-issue-log';
const ISSUE_LOG_TIMEOUT_MS = 4000;
const DEFAULT_METADATA_STRING_LIMIT = 1000;
const CRASH_STACK_STRING_LIMIT = 8000;
const SENSITIVE_KEY_PATTERN =
  /password|passwd|passcode|token|authorization|auth|jwt|secret|apikey|cookie|session/i;

export const redactAppIssueText = (value: string) =>
  value
    .replace(/\b(Bearer\s+)[A-Za-z0-9._~+/-]+=*/gi, '$1[redacted]')
    .replace(
      /([?&\s"'`])(access[_-]?token|refresh[_-]?token|id[_-]?token|api[_-]?key|client[_-]?secret|password|secret|authorization)=([^&\s"'`]+)/gi,
      '$1$2=[redacted]',
    );

const toSafeString = (value: unknown, fallback = '') => {
  if (typeof value === 'string') {
    return redactAppIssueText(value).slice(0, 1000);
  }

  if (value instanceof Error) {
    return redactAppIssueText(value.message).slice(0, 1000);
  }

  if (value === null || value === undefined) {
    return fallback;
  }

  try {
    return redactAppIssueText(JSON.stringify(value)).slice(0, 1000);
  } catch {
    return fallback;
  }
};

export const sanitizeAppIssueMetadata = (
  metadata: Record<string, unknown> = {},
) => {
  return Object.keys(metadata).reduce<Record<string, unknown>>((result, key) => {
    const normalizedKey = key.toLowerCase().replace(/[^a-z0-9]/g, '');
    if (SENSITIVE_KEY_PATTERN.test(normalizedKey)) {
      result[key] = '[redacted]';
      return result;
    }

    const value = metadata[key];

    if (Array.isArray(value)) {
      result[key] = value.slice(0, 20).map(item =>
        item && typeof item === 'object'
          ? sanitizeAppIssueMetadata(item as Record<string, unknown>)
          : toSafeString(item),
      );
      return result;
    }

    if (value && typeof value === 'object') {
      result[key] = sanitizeAppIssueMetadata(value as Record<string, unknown>);
      return result;
    }

    result[key] =
      typeof value === 'string'
        ? redactAppIssueText(value).slice(
            0,
            ['stack', 'componentstack'].includes(normalizedKey)
              ? CRASH_STACK_STRING_LIMIT
              : DEFAULT_METADATA_STRING_LIMIT,
          )
        : value;
    return result;
  }, {});
};

const getDeviceSnapshot = () => {
  try {
    return {
      app_version: DeviceInfo.getVersion?.(),
      build_number: DeviceInfo.getBuildNumber?.(),
      platform: Platform.OS,
      device_model: DeviceInfo.getModel?.(),
      os_version: `${Platform.OS} ${DeviceInfo.getSystemVersion?.() ?? Platform.Version}`,
    };
  } catch (error) {
    return {
      platform: Platform.OS,
      os_version: `${Platform.OS} ${Platform.Version}`,
    };
  }
};

export const logAppIssue = async (payload: AppIssuePayload) => {
  if (payload.endpoint?.includes(LOG_ENDPOINT)) {
    return true;
  }

  try {
    const controller = new AbortController();
    const [storedUserId, token] = await Promise.all([
      getStorageId('userId'),
      getStorageToken('authToken'),
    ]);
    const device = getDeviceSnapshot();
    const body = {
      severity: payload.severity ?? 'error',
      source: payload.source ?? 'app',
      action: payload.action,
      endpoint: payload.endpoint
        ? redactAppIssueText(payload.endpoint).slice(0, 1000)
        : undefined,
      method: payload.method,
      http_status: payload.httpStatus,
      error_code: payload.errorCode,
      message: toSafeString(payload.message, 'App issue reported.'),
      request_duration_ms: payload.requestDurationMs,
      metadata: sanitizeAppIssueMetadata(payload.metadata),
      occurred_at: new Date().toISOString(),
      user_id: storedUserId,
      ...device,
    };

    const timeout = setTimeout(() => controller.abort(), ISSUE_LOG_TIMEOUT_MS);
    try {
      const response = await fetch(`${BASE_URL}${LOG_ENDPOINT}`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...(token ? {Authorization: `Bearer ${token}`} : {}),
        },
        body: JSON.stringify(body),
        signal: controller.signal,
      });

      return response.ok;
    } finally {
      clearTimeout(timeout);
    }
  } catch (error) {
    console.log('Unable to send app issue log', error);
    return false;
  }
};

export const logApiIssue = (payload: AppIssuePayload) => {
  return logAppIssue({
    ...payload,
    source: 'api',
    action: payload.action ?? payload.endpoint,
  });
};

export const logSlowApiResponse = (
  endpoint: string,
  method: string,
  durationMs: number,
  httpStatus?: number,
) => {
  if (!claimSlowApiReport(endpoint, method, durationMs)) {
    return;
  }

  logAppIssue({
    severity: 'warning',
    source: 'api_latency',
    action: 'slow_api_response',
    endpoint,
    method,
    httpStatus,
    message: `Successful API response took ${durationMs} ms.`,
    requestDurationMs: durationMs,
  }).catch(error => {
    console.log('Unable to send slow API telemetry', error);
  });
};
