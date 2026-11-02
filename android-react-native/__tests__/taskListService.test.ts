import {getApi} from '../source/storage/server';
import {fetchTaskList} from '../source/services/taskListService';

jest.mock('../source/storage/server', () => ({
  getApi: jest.fn(),
}));

describe('task list requests', () => {
  const mockedGetApi = getApi as jest.MockedFunction<typeof getApi>;

  beforeEach(() => mockedGetApi.mockReset());

  it('preserves a successful empty task response', async () => {
    const response = {success: true, data: {success: true, data: []}};
    mockedGetApi.mockResolvedValue(response);

    await expect(fetchTaskList()).resolves.toBe(response);
    expect(mockedGetApi).toHaveBeenCalledWith('auth/get-task', {
      signal: undefined,
      timeout: 8000,
    });
  });

  it('throws transport failures so query consumers can show a retry state', async () => {
    mockedGetApi.mockResolvedValue({success: false, error: 'Service unavailable'});

    await expect(fetchTaskList()).rejects.toThrow('Service unavailable');
  });

  it('throws an unsuccessful application envelope', async () => {
    mockedGetApi.mockResolvedValue({
      success: true,
      data: {success: false, message: 'Tasks are temporarily unavailable.'},
    });

    await expect(fetchTaskList()).rejects.toThrow('Tasks are temporarily unavailable.');
  });

  it('forwards query cancellation to the request', async () => {
    const controller = new AbortController();
    mockedGetApi.mockResolvedValue({success: true, data: {data: []}});

    await fetchTaskList(controller.signal);

    expect(mockedGetApi).toHaveBeenCalledWith('auth/get-task', {
      signal: controller.signal,
      timeout: 8000,
    });
  });
});
