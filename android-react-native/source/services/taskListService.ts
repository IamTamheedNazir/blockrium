import {getApi} from '../storage/server';

export const TASK_LIST_REQUEST_TIMEOUT_MS = 8000;

export const fetchTaskList = async (signal?: AbortSignal) => {
  const response = await getApi('auth/get-task', {
    signal,
    timeout: TASK_LIST_REQUEST_TIMEOUT_MS,
  });
  const payload = response?.data;

  if (
    !response?.success ||
    payload?.success === false ||
    Number(payload?.status ?? 1) === 0
  ) {
    const message =
      (typeof response?.error === 'string' && response.error) ||
      (typeof payload?.message === 'string' && payload.message) ||
      (typeof payload?.error === 'string' && payload.error) ||
      'Tasks are temporarily unavailable. Please retry.';

    throw new Error(message);
  }

  return response;
};
