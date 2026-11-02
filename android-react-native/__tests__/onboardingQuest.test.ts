import {describe, expect, it} from '@jest/globals';
import {normalizeOnboardingQuest} from '../source/utils/onboardingQuest';

describe('normalizeOnboardingQuest', () => {
  it('normalizes the server quest payload', () => {
    expect(
      normalizeOnboardingQuest({
        title: 'Starter Quest',
        steps: [
          {
            key: 'mine',
            title: 'Start mining',
            description: 'Open Mining.',
            action: 'mining',
            completed: 1,
          },
        ],
        completed_count: 1,
        total_steps: 1,
        progress_percent: 100,
        is_completed: true,
        claimed: false,
        can_claim: true,
      }),
    ).toEqual({
      title: 'Starter Quest',
      subtitle: undefined,
      reward_amount: 0,
      reward_label: undefined,
      steps: [
        {
          key: 'mine',
          title: 'Start mining',
          description: 'Open Mining.',
          action: 'mining',
          completed: true,
        },
      ],
      completed_count: 1,
      total_steps: 1,
      progress_percent: 100,
      is_completed: true,
      claimed: false,
      can_claim: true,
    });
  });

  it('drops malformed steps and clamps invalid progress fields', () => {
    expect(
      normalizeOnboardingQuest({
        title: 'Starter Quest',
        steps: [null, 'bad row', {title: 'Safe step', description: {html: 'unsafe'}}],
        completed_count: 9,
        total_steps: 1,
        progress_percent: 150,
      }),
    ).toMatchObject({
      steps: [
        {
          key: 'step-3',
          title: 'Safe step',
          description: undefined,
          completed: false,
        },
      ],
      completed_count: 1,
      total_steps: 1,
      progress_percent: 100,
    });
  });

  it('rejects payloads that cannot represent a quest', () => {
    expect(normalizeOnboardingQuest({title: 'Starter Quest', steps: {}})).toBeUndefined();
    expect(normalizeOnboardingQuest({steps: []})).toBeUndefined();
  });
});
