import {getSquadMemberTier} from '../source/utils/squadMemberTier';

describe('getSquadMemberTier', () => {
  it('maps each population threshold to the correct badge tier', () => {
    const boundaries: Array<[number, string]> = [
      [0, 'Rising'],
      [499, 'Rising'],
      [500, '500 Trailblazer'],
      [999, '500 Trailblazer'],
      [1000, '1K Vanguard'],
      [9999, '1K Vanguard'],
      [10000, '10K Titan'],
      [99999, '10K Titan'],
      [100000, '100K Legion'],
    ];

    boundaries.forEach(([members, expected]) => {
      expect(getSquadMemberTier(members).label).toBe(expected);
    });
  });
});
