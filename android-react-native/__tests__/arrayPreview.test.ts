import {describe, expect, it} from '@jest/globals';
import {
  describeArrayPayload,
  getArrayOrEmpty,
  getArrayPreview,
} from '../source/utils/arrayPreview';

describe('getArrayPreview', () => {
  it('returns only the requested number of array items', () => {
    expect(getArrayPreview([1, 2, 3, 4], 3)).toEqual([1, 2, 3]);
  });

  it.each([null, undefined, 'not an array', {data: [1, 2]}])(
    'returns an empty list for a non-array API payload: %p',
    payload => {
      expect(getArrayPreview(payload, 3)).toEqual([]);
    },
  );

  it('keeps Home safe when get-task returns a nested error string', () => {
    const response = {
      data: {
        data: 'Task service is temporarily unavailable.',
      },
    };
    const rawTasks = response.data.data ?? response.data;

    expect(getArrayPreview(rawTasks, 3)).toEqual([]);
  });

  it('returns an empty list for a negative preview size', () => {
    expect(getArrayPreview([1, 2], -1)).toEqual([]);
  });

  it('normalizes a malformed leaderboard response to an empty list', () => {
    expect(getArrayOrEmpty({top50Miners: 'temporarily unavailable'})).toEqual(
      [],
    );
  });

  it('describes invalid payloads without including response content', () => {
    expect(describeArrayPayload('<html>private response</html>')).toEqual({
      type: 'string',
      character_count: 29,
      looks_like_html: true,
    });
    expect(describeArrayPayload({data: [1], secret: 'not logged'})).toEqual({
      type: 'object',
      keys: ['data', 'secret'],
    });
  });
});
