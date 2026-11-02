import {useMutation, useQuery, useQueryClient} from 'react-query';
import {getApi, postApi, removeLeadsTokenFromHeader} from '../storage/server';
import {logAppIssue} from '../services/appIssueLogger';
import {invalidateEngagementQueries} from './useEngagement';
import {resetNavigation} from '../utils/helper';
import {setStorageToken} from '../utils/mmkvStorage';
import {
  MySquadData,
  Squad,
  SquadActionResult,
  SquadAnnouncement,
  SquadChestClaimResult,
  SquadFeedItem,
  SquadInviteStreak,
  SquadLeaderboardEntry,
  SquadMember,
  SquadMemberTab,
  SquadMission,
  SquadPosterTemplate,
  SquadPublicInvite,
  SquadShareCard,
  SquadShareChannel,
} from '../types/squads';

export const squadQueryKeys = {
  mine: ['squads', 'mine'] as const,
  profile: ['squads', 'profile'] as const,
  discover: ['squads', 'discover'] as const,
  leaderboard: ['squads', 'leaderboard'] as const,
  missions: ['squads', 'missions'] as const,
  members: ['squads', 'members'] as const,
  membersForTab: (tab: string) => ['squads', 'members', tab] as const,
  announcements: ['squads', 'announcements'] as const,
  shareCard: ['squads', 'share-card'] as const,
  publicInvite: (code: string) => ['squads', 'public-invite', code] as const,
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
  const result = Number(value);
  return Number.isFinite(result) ? result : fallback;
};

const asBoolean = (value: unknown) =>
  value === true || value === 1 || value === '1' || value === 'true';

const asPercent = (value: unknown) => Math.max(0, Math.min(100, asNumber(value)));

const buildPosterUrl = (inviteCode?: string) =>
  inviteCode ? `https://maticlegend.com/squad/${encodeURIComponent(inviteCode)}/poster.svg` : undefined;

const normalizeCapacity = (value: any, memberCount: number) => {
  const rawMaxMembers = value?.max_members ?? value?.maxMembers ?? value?.capacity;
  const rawCapacityLabel = asString(value?.capacity_label ?? value?.capacityLabel);
  const isUnlimited =
    asBoolean(value?.is_unlimited ?? value?.isUnlimited ?? value?.unlimited ?? value?.unlimited_capacity) ||
    rawCapacityLabel.toLowerCase() === 'unlimited' ||
    asString(rawMaxMembers).toLowerCase() === 'unlimited' ||
    asNumber(rawMaxMembers, 50) <= 0;
  const maxMembers = isUnlimited ? undefined : Math.max(1, asNumber(rawMaxMembers, 50));
  return {
    isUnlimited,
    maxMembers,
    capacityLabel: rawCapacityLabel || (isUnlimited ? 'Unlimited' : `${memberCount}/${maxMembers}`),
  };
};

const normalizeMission = (value: any): SquadMission => {
  const progress = asNumber(value?.progress ?? value?.current ?? value?.completed_count);
  const target = Math.max(1, asNumber(value?.target ?? value?.required ?? value?.total, 1));
  const rewardXp = asNumber(value?.reward_xp ?? value?.rewardXp);
  const rewardBrc = asNumber(value?.reward_brc ?? value?.rewardBrc);
  const completed = asBoolean(value?.completed ?? value?.is_completed ?? progress >= target);
  const claimed = asBoolean(value?.claimed ?? value?.is_claimed);
  const rewardLabel =
    asString(value?.reward_label ?? value?.rewardLabel ?? value?.reward) ||
    [
      rewardXp > 0 ? `${rewardXp} XP` : '',
      rewardBrc > 0 ? `${rewardBrc} BRC` : '',
    ]
      .filter(Boolean)
      .join(' + ') ||
    undefined;

  return {
    id: asNumber(value?.id ?? value?.mission_id ?? value?.missionId) || undefined,
    key: asString(value?.key ?? value?.slug ?? value?.id, 'mission'),
    title: asString(value?.title ?? value?.name, 'Squad mission'),
    description: asString(value?.description ?? value?.subtitle) || undefined,
    metric: asString(value?.metric) || undefined,
    progress,
    target,
    rewardLabel,
    rewardXp: rewardXp || undefined,
    rewardBrc: rewardBrc || undefined,
    completed,
    claimed,
    canClaim: asBoolean(value?.can_claim ?? value?.claimable) || (completed && !claimed),
  };
};

