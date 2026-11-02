import React from 'react';
import {Modal, Pressable, StyleSheet, Text, View} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import {PrimaryButton, SecondaryButton} from '../design/primitives';
import theme, {alpha} from '../design/theme';

type KycPaymentReminderProps = {
  visible: boolean;
  onClose: () => void;
  onPayNow: () => void;
};

const KycPaymentReminder = ({
  visible,
  onClose,
  onPayNow,
}: KycPaymentReminderProps) => (
  <Modal
    transparent
    visible={visible}
    animationType="fade"
    statusBarTranslucent
    onRequestClose={onClose}>
    <View style={styles.overlay}>
      <View style={styles.card}>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Do this later"
          onPress={onClose}
          style={styles.closeButton}>
          <Ionicons name="close" size={20} color={theme.colors.textMuted} />
        </Pressable>
        <View style={styles.iconWrap}>
          <Ionicons name="card-outline" size={26} color={theme.colors.primary} />
        </View>
        <Text style={styles.title}>Blockrium Verified membership</Text>
        <Text style={styles.message}>
          Your KYC is approved and your KYC Verified badge is active. The
          one-time payment is for Blockrium Verified membership, not KYC. It
          unlocks BRC transfers and member benefits.
        </Text>
        <PrimaryButton label="Activate membership" onPress={onPayNow} style={styles.action} />
        <SecondaryButton label="Later" onPress={onClose} style={styles.later} />
      </View>
    </View>
  </Modal>
);

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: 24,
    backgroundColor: alpha(theme.colors.background, 0.78),
  },
  card: {
    width: '100%',
    maxWidth: 390,
    alignSelf: 'center',
    alignItems: 'center',
    padding: 24,
    paddingTop: 30,
    borderRadius: theme.radii.xl,
    borderWidth: 1,
    borderColor: theme.colors.borderStrong,
    backgroundColor: theme.colors.surface,
  },
  closeButton: {
    position: 'absolute',
    top: 12,
    right: 12,
    width: 36,
    height: 36,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: theme.radii.pill,
    backgroundColor: alpha(theme.colors.text, 0.07),
  },
  iconWrap: {
    width: 58,
    height: 58,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: theme.radii.lg,
    backgroundColor: alpha(theme.colors.primary, 0.12),
  },
  title: {
    marginTop: 18,
    color: theme.colors.text,
    fontSize: 20,
    lineHeight: 26,
    textAlign: 'center',
    fontFamily: theme.typography.display,
  },
  message: {
    marginTop: 9,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 21,
    textAlign: 'center',
    fontFamily: theme.typography.body,
  },
  action: {
    width: '100%',
    marginTop: 22,
  },
  later: {
    width: '100%',
    marginTop: 10,
  },
});

export default KycPaymentReminder;
