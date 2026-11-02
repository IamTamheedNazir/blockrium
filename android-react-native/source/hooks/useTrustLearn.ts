import {useMutation, useQuery, useQueryClient} from 'react-query';
import {getApi, postApi} from '../storage/server';
import {
  LearnCompleteResult,
  LearnModule,
  LearnModuleDetail,
  LearnQuestion,
  TrustCenterData,
  TrustCenterSection,
} from '../types/trustLearn';
import {invalidateEngagementQueries} from './useEngagement';

const TRUST_LEARN_REQUEST_TIMEOUT_MS = 8000;

export const trustLearnQueryKeys = {
  trustCenter: ['trust-learn', 'trust-center'] as const,
  learnModules: ['trust-learn', 'learn-modules'] as const,
  learnModuleDetail: (id: number | string) =>
    ['trust-learn', 'learn-module-detail', String(id)] as const,
};

const isObject = (value: unknown): value is Record<string, any> =>
  Boolean(value && typeof value === 'object' && !Array.isArray(value));

const asArray = (value: unknown): any[] => (Array.isArray(value) ? value : []);

const asString = (value: unknown, fallback = '') => {
  if (typeof value === 'string') {
    return value.trim() || fallback;
  }
  if (typeof value === 'number' && Number.isFinite(value)) {
    return String(value);
  }
  return fallback;
};

const asNumber = (value: unknown, fallback = 0) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
};

const asBoolean = (value: unknown) =>
  value === true || value === 1 || value === '1' || value === 'true';

const getMessage = (value: any, fallback: string) =>
  asString(value?.message ?? value?.error ?? value?.msg, fallback);

const unwrapResponse = (response: any, fallbackMessage: string) => {
  if (!response?.success) {
    throw new Error(getMessage(response, fallbackMessage));
  }

  const body = response?.data;
  if (!isObject(body)) {
    throw new Error(fallbackMessage);
  }

  if (body.success === false || Number(body.status ?? 1) === 0) {
    throw new Error(getMessage(body, fallbackMessage));
  }

  const payload = isObject(body.data) ? body.data : body;
  if (!isObject(payload)) {
    throw new Error(fallbackMessage);
  }

  return payload;
};

const normalizeSection = (value: any, index: number): TrustCenterSection => ({
  key: asString(value?.key ?? value?.id, `section-${index}`),
  title: asString(value?.title ?? value?.name, 'Blockrium safety note'),
  body: asString(value?.body ?? value?.description),
  sortOrder: asNumber(value?.sort_order ?? value?.sortOrder, index),
});

const normalizeModule = (value: any): LearnModule => {
  const id = asNumber(value?.id);
  const rewardBrc = asNumber(value?.reward_brc ?? value?.rewardBrc);

  return {
    id,
    key: asString(value?.key ?? value?.slug, `module-${id || 'learn'}`),
    title: asString(value?.title ?? value?.name, 'Learning module'),
    description: asString(value?.description ?? value?.subtitle),
    youtubeUrl: asString(value?.youtube_url ?? value?.youtubeUrl) || undefined,
    youtubeVideoId:
      asString(value?.youtube_video_id ?? value?.youtubeVideoId) || undefined,
    estimatedMinutes: asNumber(
      value?.estimated_minutes ?? value?.estimatedMinutes,
    ),
    rewardXp: asNumber(value?.reward_xp ?? value?.rewardXp),
    rewardBrc,
    status: asString(value?.status, 'available'),
    completed: asBoolean(value?.completed ?? value?.is_completed),
    locked: asBoolean(value?.locked ?? value?.is_locked),
    sortOrder: asNumber(value?.sort_order ?? value?.sortOrder),
  };
};

const normalizeQuestion = (value: any, index: number): LearnQuestion => {
  const options = isObject(value?.options) ? value.options : {};

  return {
    id: asNumber(value?.id ?? value?.question_id),
    question: asString(value?.question ?? value?.title, 'Question'),
    options: {
      A: asString(options.A ?? options.a),
      B: asString(options.B ?? options.b),
      C: asString(options.C ?? options.c),
      D: asString(options.D ?? options.d),
    },
    sortOrder: asNumber(value?.sort_order ?? value?.sortOrder, index),
  };
};

const fetchTrustCenter = async (signal?: AbortSignal): Promise<TrustCenterData> => {
  const payload = unwrapResponse(
    await getApi('trust-center', {
      signal,
      timeout: TRUST_LEARN_REQUEST_TIMEOUT_MS,
    }),
    'Trust Center is unavailable right now.',
  );

  const sections = asArray(payload.sections)
    .filter(isObject)
    .map(normalizeSection)
    .filter(section => section.title || section.body)
    .sort((a, b) => a.sortOrder - b.sortOrder);

  const links = isObject(payload.links) ? payload.links : {};

  return {
    sections,
    links: {
      privacyPolicy:
        asString(links.privacy_policy ?? links.privacyPolicy) || undefined,
      terms: asString(links.terms) || undefined,
      deleteAccount:
        asString(links.delete_account ?? links.deleteAccount) || undefined,
      support: asString(links.support) || undefined,
    },
    updatedAt: asString(payload.updated_at ?? payload.updatedAt) || undefined,
  };
};