const normalizeInviteStreak = (value: any): SquadInviteStreak | undefined => {
  if (!isObject(value)) {
    return undefined;
  }
  const activeInvitesToday = asNumber(value.active_invites_today ?? value.activeInvitesToday ?? value.active_invites);
  const streakDays = asNumber(value.streak_days ?? value.streakDays ?? value.current ?? value.current_streak ?? value.invite_streak);
  const rewardXp = asNumber(value.reward_xp ?? value.rewardXp);
  const rewardBrc = asNumber(value.reward_brc ?? value.rewardBrc);
  const rewardLabel =
    asString(value.reward_label ?? value.rewardLabel ?? value.reward) ||
    [
      rewardXp > 0 ? `${rewardXp} XP` : '',
      rewardBrc > 0 ? `${rewardBrc} BRC` : '',
    ]
      .filter(Boolean)
      .join(' + ') ||
    undefined;

  return {
    current: activeInvitesToday || streakDays,
    target: Math.max(1, asNumber(value.target ?? value.required ?? value.target_count, 1)),
    activeInvitesToday,
    streakDays,
    rewardLabel,
    rewardXp: rewardXp || undefined,
    rewardBrc: rewardBrc || undefined,
    canClaim: asBoolean(value.can_claim ?? value.claimable),
    claimed: asBoolean(value.claimed ?? value.is_claimed),
  };
};

const normalizeAnnouncement = (value: any): SquadAnnouncement => ({
  id: value?.id,
  title: asString(value?.title ?? value?.template_title, 'Squad update'),
  body: asString(value?.body ?? value?.message ?? value?.description, 'New squad update from Blockrium.'),
  template: asString(value?.template ?? value?.template_key) || undefined,
  ctaLabel: asString(value?.cta_label ?? value?.ctaLabel) || undefined,
});

const normalizePosterTemplate = (value: any): SquadPosterTemplate => ({
  key: asString(value?.key ?? value?.template ?? value?.id, 'poster'),
  title: asString(value?.title ?? value?.name, 'Poster'),
  url: asString(value?.url ?? value?.poster_url ?? value?.posterUrl) || undefined,
});

const allowedShareChannels: SquadShareChannel[] = [
  'copy_link',
  'whatsapp',
  'telegram',
  'instagram',
  'facebook',
  'x',
  'sms',
  'email',
  'other',
];

let didHandleSquadSessionExpiry = false;

const getMessage = (value: any, fallback: string) =>
  asString(value?.message ?? value?.error ?? value?.msg, fallback);

const getSquadApi = async (endpoint: string) => {
  const controller = new AbortController();
  let timeoutId: ReturnType<typeof setTimeout> | undefined;
  try {
    return await Promise.race([
      getApi(endpoint, {signal: controller.signal}),
      new Promise<never>((_, reject) => {
        timeoutId = setTimeout(() => {
          controller.abort();
          reject(Object.assign(new Error('Squad service timed out. Please retry.'), {status: 408}));
        }, 8000);
      }),
    ]);
  } finally {
    if (timeoutId) {
      clearTimeout(timeoutId);
    }
  }
};

const isSessionExpiredResponse = (response: any) => {
  const message = getMessage(response?.success === false ? response : response?.data, '');
  const status = Number(
    response?.status ??
      response?.data?.status ??
      response?.data?.code ??
      response?.data?.http_status ??
      0,
  );

  return (
    status === 401 ||
    /session expired|unauthenticated|unauthorized|please log in/i.test(message)
  );
};

const handleSquadSessionExpiry = () => {
  if (didHandleSquadSessionExpiry) {
    return;
  }

  didHandleSquadSessionExpiry = true;
  removeLeadsTokenFromHeader();
  setStorageToken('authToken', '')
    .catch(error => {
      console.log('Unable to clear expired squad token', error);
    })
    .finally(() => {
      resetNavigation('Login');
      setTimeout(() => {
        didHandleSquadSessionExpiry = false;
      }, 2500);
    });
};

const reportSquadLoadIssue = (
  action: string,
  endpoint: string,
  error: unknown,
  method = 'GET',
) => {
  logAppIssue({
    severity: 'error',
    source: 'squads',
    action,
    endpoint,
    method,
    message: error instanceof Error ? error.message : String(error || 'Squad load failed.'),
  }).catch(logError => {
    console.log('Unable to log squad issue', logError);
  });
};

const unwrapResponse = (response: any, fallbackMessage: string) => {
  if (isSessionExpiredResponse(response)) {
    handleSquadSessionExpiry();
    throw new Error('Session expired. Please log in again.');
  }

  if (!response?.success) {
    const message = getMessage(response, fallbackMessage);
    throw Object.assign(new Error(
      /network request failed|failed to fetch/i.test(message)
        ? 'Could not reach the Blockrium squads service. Check your connection or sign in again, then retry.'
        : message,
    ), {
      status: Number(response?.status ?? 0),
    });
  }

  const body = response.data;
  if (!isObject(body)) {
    throw new Error(fallbackMessage);
  }
  if (body.success === false || Number(body.status ?? 1) === 0) {
    throw Object.assign(new Error(getMessage(body, fallbackMessage)), {
      status: Number(body?.status ?? response?.status ?? 0),
    });
  }

  const payload = isObject(body.data) ? body.data : body;
  if (!isObject(payload)) {
    throw new Error(fallbackMessage);
  }

  return payload;
};

