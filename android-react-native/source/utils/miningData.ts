export type MiningTaskPreview = {
  id: string;
  title: string;
  reward: string;
  done: boolean;
};

const isTaskCompleted = (task: any) =>
  Boolean(
    task?.is_claimed ||
      task?.claimed ||
      task?.completed ||
      task?.status === 'claimed' ||
      task?.status === 'completed' ||
      task?.task_status === 'claimed' ||
      task?.task_status === 'completed',
  );

export const getMiningTaskPreview = (
  response: unknown,
): MiningTaskPreview[] => {
  if (!response || typeof response !== 'object') {
    return [];
  }

  const responseRecord = response as any;
  if (
    responseRecord.success === false ||
    responseRecord.data?.success === false
  ) {
    return [];
  }

  const payload = responseRecord.data;
  const tasks = Array.isArray(payload?.data)
    ? payload.data
    : Array.isArray(payload)
    ? payload
    : [];
  const seenTaskIds = new Set<string>();
  const uniqueTasks = tasks.filter((task: any, index: number) => {
    const key =
      task?.id === null || task?.id === undefined
        ? `index:${index}`
        : `id:${task.id}`;

    if (seenTaskIds.has(key)) {
      return false;
    }

    seenTaskIds.add(key);
    return true;
  });

  return uniqueTasks.slice(0, 4).map((task: any, index: number) => ({
    id: String(task?.id ?? index),
    title: task?.task_name ?? 'Daily mining task',
    reward: `+${task?.coin_reward ?? 0} BRC`,
    done: isTaskCompleted(task),
  }));
};

export const readWalletBalance = (wallet: unknown, field: string) => {
  if (!wallet || typeof wallet !== 'object') {
    return null;
  }

  const value = (wallet as Record<string, unknown>)[field];
  if (
    (typeof value !== 'number' && typeof value !== 'string') ||
    value === ''
  ) {
    return null;
  }

  const numericValue = Number(value);
  return Number.isFinite(numericValue) ? numericValue : null;
};
