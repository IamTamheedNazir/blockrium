import React from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import MaterialCommunityIcons from 'react-native-vector-icons/MaterialCommunityIcons';
import {GlassCard, SectionEyebrow} from '../design/primitives';
import theme from '../design/theme';
import {
  BadgesData,
  DailyMission,
  DailyMissionsData,
  GamificationSummary,
} from '../types/engagement';

const statusColors = {
  ready: theme.colors.primary,
  completed: theme.colors.success,
  active: theme.colors.accent,
  locked: theme.colors.textSoft,
  unavailable: theme.colors.textSoft,
  error: theme.colors.danger,
};

const actionIcons: Record<string, string> = {
  mining: 'pickaxe',
  streak: 'calendar-check-outline',
  tasks: 'check-decagram-outline',
  referral: 'account-multiple-outline',
  profile: 'account-outline',
  wallet: 'wallet-outline',
  learn: 'school-outline',
};

const clampPercent = (value: unknown) => {
  const number = Number(value);
  return Number.isFinite(number) ? Math.min(100, Math.max(0, number)) : 0;
};

export const LevelProgressCard: React.FC<{
  summary?: GamificationSummary;
  loading?: boolean;
  error?: boolean;
  onRetry?: () => void;
  compact?: boolean;
}> = ({summary, loading, error, onRetry, compact = false}) => {
  if (loading) {
    return (
      <GlassCard style={styles.levelCard}>
        <ActivityIndicator size="small" color={theme.colors.primary} />
        <Text style={styles.loadingText}>Loading level progress...</Text>
      </GlassCard>
    );
  }

  if (error || !summary) {
    return (
      <GlassCard style={styles.levelCard}>
        <View style={styles.rowBetween}>
          <View style={styles.flex}>
            <SectionEyebrow text="Progress" />
            <Text style={styles.stateTitle}>Level progress unavailable</Text>
          </View>
          {onRetry ? (
            <Pressable onPress={onRetry} style={styles.retryButton}>
              <Ionicons name="refresh" size={17} color={theme.colors.primary} />
            </Pressable>
          ) : null}
        </View>
      </GlassCard>
    );
  }

  const progress = clampPercent(summary.progressPercent);
  const seasonLabel = summary.season?.label || summary.season?.name || 'Genesis Season';

  return (
    <GlassCard style={[styles.levelCard, compact && styles.compactCard]}>
      <View style={styles.rowBetween}>
        <View style={styles.flex}>
          <SectionEyebrow text={`Level ${summary.level}`} />
          <Text style={styles.levelTitle}>{summary.levelName}</Text>
        </View>
        <View style={styles.xpPill}>
          <Text style={styles.xpValue}>{Math.round(summary.xp).toLocaleString()}</Text>
          <Text style={styles.xpLabel}>XP</Text>
        </View>
      </View>
      <View style={styles.progressTrack}>
        <View style={[styles.progressFill, {width: `${progress}%`}]} />
      </View>
      <View style={styles.progressMeta}>
        <Text style={styles.metaText}>
          {summary.nextLevelXp > 0
            ? `${Math.round(summary.currentLevelXp).toLocaleString()} / ${Math.round(
                summary.nextLevelXp,
              ).toLocaleString()} XP`
            : `${Math.round(summary.xp).toLocaleString()} XP earned`}
        </Text>
        <Text style={styles.seasonText}>
          {summary.season && summary.season.progressPercent > 0
            ? `${seasonLabel} / ${Math.round(summary.season.progressPercent)}%`
            : seasonLabel}
        </Text>
      </View>
    </GlassCard>
  );
};

const MissionRow: React.FC<{
  mission: DailyMission;
  onPress: (mission: DailyMission) => void;
}> = ({mission, onPress}) => {
  const disabled =
    mission.status === 'locked' ||
    mission.status === 'unavailable' ||
    mission.status === 'error';
  const color = statusColors[mission.status];

  return (
    <Pressable
      disabled={disabled}
      onPress={() => onPress(mission)}
      style={({pressed}) => [
        styles.missionRow,
        disabled && styles.missionDisabled,
        pressed && !disabled && styles.pressed,
      ]}>
      <View style={[styles.missionIcon, {borderColor: color}]}>
        <MaterialCommunityIcons
          name={actionIcons[mission.action ?? 'tasks'] ?? 'checkbox-marked-circle-outline'}
          size={18}
          color={color}
        />
      </View>
      <View style={styles.missionCopy}>
        <Text style={styles.missionTitle} numberOfLines={1}>
          {mission.title}
        </Text>
        {mission.subtitle ? (
          <Text style={styles.missionSubtitle} numberOfLines={1}>
            {mission.subtitle}
          </Text>
        ) : null}
        {mission.progressPercent > 0 && mission.progressPercent < 100 ? (
          <View style={styles.miniTrack}>
            <View
              style={[
                styles.miniFill,
                {width: `${clampPercent(mission.progressPercent)}%`},
              ]}
            />
          </View>
        ) : null}
      </View>
      <View style={styles.missionMeta}>
        {mission.rewardLabel ? (
          <Text style={styles.rewardText} numberOfLines={1}>
            {mission.rewardLabel}
          </Text>
        ) : null}
        <View style={[styles.statusPill, {borderColor: color}]}>
          <Text style={[styles.statusText, {color}]}>{mission.status}</Text>
        </View>
      </View>
    </Pressable>
  );
};