const fetchSquadHomePayload = async (
  endpoints = ['auth/squads/app-home', 'auth/squads/home', 'auth/squads/me'],
): Promise<Record<string, any>> => {
  let lastError: unknown;
  let lastEndpoint = endpoints[0] ?? 'auth/squads/app-home';

  for (const endpoint of endpoints) {
    lastEndpoint = endpoint;
    try {
      const response = await getSquadApi(endpoint);
      return unwrapResponse(
        response,
        'Squad details are unavailable right now.',
      );
    } catch (error) {
      lastError = error;
      reportSquadLoadIssue('squad_home_load_failed', endpoint, error);

      if (
        error instanceof Error &&
        /session expired|please log in/i.test(error.message)
      ) {
        throw error;
      }

      const status = Number((error as any)?.status ?? 0);
      // Alternate routes only help when the canonical route is not installed.
      // Retrying them for outages or timeouts multiplies the spinner duration.
      const canTryFallback = status === 404 || status === 405;
      if (!canTryFallback) {
        throw error;
      }
    }
  }

  throw new Error(
    lastError instanceof Error
      ? lastError.message
      : `${lastEndpoint} failed. Please try again.`,
  );
};

const normalizeMember = (value: any): SquadMember => ({
  userId: asNumber(value?.user_id ?? value?.userId ?? value?.id),
  name: asString(value?.name ?? value?.username, 'Squad member'),
  username: asString(value?.username) || undefined,
  role: asString(value?.role, 'member'),
  joinedAt: asString(value?.joined_at ?? value?.joinedAt) || undefined,
  score: asNumber(
    value?.score ??
      value?.points ??
      value?.weekly_points ??
      value?.weeklyPoints ??
      value?.referrals ??
      value?.mined,
  ) || undefined,
});

const formatMemberTabLabel = (key: string) =>
  key
    .split(/[_-]/)
    .filter(Boolean)
    .map(part => `${part.slice(0, 1).toUpperCase()}${part.slice(1)}`)
    .join(' ') || 'Members';

const normalizeMemberTab = (value: any): SquadMemberTab => {
  const key = asString(value?.key ?? value?.slug ?? value?.type, 'members');
  return {
    key,
    label: asString(value?.label ?? value?.title ?? value?.name) || formatMemberTabLabel(key),
    count: asNumber(value?.count),
  };
};

const normalizeFeedItem = (value: any): SquadFeedItem => ({
  type: asString(value?.type ?? value?.event_type ?? value?.eventType, 'update'),
  title: asString(value?.title, 'Squad update'),
  body: asString(value?.body ?? value?.description) || undefined,
  status: asString(value?.status) || undefined,
});

const normalizeMemberGroups = (value: any) => ({
  leaders: asArray(value?.leaders).filter(isObject).map(normalizeMember),
  activeToday: asArray(value?.active_today ?? value?.activeToday).filter(isObject).map(normalizeMember),
  newJoiners: asArray(value?.new_joiners ?? value?.newJoiners).filter(isObject).map(normalizeMember),
  topMiners: asArray(value?.top_miners ?? value?.topMiners).filter(isObject).map(normalizeMember),
  topReferrers: asArray(value?.top_referrers ?? value?.topReferrers).filter(isObject).map(normalizeMember),
});

