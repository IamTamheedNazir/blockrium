import {
  HomeRequestStage,
  scheduleHomeRequestStages,
} from '../source/utils/homeRequestSchedule';

describe('Home request schedule', () => {
  beforeEach(() => {
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('releases optional Home requests in staged order after interactions', () => {
    let afterInteractions!: () => void;
    const readyStages: HomeRequestStage[] = [];
    const cancelInteractions = jest.fn();
    const cancel = scheduleHomeRequestStages(callback => {
      afterInteractions = callback;
      return {cancel: cancelInteractions};
    }, stage => readyStages.push(stage));

    expect(readyStages).toEqual([]);
    afterInteractions();
    expect(readyStages).toEqual([]);

    jest.advanceTimersByTime(299);
    expect(readyStages).toEqual([]);
    jest.advanceTimersByTime(1);
    expect(readyStages).toEqual(['activity']);
    jest.advanceTimersByTime(599);
    expect(readyStages).toEqual(['activity']);
    jest.advanceTimersByTime(1);
    expect(readyStages).toEqual(['activity', 'squad']);
    jest.advanceTimersByTime(699);
    expect(readyStages).toEqual(['activity', 'squad']);
    jest.advanceTimersByTime(1);
    expect(readyStages).toEqual(['activity', 'squad', 'secondary']);

    cancel();
    expect(cancelInteractions).toHaveBeenCalledTimes(1);
  });

  it('cancels pending stages when Home unmounts', () => {
    let afterInteractions!: () => void;
    const readyStages: HomeRequestStage[] = [];
    const cancel = scheduleHomeRequestStages(callback => {
      afterInteractions = callback;
      return {cancel: jest.fn()};
    }, stage => readyStages.push(stage));

    afterInteractions();
    cancel();
    jest.runOnlyPendingTimers();

    expect(readyStages).toEqual([]);
  });
});
