import React, {useCallback, useEffect, useState} from 'react';
import {Alert, Modal, Pressable, ScrollView, Share as NativeShare, StyleSheet, Text, View} from 'react-native';
import {useNavigation, useRoute} from '@react-navigation/native';
import RNShare from 'react-native-share';
import QRCode from 'react-native-qrcode-svg';
import {SvgXml} from 'react-native-svg';
import Ionicons from 'react-native-vector-icons/Ionicons';
import MaterialCommunityIcons from 'react-native-vector-icons/MaterialCommunityIcons';
import {
  AppScreen,
  AppTextField,
  GlassCard,
  InlineLoader,
  PrimaryButton,
  ScreenHeader,
  ScreenState,
  SectionEyebrow,
  SecondaryButton,
} from '../design/primitives';
import theme, {alpha} from '../design/theme';
import {useThisAlert} from '../hooks/AlertContext';
import {
  useClaimSquadChest,
  useClaimSquadInviteStreak,
  useClaimSquadMission,
  useCreateSquad,
  useFollowSquad,
  useJoinSquad,
  useLeaveSquad,
  useMySquad,
  useMySquadProfile,
  usePublicSquadInvite,
  useRegenerateSquadInvite,
  useRemoveSquadMember,
  useSquadAnnouncements,
  useSquadDiscovery,
  useSquadLeaderboard,
  useSquadMembers,
  useSquadMissions,
  useSquadShareCard,
  useTrackSquadShare,
  useUpdateSquadMemberRole,
} from '../hooks/useSquads';
import {Squad, SquadLeaderboardEntry, SquadMember, SquadMission, SquadShareCard} from '../types/squads';
import {getSquadMemberTier} from '../utils/squadMemberTier';
import {shouldShowSquadJoinOptions} from '../utils/squadScreenState';
import {navigate} from '../utils/helper';
import {queueEngagementEvent} from '../services/engagementAnalyticsService';

type Tab = 'mine' | 'discover' | 'leaderboard';
type MemberFilter = 'members' | 'active_today' | 'new_joiners' | 'top_miners' | 'top_referrers';

const tabs: {key: Tab; label: string}[] = [
  {key: 'mine', label: 'My squad'},
  {key: 'discover', label: 'Discover'},
  {key: 'leaderboard', label: 'Ranks'},
];

const posterTemplates = [
  {key: 'white', label: 'White'},
  {key: 'dark', label: 'Dark'},
  {key: 'country', label: 'Country'},
  {key: 'weekly_winner', label: 'Winner'},
];

const formatNumber = (value: number) => Math.max(0, value).toLocaleString();

const clampPercent = (value: number) => Math.min(100, Math.max(0, Math.round(value || 0)));

const escapePosterText = (value?: string | number) =>
  String(value ?? '').replace(/[&<>"']/g, char => {
    const map: Record<string, string> = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&apos;',
    };
    return map[char] ?? char;
  });

const truncatePosterText = (value: string | undefined, maxLength: number) => {
  const text = String(value ?? '').trim();
  if (text.length <= maxLength) {
    return text;
  }
  return `${text.slice(0, Math.max(0, maxLength - 3)).trim()}...`;
};

const normalizePosterColor = (value: string | undefined, fallback: string) =>
  value && /^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test(value) ? value : fallback;

const buildPosterPattern = (seedText: string, color: string) => {
  const seed = seedText.split('').reduce((sum, char) => sum + char.charCodeAt(0), 0);
  const cell = 22;
  const gap = 5;
  const rects: string[] = [];

  for (let row = 0; row < 9; row += 1) {
    for (let col = 0; col < 9; col += 1) {
      const inCorner =
        (row < 3 && col < 3) ||
        (row < 3 && col > 5) ||
        (row > 5 && col < 3);
      const active = inCorner || ((row * 7 + col * 11 + seed) % 5 < 2);
      if (active) {
        rects.push(
          `<rect x="${col * (cell + gap)}" y="${row * (cell + gap)}" width="${cell}" height="${cell}" rx="5" fill="${color}" />`,
        );
      }
    }
  }

  return rects.join('');
};

const buildSquadPosterSvg = (squad: Squad, shareCard?: SquadShareCard) => {
  const template = String(shareCard?.poster?.template ?? 'white').toLowerCase();
  const isDark = template.includes('dark') || template.includes('winner');
  const isWinner = template.includes('winner');
  const background = normalizePosterColor(
    shareCard?.poster?.backgroundColor,
    isDark ? '#07111f' : template.includes('country') ? '#f4fbf7' : '#f8fbff',
  );
  const panel = isDark ? '#102033' : '#ffffff';
  const panelStroke = isDark ? '#223955' : '#dbe7f5';
  const text = normalizePosterColor(shareCard?.poster?.textColor, isDark ? '#ffffff' : '#07111f');
  const muted = isDark ? '#b8c8da' : '#526276';
  const accent = normalizePosterColor(
    shareCard?.poster?.accentColor,
    template.includes('winner') ? '#f5b642' : '#1fd6c5',
  );
  const primary = template.includes('country') ? '#10876f' : '#2563eb';
  const softAccent = isDark ? '#0c2d35' : '#e9fffb';
  const inviteCode = shareCard?.squad?.inviteCode ?? squad.inviteCode ?? squad.slug;
  const shareUrl =
    shareCard?.squad?.shareUrl ??
    squad.shareUrl ??
    (inviteCode ? `https://maticlegend.com/squad/${inviteCode}` : 'https://maticlegend.com');
  const title = shareCard?.poster?.title ?? 'Join my Blockrium squad';
  const country = (shareCard?.squad?.countryCode ?? squad.countryCode ?? 'Global').toUpperCase();
  const members = shareCard?.squad?.memberCount ?? squad.memberCount;
  const capacity = shareCard?.squad?.capacityLabel ?? squad.capacityLabel ?? 'Unlimited';
  const weeklyPoints = shareCard?.squad?.weeklyPoints ?? squad.weeklyPoints;
  const boost = shareCard?.squad?.boostPercent ?? squad.communityBoostPercent;
  const activeToday = squad.statsDeferred ? undefined : getSquadActiveTodayCount(squad);
  const followers = squad.social?.followersCount ?? 0;
  const progress = clampPercent(squad.weeklyProgressPercent);
  const safeNote =
    shareCard?.poster?.safeNote ??
    'Verified activity only. Rewards and boosts are controlled by Blockrium.';
  const pattern = buildPosterPattern(inviteCode || squad.name, accent);
  const description = truncatePosterText(
    squad.description || 'Mine together, invite active members, and unlock weekly squad rewards.',
    92,
  );

  return `
<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1350" viewBox="0 0 1080 1350">
  <rect width="1080" height="1350" fill="${background}" />
  <circle cx="60" cy="60" r="220" fill="${accent}" opacity="${isDark ? '0.13' : '0.18'}" />
  <circle cx="1040" cy="1240" r="260" fill="${primary}" opacity="${isDark ? '0.14' : '0.1'}" />
  <rect x="58" y="58" width="964" height="1234" rx="56" fill="${panel}" stroke="${panelStroke}" stroke-width="3" />
  <rect x="92" y="92" width="896" height="1166" rx="40" fill="none" stroke="${isDark ? '#284461' : '#e8f1fb'}" stroke-width="2" />
  <rect x="126" y="126" width="180" height="62" rx="31" fill="${softAccent}" />
  <circle cx="166" cy="157" r="24" fill="${accent}" />
  <text x="166" y="167" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="28" font-weight="900" fill="${isDark ? '#07111f' : '#ffffff'}">B</text>
  <text x="206" y="166" font-family="Arial, Helvetica, sans-serif" font-size="24" font-weight="800" fill="${text}">Blockrium</text>
  <rect x="738" y="126" width="220" height="62" rx="31" fill="${primary}" opacity="0.13" />
  <text x="848" y="166" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="23" font-weight="800" fill="${primary}">${escapePosterText(country)}</text>
  <text x="126" y="292" font-family="Arial, Helvetica, sans-serif" font-size="34" font-weight="900" fill="${accent}" letter-spacing="4">${escapePosterText(title.toUpperCase())}</text>
  <text x="126" y="398" font-family="Arial, Helvetica, sans-serif" font-size="86" font-weight="900" fill="${text}">${escapePosterText(truncatePosterText(squad.name, 18))}</text>
  ${isWinner ? `<text x="126" y="468" font-family="Arial, Helvetica, sans-serif" font-size="32" font-weight="800" fill="${accent}">Weekly winner energy</text>` : ''}
  <text x="126" y="${isWinner ? 524 : 486}" font-family="Arial, Helvetica, sans-serif" font-size="29" fill="${muted}">${escapePosterText(description)}</text>
  <rect x="126" y="586" width="828" height="178" rx="34" fill="${isDark ? '#0b1728' : '#f1f8ff'}" stroke="${panelStroke}" stroke-width="2" />
  <text x="170" y="646" font-family="Arial, Helvetica, sans-serif" font-size="24" font-weight="800" fill="${muted}">WEEKLY POINTS</text>
  <text x="170" y="720" font-family="Arial, Helvetica, sans-serif" font-size="70" font-weight="900" fill="${text}">${escapePosterText(squad.statsDeferred ? '-' : formatNumber(weeklyPoints))}</text>
  <text x="510" y="646" font-family="Arial, Helvetica, sans-serif" font-size="24" font-weight="800" fill="${muted}">INVITE CODE</text>
  <text x="510" y="718" font-family="Arial, Helvetica, sans-serif" font-size="54" font-weight="900" fill="${accent}">${escapePosterText(inviteCode || 'BLOCKRIUM')}</text>
  <rect x="126" y="816" width="828" height="54" rx="27" fill="${isDark ? '#0b1728' : '#eef5ff'}" />
  <rect x="126" y="816" width="${Math.max(36, Math.round(828 * progress / 100))}" height="54" rx="27" fill="${accent}" />
  <text x="540" y="852" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="22" font-weight="900" fill="${progress > 42 || isDark ? '#ffffff' : text}">${progress}% weekly chest progress</text>
  <rect x="126" y="930" width="186" height="142" rx="28" fill="${isDark ? '#0b1728' : '#f7faff'}" stroke="${panelStroke}" stroke-width="2" />
  <text x="154" y="984" font-family="Arial, Helvetica, sans-serif" font-size="21" font-weight="800" fill="${muted}">MEMBERS</text>
  <text x="154" y="1037" font-family="Arial, Helvetica, sans-serif" font-size="41" font-weight="900" fill="${text}">${escapePosterText(formatNumber(members))}</text>
  <rect x="340" y="930" width="186" height="142" rx="28" fill="${isDark ? '#0b1728' : '#f7faff'}" stroke="${panelStroke}" stroke-width="2" />
  <text x="368" y="984" font-family="Arial, Helvetica, sans-serif" font-size="21" font-weight="800" fill="${muted}">ACTIVE</text>
  <text x="368" y="1037" font-family="Arial, Helvetica, sans-serif" font-size="41" font-weight="900" fill="${text}">${escapePosterText(activeToday === undefined ? '-' : formatNumber(activeToday))}</text>
  <rect x="554" y="930" width="186" height="142" rx="28" fill="${isDark ? '#0b1728' : '#f7faff'}" stroke="${panelStroke}" stroke-width="2" />
  <text x="582" y="984" font-family="Arial, Helvetica, sans-serif" font-size="21" font-weight="800" fill="${muted}">WEEKLY</text>
  <text x="582" y="1037" font-family="Arial, Helvetica, sans-serif" font-size="41" font-weight="900" fill="${text}">${escapePosterText(formatNumber(weeklyPoints))}</text>
  <rect x="768" y="930" width="186" height="142" rx="28" fill="${isDark ? '#0b1728' : '#f7faff'}" stroke="${panelStroke}" stroke-width="2" />
  <text x="796" y="984" font-family="Arial, Helvetica, sans-serif" font-size="21" font-weight="800" fill="${muted}">BOOST</text>
  <text x="796" y="1037" font-family="Arial, Helvetica, sans-serif" font-size="41" font-weight="900" fill="${text}">+${escapePosterText(boost)}%</text>
  <g transform="translate(126 1126) scale(0.58)">${pattern}</g>
  <text x="292" y="1156" font-family="Arial, Helvetica, sans-serif" font-size="24" font-weight="800" fill="${text}">Join with invite code or link</text>
  <text x="292" y="1204" font-family="Arial, Helvetica, sans-serif" font-size="22" fill="${muted}">Capacity: ${escapePosterText(capacity)} | Followers: ${escapePosterText(formatNumber(followers))}</text>
  <text x="126" y="1264" font-family="Arial, Helvetica, sans-serif" font-size="20" fill="${muted}">${escapePosterText(truncatePosterText(safeNote, 105))}</text>
  <text x="126" y="1310" font-family="Arial, Helvetica, sans-serif" font-size="22" font-weight="800" fill="${primary}">${escapePosterText(truncatePosterText(shareUrl, 78))}</text>
</svg>`.trim();
};