const normalizeSquad = (value: any): Squad => {
  const weeklyPoints = asNumber(value?.weekly_points ?? value?.weeklyPoints);
  const weeklyTargetPoints = Math.max(
    1,
    asNumber(value?.weekly_target_points ?? value?.weeklyTargetPoints, 500),
  );
  const memberCount = asNumber(value?.member_count ?? value?.memberCount);
  const capacity = normalizeCapacity(value, memberCount);
  const inviteCode = asString(value?.invite_code ?? value?.inviteCode) || undefined;

  return {
    id: asNumber(value?.id ?? value?.squad_id ?? value?.squadId),
    name: asString(value?.name, 'Blockrium Squad'),
    slug: asString(value?.slug),
    inviteCode,
    shareUrl: asString(value?.share_url ?? value?.shareUrl) || undefined,
    shareText: asString(value?.share_text ?? value?.shareText) || undefined,
    countryCode: asString(value?.country_code ?? value?.countryCode) || undefined,
    languageCode: asString(value?.language_code ?? value?.languageCode) || undefined,
    shareCount: asNumber(value?.share_count ?? value?.shareCount),
    description: asString(value?.description),
    memberCount,
    maxMembers: capacity.maxMembers,
    isUnlimited: capacity.isUnlimited,
    capacityLabel: capacity.capacityLabel,
    isPublic: asBoolean(value?.is_public ?? value?.isPublic),
    featured: asBoolean(value?.featured ?? value?.is_featured),
    promoted: asBoolean(value?.promoted ?? value?.is_promoted),
    weeklyTargetPoints,
    weeklyPoints,
    weeklyProgressPercent: asPercent(
      value?.weekly_progress_percent ?? value?.weeklyProgressPercent ??
        (weeklyPoints / weeklyTargetPoints) * 100,
    ),
    statsDeferred: asBoolean(value?.stats_deferred ?? value?.statsDeferred),
    communityBoostPercent: asNumber(
      value?.community_boost_percent ?? value?.communityBoostPercent,
    ),
    chestUnlocked: asBoolean(value?.chest_unlocked ?? value?.chestUnlocked),
    chestClaimed: asBoolean(value?.chest_claimed ?? value?.chestClaimed),
    boostPercent: asNumber(value?.boost_percent ?? value?.boostPercent) || undefined,
    posterUrl: asString(value?.poster_url ?? value?.posterUrl) || buildPosterUrl(inviteCode),
    qrUrl: asString(value?.qr_url ?? value?.qrUrl) || undefined,
    countryRank: asNumber(value?.country_rank ?? value?.countryRank) || undefined,
    countryName: asString(value?.country_name ?? value?.countryName) || undefined,
    weekStart: asString(value?.week_start ?? value?.weekStart) || undefined,
    weekEnd: asString(value?.week_end ?? value?.weekEnd) || undefined,
    members: asArray(value?.members).filter(isObject).map(normalizeMember),
    memberGroups: normalizeMemberGroups(value?.member_groups ?? value?.memberGroups),
    social: isObject(value?.social)
      ? {
          followersCount: asNumber(value.social.followers_count ?? value.social.followersCount),
          following: asBoolean(value.social.following ?? value.social.is_following ?? value.social.isFollowing),
          feedEnabled: asBoolean(value.social.feed_enabled ?? value.social.feedEnabled),
          postFeedStatus: asString(value.social.post_feed_status ?? value.social.postFeedStatus) || undefined,
          postFeedNote: asString(value.social.post_feed_note ?? value.social.postFeedNote) || undefined,
        }
      : undefined,
    activityBreakdown: isObject(value?.activity_breakdown ?? value?.activityBreakdown)
      ? (value.activity_breakdown ?? value.activityBreakdown)
      : {},
    weeklyMissions: asArray(value?.weekly_missions ?? value?.weeklyMissions ?? value?.missions)
      .filter(isObject)
      .map(normalizeMission),
    inviteStreak: normalizeInviteStreak(value?.invite_streak ?? value?.inviteStreak),
    announcements: asArray(value?.announcements).filter(isObject).map(normalizeAnnouncement),
  };
};

const normalizeLeaderboard = (value: any): SquadLeaderboardEntry => ({
  squadId: asNumber(value?.squad_id ?? value?.squadId ?? value?.id),
  name: asString(value?.name, 'Blockrium Squad'),
  memberCount: asNumber(value?.member_count ?? value?.memberCount),
  weeklyPoints: asNumber(value?.weekly_points ?? value?.weeklyPoints),
  communityBoostPercent: asNumber(
    value?.community_boost_percent ?? value?.communityBoostPercent,
  ),
  chestUnlocked: asBoolean(value?.chest_unlocked ?? value?.chestUnlocked),
  countryCode: asString(value?.country_code ?? value?.countryCode) || undefined,
  countryName: asString(value?.country_name ?? value?.countryName) || undefined,
  rankLabel: asString(value?.rank_label ?? value?.rankLabel) || undefined,
});

