import React from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';
import theme, {alpha} from '../design/theme';

type AppMode = 'blockrium' | 'wallet';

type AppModeSwitcherProps = {
  activeMode: AppMode;
  onSelectMode: (mode: AppMode) => void;
};

const AppModeSwitcher = ({
  activeMode,
  onSelectMode,
}: AppModeSwitcherProps) => {
  return (
    <View style={styles.shell}>
      {[
        {key: 'blockrium' as AppMode, label: 'Blockrium'},
        {key: 'wallet' as AppMode, label: 'Wallet'},
      ].map(item => {
        const isActive = item.key === activeMode;

        return (
          <Pressable
            key={item.key}
            onPress={() => onSelectMode(item.key)}
            style={[styles.segment, isActive && styles.segmentActive]}>
            <Text style={[styles.segmentText, isActive && styles.segmentTextActive]}>
              {item.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
};

const styles = StyleSheet.create({
  shell: {
    flexDirection: 'row',
    borderRadius: theme.radii.pill,
    borderWidth: 1,
    borderColor: theme.colors.borderStrong,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.48),
    padding: 4,
  },
  segment: {
    flex: 1,
    minHeight: 42,
    borderRadius: theme.radii.pill,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: theme.spacing.md,
  },
  segmentActive: {
    backgroundColor: alpha(theme.colors.primary, 0.18),
  },
  segmentText: {
    color: theme.colors.textSoft,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
  },
  segmentTextActive: {
    color: theme.colors.text,
  },
});

export default AppModeSwitcher;
