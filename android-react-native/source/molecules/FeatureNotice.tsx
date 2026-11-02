import React from 'react';
import {Modal, Pressable, StyleSheet, Text, View} from 'react-native';
import LinearGradient from 'react-native-linear-gradient';
import MaterialCommunityIcons from 'react-native-vector-icons/MaterialCommunityIcons';
import theme, {alpha} from '../design/theme';

type FeatureNoticeProps = {
  visible: boolean;
  title: string;
  message: string;
  onClose: () => void;
};

const FeatureNotice = ({
  visible,
  title,
  message,
  onClose,
}: FeatureNoticeProps) => {
  return (
    <Modal transparent animationType="fade" visible={visible} onRequestClose={onClose}>
      <View style={styles.overlay}>
        <LinearGradient colors={theme.gradients.surface} style={styles.card}>
          <View style={styles.iconShell}>
            <MaterialCommunityIcons
              name="shield-star-outline"
              size={24}
              color={theme.colors.primary}
            />
          </View>
          <Text style={styles.title}>{title}</Text>
          <Text style={styles.message}>{message}</Text>
          <Pressable onPress={onClose}>
            <LinearGradient colors={theme.gradients.accent} style={styles.button}>
              <Text style={styles.buttonText}>Close</Text>
            </LinearGradient>
          </Pressable>
        </LinearGradient>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.background, 0.78),
    paddingHorizontal: theme.spacing.lg,
  },
  card: {
    width: '100%',
    borderRadius: theme.radii.xl,
    borderWidth: 1,
    borderColor: theme.colors.borderStrong,
    padding: theme.spacing.xl,
    alignItems: 'center',
  },
  iconShell: {
    width: 58,
    height: 58,
    borderRadius: 29,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.primary, 0.12),
    marginBottom: theme.spacing.lg,
  },
  title: {
    color: theme.colors.text,
    fontSize: 24,
    textAlign: 'center',
    fontFamily: theme.typography.display,
  },
  message: {
    marginTop: theme.spacing.md,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 20,
    textAlign: 'center',
    fontFamily: theme.typography.body,
  },
  button: {
    minWidth: 150,
    minHeight: 50,
    borderRadius: theme.radii.pill,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: theme.spacing.xl,
    marginTop: theme.spacing.xl,
  },
  buttonText: {
    color: theme.colors.background,
    fontSize: 15,
    fontFamily: theme.typography.bodyBold,
  },
});

export default FeatureNotice;
