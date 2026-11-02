import {getCommunitySquadShortcutCopy} from '../source/utils/communitySquadShortcut';

describe('Community squad shortcut', () => {
  it('does not suggest finding a squad while membership is loading', () => {
    expect(getCommunitySquadShortcutCopy({isLoading: true, isError: false})).toEqual({
      title: 'Loading your squad',
      meta: '',
    });
  });

  it('does not suggest finding a squad when membership could not be loaded', () => {
    expect(getCommunitySquadShortcutCopy({isLoading: false, isError: true})).toEqual({
      title: 'Squad unavailable',
      meta: '',
    });
  });

  it('shows the current squad and member count when membership is known', () => {
    expect(getCommunitySquadShortcutCopy({
      isLoading: false,
      isError: false,
      squad: {name: 'Blockrium Core', memberCount: 558},
    })).toEqual({title: 'Blockrium Core', meta: '558 members'});
  });

  it('does not suggest discovery when a malformed response confirms membership without a profile', () => {
    expect(getCommunitySquadShortcutCopy({isLoading: false, isError: false, hasSquad: true})).toEqual({
      title: 'Squad unavailable',
      meta: '',
    });
  });

  it('offers discovery only after a successful empty-membership response', () => {
    expect(getCommunitySquadShortcutCopy({isLoading: false, isError: false})).toEqual({
      title: 'Find a squad',
      meta: 'Create or join a squad',
    });
  });
});
