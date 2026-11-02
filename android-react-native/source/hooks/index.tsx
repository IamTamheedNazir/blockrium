import {useEffect} from 'react';
import {useMutation, useQuery} from 'react-query';
import {switchRequestType} from '../storage/server/ApiService';
import type {ApiResponse} from '../storage/server';
import {POST} from '../utils/constants';

export const useAlert = (
  selectSnack: any,
  snackTitle: string | null,
  timer: NodeJS.Timeout | null,
  onCloseSnack: (wait?: number) => void,
  setSnackTitle: (value: string) => void,
  setSnackMode: (value: any) => void,
  setTimer: (timer: NodeJS.Timeout | null) => void, // Add setTimer here
) => {
  useEffect(() => {
    if (selectSnack.called) {
      const { title, mode } = selectSnack;
      if (snackTitle) {
        onCloseSnack();
      }
      if (timer !== null) {
        clearTimeout(timer);
      }

      if (title) {
        const newTimer = setTimeout(
          () => {
            setSnackTitle(title);
            setSnackMode(mode);
          },
          snackTitle === '' ? 0 : 300,
        );
        setTimer(newTimer);
      }
    }
  }, [selectSnack, snackTitle, timer, onCloseSnack, setSnackTitle, setSnackMode, setTimer]);
};


export const useAppQuery = (endPoint: string, options: any = {}) => {
  const {queryFn, requestTimeoutMs, ...queryOptions} = options;
  const {data, error, isLoading, isFetching, refetch} = useQuery<
    ApiResponse,
    unknown,
    ApiResponse,
    [string, string]
  >(
    [endPoint, 'GET'],
    queryFn ??
      (({signal}: {signal?: AbortSignal}) =>
        switchRequestType('GET', endPoint, undefined, {
          signal,
          timeout: requestTimeoutMs,
        })()),
    {
      refetchOnWindowFocus: false,
      refetchOnReconnect: true,
      retry: 1,
      retryDelay: attempt => Math.min(750 * 2 ** attempt, 1500),
      ...queryOptions,
    },
  );

  return {data, error, isLoading, isFetching, refetch};
};


export const useAppMutation = (
  endPoint: string,
  body: any,
  requestType?: string,
  options: any = {},
) => {
  const method = requestType ?? POST;

  const {data, error, isLoading, mutate, mutateAsync, reset} = useMutation<
    ApiResponse,
    unknown,
    void
  >(
    () => switchRequestType(method, endPoint, body)(),
    {
      retry: 0,
      ...options,
    },
  );

  return {data, error, isLoading, mutate, mutateAsync, reset};
};

export const callQuery = useAppQuery;
export const callMutation = useAppMutation;