const normalizeShareCard = (value: any): SquadShareCard => {
  const squad = isObject(value?.squad) ? value.squad : {};
  const poster = isObject(value?.poster) ? value.poster : {};
  const shareOptions = isObject(value?.share_options ?? value?.shareOptions)
    ? value.share_options ?? value.shareOptions
    : {};
  const channels = asArray(shareOptions.channels)
    .map(item => asString(item) as SquadShareChannel)
    .filter(channel => allowedShareChannels.includes(channel));
  const shareUrl = asString(squad.share_url ?? squad.shareUrl);
  const inviteCode = asString(squad.invite_code ?? squad.inviteCode) || undefined;
  const memberCount = asNumber(squad.member_count ?? squad.memberCount);
  const capacity = normalizeCapacity(squad, memberCount);
  const shareText =
    asString(squad.share_text ?? squad.shareText) ||
    (shareUrl
      ? `Join my Blockrium squad and help unlock weekly activity chests. ${shareUrl}`
      : 'Join my Blockrium squad and help unlock weekly activity chests.');

  return {
    squad: {
      inviteCode,
      shareUrl: shareUrl || undefined,
      shareText,
      countryCode: asString(squad.country_code ?? squad.countryCode) || undefined,
      languageCode: asString(squad.language_code ?? squad.languageCode) || undefined,
      memberCount,
      maxMembers: capacity.maxMembers,
      isUnlimited: capacity.isUnlimited,
      capacityLabel: capacity.capacityLabel,
      weeklyPoints: asNumber(squad.weekly_points ?? squad.weeklyPoints ?? value?.weekly_points ?? value?.weeklyPoints),
      boostPercent: asNumber(
        squad.boost_percent ?? squad.boostPercent ?? squad.community_boost_percent ?? squad.communityBoostPercent,
      ),
    },
    poster: {
      url: asString(
        poster.url ??
          poster.poster_svg_url ??
          poster.posterSvgUrl ??
          poster.poster_url ??
          poster.posterUrl ??
          value?.poster_svg_url ??
          value?.posterSvgUrl ??
          value?.poster_url ??
          value?.posterUrl,
      ) ||
        buildPosterUrl(inviteCode),
      template: asString(poster.template ?? poster.template_key ?? poster.style) || undefined,
      title: asString(poster.title ?? poster.name) || undefined,
      qrUrl: asString(poster.qr_url ?? poster.qrUrl) || undefined,
      backgroundColor: asString(poster.background_color ?? poster.backgroundColor ?? poster.style?.background) || undefined,
      textColor: asString(poster.text_color ?? poster.textColor ?? poster.style?.text) || undefined,
      accentColor: asString(poster.accent_color ?? poster.accentColor ?? poster.style?.accent ?? poster.style?.primary) || undefined,
      qrText: asString(poster.qr_text ?? poster.qrText) || shareUrl || undefined,
      safeNote:
        asString(poster.safe_note ?? poster.safeNote) ||
        'Weekly squad activity is validated by Blockrium.',
      templates: asArray(poster.templates ?? value?.poster_templates ?? value?.posterTemplates)
        .filter(isObject)
        .map(normalizePosterTemplate)
        .concat(
          asArray(poster.templates ?? value?.poster_templates ?? value?.posterTemplates).length
            ? []
            : [
                {key: 'white', title: 'White'},
                {key: 'dark', title: 'Dark'},
                {key: 'country', title: 'Country'},
                {key: 'weekly_winner', title: 'Weekly Winner'},
              ],
        ),
    },
    shareOptions: {
      channels: channels.length ? channels : ['other'],
    },
  };
};

const normalizeMySquad = (payload: Record<string, any>): MySquadData => {
  const squadPayload = payload.squad ?? payload.my_squad ?? payload.mySquad ?? payload.user_squad ?? payload.userSquad;
  const hasSquad = asBoolean(payload.has_squad ?? payload.hasSquad) || isObject(squadPayload);
  const squadWithProfileData = isObject(squadPayload)
    ? {
        ...squadPayload,
        invite_streak: squadPayload.invite_streak ?? squadPayload.inviteStreak ?? payload.invite_streak ?? payload.inviteStreak,
      }
    : null;

  return {
    hasSquad,
    squad: squadWithProfileData ? normalizeSquad(squadWithProfileData) : undefined,
    membership: isObject(payload.membership)
      ? {
          role: asString(payload.membership.role, 'member'),
          joinedAt: asString(payload.membership.joined_at ?? payload.membership.joinedAt) || undefined,
        }
      : undefined,
    discover: asArray(payload.discover).filter(isObject).map(normalizeSquad).filter(squad => squad.id > 0),
    recommended: asArray(payload.recommended).filter(isObject).map(normalizeSquad).filter(squad => squad.id > 0),
    leaderboard: asArray(payload.leaderboard).filter(isObject).map(normalizeLeaderboard).filter(entry => entry.squadId > 0),
    countryLeaderboard: asArray(payload.country_leaderboard ?? payload.countryLeaderboard)
      .filter(isObject)
      .map(normalizeLeaderboard)
      .filter(entry => entry.squadId > 0),
    weeklyMissions: asArray(payload.weekly_missions ?? payload.weeklyMissions ?? payload.missions)
      .filter(isObject)
      .map(normalizeMission),
    announcements: asArray(payload.announcements).filter(isObject).map(normalizeAnnouncement),
    memberTabs: asArray(payload.member_tabs ?? payload.memberTabs).filter(isObject).map(normalizeMemberTab),
    followers: isObject(payload.followers)
      ? {
          count: asNumber(payload.followers.count ?? payload.followers.followers_count ?? payload.followers.followersCount),
          following: asBoolean(payload.followers.following ?? payload.followers.is_following ?? payload.followers.isFollowing),
        }
      : undefined,
    socialFeedPreview: asArray(payload.social_feed_preview ?? payload.socialFeedPreview).filter(isObject).map(normalizeFeedItem),
    fastLoaded: asBoolean(payload.fast_loaded ?? payload.fastLoaded),
    cacheTtlSeconds: asNumber(payload.cache_ttl_seconds ?? payload.cacheTtlSeconds) || undefined,
  };
};