const buildSquadPosterDataUrl = (squad: Squad, shareCard?: SquadShareCard) =>
  `data:image/svg+xml;utf8,${encodeURIComponent(buildSquadPosterSvg(squad, shareCard))}`;

const renderSquadSubtitle = (squad: Squad) =>
  squad.description || `${formatNumber(squad.memberCount)} members`;

const renderCountryLabel = (squad: Squad) =>
  squad.countryName || squad.countryCode?.toUpperCase() || 'Global';

const getMemberTabCount = (memberTabItems: {key: string; count: number}[] | undefined, key: string) =>
  memberTabItems?.find(tab => tab.key === key)?.count;

const getSquadActiveTodayCount = (squad: Squad, memberTabs?: {key: string; count: number}[]) =>
  getMemberTabCount(memberTabs, 'active_today') ?? squad.memberGroups?.activeToday?.length ?? 0;

const getSquadFollowersCount = (squad: Squad, followingCount?: number) =>
  followingCount ?? squad.social?.followersCount ?? 0;

const Squads = () => {
  const navigation = useNavigation<any>();
  const route = useRoute<any>();
  const {showAlert} = useThisAlert();
  const [tab, setTab] = useState<Tab>('mine');
  const [showCreate, setShowCreate] = useState(false);
  const [inviteCode, setInviteCode] = useState('');
  const [squadName, setSquadName] = useState('');
  const [squadDescription, setSquadDescription] = useState('');
  const [isPublic, setIsPublic] = useState(true);
  const [posterTemplate, setPosterTemplate] = useState('white');
  const [memberFilter, setMemberFilter] = useState<MemberFilter>('members');
  const [posterPreview, setPosterPreview] = useState<{squad: Squad; shareCard?: SquadShareCard} | null>(null);
  const [qrPreview, setQrPreview] = useState<{title: string; value: string} | null>(null);
  const invitePreviewCode = inviteCode.trim().toUpperCase();
  const {data: mine, isLoading: isMineLoading, isError: isMineError, error: mineError, refetch: refetchMine, isFetching: isMineFetching} = useMySquad();
  const {data: profile, isLoading: isProfileLoading, isError: isProfileError, refetch: refetchProfile, isFetching: isProfileFetching} = useMySquadProfile(Boolean(mine?.hasSquad) && tab === 'mine');
  const {data: memberList = [], isLoading: isMembersLoading, isError: isMembersError, refetch: refetchMembers, isFetching: isMembersFetching} = useSquadMembers(memberFilter, Boolean(mine?.hasSquad) && tab === 'mine');
  const {data: discovered = [], isLoading: isDiscoverLoading, isError: isDiscoverError, error: discoverError, refetch: refetchDiscover, isFetching: isDiscoverFetching} = useSquadDiscovery();
  const {data: leaderboard = [], isLoading: isLeaderboardLoading, isError: isLeaderboardError, error: leaderboardError, refetch: refetchLeaderboard, isFetching: isLeaderboardFetching} = useSquadLeaderboard();
  const createSquad = useCreateSquad();
  const joinSquad = useJoinSquad();
  const leaveSquad = useLeaveSquad();
  const claimChest = useClaimSquadChest();
  const claimMission = useClaimSquadMission();
  const claimInviteStreak = useClaimSquadInviteStreak();
  const trackShare = useTrackSquadShare();
  const followSquad = useFollowSquad();
  const regenerateInvite = useRegenerateSquadInvite();
  const updateMemberRole = useUpdateSquadMemberRole();
  const removeMember = useRemoveSquadMember();
  const {data: shareCard, refetch: refetchShareCard, isFetching: isShareCardFetching} = useSquadShareCard(false);
  const {data: invitePreview, isFetching: isInvitePreviewFetching, isError: isInvitePreviewError} =
    usePublicSquadInvite(invitePreviewCode, tab === 'mine' && !mine?.hasSquad && invitePreviewCode.length >= 4);
  const useLegacySquadDetails = Boolean(mine?.hasSquad) && tab === 'mine' && isProfileError;
  const {data: standaloneMissions = [], refetch: refetchStandaloneMissions} = useSquadMissions(useLegacySquadDetails);
  const {data: standaloneAnnouncements = [], refetch: refetchStandaloneAnnouncements} = useSquadAnnouncements(useLegacySquadDetails);

  useEffect(() => {
    queueEngagementEvent({
      eventName: 'squad_screen_open',
      area: 'squad',
      status: 'viewed',
      route: 'Squads',
      metadata: {initialInviteCode: route.params?.inviteCode},
    });
  }, [route.params?.inviteCode]);

  const selectTab = useCallback((nextTab: Tab) => {
    setTab(nextTab);
    queueEngagementEvent({
      eventName: 'squad_tab_selected',
      area: 'squad',
      status: 'viewed',
      route: 'Squads',
      metadata: {tab: nextTab, hasSquad: Boolean(mine?.hasSquad)},
    });
  }, [mine?.hasSquad]);

  const squad = profile?.squad
    ? {...mine?.squad, ...profile.squad}
    : mine?.squad;
  const countryLeaderboard = profile?.countryLeaderboard?.length
    ? profile.countryLeaderboard
    : mine?.countryLeaderboard ?? [];
  const weeklyMissions = squad?.weeklyMissions?.length
    ? squad.weeklyMissions
    : mine?.weeklyMissions?.length
      ? mine.weeklyMissions
      : standaloneMissions;
  const announcements = squad?.announcements?.length
    ? squad.announcements
    : mine?.announcements?.length
      ? mine.announcements
      : standaloneAnnouncements;
  const userRole = (profile?.membership?.role ?? mine?.membership?.role)?.toLowerCase() ?? 'member';
  const canManageInvite = userRole === 'owner' || userRole === 'co-leader' || userRole === 'co_leader';
  const isBusy =
    createSquad.isLoading ||
    joinSquad.isLoading ||
    leaveSquad.isLoading ||
    claimChest.isLoading ||
    claimMission.isLoading ||
    claimInviteStreak.isLoading ||
    trackShare.isLoading ||
    followSquad.isLoading ||
    regenerateInvite.isLoading ||
    updateMemberRole.isLoading ||
    removeMember.isLoading;
  const resolvedMemberTabs = profile?.memberTabs?.length ? profile.memberTabs : mine?.memberTabs;
  const memberTabs = resolvedMemberTabs?.length
    ? resolvedMemberTabs
    : [
        {key: 'members', label: 'Members', count: squad?.memberCount ?? 0},
        {key: 'active_today', label: 'Active', count: 0},
        {key: 'new_joiners', label: 'New', count: 0},
        {key: 'top_miners', label: 'Top miners', count: 0},
        {key: 'top_referrers', label: 'Top referrers', count: 0},
      ];
  const followersCount = squad ? getSquadFollowersCount(squad, profile?.followers?.count ?? mine?.followers?.count) : 0;
  const isFollowingSquad = Boolean(profile?.followers?.following ?? mine?.followers?.following ?? squad?.social?.following);
  const squadProgressPercent = squad ? clampPercent(squad.weeklyProgressPercent) : 0;

  useEffect(() => {
    const linkedInviteCode = String(route?.params?.inviteCode ?? '').trim();
    if (linkedInviteCode && !mine?.hasSquad) {
      setInviteCode(linkedInviteCode);
      selectTab('mine');
    }
  }, [mine?.hasSquad, route?.params?.inviteCode, selectTab]);

  useEffect(() => {
    if (route?.params?.initialTab === 'discover') {
      selectTab('discover');
      navigation.setParams({initialTab: undefined});
    }
  }, [navigation, route?.params?.initialTab, selectTab]);

  const refreshAll = () => {
    refetchMine();
    if (mine?.hasSquad && tab === 'mine') {
      refetchProfile();
    }
    refetchMembers();
    refetchDiscover();
    refetchLeaderboard();
    if (useLegacySquadDetails) {
      refetchStandaloneMissions();
      refetchStandaloneAnnouncements();
    }
  };

  const showPosterPreview = (previewSquad: Squad, previewShareCard?: SquadShareCard) => {
    setPosterPreview({squad: previewSquad, shareCard: previewShareCard});
  };

  const showQrPreview = (previewSquad: Squad, previewShareCard?: SquadShareCard) => {
    const value =
      previewShareCard?.poster?.qrText ||
      previewShareCard?.squad?.shareUrl ||
      previewSquad.shareUrl ||
      (previewSquad.inviteCode ? `https://maticlegend.com/squad/${previewSquad.inviteCode}` : '');

    if (!value) {
      showAlert('Squad QR is not ready yet.', 'INFO');
      return;
    }

    setQrPreview({title: previewSquad.name, value});
  };

  const handleBack = () => {
    if (navigation.canGoBack()) {
      navigation.goBack();
      return;
    }
    navigate('CommunityFlow');
  };

  const handleJoin = async (payload: {squad_id?: number; invite_code?: string}) => {
    if (isBusy) {
      return;
    }

    try {
      const result = await joinSquad.mutateAsync(payload);
      setInviteCode('');
      selectTab('mine');
      showAlert(result.message, 'SUCCESS');
    } catch (error) {
      showAlert((error as Error)?.message || 'Squad could not be joined.', 'ERROR');
    }
  };

  const handleJoinByCode = () => {
    const code = inviteCode.trim();
    if (!code) {
      showAlert('Enter a squad invite code first.', 'INFO');
      return;
    }
    handleJoin({invite_code: code});
  };

  const handleCreate = async () => {
    const name = squadName.trim();
    if (name.length < 3) {
      showAlert('Choose a squad name with at least 3 characters.', 'INFO');
      return;
    }

    try {
      const result = await createSquad.mutateAsync({
        name,
        description: squadDescription.trim() || undefined,
        is_public: isPublic,
        poster_template: posterTemplate,
      });
      setSquadName('');
      setSquadDescription('');
      setPosterTemplate('white');
      setShowCreate(false);
      selectTab('mine');
      showAlert(result.message, 'SUCCESS');
    } catch (error) {
      showAlert((error as Error)?.message || 'Squad could not be created.', 'ERROR');
    }
  };

  const handleLeave = () => {
    if (!squad || isBusy) {
      return;
    }

    Alert.alert(
      'Leave squad?',
      'You will stop contributing to this squad. Squad owners can only leave after other active members leave.',
      [
        {text: 'Cancel', style: 'cancel'},
        {
          text: 'Leave',
          style: 'destructive',
          onPress: async () => {
            try {
              const result = await leaveSquad.mutateAsync();
              showAlert(result.message, 'INFO');
            } catch (error) {
              showAlert((error as Error)?.message || 'Squad could not be left.', 'ERROR');
            }
          },
        },
      ],
    );
  };

  const handleChest = async () => {
    if (isBusy || !squad?.chestUnlocked || squad.chestClaimed) {
      if (squad?.chestClaimed) {
        showAlert('Squad chest was already claimed this week.', 'INFO');
      }
      return;
    }
    try {
      const result = await claimChest.mutateAsync();
      showAlert(result.message, result.alreadyClaimed ? 'INFO' : 'SUCCESS');
    } catch (error) {
      showAlert((error as Error)?.message || 'Squad chest could not be claimed.', 'ERROR');
    }
  };

  const handleShare = async () => {
    if (!squad || isBusy) {
      return;
    }

    try {
      const result = shareCard ?? (await refetchShareCard()).data;
      const shareUrl = result?.squad?.shareUrl ?? squad.shareUrl;
      const message =
        result?.squad?.shareText ??
        squad.shareText ??
        (shareUrl
          ? `Join my Blockrium squad and help unlock weekly activity chests. ${shareUrl}`
          : 'Join my Blockrium squad and help unlock weekly activity chests.');
      const response = await NativeShare.share({message, url: shareUrl, title: squad.name});
      if (response.action === NativeShare.sharedAction) {
        await trackShare.mutateAsync({channel: 'other'});
        showAlert('Squad invite shared.', 'SUCCESS');
      }
    } catch (error) {
      showAlert((error as Error)?.message || 'Squad invite could not be shared.', 'ERROR');
    }
  };

  const handleSharePoster = async (previewSquad?: Squad, previewShareCard?: SquadShareCard) => {
    const targetSquad = previewSquad ?? squad;
    if (!targetSquad || isBusy) {
      return;
    }

    try {
      const result = previewShareCard ?? (previewSquad ? undefined : shareCard ?? (await refetchShareCard()).data);
      const posterUrl = result?.poster?.url ?? targetSquad.posterUrl;
      const shareUrl = result?.squad?.shareUrl ?? targetSquad.shareUrl;
      const posterInviteCode = result?.squad?.inviteCode ?? targetSquad.inviteCode;
      const message =
        result?.squad?.shareText ??
        targetSquad.shareText ??
        `Join my Blockrium squad ${posterInviteCode ?? ''}${shareUrl ? ` ${shareUrl}` : ''}`;

      try {
        await RNShare.open({
          title: `${targetSquad.name} squad poster`,
          message,
          url: buildSquadPosterDataUrl(targetSquad, result),
          type: 'image/svg+xml',
          failOnCancel: false,
        });
      } catch (posterError) {
        if (!posterUrl) {
          throw posterError;
        }
        await RNShare.open({
          title: `${targetSquad.name} squad poster`,
          message: `${message} Poster: ${posterUrl}`,
          url: posterUrl,
          failOnCancel: false,
        });
      }

      if (!previewSquad || previewSquad.id === squad?.id) {
        await trackShare.mutateAsync({channel: 'other'});
      }
      showAlert('Squad poster shared.', 'SUCCESS');
    } catch (error) {
      const message = String((error as Error)?.message ?? '').toLowerCase();
      if (!message.includes('cancel')) {
        showAlert((error as Error)?.message || 'Squad poster could not be shared.', 'ERROR');
      }
    }
  };

  const handleRegenerateInvite = () => {
    if (!squad || !canManageInvite || isBusy) {
      return;
    }

    Alert.alert(
      'Refresh invite code?',
      'The current invite code may stop working after a new one is created.',
      [
        {text: 'Cancel', style: 'cancel'},
        {
          text: 'Refresh',
          onPress: async () => {
            try {
              const result = await regenerateInvite.mutateAsync();
              showAlert(result.message, 'SUCCESS');
            } catch (error) {
              showAlert((error as Error)?.message || 'Squad invite could not be refreshed.', 'ERROR');
            }
          },
        },
      ],
    );
  };

  const handleFollowSquad = async () => {
    if (!squad || isBusy) {
      return;
    }

    try {
      const result = await followSquad.mutateAsync({
        squad_id: squad.id,
        follow: !isFollowingSquad,
      });
      showAlert(result.message, 'SUCCESS');
    } catch (error) {
      showAlert((error as Error)?.message || 'Squad follow could not be updated.', 'ERROR');
    }
  };

  const handleClaimMission = async (mission: SquadMission) => {
    if (!mission.id || !mission.canClaim || isBusy) {
      return;
    }

    try {
      const result = await claimMission.mutateAsync({id: mission.id});
      showAlert(result.message, result.alreadyClaimed ? 'INFO' : 'SUCCESS');
    } catch (error) {
      showAlert((error as Error)?.message || 'Squad mission could not be claimed.', 'ERROR');
    }
  };

  const handleClaimInviteStreak = async () => {
    if (!squad?.inviteStreak?.canClaim || isBusy) {
      return;
    }

    try {
      const result = await claimInviteStreak.mutateAsync();
      showAlert(result.message, result.alreadyClaimed ? 'INFO' : 'SUCCESS');
    } catch (error) {
      showAlert((error as Error)?.message || 'Invite streak could not be claimed.', 'ERROR');
    }
  };

  const handleRoleChange = (member: SquadMember, role: 'co_leader' | 'moderator' | 'member') => {
    if (!canManageInvite || member.role === 'owner' || isBusy) {
      return;
    }

    Alert.alert(
      'Update squad role?',
      `Set ${member.name} as ${role.replace('_', '-')}.`,
      [
        {text: 'Cancel', style: 'cancel'},
        {
          text: 'Update',
          onPress: async () => {
            try {
              const result = await updateMemberRole.mutateAsync({user_id: member.userId, role});
              showAlert(result.message, 'SUCCESS');
            } catch (error) {
              showAlert((error as Error)?.message || 'Squad member role could not be updated.', 'ERROR');
            }
          },
        },
      ],
    );
  };

  const handleRemoveMember = (member: SquadMember) => {
    if (!canManageInvite || member.role === 'owner' || isBusy) {
      return;
    }

    Alert.alert(
      'Remove squad member?',
      `${member.name} will stop contributing to this squad.`,
      [
        {text: 'Cancel', style: 'cancel'},
        {
          text: 'Remove',
          style: 'destructive',
          onPress: async () => {
            try {
              const result = await removeMember.mutateAsync({user_id: member.userId});
              showAlert(result.message, 'INFO');
            } catch (error) {
              showAlert((error as Error)?.message || 'Squad member could not be removed.', 'ERROR');
            }
          },
        },
      ],
    );
  };

  const renderSquadRow = (item: Squad, canJoin = true) => {
    const isFull = !item.isUnlimited && Boolean(item.maxMembers) && item.memberCount >= Number(item.maxMembers);
    const tier = getSquadMemberTier(item.memberCount);
    return (
    <GlassCard key={item.id} style={styles.listCard}>
      <View style={styles.squadAvatar}>
        <Text style={styles.squadAvatarText}>{item.name.slice(0, 1).toUpperCase()}</Text>
      </View>
      <View style={styles.rowCopy}>
        <View style={styles.squadNameRow}>
          <Text style={styles.rowTitle} numberOfLines={1}>{item.name}</Text>
          <View style={[styles.memberTierBadge, {borderColor: `${tier.color}55`, backgroundColor: `${tier.color}18`}]}>
            <MaterialCommunityIcons name={tier.icon as any} size={12} color={tier.color} />
            <Text style={[styles.memberTierText, {color: tier.color}]}>{tier.label}</Text>
          </View>
        </View>
        <Text style={styles.rowSubtitle} numberOfLines={2}>{renderSquadSubtitle(item)}</Text>
        <Text style={styles.rowMeta}>
          {formatNumber(item.memberCount)} members | {item.capacityLabel} capacity | {item.communityBoostPercent}% boost
        </Text>
      </View>
      {canJoin ? (
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={`Join ${item.name}`}
          disabled={isBusy || isFull}
          onPress={() => handleJoin({squad_id: item.id})}
          style={[styles.compactAction, (isBusy || isFull) && styles.compactActionDisabled]}>
          <Text style={styles.compactActionText}>{isFull ? 'Full' : 'Join'}</Text>
        </Pressable>
      ) : null}
    </GlassCard>
    );
  };

  const renderLeaderboardRow = (entry: SquadLeaderboardEntry, index: number) => {
    const tier = getSquadMemberTier(entry.memberCount);
    return (
      <GlassCard key={entry.squadId} style={styles.listCard}>
        <View style={[styles.rankBubble, index < 3 && styles.rankBubbleTop]}>
          <Text style={styles.rankText}>#{index + 1}</Text>
        </View>
        <View style={styles.rowCopy}>
          <View style={styles.squadNameRow}>
            <Text style={styles.rowTitle} numberOfLines={1}>{entry.name}</Text>
            <View style={[styles.memberTierBadge, {borderColor: `${tier.color}55`, backgroundColor: `${tier.color}18`}]}>
              <MaterialCommunityIcons name={tier.icon as any} size={12} color={tier.color} />
              <Text style={[styles.memberTierText, {color: tier.color}]}>{tier.label}</Text>
            </View>
          </View>
          <Text style={styles.rowSubtitle}>{formatNumber(entry.memberCount)} members</Text>
        </View>
        <View style={styles.rankValue}>
          <Text style={styles.rankPoints}>{formatNumber(entry.weeklyPoints)}</Text>
          <Text style={styles.rankMeta}>{entry.communityBoostPercent}% boost</Text>
        </View>
      </GlassCard>
    );
  };

  const activeLoading = tab === 'mine' ? isMineLoading : tab === 'discover' ? isDiscoverLoading : isLeaderboardLoading;
  const activeError = tab === 'mine' ? isMineError : tab === 'discover' ? isDiscoverError : isLeaderboardError;
  const activeErrorMessage = tab === 'mine'
    ? (mineError as Error)?.message
    : tab === 'discover'
      ? (discoverError as Error)?.message
      : (leaderboardError as Error)?.message;

  return (
    <AppScreen
      scrollable
      refreshing={Boolean(isMineFetching || isProfileFetching || isDiscoverFetching || isLeaderboardFetching || isMembersFetching)}
      onRefresh={refreshAll}
      contentContainerStyle={styles.contentContainer}>
      <ScreenHeader
        title="Squads"
        showBack
        onBackPress={handleBack}
      />

      <View style={styles.tabBar}>
        {tabs.map(item => (
          <Pressable key={item.key} onPress={() => selectTab(item.key)} style={[styles.tab, tab === item.key && styles.tabActive]}>
            <Text style={[styles.tabText, tab === item.key && styles.tabTextActive]}>{item.label}</Text>
          </Pressable>
        ))}
      </View>

      {activeLoading ? (
        <GlassCard style={styles.loadingCard}>
          <InlineLoader label={tab === 'mine' ? 'Loading squad...' : 'Loading squads...'} />
        </GlassCard>
      ) : null}
      {activeError ? (
        <ScreenState
          icon="people-outline"
          title={tab === 'mine' ? 'My squad unavailable' : 'Squads unavailable'}
          message={activeErrorMessage || 'Please try again shortly.'}
          actionLabel="Retry"
          onActionPress={refreshAll}
        />
      ) : null}

      {shouldShowSquadJoinOptions({
        tab,
        isLoading: activeLoading,
        isError: activeError,
        hasSquad: Boolean(mine?.hasSquad),
      }) ? (
        <>
          <GlassCard style={styles.joinCard}>
            <SectionEyebrow text="Join with invite" />
            <Text style={styles.cardTitle}>Have a squad code?</Text>
            <AppTextField
              label="Invite code"
              value={inviteCode}
              onChangeText={setInviteCode}
              placeholder="SQXXXXXXXX"
              autoCapitalize="characters"
              autoCorrect={false}
              leadingIcon="key-outline"
              maxLength={40}
            />
            {invitePreviewCode.length >= 4 ? (
              <View style={styles.invitePreview}>
                <View style={styles.squadAvatar}>
                  <Text style={styles.squadAvatarText}>
                    {invitePreview?.squad?.name?.slice(0, 1).toUpperCase() ?? 'B'}
                  </Text>
                </View>
                <View style={styles.rowCopy}>
                  <Text style={styles.rowTitle} numberOfLines={1}>
                    {isInvitePreviewFetching
                      ? 'Checking invite...'
                      : invitePreview?.squad?.name ?? (isInvitePreviewError ? 'Invite not found yet' : 'Squad invite')}
                  </Text>
                  <Text style={styles.rowSubtitle} numberOfLines={2}>
                    {invitePreview?.squad
                      ? `${formatNumber(invitePreview.squad.memberCount)} members | ${invitePreview.squad.capacityLabel} capacity | ${invitePreview.squad.communityBoostPercent}% boost`
                      : isInvitePreviewError
                        ? 'Check the code or join directly if it was shared recently.'
                        : 'Preview will appear before joining.'}
                  </Text>
                </View>
                {invitePreview?.shareCard?.poster?.url ? (
                  <Pressable
                    accessibilityRole="button"
                    onPress={() => showPosterPreview(invitePreview.squad, invitePreview.shareCard)}
                    style={styles.previewPosterButton}>
                    <MaterialCommunityIcons name="image-outline" size={18} color={theme.colors.primary} />
                  </Pressable>
                ) : null}
              </View>
            ) : null}
            <PrimaryButton label="Join squad" icon="account-plus-outline" onPress={handleJoinByCode} loading={joinSquad.isLoading} />
          </GlassCard>

          <GlassCard>
            <SectionEyebrow text="Create squad" />
            <SecondaryButton label="Create a squad" icon="account-group-outline" onPress={() => setShowCreate(value => !value)} />
          </GlassCard>

          {showCreate ? (
            <GlassCard style={styles.createCard}>
              <SectionEyebrow text="New squad" />
              <AppTextField label="Squad name" value={squadName} onChangeText={setSquadName} placeholder="Example: Blockrium Builders" maxLength={120} />
              <AppTextField label="Short description" value={squadDescription} onChangeText={setSquadDescription} placeholder="Optional" maxLength={1000} multiline />
              <View>
                <Text style={styles.fieldHint}>Poster style</Text>
                <View style={styles.templateRow}>
                  {posterTemplates.map(template => (
                    <Pressable
                      key={template.key}
                      accessibilityRole="button"
                      onPress={() => setPosterTemplate(template.key)}
                      style={[
                        styles.templateChip,
                        posterTemplate === template.key && styles.templateChipActive,
                      ]}>
                      <Text
                        style={[
                          styles.templateText,
                          posterTemplate === template.key && styles.templateTextActive,
                        ]}>
                        {template.label}
                      </Text>
                    </Pressable>
                  ))}
                </View>
              </View>
              <Pressable onPress={() => setIsPublic(value => !value)} style={styles.publicRow}>
                <Ionicons name={isPublic ? 'radio-button-on-outline' : 'radio-button-off-outline'} size={20} color={theme.colors.primary} />
                <View style={styles.rowCopy}>
                  <Text style={styles.rowTitle}>Public squad</Text>
                  <Text style={styles.rowSubtitle}>Let other Blockrium users discover and join it.</Text>
                </View>
              </Pressable>
              <PrimaryButton label="Create squad" icon="plus" onPress={handleCreate} loading={createSquad.isLoading} />
            </GlassCard>
          ) : null}

          {mine?.discover?.length ? (
            <View>
              <View style={styles.sectionHeader}><Text style={styles.sectionTitle}>Suggested squads</Text><Pressable onPress={() => selectTab('discover')}><Text style={styles.sectionLink}>See all</Text></Pressable></View>
              {mine.discover.slice(0, 3).map(item => renderSquadRow(item))}
            </View>
          ) : null}
        </>
      ) : null}

      {!activeLoading && !activeError && tab === 'mine' && mine?.hasSquad && squad ? (
        <>
          <GlassCard style={styles.profileCard}>
            <SectionEyebrow text="Squad profile" />
            <View style={styles.profileHeader}>
              <View style={styles.profileAvatar}>
                <Text style={styles.profileAvatarText}>{squad.name.slice(0, 1).toUpperCase()}</Text>
              </View>
              <View style={styles.rowCopy}>
                <View style={styles.squadNameRow}>
                  <Text style={styles.profileName} numberOfLines={2}>{squad.name}</Text>
                  {(() => {
                    const tier = getSquadMemberTier(squad.memberCount);
                    return <View style={[styles.memberTierBadge, {borderColor: `${tier.color}55`, backgroundColor: `${tier.color}18`}]}>
                      <MaterialCommunityIcons name={tier.icon as any} size={12} color={tier.color} />
                      <Text style={[styles.memberTierText, {color: tier.color}]}>{tier.label}</Text>
                    </View>;
                  })()}
                </View>
                {squad.description ? <Text style={styles.profileBio} numberOfLines={2}>{squad.description}</Text> : null}
              </View>
            </View>

            <View style={styles.profileStats}>
              <View style={styles.profileStat}>
                <Text style={styles.profileStatValue}>{formatNumber(squad.memberCount)}</Text>
                <Text style={styles.profileStatLabel}>Members</Text>
              </View>
              <View style={styles.profileStat}>
                <Text style={styles.profileStatValue}>{formatNumber(followersCount)}</Text>
                <Text style={styles.profileStatLabel}>Followers</Text>
              </View>
              <View style={styles.profileStat}>
                <Text style={styles.profileStatValue}>{squad.countryRank ? `#${formatNumber(squad.countryRank)}` : '—'}</Text>
                <Text style={styles.profileStatLabel}>Rank</Text>
              </View>
            </View>

            <View style={styles.profilePills}>
              <View style={styles.profilePill}>
                <Ionicons name="location-outline" size={14} color={theme.colors.primary} />
                <Text style={styles.profilePillText}>{renderCountryLabel(squad)}</Text>
              </View>
              <View style={styles.profilePill}>
                <Ionicons name={squad.isPublic ? 'earth-outline' : 'lock-closed-outline'} size={14} color={theme.colors.primary} />
                <Text style={styles.profilePillText}>{squad.isPublic ? 'Public' : 'Private'}</Text>
              </View>
            </View>

            {squad.inviteCode ? (
              <View style={styles.profileInvite}>
                <View style={styles.inviteHeader}>
                  <View style={styles.rowCopy}>
                    <SectionEyebrow text="Invite code" />
                    <Text style={styles.inviteCode}>{squad.inviteCode}</Text>
                  </View>
                  {squad.shareCount > 0 ? (
                    <View style={styles.shareCountBadge}>
                      <Text style={styles.shareCountText}>{formatNumber(squad.shareCount)} shares</Text>
                    </View>
                  ) : null}
                </View>
                <View style={styles.inviteActions}>
                  <Pressable accessibilityRole="button" onPress={() => showPosterPreview(squad, shareCard)} style={styles.inviteAction}>
                    <Ionicons name="image-outline" size={18} color={theme.colors.primary} /><Text style={styles.inviteActionText}>Poster</Text>
                  </Pressable>
                  <Pressable accessibilityRole="button" onPress={() => showQrPreview(squad, shareCard)} style={styles.inviteAction}>
                    <MaterialCommunityIcons name="qrcode" size={18} color={theme.colors.primary} /><Text style={styles.inviteActionText}>QR code</Text>
                  </Pressable>
                  {canManageInvite ? <Pressable accessibilityRole="button" onPress={handleRegenerateInvite} style={styles.inviteAction}>
                    <Ionicons name="refresh-outline" size={18} color={theme.colors.primary} /><Text style={styles.inviteActionText}>Refresh</Text>
                  </Pressable> : null}
                </View>
              </View>
            ) : null}

            <View style={styles.profileActions}>
              <View style={styles.profileActionItem}>
                <SecondaryButton
                  label={isFollowingSquad ? 'Following' : 'Follow squad'}
                  icon={isFollowingSquad ? 'heart' : 'heart-outline'}
                  onPress={handleFollowSquad}
                  loading={followSquad.isLoading}
                />
              </View>
              <View style={styles.profileActionItem}>
                <PrimaryButton label="Share invite" icon="share-variant-outline" onPress={handleShare} loading={trackShare.isLoading || isShareCardFetching} />
              </View>
            </View>
          </GlassCard>

          {!squad.statsDeferred ? <GlassCard style={styles.progressCard}>
            <View style={styles.progressTitleRow}>
              <View><SectionEyebrow text="This week" /><Text style={styles.cardTitle}>{formatNumber(squad.weeklyPoints)} / {formatNumber(squad.weeklyTargetPoints)} pts</Text></View>
              {squad.communityBoostPercent > 0 ? <View style={styles.boostBadge}><Text style={styles.boostText}>+{squad.communityBoostPercent}%</Text></View> : null}
            </View>
            <View style={styles.progressTrack}><View style={[styles.progressFill, {width: `${squadProgressPercent}%`}]} /></View>
            {squad.chestUnlocked && !squad.chestClaimed ? <PrimaryButton label="Claim weekly chest" icon="treasure-chest-outline" loading={claimChest.isLoading} onPress={handleChest} /> : null}
            {squad.chestClaimed ? <Text style={styles.chestStatus}>Chest claimed</Text> : null}
          </GlassCard> : isProfileLoading || isProfileFetching ? <GlassCard style={styles.progressCard}>
            <InlineLoader label="Loading weekly score..." />
          </GlassCard> : isProfileError ? <GlassCard style={styles.progressCard}>
            <View style={styles.progressTitleRow}>
              <View><SectionEyebrow text="This week" /><Text style={styles.cardTitle}>Score unavailable</Text></View>
              <Pressable accessibilityRole="button" onPress={() => refetchProfile()} style={styles.retryScoreButton}>
                <Ionicons name="refresh-outline" size={17} color={theme.colors.primary} />
                <Text style={styles.retryScoreText}>Retry</Text>
              </Pressable>
            </View>
          </GlassCard> : null}

          {weeklyMissions.length ? (
            <GlassCard>
              <SectionEyebrow text="Weekly missions" />
              {weeklyMissions.slice(0, 4).map(mission => (
                <View key={mission.key} style={styles.missionRow}>
                  <View style={styles.missionIcon}>
                    <Ionicons
                      name={mission.claimed ? 'checkmark-done' : mission.completed ? 'checkmark' : 'flag-outline'}
                      size={mission.claimed ? 16 : 18}
                      color={mission.completed ? theme.colors.background : theme.colors.primary}
                    />
                  </View>
                  <View style={styles.rowCopy}>
                    <Text style={styles.rowTitle}>{mission.title}</Text>
                    <Text style={styles.rowSubtitle} numberOfLines={2}>
                      {mission.description || `${formatNumber(mission.progress)} / ${formatNumber(mission.target)} progress`}
                    </Text>
                    <View style={styles.inlineProgressTrack}>
                      <View
                        style={[
                          styles.inlineProgressFill,
                          {width: `${Math.min(100, Math.round((mission.progress / mission.target) * 100))}%`},
                        ]}
                      />
                    </View>
                  </View>
                  <View style={styles.missionActionWrap}>
                    {mission.rewardLabel ? <Text style={styles.rewardText}>{mission.rewardLabel}</Text> : null}
                    {mission.canClaim ? (
                      <Pressable
                        accessibilityRole="button"
                        disabled={claimMission.isLoading}
                        onPress={() => handleClaimMission(mission)}
                        style={[styles.claimMiniButton, claimMission.isLoading && styles.compactActionDisabled]}>
                        <Text style={styles.claimMiniButtonText}>Claim</Text>
                      </Pressable>
                    ) : mission.claimed ? (
                      <Text style={styles.claimedText}>Claimed</Text>
                    ) : null}
                  </View>
                </View>
              ))}
            </GlassCard>
          ) : null}

          {squad.inviteStreak ? (
            <GlassCard style={styles.inviteStreakCard}>
              <View>
                <SectionEyebrow text="Invite streak" />
                <Text style={styles.cardTitle}>
                  {formatNumber(squad.inviteStreak.activeInvitesToday ?? squad.inviteStreak.current)} active invites today
                </Text>
                <Text style={styles.cardText}>
                  {squad.inviteStreak.streakDays
                    ? `${formatNumber(squad.inviteStreak.streakDays)} day invite streak. Keep bringing active members.`
                    : 'Keep inviting active members to unlock squad streak rewards.'}
                </Text>
              </View>
              <View style={styles.inviteStreakAction}>
                {squad.inviteStreak.rewardLabel ? <Text style={styles.rewardPill}>{squad.inviteStreak.rewardLabel}</Text> : null}
                {squad.inviteStreak.canClaim ? (
                  <Pressable
                    accessibilityRole="button"
                    disabled={claimInviteStreak.isLoading}
                    onPress={handleClaimInviteStreak}
                    style={[styles.claimMiniButton, claimInviteStreak.isLoading && styles.compactActionDisabled]}>
                    <Text style={styles.claimMiniButtonText}>Claim</Text>
                  </Pressable>
                ) : squad.inviteStreak.claimed ? (
                  <Text style={styles.claimedText}>Claimed today</Text>
                ) : null}
              </View>
            </GlassCard>
          ) : null}

          {announcements.length ? (
            <GlassCard>
              <SectionEyebrow text="Announcements" />
              {announcements.slice(0, 3).map((announcement, index) => (
                <View key={`${announcement.id ?? announcement.title}-${index}`} style={styles.announcementRow}>
                  <Ionicons name="megaphone-outline" size={18} color={theme.colors.primary} />
                  <View style={styles.rowCopy}>
                    <Text style={styles.rowTitle}>{announcement.title}</Text>
                    <Text style={styles.rowSubtitle}>{announcement.body}</Text>
                  </View>
                </View>
              ))}
            </GlassCard>
          ) : null}

          <View style={styles.sectionHeader}><Text style={styles.sectionTitle}>Members</Text><Text style={styles.sectionCaption}>{mine.membership?.role || 'member'}</Text></View>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.memberTabRow}>
            {memberTabs.map(tabItem => (
              <Pressable
                key={tabItem.key}
                accessibilityRole="button"
                onPress={() => setMemberFilter(tabItem.key as MemberFilter)}
                style={[styles.memberTabChip, memberFilter === tabItem.key && styles.memberTabChipActive]}>
                <Text style={[styles.memberTabText, memberFilter === tabItem.key && styles.memberTabTextActive]}>
                  {tabItem.label}
                </Text>
                {mine?.memberTabs?.length || tabItem.key === 'members' ? <Text style={styles.memberTabCount}>{formatNumber(tabItem.count)}</Text> : null}
              </Pressable>
            ))}
          </ScrollView>
          {isMembersLoading ? <GlassCard style={styles.memberState}><InlineLoader label="Loading members" /></GlassCard> : null}
          {isMembersError ? <GlassCard style={styles.memberState}>
            <Text style={styles.rowSubtitle}>Members unavailable.</Text>
            <Pressable accessibilityRole="button" onPress={() => refetchMembers()}><Text style={styles.sectionLink}>Retry</Text></Pressable>
          </GlassCard> : null}
          {!isMembersLoading && !isMembersError && memberList.length ? memberList.map(member => (
            <GlassCard key={member.userId} style={styles.memberRow}>
              <View style={styles.memberAvatar}><Text style={styles.squadAvatarText}>{member.name.slice(0, 1).toUpperCase()}</Text></View>
              <View style={styles.rowCopy}>
                <Text style={styles.rowTitle}>{member.name}</Text>
                <Text style={styles.rowSubtitle}>
                  {member.role.replace('_', '-')}{member.score ? ` | ${formatNumber(member.score)} ${memberFilter === 'top_referrers' ? 'referrals' : 'actions'}` : ''}
                </Text>
                {canManageInvite && member.role !== 'owner' ? (
                  <View style={styles.roleActions}>
                    {member.role !== 'co_leader' && userRole === 'owner' ? (
                      <Pressable onPress={() => handleRoleChange(member, 'co_leader')} style={styles.roleActionChip}>
                        <Text style={styles.roleActionText}>Co-leader</Text>
                      </Pressable>
                    ) : null}
                    {member.role !== 'moderator' ? (
                      <Pressable onPress={() => handleRoleChange(member, 'moderator')} style={styles.roleActionChip}>
                        <Text style={styles.roleActionText}>Moderator</Text>
                      </Pressable>
                    ) : null}
                    {member.role !== 'member' ? (
                      <Pressable onPress={() => handleRoleChange(member, 'member')} style={styles.roleActionChip}>
                        <Text style={styles.roleActionText}>Member</Text>
                      </Pressable>
                    ) : null}
                    <Pressable onPress={() => handleRemoveMember(member)} style={[styles.roleActionChip, styles.roleDangerChip]}>
                      <Text style={[styles.roleActionText, styles.roleDangerText]}>Remove</Text>
                    </Pressable>
                  </View>
                ) : null}
              </View>
            </GlassCard>
          )) : !isMembersLoading && !isMembersError ? <GlassCard style={styles.memberState}><Text style={styles.rowSubtitle}>No members in this list.</Text></GlassCard> : null}
          <SecondaryButton label="Leave squad" icon="account-remove-outline" onPress={handleLeave} loading={leaveSquad.isLoading} />
        </>
      ) : null}

      {!activeLoading && !activeError && tab === 'discover' ? (
        discovered.length ? <>{discovered.map(item => renderSquadRow(item))}</> : <ScreenState icon="people-outline" title="No public squads yet" message="Public squads will appear here once they are created." actionLabel="Refresh" onActionPress={refetchDiscover} />
      ) : null}

      {!activeLoading && !activeError && tab === 'leaderboard' ? (
        leaderboard.length || countryLeaderboard.length ? (
          <>
            {leaderboard.map((entry, index) => renderLeaderboardRow(entry, index))}
            {countryLeaderboard.length ? (
              <View>
                <View style={styles.sectionHeader}>
                  <Text style={styles.sectionTitle}>Country ranks</Text>
                  <Text style={styles.sectionCaption}>Local weekly points</Text>
                </View>
                {countryLeaderboard.map((entry, index) => renderLeaderboardRow(entry, index))}
              </View>
            ) : null}
          </>
        ) : <ScreenState icon="trophy-outline" title="No squad ranks yet" message="Weekly ranks will appear after squads start contributing activity." actionLabel="Refresh" onActionPress={refetchLeaderboard} />
      ) : null}

      <Modal
        transparent
        animationType="fade"
        visible={Boolean(posterPreview)}
        onRequestClose={() => setPosterPreview(null)}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalSheet}>
            <View style={styles.modalHeader}>
              <View>
                <SectionEyebrow text="Poster preview" />
                <Text style={styles.modalTitle}>{posterPreview?.squad.name ?? 'Squad poster'}</Text>
              </View>
              <Pressable accessibilityRole="button" onPress={() => setPosterPreview(null)} style={styles.modalClose}>
                <Ionicons name="close" size={20} color={theme.colors.text} />
              </Pressable>
            </View>
            <ScrollView style={styles.posterScroll} contentContainerStyle={styles.posterScrollContent}>
              {posterPreview ? (
                <View style={styles.posterFrame}>
                  <SvgXml
                    xml={buildSquadPosterSvg(posterPreview.squad, posterPreview.shareCard)}
                    width="100%"
                    height={438}
                  />
                </View>
              ) : null}
            </ScrollView>
            <View style={styles.modalActions}>
              <SecondaryButton label="Close" icon="close" onPress={() => setPosterPreview(null)} />
              <PrimaryButton
                label="Share poster"
                icon="share-variant-outline"
                onPress={() => {
                  const preview = posterPreview;
                  setPosterPreview(null);
                  handleSharePoster(preview?.squad, preview?.shareCard);
                }}
                loading={trackShare.isLoading || isShareCardFetching}
              />
            </View>
          </View>
        </View>
      </Modal>

      <Modal
        transparent
        animationType="fade"
        visible={Boolean(qrPreview)}
        onRequestClose={() => setQrPreview(null)}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalSheet}>
            <View style={styles.modalHeader}>
              <View>
                <SectionEyebrow text="Invite QR" />
                <Text style={styles.modalTitle}>{qrPreview?.title ?? 'Squad invite'}</Text>
              </View>
              <Pressable accessibilityRole="button" onPress={() => setQrPreview(null)} style={styles.modalClose}>
                <Ionicons name="close" size={20} color={theme.colors.text} />
              </Pressable>
            </View>
            <View style={styles.qrFrame}>
              {qrPreview?.value ? (
                <QRCode value={qrPreview.value} size={210} backgroundColor="#ffffff" color="#07111f" />
              ) : null}
            </View>
            <Text style={styles.qrText} numberOfLines={2}>{qrPreview?.value}</Text>
            <View style={styles.modalActions}>
              <SecondaryButton label="Close" icon="close" onPress={() => setQrPreview(null)} />
              <PrimaryButton
                label="Share squad"
                icon="share-variant-outline"
                onPress={() => {
                  setQrPreview(null);
                  handleShare();
                }}
                loading={trackShare.isLoading || isShareCardFetching}
              />
            </View>
          </View>
        </View>
      </Modal>
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {paddingTop: 6, paddingBottom: 126},
  tabBar: {flexDirection: 'row', gap: 6, padding: 4, borderRadius: theme.radii.lg, backgroundColor: alpha(theme.colors.black, 0.2), borderWidth: 1, borderColor: theme.colors.border},
  tab: {flex: 1, minHeight: 36, alignItems: 'center', justifyContent: 'center', borderRadius: theme.radii.sm},
  tabActive: {backgroundColor: alpha(theme.colors.primary, 0.18), borderWidth: 1, borderColor: alpha(theme.colors.primary, 0.22)},
  tabText: {color: theme.colors.textSoft, fontSize: 13, fontFamily: theme.typography.bodyBold},
  tabTextActive: {color: theme.colors.primary},
  loadingCard: {gap: 8},
  profileCard: {gap: 12},
  profileHeader: {flexDirection: 'row', alignItems: 'center', gap: 12},
  profileAvatar: {width: 60, height: 60, borderRadius: 20, alignItems: 'center', justifyContent: 'center', backgroundColor: alpha(theme.colors.primary, 0.18), borderWidth: 1, borderColor: alpha(theme.colors.primary, 0.32)},
  profileAvatarText: {color: theme.colors.primary, fontSize: 26, fontFamily: theme.typography.heading},
  profileName: {flex: 1, marginTop: 2, color: theme.colors.text, fontSize: 19, lineHeight: 24, fontFamily: theme.typography.heading},
  profileBio: {marginTop: 4, color: theme.colors.textMuted, fontSize: 12, lineHeight: 17, fontFamily: theme.typography.body},
  profileStats: {flexDirection: 'row', alignItems: 'stretch', gap: 8},
  profileStat: {flex: 1, minWidth: 0, minHeight: 62, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 4, borderRadius: theme.radii.md, backgroundColor: alpha(theme.colors.black, 0.16), borderWidth: 1, borderColor: theme.colors.border},
  profileStatValue: {color: theme.colors.text, fontSize: 16, lineHeight: 21, fontFamily: theme.typography.heading},
  profileStatLabel: {marginTop: 3, color: theme.colors.textSoft, fontSize: 9, textTransform: 'uppercase', letterSpacing: 0.35, fontFamily: theme.typography.bodyBold},
  profilePills: {flexDirection: 'row', flexWrap: 'wrap', gap: 8},
  profilePill: {minHeight: 32, borderRadius: theme.radii.pill, paddingHorizontal: 10, flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: alpha(theme.colors.primary, 0.1), borderWidth: 1, borderColor: alpha(theme.colors.primary, 0.18)},
  profilePillText: {color: theme.colors.textMuted, fontSize: 12, textTransform: 'capitalize', fontFamily: theme.typography.bodyBold},
  profileActions: {flexDirection: 'row', gap: 10},
  profileActionItem: {flex: 1, minWidth: 0},
  profileInvite: {gap: 9, paddingTop: 12, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: theme.colors.border},
  joinCard: {gap: 12},
  createCard: {gap: 12},
  cardTitle: {color: theme.colors.text, fontSize: 18, lineHeight: 24, fontFamily: theme.typography.heading},
  cardText: {marginTop: 6, color: theme.colors.textMuted, fontSize: 13, lineHeight: 19, fontFamily: theme.typography.body},
  fieldHint: {color: theme.colors.textSoft, fontSize: 12, fontFamily: theme.typography.bodyBold},
  invitePreview: {minHeight: 66, borderRadius: theme.radii.lg, padding: 10, flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: alpha(theme.colors.primary, 0.08), borderWidth: 1, borderColor: alpha(theme.colors.primary, 0.16)},
  previewPosterButton: {width: 36, height: 36, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: alpha(theme.colors.primary, 0.12), borderWidth: 1, borderColor: alpha(theme.colors.primary, 0.22)},
  publicRow: {flexDirection: 'row', gap: 12, alignItems: 'center', paddingVertical: 6},
  sectionHeader: {marginTop: 4, marginBottom: 2, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between'},
  sectionTitle: {color: theme.colors.text, fontSize: 18, fontFamily: theme.typography.heading},
  sectionLink: {color: theme.colors.primary, fontSize: 13, fontFamily: theme.typography.bodyBold},
  sectionCaption: {color: theme.colors.textSoft, fontSize: 12, textTransform: 'capitalize', fontFamily: theme.typography.bodyBold},
  listCard: {flexDirection: 'row', gap: 10, alignItems: 'center', minHeight: 68, paddingVertical: 12},
  squadAvatar: {width: 40, height: 40, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: alpha(theme.colors.primary, 0.14), borderWidth: 1, borderColor: alpha(theme.colors.primary, 0.2)},
  squadAvatarText: {color: theme.colors.primary, fontSize: 17, fontFamily: theme.typography.heading},
  rowCopy: {flex: 1},
  squadNameRow: {flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 7},
  memberTierBadge: {maxWidth: 132, minHeight: 22, flexShrink: 0, borderRadius: theme.radii.pill, paddingHorizontal: 7, flexDirection: 'row', alignItems: 'center', gap: 4, borderWidth: 1},
  memberTierText: {fontSize: 9, fontFamily: theme.typography.bodyBold},
  rowTitle: {color: theme.colors.text, fontSize: 15, lineHeight: 20, fontFamily: theme.typography.bodyBold},
  rowSubtitle: {marginTop: 2, color: theme.colors.textMuted, fontSize: 12, lineHeight: 17, fontFamily: theme.typography.body},
  rowMeta: {marginTop: 6, color: theme.colors.primary, fontSize: 11, fontFamily: theme.typography.bodyBold},
  compactAction: {minWidth: 58, minHeight: 36, paddingHorizontal: 12, alignItems: 'center', justifyContent: 'center', borderRadius: theme.radii.pill, borderWidth: 1, borderColor: alpha(theme.colors.primary, 0.42), backgroundColor: alpha(theme.colors.primary, 0.14)},
  compactActionDisabled: {opacity: 0.45},
  compactActionText: {color: theme.colors.primary, fontSize: 12, fontFamily: theme.typography.bodyBold},
  progressCard: {gap: 10},
  chestStatus: {color: theme.colors.success, fontSize: 13, fontFamily: theme.typography.bodyBold},
  progressTitleRow: {flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12},
  retryScoreButton: {flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 12, paddingVertical: 8, borderRadius: 18, backgroundColor: theme.colors.surfaceMuted},
  retryScoreText: {color: theme.colors.primary, fontSize: 13, fontFamily: theme.typography.bodyBold},
  boostBadge: {paddingHorizontal: 10, paddingVertical: 6, borderRadius: theme.radii.pill, backgroundColor: alpha(theme.colors.success, 0.16)},
  boostText: {color: theme.colors.success, fontSize: 12, fontFamily: theme.typography.bodyBold},
  progressTrack: {height: 10, overflow: 'hidden', borderRadius: theme.radii.pill, backgroundColor: alpha(theme.colors.surfaceStrong, 0.72)},
  progressFill: {height: '100%', borderRadius: theme.radii.pill, backgroundColor: theme.colors.primary},
  inviteHeader: {flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12},
  inviteCode: {color: theme.colors.primary, fontSize: 21, letterSpacing: 1, fontFamily: theme.typography.heading},
  inviteActions: {flexDirection: 'row', gap: 8},
  inviteAction: {flex: 1, minHeight: 42, borderRadius: theme.radii.md, paddingHorizontal: 8, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, backgroundColor: alpha(theme.colors.primary, 0.09), borderWidth: 1, borderColor: alpha(theme.colors.primary, 0.18)},
  inviteActionText: {color: theme.colors.text, fontSize: 11, fontFamily: theme.typography.bodyBold},
  templateRow: {marginTop: 10, flexDirection: 'row', flexWrap: 'wrap', gap: 8},
  templateChip: {minHeight: 28, borderRadius: theme.radii.pill, paddingHorizontal: 10, alignItems: 'center', justifyContent: 'center', backgroundColor: alpha(theme.colors.surfaceStrong, 0.8), borderWidth: 1, borderColor: theme.colors.border},
  templateChipActive: {backgroundColor: alpha(theme.colors.primary, 0.16), borderColor: alpha(theme.colors.primary, 0.35)},
  templateText: {color: theme.colors.textSoft, fontSize: 11, fontFamily: theme.typography.bodyBold},
  templateTextActive: {color: theme.colors.primary},
  shareCountBadge: {paddingHorizontal: 10, paddingVertical: 6, borderRadius: theme.radii.pill, backgroundColor: alpha(theme.colors.primary, 0.12), borderWidth: 1, borderColor: alpha(theme.colors.primary, 0.24)},
  shareCountText: {color: theme.colors.primary, fontSize: 11, fontFamily: theme.typography.bodyBold},
  missionRow: {flexDirection: 'row', gap: 10, alignItems: 'center', paddingVertical: 10, borderTopWidth: 1, borderTopColor: theme.colors.border},
  missionIcon: {width: 36, height: 36, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: alpha(theme.colors.primary, 0.14), borderWidth: 1, borderColor: alpha(theme.colors.primary, 0.22)},
  inlineProgressTrack: {height: 5, marginTop: 8, overflow: 'hidden', borderRadius: theme.radii.pill, backgroundColor: alpha(theme.colors.surfaceStrong, 0.9)},
  inlineProgressFill: {height: '100%', borderRadius: theme.radii.pill, backgroundColor: theme.colors.primary},
  missionActionWrap: {alignItems: 'flex-end', gap: 6, maxWidth: 92},
  rewardText: {color: theme.colors.primary, fontSize: 12, fontFamily: theme.typography.bodyBold},
  claimMiniButton: {minHeight: 30, minWidth: 64, borderRadius: theme.radii.pill, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 10, backgroundColor: theme.colors.primary},
  claimMiniButtonText: {color: theme.colors.background, fontSize: 11, fontFamily: theme.typography.bodyBold},
  claimedText: {color: theme.colors.success, fontSize: 11, textAlign: 'right', fontFamily: theme.typography.bodyBold},
  inviteStreakCard: {flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12},
  inviteStreakAction: {alignItems: 'flex-end', gap: 8},
  rewardPill: {overflow: 'hidden', borderRadius: theme.radii.pill, paddingHorizontal: 10, paddingVertical: 7, color: theme.colors.primary, fontSize: 12, fontFamily: theme.typography.bodyBold, backgroundColor: alpha(theme.colors.primary, 0.12)},
  announcementRow: {flexDirection: 'row', gap: 10, alignItems: 'flex-start', paddingVertical: 10, borderTopWidth: 1, borderTopColor: theme.colors.border},
  memberRow: {flexDirection: 'row', gap: 12, alignItems: 'center'},
  memberTabRow: {flexDirection: 'row', gap: 8, paddingRight: 12},
  memberState: {minHeight: 66, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 10},
  memberTabChip: {minHeight: 36, borderRadius: theme.radii.pill, paddingHorizontal: 10, flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: alpha(theme.colors.surfaceStrong, 0.84), borderWidth: 1, borderColor: theme.colors.border},
  memberTabChipActive: {backgroundColor: alpha(theme.colors.primary, 0.16), borderColor: alpha(theme.colors.primary, 0.35)},
  memberTabText: {color: theme.colors.textSoft, fontSize: 12, fontFamily: theme.typography.bodyBold},
  memberTabTextActive: {color: theme.colors.primary},
  memberTabCount: {overflow: 'hidden', minWidth: 22, textAlign: 'center', borderRadius: 11, paddingHorizontal: 6, paddingVertical: 2, color: theme.colors.text, fontSize: 11, fontFamily: theme.typography.bodyBold, backgroundColor: alpha(theme.colors.black, 0.18)},
  memberAvatar: {width: 38, height: 38, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: alpha(theme.colors.accent, 0.16)},
  roleActions: {marginTop: 9, flexDirection: 'row', flexWrap: 'wrap', gap: 7},
  roleActionChip: {minHeight: 28, borderRadius: theme.radii.pill, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 10, backgroundColor: alpha(theme.colors.surfaceStrong, 0.85), borderWidth: 1, borderColor: theme.colors.border},
  roleActionText: {color: theme.colors.textMuted, fontSize: 11, fontFamily: theme.typography.bodyBold},
  roleDangerChip: {backgroundColor: alpha(theme.colors.danger, 0.1), borderColor: alpha(theme.colors.danger, 0.2)},
  roleDangerText: {color: theme.colors.danger},
  rankBubble: {width: 42, height: 42, borderRadius: 21, alignItems: 'center', justifyContent: 'center', backgroundColor: alpha(theme.colors.surfaceStrong, 0.8)},
  rankBubbleTop: {backgroundColor: alpha(theme.colors.accentWarm, 0.2)},
  rankText: {color: theme.colors.text, fontSize: 12, fontFamily: theme.typography.bodyBold},
  rankValue: {alignItems: 'flex-end'},
  rankPoints: {color: theme.colors.text, fontSize: 16, fontFamily: theme.typography.heading},
  rankMeta: {marginTop: 2, color: theme.colors.primary, fontSize: 11, fontFamily: theme.typography.bodyBold},
  modalBackdrop: {flex: 1, justifyContent: 'center', padding: 18, backgroundColor: alpha(theme.colors.black, 0.74)},
  modalSheet: {maxHeight: '92%', borderRadius: 24, padding: 16, gap: 14, backgroundColor: theme.colors.surface, borderWidth: 1, borderColor: theme.colors.border},
  modalHeader: {flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12},
  modalTitle: {marginTop: 2, color: theme.colors.text, fontSize: 20, lineHeight: 25, fontFamily: theme.typography.heading},
  modalClose: {width: 38, height: 38, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: alpha(theme.colors.surfaceStrong, 0.86), borderWidth: 1, borderColor: theme.colors.border},
  posterScroll: {maxHeight: 470},
  posterScrollContent: {alignItems: 'center'},
  posterFrame: {width: '100%', maxWidth: 356, aspectRatio: 0.8, overflow: 'hidden', borderRadius: 22, backgroundColor: '#ffffff', borderWidth: 1, borderColor: alpha(theme.colors.primary, 0.18)},
  modalActions: {flexDirection: 'row', gap: 10, flexWrap: 'wrap'},
  qrFrame: {alignSelf: 'center', borderRadius: 28, padding: 18, backgroundColor: '#ffffff', borderWidth: 1, borderColor: alpha(theme.colors.primary, 0.18)},
  qrText: {color: theme.colors.textSoft, fontSize: 12, lineHeight: 17, textAlign: 'center', fontFamily: theme.typography.body},
});

export default Squads;