export const DailyMissionHub: React.FC<{
  data?: DailyMissionsData;
  loading?: boolean;
  error?: boolean;
  fetching?: boolean;
  maxItems?: number;
  onRetry: () => void;
  onMissionPress: (mission: DailyMission) => void;
}> = ({
  data,
  loading,
  error,
  fetching,
  maxItems = 6,
  onRetry,
  onMissionPress,
}) => {
  if (loading) {
    return (
      <GlassCard style={styles.hubCard}>
        <View style={styles.loadingRow}>
          <ActivityIndicator size="small" color={theme.colors.primary} />
          <View>
            <Text style={styles.stateTitle}>Preparing today's plan</Text>
            <Text style={styles.stateCopy}>Checking live mission progress.</Text>
          </View>
        </View>
      </GlassCard>
    );
  }

  if (error || !data) {
    return (
      <GlassCard style={styles.hubCard}>
        <View style={styles.rowBetween}>
          <View style={styles.flex}>
            <SectionEyebrow text="Today's Plan" />
            <Text style={styles.stateTitle}>Missions are temporarily unavailable</Text>
            <Text style={styles.stateCopy}>Check your connection and try again.</Text>
          </View>
          <Pressable onPress={onRetry} style={styles.retryButton}>
            <Ionicons name="refresh" size={17} color={theme.colors.primary} />
          </Pressable>
        </View>
      </GlassCard>
    );
  }

  const missions = data.missions.slice(0, Math.max(1, maxItems));
  const progress = clampPercent(data.summary.progressPercent);

  return (
    <GlassCard style={styles.hubCard}>
      <View style={styles.rowBetween}>
        <View style={styles.flex}>
          <SectionEyebrow text="Today's Plan" />
          <Text style={styles.hubTitle}>Your next actions</Text>
        </View>
        <View style={styles.countPill}>
          {fetching ? (
            <ActivityIndicator size="small" color={theme.colors.primary} />
          ) : (
            <Text style={styles.countText}>
              {data.summary.completedCount}/{data.summary.totalCount}
            </Text>
          )}
        </View>
      </View>
      <View style={styles.progressTrack}>
        <View style={[styles.progressFill, {width: `${progress}%`}]} />
      </View>
      <Text style={styles.planProgress}>{Math.round(progress)}% complete</Text>
      {missions.length === 0 ? (
        <View style={styles.emptyState}>
          <Ionicons name="checkmark-circle-outline" size={22} color={theme.colors.success} />
          <Text style={styles.emptyText}>No missions are available right now.</Text>
        </View>
      ) : (
        <View style={styles.missionList}>
          {missions.map(mission => (
            <MissionRow key={mission.key} mission={mission} onPress={onMissionPress} />
          ))}
        </View>
      )}
    </GlassCard>
  );
};

export const BadgeStrip: React.FC<{
  data?: BadgesData;
  summaryBadges?: GamificationSummary['badges'];
  loading?: boolean;
  error?: boolean;
  onRetry?: () => void;
}> = ({data, summaryBadges = [], loading, error, onRetry}) => {
  const earned = data?.earned.length ? data.earned : summaryBadges.filter(badge => badge.earned);
  const available = data?.available ?? [];
  const visibleBadges = [...earned, ...available].slice(0, 6);

  return (
    <GlassCard>
      <View style={styles.rowBetween}>
        <View style={styles.flex}>
          <SectionEyebrow text="Badges" />
          <Text style={styles.hubTitle}>Achievements</Text>
        </View>
        <Text style={styles.badgeCount}>{earned.length} earned</Text>
      </View>
      {loading ? (
        <View style={styles.loadingRow}>
          <ActivityIndicator size="small" color={theme.colors.primary} />
          <Text style={styles.loadingText}>Loading badges...</Text>
        </View>
      ) : error ? (
        <Pressable onPress={onRetry} style={styles.inlineRetry}>
          <Text style={styles.stateCopy}>Badges are unavailable.</Text>
          <Ionicons name="refresh" size={17} color={theme.colors.primary} />
        </Pressable>
      ) : visibleBadges.length === 0 ? (
        <View style={styles.emptyState}>
          <MaterialCommunityIcons
            name="medal-outline"
            size={22}
            color={theme.colors.textSoft}
          />
          <Text style={styles.emptyText}>Complete missions to unlock your first badge.</Text>
        </View>
      ) : (
        <View style={styles.badgeGrid}>
          {visibleBadges.map((badge, index) => (
            <View
              key={`${badge.id}-${index}`}
              style={[styles.badgeItem, !badge.earned && styles.badgeLocked]}>
              <View style={[styles.badgeIcon, badge.earned && styles.badgeIconEarned]}>
                <MaterialCommunityIcons
                  name={badge.earned ? 'medal' : 'lock-outline'}
                  size={18}
                  color={badge.earned ? theme.colors.background : theme.colors.textSoft}
                />
              </View>
              <Text style={styles.badgeTitle} numberOfLines={2}>
                {badge.title}
              </Text>
            </View>
          ))}
        </View>
      )}
    </GlassCard>
  );
};