const fetchMySquad = async (): Promise<MySquadData> =>
  normalizeMySquad(await fetchSquadHomePayload());

const fetchMySquadProfile = async (): Promise<MySquadData> => {
  const payload = unwrapResponse(
    await getSquadApi('auth/squads/me?profile=1'),
    'Squad profile details are unavailable right now.',
  );
  return normalizeMySquad(payload);
};

const fetchSquadList = async (endpoint: string, fallbackMessage: string) => {
  const payload = unwrapResponse(await getSquadApi(endpoint), fallbackMessage);
  return asArray(payload.squads ?? payload.leaderboard ?? payload.country_leaderboard ?? payload.data).filter(isObject);
};

const fetchSquadMembers = async (tab: string): Promise<SquadMember[]> => {
  const payload = unwrapResponse(
    await getSquadApi(`auth/squads/members?tab=${encodeURIComponent(tab)}&limit=25`),
    'Squad members are unavailable right now.',
  );
  return asArray(payload.members ?? payload.data)
    .filter(isObject)
    .map(normalizeMember);
};

const fetchDiscover = async (): Promise<Squad[]> =>
  (await fetchSquadList('auth/squads/discover', 'Squad discovery is unavailable right now.'))
    .map(normalizeSquad)
    .filter(squad => squad.id > 0);

const fetchLeaderboard = async (): Promise<SquadLeaderboardEntry[]> =>
  (await fetchSquadList('auth/squads/leaderboard', 'Squad leaderboard is unavailable right now.'))
    .map(normalizeLeaderboard)
    .filter(entry => entry.squadId > 0);

const fetchShareCard = async (): Promise<SquadShareCard> => {
  const payload = unwrapResponse(
    await getSquadApi('auth/squads/share-card'),
    'Squad share card is unavailable right now.',
  );
  return normalizeShareCard(payload);
};

const fetchPublicInvite = async (code: string): Promise<SquadPublicInvite> => {
  const payload = unwrapResponse(
    await getSquadApi(`squads/invite/${encodeURIComponent(code)}`),
    'Squad invite preview is unavailable right now.',
  );
  const squad = isObject(payload.squad) ? normalizeSquad(payload.squad) : undefined;
  if (!squad || squad.id <= 0) {
    throw new Error('Squad invite was not found.');
  }

  return {
    squad,
    shareCard: normalizeShareCard(payload),
  };
};

const fetchSquadMissions = async (): Promise<SquadMission[]> => {
  const payload = unwrapResponse(
    await getSquadApi('auth/squads/missions'),
    'Squad missions are unavailable right now.',
  );
  return asArray(payload.missions ?? payload.data ?? payload)
    .filter(isObject)
    .map(normalizeMission);
};

const fetchSquadAnnouncements = async (): Promise<SquadAnnouncement[]> => {
  const payload = unwrapResponse(
    await getSquadApi('auth/squads/announcements'),
    'Squad announcements are unavailable right now.',
  );
  return asArray(payload.announcements ?? payload.data ?? payload)
    .filter(isObject)
    .map(normalizeAnnouncement);
};

export const useMySquad = (enabled = true) =>
  useQuery(squadQueryKeys.mine, fetchMySquad, {
    enabled,
    staleTime: 30000,
    retry: false,
    refetchOnReconnect: true,
  });

export const useMySquadProfile = (enabled: boolean) =>
  useQuery(squadQueryKeys.profile, fetchMySquadProfile, {
    enabled,
    staleTime: 30000,
    retry: false,
    refetchOnReconnect: true,
  });

export const useSquadMembers = (tab: string, enabled: boolean) =>
  useQuery(squadQueryKeys.membersForTab(tab), () => fetchSquadMembers(tab), {
    enabled,
    staleTime: 30000,
    retry: false,
    refetchOnReconnect: true,
  });

