import React, {useEffect, useState} from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import {AppScreen, AppTopNav, GlassCard, InlineLoader, SectionEyebrow} from '../../design/primitives';
import theme, {alpha} from '../../design/theme';
import {callQuery} from '../../hooks';
import {useMySquad, useSquadDiscovery} from '../../hooks/useSquads';
import {fetchCommunityLeaderboard} from '../../services/communityLeaderboardService';
import {getStorageId} from '../../utils/mmkvStorage';
import {navigate} from '../../utils/helper';
import {getCommunityLeaderboardPosition, getCommunityLeaderboardValue} from '../../utils/communityLeaderboard';
import {getCommunitySquadShortcutCopy} from '../../utils/communitySquadShortcut';

type CommunityTab = 'miners' | 'referrals';

const readList = (payload: any, keys: string[]) => {
  let value = payload?.data?.data ?? payload?.data ?? payload;
  for (const key of keys) {
    value = value?.[key];
  }
  return Array.isArray(value) ? value : [];
};

const Community = () => {
  const navigation = useNavigation<any>();
  const [tab, setTab] = useState<CommunityTab>('miners');
  const [userId, setUserId] = useState<number>();
  const {data: mine, refetch: refetchSquad, isLoading: isSquadLoading, isError: isSquadError, isFetching: isSquadFetching} = useMySquad();
  const {data: discover = [], isFetching: isDiscoverFetching, isError: isDiscoverError, refetch: refetchDiscover} = useSquadDiscovery();
  const minersQuery: any = callQuery(`top-miners?userId=${userId ?? ''}`, {
    enabled: tab === 'miners' && userId !== undefined,
    queryFn: ({signal}: {signal?: AbortSignal}) => fetchCommunityLeaderboard('miners', userId ?? 0, signal),
    retry: 0,
    staleTime: 60000,
  });
  const referralsQuery: any = callQuery(`top-refferals?userId=${userId ?? ''}`, {
    enabled: tab === 'referrals' && userId !== undefined,
    queryFn: ({signal}: {signal?: AbortSignal}) => fetchCommunityLeaderboard('referrals', userId ?? 0, signal),
    retry: 0,
    staleTime: 60000,
  });
  useEffect(() => {
    getStorageId('userId').then(id => setUserId(id));
  }, []);

  const refresh = () => {
    refetchSquad();
    if (tab === 'miners') {
      minersQuery.refetch();
    }
    if (tab === 'referrals') {
      referralsQuery.refetch();
    }
    refetchDiscover();
  };

  const activeQuery = tab === 'miners' ? minersQuery : referralsQuery;
  const rows = tab === 'miners'
    ? readList(minersQuery.data, ['top50Miners'])
    : readList(referralsQuery.data, ['top50Miners']);
  const squad = mine?.squad;
  const squadShortcut = getCommunitySquadShortcutCopy({
    isLoading: isSquadLoading,
    isError: isSquadError,
    hasSquad: Boolean(mine?.hasSquad),
    squad: mine?.hasSquad ? squad : null,
  });
  const sectionLabel = tab === 'miners' ? 'Top miners' : 'Top referrers';
  const openSquadDiscovery = () => navigation.navigate('Squads', {initialTab: 'discover'});

  return (
    <AppScreen scrollable refreshing={Boolean(activeQuery.isFetching || isSquadFetching)} onRefresh={refresh} contentContainerStyle={styles.content}>
      <AppTopNav
        title="Community"
        notificationCount={0}
        onNotificationsPress={() => navigate('Notifications')}
        onSearchPress={() => navigate('AppSearch')}
        onSettingsPress={() => navigate('AppSettings')}
      />

      <Pressable accessibilityRole="button" onPress={() => navigation.navigate('Squads')}>
        <GlassCard style={styles.squadShortcut}>
          <View style={styles.squadIcon}><Ionicons name="people" size={20} color={theme.colors.primary} /></View>
          <View style={styles.squadCopy}>
            <Text style={styles.squadLabel}>MY SQUAD</Text>
            <Text style={styles.squadTitle} numberOfLines={1}>{squadShortcut.title}</Text>
            {squadShortcut.meta ? <Text style={styles.squadMeta}>{squadShortcut.meta}</Text> : null}
          </View>
          <Ionicons name="chevron-forward" size={20} color={theme.colors.textSoft} />
        </GlassCard>
      </Pressable>

      <View style={styles.sectionHeading}>
        <Text style={styles.sectionTitle}>Top squads</Text>
        <Pressable accessibilityRole="button" onPress={openSquadDiscovery}>
          <Text style={styles.seeAll}>Browse squads</Text>
        </Pressable>
      </View>

      {isDiscoverFetching && discover.length === 0 ? (
        <GlassCard style={styles.stateCard}><InlineLoader label="Loading squads" /></GlassCard>
      ) : isDiscoverError && discover.length === 0 ? (
        <GlassCard style={styles.stateCard}>
          <Text style={styles.emptyText}>Squads are unavailable.</Text>
          <Pressable accessibilityRole="button" onPress={() => refetchDiscover()}><Text style={styles.seeAll}>Retry</Text></Pressable>
        </GlassCard>
      ) : discover.length ? (
        <GlassCard style={styles.rankingCard}>
          {discover.slice(0, 3).map((item, index) => (
            <Pressable
              key={item.id}
              accessibilityRole="button"
              accessibilityLabel={`Open ${item.name} in squad discovery`}
              onPress={openSquadDiscovery}
              style={[styles.row, index > 0 && styles.rowDivider]}>
              <View style={styles.squadAvatar}><Text style={styles.squadAvatarText}>{item.name.slice(0, 1).toUpperCase()}</Text></View>
              <View style={styles.rowCopy}>
                <Text style={styles.rowTitle} numberOfLines={1}>{item.name}</Text>
                <Text style={styles.rowValue}>{Number(item.memberCount).toLocaleString()} members</Text>
              </View>
              <Ionicons name="chevron-forward" size={18} color={theme.colors.textSoft} />
            </Pressable>
          ))}
        </GlassCard>
      ) : (
        <GlassCard style={styles.stateCard}>
          <Text style={styles.emptyText}>No public squads yet.</Text>
          <Pressable accessibilityRole="button" onPress={openSquadDiscovery}><Text style={styles.seeAll}>Open squads</Text></Pressable>
        </GlassCard>
      )}

      <View style={styles.sectionHeading}>
        <SectionEyebrow text="RANKINGS" />
        <Pressable accessibilityRole="button" onPress={() => navigation.navigate('LeaderBoardFlow')}>
          <Text style={styles.seeAll}>Full rankings</Text>
        </Pressable>
      </View>

      <View style={styles.tabs}>
        {([
          ['miners', 'Miners'],
          ['referrals', 'Referrals'],
        ] as [CommunityTab, string][]).map(([key, label]) => (
          <Pressable key={key} accessibilityRole="button" onPress={() => setTab(key)} style={[styles.tab, tab === key && styles.tabActive]}>
            <Text style={[styles.tabText, tab === key && styles.tabTextActive]}>{label}</Text>
          </Pressable>
        ))}
      </View>

      <View style={styles.sectionHeading}>
        <Text style={styles.sectionTitle}>{sectionLabel}</Text>
        <Text style={styles.sectionCaption}>Top 8</Text>
      </View>

      {activeQuery.isLoading ? (
        <GlassCard style={styles.stateCard}><InlineLoader label="Loading" /></GlassCard>
      ) : activeQuery.isError ? (
        <GlassCard style={styles.stateCard}>
          <Text style={styles.emptyText}>Couldn’t load this list.</Text>
          <Pressable accessibilityRole="button" onPress={refresh}><Text style={styles.seeAll}>Try again</Text></Pressable>
        </GlassCard>
      ) : rows.length ? (
        <GlassCard style={styles.rankingCard}>
          {rows.slice(0, 8).map((item: any, index: number) => {
            const name = String(item?.name ?? item?.username ?? item?.title ?? 'Blockrium member');
            const value = getCommunityLeaderboardValue(item, tab);
            const position = getCommunityLeaderboardPosition(item, index + 1);
            return (
              <View key={String(item?.id ?? item?.user_id ?? `${tab}-${index}`)} style={[styles.row, index > 0 && styles.rowDivider]}>
                <View style={[styles.rank, index === 0 && styles.rankFirst]}>
                  <Text style={styles.rankText}>{index + 1}</Text>
                </View>
                <View style={styles.rowCopy}>
                  <Text style={styles.rowTitle} numberOfLines={1}>{name}</Text>
                  {value ? <Text style={styles.rowValue} numberOfLines={2}>{value}</Text> : null}
                </View>
                <Text style={styles.rowPosition}>#{position}</Text>
              </View>
            );
          })}
        </GlassCard>
      ) : (
        <GlassCard style={styles.stateCard}>
          <Text style={styles.emptyText}>No {tab === 'miners' ? 'miners' : 'referrers'} to show.</Text>
        </GlassCard>
      )}
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  content: {paddingHorizontal: 18, paddingBottom: 126, gap: 14},
  squadShortcut: {minHeight: 84, flexDirection: 'row', alignItems: 'center', gap: 12},
  squadIcon: {width: 44, height: 44, borderRadius: 15, alignItems: 'center', justifyContent: 'center', backgroundColor: alpha(theme.colors.primary, 0.13)},
  squadAvatar: {width: 38, height: 38, borderRadius: 13, alignItems: 'center', justifyContent: 'center', backgroundColor: alpha(theme.colors.primary, 0.13), borderWidth: 1, borderColor: alpha(theme.colors.primary, 0.22)},
  squadAvatarText: {color: theme.colors.primary, fontSize: 16, fontFamily: theme.typography.heading},
  squadCopy: {flex: 1, minWidth: 0},
  squadLabel: {color: theme.colors.primary, fontSize: 9, letterSpacing: 1, fontFamily: theme.typography.bodyBold},
  squadTitle: {marginTop: 3, color: theme.colors.text, fontSize: 15, fontFamily: theme.typography.heading},
  squadMeta: {marginTop: 2, color: theme.colors.textMuted, fontSize: 12, fontFamily: theme.typography.body},
  sectionHeading: {flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between'},
  seeAll: {color: theme.colors.primary, fontSize: 12, fontFamily: theme.typography.bodyBold},
  tabs: {flexDirection: 'row', padding: 4, gap: 4, borderRadius: theme.radii.lg, backgroundColor: alpha(theme.colors.black, 0.2), borderWidth: 1, borderColor: theme.colors.border},
  tab: {flex: 1, minHeight: 38, borderRadius: theme.radii.md, alignItems: 'center', justifyContent: 'center'},
  tabActive: {backgroundColor: alpha(theme.colors.primary, 0.15)},
  tabText: {color: theme.colors.textSoft, fontSize: 12, fontFamily: theme.typography.bodyBold},
  tabTextActive: {color: theme.colors.primary},
  sectionTitle: {color: theme.colors.text, fontSize: 18, fontFamily: theme.typography.heading},
  sectionCaption: {color: theme.colors.textSoft, fontSize: 11, fontFamily: theme.typography.bodyBold},
  rankingCard: {paddingVertical: 2},
  row: {minHeight: 62, flexDirection: 'row', alignItems: 'center', gap: 11},
  rowDivider: {borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: theme.colors.border},
  rank: {width: 32, height: 32, borderRadius: 12, alignItems: 'center', justifyContent: 'center', backgroundColor: alpha(theme.colors.primary, 0.1)},
  rankFirst: {backgroundColor: alpha(theme.colors.accentWarm, 0.17)},
  rankText: {color: theme.colors.primary, fontSize: 12, fontFamily: theme.typography.bodyBold},
  rowCopy: {flex: 1, minWidth: 0},
  rowTitle: {color: theme.colors.text, fontSize: 14, fontFamily: theme.typography.bodyBold},
  rowValue: {marginTop: 2, color: theme.colors.textMuted, fontSize: 12, lineHeight: 17, fontFamily: theme.typography.body},
  rowPosition: {color: theme.colors.textSoft, fontSize: 11, fontFamily: theme.typography.bodyBold},
  stateCard: {minHeight: 78, alignItems: 'center', justifyContent: 'center', gap: 8},
  emptyText: {color: theme.colors.textMuted, fontSize: 13, fontFamily: theme.typography.body},
});

export default Community;