const styles = StyleSheet.create({
  flex: {flex: 1, minWidth: 0},
  levelCard: {gap: 12},
  compactCard: {paddingVertical: 16},
  rowBetween: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 12,
  },
  levelTitle: {
    color: theme.colors.text,
    fontSize: 21,
    fontFamily: theme.typography.heading,
  },
  xpPill: {
    minWidth: 74,
    minHeight: 48,
    borderRadius: theme.radii.md,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(120, 167, 255, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(120, 167, 255, 0.25)',
  },
  xpValue: {color: theme.colors.text, fontSize: 16, fontFamily: theme.typography.heading},
  xpLabel: {color: theme.colors.accent, fontSize: 10, fontFamily: theme.typography.bodyBold},
  progressTrack: {
    height: 7,
    borderRadius: 4,
    overflow: 'hidden',
    backgroundColor: 'rgba(120, 167, 255, 0.13)',
  },
  progressFill: {height: '100%', borderRadius: 4, backgroundColor: theme.colors.primary},
  progressMeta: {flexDirection: 'row', justifyContent: 'space-between', gap: 10},
  metaText: {color: theme.colors.textMuted, fontSize: 12, fontFamily: theme.typography.body},
  seasonText: {color: theme.colors.accent, fontSize: 12, fontFamily: theme.typography.bodyBold},
  hubCard: {paddingBottom: 15},
  hubTitle: {color: theme.colors.text, fontSize: 20, fontFamily: theme.typography.heading},
  countPill: {
    minWidth: 52,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(47, 224, 196, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(47, 224, 196, 0.24)',
  },
  countText: {color: theme.colors.primary, fontSize: 13, fontFamily: theme.typography.bodyBold},
  planProgress: {marginTop: 7, color: theme.colors.textSoft, fontSize: 11, fontFamily: theme.typography.bodyBold},
  missionList: {marginTop: 12, gap: 8},
  missionRow: {
    minHeight: 62,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 10,
    paddingVertical: 9,
    borderRadius: theme.radii.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(18, 39, 66, 0.42)',
  },
  missionDisabled: {opacity: 0.58},
  pressed: {opacity: 0.84, transform: [{scale: 0.992}]},
  missionIcon: {
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    backgroundColor: 'rgba(8, 17, 31, 0.46)',
  },
  missionCopy: {flex: 1, minWidth: 0},
  missionTitle: {color: theme.colors.text, fontSize: 14, fontFamily: theme.typography.bodyBold},
  missionSubtitle: {marginTop: 2, color: theme.colors.textMuted, fontSize: 11, fontFamily: theme.typography.body},
  missionMeta: {alignItems: 'flex-end', gap: 5, maxWidth: 92},
  rewardText: {color: theme.colors.text, fontSize: 10, fontFamily: theme.typography.bodyBold},
  statusPill: {minHeight: 23, borderRadius: 12, paddingHorizontal: 8, justifyContent: 'center', borderWidth: 1},
  statusText: {fontSize: 9, textTransform: 'capitalize', fontFamily: theme.typography.bodyBold},
  miniTrack: {height: 3, marginTop: 5, borderRadius: 2, overflow: 'hidden', backgroundColor: theme.colors.border},
  miniFill: {height: '100%', backgroundColor: theme.colors.accent},
  retryButton: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(24, 52, 87, 0.52)',
  },
  stateTitle: {color: theme.colors.text, fontSize: 16, fontFamily: theme.typography.heading},
  stateCopy: {marginTop: 4, color: theme.colors.textMuted, fontSize: 12, lineHeight: 17, fontFamily: theme.typography.body},
  loadingRow: {flexDirection: 'row', alignItems: 'center', gap: 12},
  loadingText: {color: theme.colors.textMuted, fontSize: 13, fontFamily: theme.typography.body},
  emptyState: {marginTop: 14, flexDirection: 'row', alignItems: 'center', gap: 9},
  emptyText: {flex: 1, color: theme.colors.textMuted, fontSize: 13, lineHeight: 18, fontFamily: theme.typography.body},
  badgeCount: {color: theme.colors.textMuted, fontSize: 12, fontFamily: theme.typography.bodyBold},
  inlineRetry: {marginTop: 14, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between'},
  badgeGrid: {marginTop: 14, flexDirection: 'row', flexWrap: 'wrap', gap: 9},
  badgeItem: {
    width: '31%',
    minHeight: 91,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 8,
    borderRadius: theme.radii.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(24, 52, 87, 0.38)',
  },
  badgeLocked: {opacity: 0.55},
  badgeIcon: {
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(113, 131, 157, 0.14)',
  },
  badgeIconEarned: {backgroundColor: theme.colors.primary},
  badgeTitle: {marginTop: 7, color: theme.colors.text, fontSize: 11, lineHeight: 14, textAlign: 'center', fontFamily: theme.typography.bodyBold},
});
