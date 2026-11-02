import ky from 'ky';
import { BASE_URL } from '../../utils/constants';
import { getErrorMessages } from '../../utils/errorMessages';
import {logApiIssue, logSlowApiResponse} from '../../services/appIssueLogger';


if (typeof Object.hasOwn !== 'function') {
    Object.hasOwn = function (obj, prop) {
        return Object.prototype.hasOwnProperty.call(obj, prop);
    };
}

export function attachLeadsTokenInHeader(token: string) {
    dynamicHeaders.Authorization = `Bearer ${token}`;
}

let dynamicHeaders: Record<string, string> = {};

export type ApiResponse = {
    success: boolean;
    data?: any;
    error?: any;
    status?: number;
};

const parseResponsePayload = async (response: any) => {
    const contentType = response?.headers?.get?.('content-type') ?? '';

    try {
        const text = await response.text();

        if (!text) {
            return {};
        }

        if (contentType.includes('application/json') || /^[\s\n\r]*[{[]/.test(text)) {
            try {
                return JSON.parse(text);
            } catch (jsonError) {
                console.log('JSON response parsing warning', jsonError);
            }
        }

        return text;
    } catch (parseError) {
        console.error('Response parsing error:', parseError);
        return {};
    }
};

const isHtmlErrorPayload = (value: string) =>
    /<!doctype html|<html|route \[login\] not defined|whoops|laravel/i.test(value);

const isSqlErrorPayload = (value: string) =>
    /SQLSTATE|Column not found|Unknown column|Integrity constraint violation|SQL: /i.test(value);

const getHttpStatus = (error: any) =>
    Number(error?.response?.status ?? error?.status ?? 0);

const normalizeAuthErrorMessage = (status: number, value: any) => {
    const rawMessage = typeof value === 'string' ? value : value?.message;

    if (
        status === 401 ||
        String(rawMessage ?? '').toLowerCase().includes('unauthenticated')
    ) {
        return 'Session expired. Please log in again.';
    }

    return undefined;
};

const stringifyErrorMessage = (value: any): string => {
    if (Array.isArray(value)) {
        return value.filter(Boolean).join('\n') || 'Something went wrong. Please try again.';
    }

    if (typeof value === 'string') {
        const trimmedValue = value.trim();

        if (!trimmedValue) {
            return 'Something went wrong. Please try again.';
        }

        if (isHtmlErrorPayload(trimmedValue)) {
            return 'Server returned a web error page. Please refresh your session and try again.';
        }

        if (isSqlErrorPayload(trimmedValue)) {
            return 'Server database update failed. Please update the backend and try again.';
        }

        try {
            return stringifyErrorMessage(JSON.parse(trimmedValue));
        } catch {
            return trimmedValue;
        }
    }

    if (value && typeof value === 'object') {
        const directMessage =
            value.message ||
            value.error ||
            value.msg ||
            value.status_message;

        if (typeof directMessage === 'string' && directMessage.trim()) {
            return stringifyErrorMessage(directMessage.trim());
        }

        const helperMessages = getErrorMessages(value);

        if (Array.isArray(helperMessages) && helperMessages.length > 0) {
            return helperMessages.filter(Boolean).join('\n');
        }

        if (typeof helperMessages === 'string' && helperMessages.trim()) {
            return helperMessages.trim();
        }
    }

    return 'Something went wrong. Please try again.';
};

const normalizeErrorPayload = async (error: any) => {
    let errorData: any = 'An unknown error occurred';

    if (error?.response) {
        const status = getHttpStatus(error);
        errorData = await parseResponsePayload(error.response);
        const authMessage = normalizeAuthErrorMessage(status, errorData);

        if (authMessage) {
            return {
                message: authMessage,
                data: errorData,
                status,
            };
        }
    } else if (error?.message) {
        errorData = error.message;
    }

    return {
        message: stringifyErrorMessage(errorData),
        data: errorData,
        status: getHttpStatus(error),
    };
};

const api = ky.create({
    prefixUrl: BASE_URL,
    timeout: 15000,
    retry: 0,
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...dynamicHeaders,
    },
    hooks: {
        beforeRequest: [
            (request) => {
                request.headers.set('Accept', 'application/json');
                const isMultipart = request.headers.get('X-Blockrium-Multipart') === '1';
                if (isMultipart) {
                    request.headers.delete('X-Blockrium-Multipart');
                    request.headers.delete('Content-Type');
                } else {
                    request.headers.set('Content-Type', 'application/json');
                }
                Object.keys(dynamicHeaders).forEach((key) => {
                    request.headers.set(key, dynamicHeaders[key]);
                });
            },
        ],
    },
});

const reportApiIssue = (payload: Parameters<typeof logApiIssue>[0]) => {
    logApiIssue(payload).catch(error => {
        console.log('Unable to queue API issue log', error);
    });
};

export const postFormApi = async (endPoint: string, body: FormData): Promise<ApiResponse> => {
    const requestStartedAt = Date.now();
    try {
        const response = await api.post(endPoint, {body, headers: {'X-Blockrium-Multipart': '1'}});
        const responseData = await parseResponsePayload(response);
        logSlowApiResponse(endPoint, 'POST', Date.now() - requestStartedAt, response.status);
        return {success: true, data: responseData, status: response.status};
    } catch (error: any) {
        const errorPayload = await normalizeErrorPayload(error);
        reportApiIssue({
            endpoint: endPoint,
            method: 'POST',
            httpStatus: errorPayload.status,
            message: errorPayload.message,
            errorCode: error?.name,
            requestDurationMs: Date.now() - requestStartedAt,
            metadata: {response: errorPayload.data},
        });
        return {success: false, error: errorPayload.message, data: errorPayload.data, status: errorPayload.status};
    }
};


export function removeLeadsTokenFromHeader() {
    delete dynamicHeaders.Authorization;
}

export const postApi = async (endPoint: string, data: any): Promise<ApiResponse> => {
    const requestStartedAt = Date.now();

    try {
        const response = await api.post(endPoint, { json: data });
        const responseData = await parseResponsePayload(response);
        logSlowApiResponse(endPoint, 'POST', Date.now() - requestStartedAt, response.status);
        return { success: true, data: responseData };
    } catch (error: any) {
        console.log('Error>>>', error);
        const errorPayload = await normalizeErrorPayload(error);
        console.log(errorPayload.message);
        reportApiIssue({
            endpoint: endPoint,
            method: 'POST',
            httpStatus: errorPayload.status,
            message: errorPayload.message,
            errorCode: error?.name,
            requestDurationMs: Date.now() - requestStartedAt,
            metadata: {response: errorPayload.data},
        });

        return { success: false, error: errorPayload.message, data: errorPayload.data, status: errorPayload.status };
    }
};

export const deleteApi = async (endPoint: string, data: any, headers = {}): Promise<ApiResponse> => {
    const requestStartedAt = Date.now();

    return api.delete(endPoint, {
        json: data,
        headers,
    }).then(async response => {
        const responseData = await parseResponsePayload(response);
        logSlowApiResponse(endPoint, 'DELETE', Date.now() - requestStartedAt, response.status);
        return {
            success: true,
            data: responseData,
        };
    }).catch(async error => {
        handleError(error);
        const errorPayload = await normalizeErrorPayload(error);
        reportApiIssue({
            endpoint: endPoint,
            method: 'DELETE',
            httpStatus: errorPayload.status,
            message: errorPayload.message,
            errorCode: error?.name,
            requestDurationMs: Date.now() - requestStartedAt,
            metadata: {response: errorPayload.data},
        });
        return { success: false, error: errorPayload.message, data: errorPayload.data };
    });
};

export const getApi = async (
    endPoint: string,
    options: {signal?: AbortSignal; timeout?: number} = {},
): Promise<ApiResponse> => {
    const requestStartedAt = Date.now();

    try {
        const response = await api.get(endPoint, {
            signal: options.signal,
            timeout: options.timeout,
        });
        const responseData = await parseResponsePayload(response);
        logSlowApiResponse(endPoint, 'GET', Date.now() - requestStartedAt, response.status);
        return {
            success: true,
            data: responseData,
            status: response.status,
        };
    } catch (error: any) {
        if (error?.name === 'AbortError' || options.signal?.aborted) {
            throw error;
        }

        const errorPayload = await normalizeErrorPayload(error);
        console.log(errorPayload.message);
        reportApiIssue({
            endpoint: endPoint,
            method: 'GET',
            httpStatus: errorPayload.status,
            message: errorPayload.message,
            errorCode: error?.name,
            requestDurationMs: Date.now() - requestStartedAt,
            metadata: {response: errorPayload.data},
        });

        return {
            success: false,
            error: errorPayload.message,
            data: errorPayload.data,
            status: errorPayload.status,
        };
    }
};

export const putApi = async (endPoint: string, data: any, headers?: {}): Promise<ApiResponse> => {
    const requestStartedAt = Date.now();

    return api.put(endPoint, {
        json: data,
        headers,
    }).then(async response => {
        const responseData = await parseResponsePayload(response);
        logSlowApiResponse(endPoint, 'PUT', Date.now() - requestStartedAt, response.status);
        return {
            success: true,
            data: responseData,
        };
    }).catch(async error => {
        handleError(error);
        const errorPayload = await normalizeErrorPayload(error);
        reportApiIssue({
            endpoint: endPoint,
            method: 'PUT',
            httpStatus: errorPayload.status,
            message: errorPayload.message,
            errorCode: error?.name,
            requestDurationMs: Date.now() - requestStartedAt,
            metadata: {response: errorPayload.data},
        });
        return { success: false, error: errorPayload.message, data: errorPayload.data };
    });
};

const handleError = (error: any) => {
    console.log('API error =>', error);
    if (error) {
        // Handle timeout
        console.error('Request timed out:', error);
    } else if (error instanceof Error) {
        // Handle other errors
        console.error('An error occurred:', error.message);
    } else {
        console.error('Network Error');
    }
};
