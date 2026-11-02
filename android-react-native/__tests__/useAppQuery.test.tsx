import React from 'react';
import renderer, {act} from 'react-test-renderer';
import {QueryClient, QueryClientProvider} from 'react-query';
import {useAppQuery} from '../source/hooks';

const mockRequest = jest.fn();

jest.mock('../source/storage/server/ApiService', () => ({
  switchRequestType: (...args: any[]) => mockRequest(...args),
}));

describe('useAppQuery', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('uses a screen-provided query function instead of the default API request', async () => {
    const queryFn = jest.fn().mockResolvedValue({success: true, data: 'plans'});
    const queryClient = new QueryClient({
      defaultOptions: {queries: {retry: false}},
    });
    let result: any;
    const Probe = () => {
      result = useAppQuery('auth/boost-plans', {queryFn, retry: 0});
      return null;
    };
    let tree!: renderer.ReactTestRenderer;

    act(() => {
      tree = renderer.create(
        <QueryClientProvider client={queryClient}>
          <Probe />
        </QueryClientProvider>,
      );
    });
    await act(async () => {
      await new Promise(resolve => setTimeout(resolve, 20));
    });

    expect(queryFn).toHaveBeenCalledTimes(1);
    expect(mockRequest).not.toHaveBeenCalled();
    expect(result.data).toEqual({success: true, data: 'plans'});

    act(() => tree.unmount());
    queryClient.clear();
  });

  it('passes React Query cancellation to the default GET request', async () => {
    let requestOptions: any;
    mockRequest.mockImplementation((_method, _endpoint, _data, options) => {
      requestOptions = options;
      return () => Promise.resolve({success: true, data: 'profile'});
    });

    const queryClient = new QueryClient({
      defaultOptions: {queries: {retry: false}},
    });
    const Probe = () => {
      useAppQuery('auth/profile', {requestTimeoutMs: 8000, retry: 0});
      return null;
    };
    let tree!: renderer.ReactTestRenderer;

    act(() => {
      tree = renderer.create(
        <QueryClientProvider client={queryClient}>
          <Probe />
        </QueryClientProvider>,
      );
    });
    await act(async () => {
      await new Promise(resolve => setTimeout(resolve, 20));
    });

    expect(mockRequest).toHaveBeenCalledWith(
      'GET',
      'auth/profile',
      undefined,
      expect.objectContaining({signal: expect.anything(), timeout: 8000}),
    );
    expect(typeof requestOptions.signal.addEventListener).toBe('function');
    expect(
      queryClient.getQueryCache().find(['auth/profile', 'GET'])?.options.retry,
    ).toBe(0);

    act(() => tree.unmount());
    queryClient.clear();
  });
});