export const useSquadDiscovery = (enabled = true) => {
  const queryClient = useQueryClient();
  return useQuery(
    squadQueryKeys.discover,
    async () => {
      try {
        const home = await queryClient.fetchQuery(squadQueryKeys.mine, fetchMySquad, {staleTime: 30000});
        const featured = home.discover.length ? home.discover : home.recommended;
        if (featured.length) {
          return featured;
        }
      } catch {
        // Discovery has its own endpoint and should survive a broken app-home summary.
      }
      return fetchDiscover();
    },
    {enabled, staleTime: 30000, retry: false, refetchOnReconnect: true},
  );
};

export const useSquadLeaderboard = (enabled = true) => {
  const queryClient = useQueryClient();
  return useQuery(
    squadQueryKeys.leaderboard,
    async () => {
      try {
        const home = await queryClient.fetchQuery(squadQueryKeys.mine, fetchMySquad, {staleTime: 30000});
        const ranks = home.leaderboard.length ? home.leaderboard : home.countryLeaderboard;
        if (ranks.length) {
          return ranks;
        }
      } catch {
        // Rankings has its own endpoint and should survive a broken app-home summary.
      }
      return fetchLeaderboard();
    },
    {enabled, staleTime: 30000, retry: false, refetchOnReconnect: true},
  );
};

export const useSquadShareCard = (enabled: boolean) =>
  useQuery(squadQueryKeys.shareCard, fetchShareCard, {
    enabled,
    staleTime: 30000,
    retry: 1,
    refetchOnReconnect: true,
  });

export const usePublicSquadInvite = (code: string, enabled: boolean) =>
  useQuery(squadQueryKeys.publicInvite(code), () => fetchPublicInvite(code), {
    enabled: enabled && code.length >= 4,
    staleTime: 30000,
    retry: 1,
    refetchOnReconnect: true,
  });

export const useSquadMissions = (enabled: boolean) =>
  useQuery(squadQueryKeys.missions, fetchSquadMissions, {
    enabled,
    staleTime: 30000,
    retry: 1,
    refetchOnReconnect: true,
  });

export const useSquadAnnouncements = (enabled: boolean) =>
  useQuery(squadQueryKeys.announcements, fetchSquadAnnouncements, {
    enabled,
    staleTime: 30000,
    retry: 1,
    refetchOnReconnect: true,
  });

const invalidateSquadQueries = async (queryClient: ReturnType<typeof useQueryClient>) => {
  await Promise.all([
    queryClient.invalidateQueries(squadQueryKeys.mine),
    queryClient.invalidateQueries(squadQueryKeys.profile),
    queryClient.invalidateQueries(squadQueryKeys.members),
    queryClient.invalidateQueries(squadQueryKeys.discover),
    queryClient.invalidateQueries(squadQueryKeys.leaderboard),
    queryClient.invalidateQueries(squadQueryKeys.shareCard),
    queryClient.invalidateQueries(squadQueryKeys.missions),
    queryClient.invalidateQueries(squadQueryKeys.announcements),
    invalidateEngagementQueries(queryClient, {wallet: true, tasks: true, onboardingQuest: true, referrals: true}),
  ]);
};

export const useCreateSquad = () => {
  const queryClient = useQueryClient();
  return useMutation<
    any,
    Error,
    {
      name: string;
      description?: string;
      max_members?: number;
      is_public?: boolean;
      country_code?: string;
      language_code?: string;
      poster_template?: string;
    }
  >(
    async payload => {
      const response = await postApi('auth/squads/create', payload);
      const data = unwrapResponse(response, 'Squad could not be created.');
      return {message: getMessage(response.data, 'Squad created.'), data};
    },
    {onSuccess: () => invalidateSquadQueries(queryClient)},
  );
};

export const useJoinSquad = () => {
  const queryClient = useQueryClient();
  return useMutation<any, Error, {squad_id?: number; invite_code?: string}>(
    async payload => {
      const response = await postApi('auth/squads/join', payload);
      const data = unwrapResponse(response, 'Squad could not be joined.');
      return {message: getMessage(response.data, 'Joined squad.'), data};
    },
    {onSuccess: () => invalidateSquadQueries(queryClient)},
  );
};

export const useLeaveSquad = () => {
  const queryClient = useQueryClient();
  return useMutation<any, Error, void>(
    async () => {
      const response = await postApi('auth/squads/leave', {});
      const data = unwrapResponse(response, 'Squad could not be left.');
      return {message: getMessage(response.data, 'Left squad.'), data};
    },
    {onSuccess: () => invalidateSquadQueries(queryClient)},
  );
};

