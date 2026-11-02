import {shouldShowSquadJoinOptions} from '../source/utils/squadScreenState';

describe('squad screen empty state', () => {
  it('offers join/create only after a successful response confirms no squad', () => {
    expect(shouldShowSquadJoinOptions({tab: 'mine', isLoading: false, isError: false, hasSquad: false})).toBe(true);
  });

  it('does not offer join/create while membership is loading or failed', () => {
    expect(shouldShowSquadJoinOptions({tab: 'mine', isLoading: true, isError: false, hasSquad: false})).toBe(false);
    expect(shouldShowSquadJoinOptions({tab: 'mine', isLoading: false, isError: true, hasSquad: false})).toBe(false);
  });

  it('does not offer join/create on other tabs or for existing members', () => {
    expect(shouldShowSquadJoinOptions({tab: 'discover', isLoading: false, isError: false, hasSquad: false})).toBe(false);
    expect(shouldShowSquadJoinOptions({tab: 'mine', isLoading: false, isError: false, hasSquad: true})).toBe(false);
  });
});
