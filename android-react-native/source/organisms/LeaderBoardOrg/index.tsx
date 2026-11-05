import React from 'react';
import {StyleSheet, Text, View} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import {
  AppScreen,
  GlassCard,
  SectionEyebrow,
  SecondaryButton,
} from '../../design/primitives';
import theme, {alpha} from '../../design/theme';
import {getArrayOrEmpty} from '../../utils/arrayPreview';

const LeaderBoardOrg = ({
  topMiners = [],
  authMiner,
  errorMessage,
  onRetry,
}: any) => {
  const normalizedTopMiners = getArrayOrEmpty<any>(topMiners);
  const leadMiners = normalizedTopMiners.slice(0, 3);
  const remainingMiners = normalizedTopMiners.slice(3);

  return (
    <AppScreen scrollable contentContainerStyle={styles.contentContainer}>
      <Text style={styles.screenTitle}>Leaderboard</Text>
      <Text style={styles.screenSubtitle}>
        Stronger hierarchy for top performers and a cleaner ranking list for the
        full network.
      </Text>

      {errorMessage ? (
        <GlassCard style={styles.errorCard}>
          <View style={styles.errorCopy}>
            <SectionEyebrow text="Ranking unavailable" />
            <Text style={styles.errorMessage}>{errorMessage}</Text>
          </View>
          <SecondaryButton label="Retry" onPress={onRetry} />
        </GlassCard>
      ) : null}

      {authMiner && typeof authMiner === 'object' && !Array.isArray(authMiner) ? (
        <GlassCard>
          <SectionEyebrow text="Your Position" />
          <View style={styles.selfRow}>
            <View>
              <Text style={styles.selfName}>
                {authMiner?.name ?? 'Current user'}
              </Text>
              <Text style={styles.selfCopy}>
                Stay visible in the network by mining consistently and inviting
                high-quality referrals.
              </Text>
            </View>
            <View style={styles.rankBadge}>
              <Text style={styles.rankBadgeLabel}>
                #{authMiner?.position ?? '--'}
              </Text>
            </View>
          </View>
        </GlassCard>
      ) : null}

      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Top performers</Text>
      </View>

      <View style={styles.podiumGrid}>
        {leadMiners.map((miner: any, index: number) => {
          const accent =
            index === 0
              ? theme.colors.accentWarm
              : index === 1
              ? theme.colors.primary
              : theme.colors.accent;

          return (
            <GlassCard
              key={`${miner?.name}-${index}`}
              style={styles.podiumCard}>
              <View
                style={[
                  styles.podiumIconShell,
                  {backgroundColor: alpha(accent, 0.16)},
                ]}>
                <Ionicons name="trophy-outline" size={22} color={accent} />
              </View>
              <Text style={styles.podiumName}>{miner?.name ?? 'Miner'}</Text>
              <Text style={styles.podiumRank}>
                Rank #{miner?.position ?? index + 1}
              </Text>
            </GlassCard>
          );
        })}
      </View>

      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>All rankings</Text>
      </View>

      {remainingMiners.map((miner: any, index: number) => (
        <GlassCard key={`${miner?.name}-${index + 3}`} style={styles.listCard}>
          <View style={styles.listIndex}>
            <Text style={styles.listIndexText}>#{index + 4}</Text>
          </View>
          <View style={styles.listCopy}>
            <Text style={styles.listName}>{miner?.name ?? 'Miner'}</Text>
            <Text style={styles.listSubtitle}>
              Position {miner?.position ?? index + 4} in the active miner board
            </Text>
          </View>
          <Ionicons
            name="chevron-forward"
            size={18}
            color={theme.colors.textSoft}
          />
        </GlassCard>
      ))}
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {
    paddingTop: 4,
    paddingBottom: 126,
  },
  screenTitle: {
    color: theme.colors.text,
    fontSize: 28,
    lineHeight: 32,
    fontFamily: theme.typography.heading,
  },
  screenSubtitle: {
    marginTop: 4,
    marginBottom: 12,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  errorCard: {
    gap: 14,
    marginBottom: 18,
  },
  errorCopy: {
    gap: 5,
  },
  errorMessage: {
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  selfRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  selfName: {
    color: theme.colors.text,
    fontSize: 19,
    fontFamily: theme.typography.heading,
  },
  selfCopy: {
    marginTop: 6,
    maxWidth: 220,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  rankBadge: {
    minWidth: 70,
    borderRadius: 18,
    paddingHorizontal: 12,
    paddingVertical: 9,
    alignItems: 'center',
    backgroundColor: 'rgba(120, 167, 255, 0.14)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  rankBadgeLabel: {
    color: theme.colors.text,
    fontSize: 17,
    fontFamily: theme.typography.heading,
  },
  sectionHeader: {
    marginBottom: 10,
  },
  sectionTitle: {
    color: theme.colors.text,
    fontSize: 20,
    fontFamily: theme.typography.heading,
  },
  podiumGrid: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 10,
  },
  podiumCard: {
    flex: 1,
    alignItems: 'center',
    minHeight: 116,
  },
  podiumIconShell: {
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 10,
  },
  podiumName: {
    color: theme.colors.text,
    textAlign: 'center',
    fontSize: 14,
    fontFamily: theme.typography.heading,
  },
  podiumRank: {
    marginTop: 5,
    color: theme.colors.textMuted,
    textAlign: 'center',
    fontSize: 13,
    fontFamily: theme.typography.body,
  },
  listCard: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  listIndex: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(24, 52, 87, 0.72)',
    marginRight: 12,
  },
  listIndexText: {
    color: theme.colors.text,
    fontSize: 14,
    fontFamily: theme.typography.bodyBold,
  },
  listCopy: {
    flex: 1,
  },
  listName: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.heading,
  },
  listSubtitle: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
});

export default LeaderBoardOrg;