export const useClaimSquadChest = () => {
  const queryClient = useQueryClient();
  return useMutation<SquadChestClaimResult, Error, void>(
    async () => {
      const response = await postApi('auth/squads/chest/claim', {});
      const body = response?.data;
      const message = getMessage(response?.success ? body : response, 'Squad chest could not be claimed.');
      const alreadyClaimed = message.toLowerCase().includes('already claimed');

      if (!response?.success || body?.success === false || Number(body?.status ?? 1) === 0) {
        if (alreadyClaimed) {
          return {message: 'Squad chest was already claimed this week.', claimed: false, alreadyClaimed: true, rewardXp: 0};
        }
        throw new Error(message);
      }

      const payload = isObject(body?.data) ? body.data : {};
      return {
        message,
        claimed: asBoolean(payload.claimed),
        alreadyClaimed,
        rewardXp: asNumber(payload.reward_xp ?? payload.rewardXp),
        rewardBrc: asNumber(payload.reward_brc ?? payload.rewardBrc) || undefined,
      };
    },
    {onSuccess: () => invalidateSquadQueries(queryClient)},
  );
};

export const useTrackSquadShare = () => {
  const queryClient = useQueryClient();
  return useMutation<any, Error, {channel: SquadShareChannel}>(
    async payload => {
      const response = await postApi('auth/squads/share', payload);
      const data = unwrapResponse(response, 'Squad share could not be tracked.');
      return {message: getMessage(response.data, 'Squad share tracked.'), data};
    },
    {onSuccess: () => invalidateSquadQueries(queryClient)},
  );
};

export const useFollowSquad = () => {
  const queryClient = useQueryClient();
  return useMutation<any, Error, {squad_id: number; follow?: boolean}>(
    async payload => {
      const response = await postApi('auth/squads/follow', payload);
      const data = unwrapResponse(response, 'Squad follow could not be updated.');
      return {message: getMessage(response.data, 'Squad follow updated.'), data};
    },
    {onSuccess: () => invalidateSquadQueries(queryClient)},
  );
};

export const useRegenerateSquadInvite = () => {
  const queryClient = useQueryClient();
  return useMutation<any, Error, void>(
    async () => {
      const response = await postApi('auth/squads/invite/regenerate', {});
      const data = unwrapResponse(response, 'Squad invite could not be refreshed.');
      return {message: getMessage(response.data, 'Squad invite refreshed.'), data};
    },
    {onSuccess: () => invalidateSquadQueries(queryClient)},
  );
};

export const useClaimSquadMission = () => {
  const queryClient = useQueryClient();
  return useMutation<SquadActionResult, Error, {id: number}>(
    async ({id}) => {
      const response = await postApi(`auth/squads/missions/${id}/claim`, {});
      const data = unwrapResponse(response, 'Squad mission could not be claimed.');
      return {
        message: getMessage(response.data, 'Squad mission claimed.'),
        claimed: asBoolean(data.claimed),
        alreadyClaimed: getMessage(response.data, '').toLowerCase().includes('already'),
        rewardXp: asNumber(data.reward_xp ?? data.rewardXp),
        rewardBrc: asNumber(data.reward_brc ?? data.rewardBrc) || undefined,
      };
    },
    {onSuccess: () => invalidateSquadQueries(queryClient)},
  );
};

export const useClaimSquadInviteStreak = () => {
  const queryClient = useQueryClient();
  return useMutation<SquadActionResult, Error, void>(
    async () => {
      const response = await postApi('auth/squads/invite-streak/claim', {});
      const data = unwrapResponse(response, 'Invite streak could not be claimed.');
      return {
        message: getMessage(response.data, 'Invite streak claimed.'),
        claimed: asBoolean(data.claimed),
        alreadyClaimed: getMessage(response.data, '').toLowerCase().includes('already'),
      };
    },
    {onSuccess: () => invalidateSquadQueries(queryClient)},
  );
};

export const useUpdateSquadMemberRole = () => {
  const queryClient = useQueryClient();
  return useMutation<any, Error, {user_id: number; role: 'co_leader' | 'moderator' | 'member'}>(
    async payload => {
      let response = await postApi('auth/squads/members/role', payload);
      if (!response.success) {
        response = await postApi('auth/squads/member/role', payload);
      }
      const data = unwrapResponse(response, 'Squad member role could not be updated.');
      return {message: getMessage(response.data, 'Squad member role updated.'), data};
    },
    {onSuccess: () => invalidateSquadQueries(queryClient)},
  );
};

export const useRemoveSquadMember = () => {
  const queryClient = useQueryClient();
  return useMutation<any, Error, {user_id: number}>(
    async payload => {
      let response = await postApi('auth/squads/members/remove', payload);
      if (!response.success) {
        response = await postApi('auth/squads/member/remove', payload);
      }
      const data = unwrapResponse(response, 'Squad member could not be removed.');
      return {message: getMessage(response.data, 'Squad member removed.'), data};
    },
    {onSuccess: () => invalidateSquadQueries(queryClient)},
  );
};
