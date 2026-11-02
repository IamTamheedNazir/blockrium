import {describe, expect, it} from '@jest/globals';
import {shouldWaitForHomeProfile} from '../source/utils/profile';

describe('shouldWaitForHomeProfile', () => {
  it('waits when a profile is loading and no cached identity exists', () => {
    expect(shouldWaitForHomeProfile(true)).toBe(true);
  });

  it('lets Home render from the cached profile while the request is pending', () => {
    expect(shouldWaitForHomeProfile(true, {name: 'Miner'})).toBe(false);
  });

  it('does not block after the profile request finishes', () => {
    expect(shouldWaitForHomeProfile(false)).toBe(false);
  });

  it('opens Home after the bounded wait if profile and cache are unavailable', () => {
    expect(shouldWaitForHomeProfile(true, undefined, true)).toBe(false);
  });
});
