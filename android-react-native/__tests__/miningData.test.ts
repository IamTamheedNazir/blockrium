import {describe, expect, it} from '@jest/globals';
import {
  getMiningTaskPreview,
  readWalletBalance,
} from '../source/utils/miningData';

describe('getMiningTaskPreview', () => {
  it('normalizes valid task data and removes duplicate task ids', () => {
    expect(
      getMiningTaskPreview({
        success: true,
        data: {
          data: [
            {id: 1, task_name: 'Daily check-in', coin_reward: 5},
            {id: 1, task_name: 'Duplicate', coin_reward: 9},
            {id: 2, task_name: 'Invite', coin_reward: 20, status: 'claimed'},
          ],
        },
      }),
    ).toEqual([
      {
        id: '1',
        title: 'Daily check-in',
        reward: '+5 BRC',
        done: false,
      },
      {id: '2', title: 'Invite', reward: '+20 BRC', done: true},
    ]);
  });

  it.each([
    undefined,
    null,
    {success: false, data: {data: [{id: 1, task_name: 'Unavailable'}]}},
    {success: true, data: '<html>server error</html>'},
    {success: true, data: {data: 'not a task list'}},
  ])('returns no fabricated tasks for an unavailable payload: %p', payload => {
    expect(getMiningTaskPreview(payload)).toEqual([]);
  });

  it('limits the mining-screen preview to four configured tasks', () => {
    const tasks = Array.from({length: 6}, (_, id) => ({id}));
    expect(getMiningTaskPreview({data: tasks})).toHaveLength(4);
  });
});

describe('readWalletBalance', () => {
  it('reads numeric and numeric-string balances', () => {
    expect(readWalletBalance({wallet_balance: 12.5}, 'wallet_balance')).toBe(
      12.5,
    );
    expect(readWalletBalance({wallet_balance: '12.5'}, 'wallet_balance')).toBe(
      12.5,
    );
  });

  it.each([undefined, null, '', true, 'not-a-number'])(
    'does not turn a missing or malformed wallet value into a zero balance: %p',
    value => {
      expect(
        readWalletBalance({wallet_balance: value}, 'wallet_balance'),
      ).toBeNull();
    },
  );
});
