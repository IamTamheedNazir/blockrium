export type OnboardingQuestStep = {
  key: string;
  title: string;
  description?: string;
  action?: string;
  completed: boolean;
};

export type OnboardingQuest = {
  title: string;
  subtitle?: string;
  reward_amount: number;
  reward_label?: string;
  steps: OnboardingQuestStep[];
  completed_count: number;
  total_steps: number;
  progress_percent: number;
  is_completed: boolean;
  claimed: boolean;
  can_claim: boolean;
};

const isRecord = (value: unknown): value is Record<string, unknown> =>
  Boolean(value && typeof value === 'object' && !Array.isArray(value));

const readText = (value: unknown) =>
  typeof value === 'string' ? value.trim() : '';

const readCount = (value: unknown, fallback = 0) => {
  const count = Number(value);
  return Number.isFinite(count) ? count : fallback;
};

const readBoolean = (value: unknown) =>
  value === true || value === 1 || value === '1' || value === 'true';

export const normalizeOnboardingQuest = (
  value: unknown,
): OnboardingQuest | undefined => {
  if (!isRecord(value)) {
    return undefined;
  }

  const title = readText(value.title);
  if (!title || !Array.isArray(value.steps)) {
    return undefined;
  }

  const steps = value.steps.reduce<OnboardingQuestStep[]>((result, step, index) => {
    if (!isRecord(step)) {
      return result;
    }

    const stepTitle = readText(step.title);
    if (!stepTitle) {
      return result;
    }

    result.push({
      key: readText(step.key) || `step-${index + 1}`,
      title: stepTitle,
      description: readText(step.description) || undefined,
      action: readText(step.action) || undefined,
      completed: readBoolean(step.completed),
    });
    return result;
  }, []);

  const totalSteps = Math.max(steps.length, readCount(value.total_steps, steps.length));
  const completedCount = Math.max(
    0,
    Math.min(totalSteps, readCount(value.completed_count)),
  );
  const progressPercent = Math.max(
    0,
    Math.min(100, readCount(value.progress_percent)),
  );

  return {
    title,
    subtitle: readText(value.subtitle) || undefined,
    reward_amount: readCount(value.reward_amount),
    reward_label: readText(value.reward_label) || undefined,
    steps,
    completed_count: completedCount,
    total_steps: totalSteps,
    progress_percent: progressPercent,
    is_completed: readBoolean(value.is_completed),
    claimed: readBoolean(value.claimed),
    can_claim: readBoolean(value.can_claim),
  };
};