const fetchLearnModules = async (signal?: AbortSignal): Promise<LearnModule[]> => {
  const payload = unwrapResponse(
    await getApi('auth/learn/modules', {
      signal,
      timeout: TRUST_LEARN_REQUEST_TIMEOUT_MS,
    }),
    'Learning modules are unavailable right now.',
  );

  return asArray(payload.modules)
    .filter(isObject)
    .map(normalizeModule)
    .filter(module => module.id > 0)
    .sort((a, b) => a.sortOrder - b.sortOrder);
};

const fetchLearnModuleDetail = async (
  moduleId: number | string,
  signal?: AbortSignal,
): Promise<LearnModuleDetail> => {
  const payload = unwrapResponse(
    await getApi(`auth/learn/modules/${encodeURIComponent(String(moduleId))}`, {
      signal,
      timeout: TRUST_LEARN_REQUEST_TIMEOUT_MS,
    }),
    'This learning module is unavailable right now.',
  );
  const rawModule = isObject(payload.module) ? payload.module : {};
  const rawProgress = isObject(payload.progress) ? payload.progress : {};

  return {
    module: {
      ...normalizeModule(rawModule),
      passingScore: asNumber(rawModule.passing_score ?? rawModule.passingScore),
      content: asString(rawModule.content) || undefined,
    },
    questions: asArray(payload.questions)
      .filter(isObject)
      .map(normalizeQuestion)
      .filter(question => question.id > 0)
      .sort((a, b) => a.sortOrder - b.sortOrder),
    progress: {
      status: asString(rawProgress.status, 'available'),
      completed: asBoolean(rawProgress.completed ?? rawProgress.is_completed),
      score: asNumber(rawProgress.score),
      completedAt:
        asString(rawProgress.completed_at ?? rawProgress.completedAt) ||
        undefined,
      rewardClaimedAt:
        asString(
          rawProgress.reward_claimed_at ?? rawProgress.rewardClaimedAt,
        ) || undefined,
    },
  };
};

export const useTrustCenter = () =>
  useQuery(trustLearnQueryKeys.trustCenter, ({signal}) => fetchTrustCenter(signal), {
    staleTime: 300000,
    retry: 0,
    refetchOnReconnect: true,
  });

export const useLearnModules = () =>
  useQuery(trustLearnQueryKeys.learnModules, ({signal}) => fetchLearnModules(signal), {
    staleTime: 60000,
    retry: 0,
    refetchOnReconnect: true,
  });

export const useLearnModuleDetail = (moduleId?: number) =>
  useQuery(
    trustLearnQueryKeys.learnModuleDetail(moduleId ?? 'none'),
    ({signal}) => fetchLearnModuleDetail(moduleId ?? 0, signal),
    {
      enabled: Boolean(moduleId),
      staleTime: 30000,
      retry: 0,
      refetchOnReconnect: true,
    },
  );

export const useCompleteLearnModule = () => {
  const queryClient = useQueryClient();

  return useMutation<
    LearnCompleteResult,
    Error,
    {moduleId: number; answers: {question_id: number; selected_option: string}[]}
  >(
    async ({moduleId, answers}) => {
      if (!moduleId || answers.length === 0) {
        throw new Error('Complete the lesson answers before submitting.');
      }

      const response = await postApi(
        `auth/learn/modules/${encodeURIComponent(String(moduleId))}/complete`,
        {answers},
      );
      const body = response?.data;
      const message = getMessage(
        response?.success ? body : response,
        'This lesson could not be completed right now.',
      );
      const alreadyCompleted = message.toLowerCase().includes('already');

      if (
        !response?.success ||
        body?.success === false ||
        Number(body?.status ?? 1) === 0
      ) {
        if (alreadyCompleted) {
          return {
            message: 'This lesson was already completed.',
            completed: true,
            score: 100,
            rewardXp: 0,
            rewardBrc: 0,
            alreadyCompleted: true,
          };
        }
        throw new Error(message);
      }

      const payload = isObject(body?.data) ? body.data : {};

      return {
        message,
        completed: asBoolean(payload.completed ?? true),
        score: asNumber(payload.score),
        rewardXp: asNumber(payload.reward_xp ?? payload.rewardXp),
        rewardBrc: asNumber(payload.reward_brc ?? payload.rewardBrc),
        alreadyCompleted: false,
      };
    },
    {
      onSuccess: async (_result, variables) => {
        await Promise.all([
          queryClient.invalidateQueries(trustLearnQueryKeys.learnModules),
          queryClient.invalidateQueries(
            trustLearnQueryKeys.learnModuleDetail(variables.moduleId),
          ),
          invalidateEngagementQueries(queryClient, {
            wallet: true,
            tasks: true,
            onboardingQuest: true,
          }),
        ]);
      },
    },
  );
};
