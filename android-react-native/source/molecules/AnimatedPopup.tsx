import React, {useEffect, useRef, useState} from 'react';
import {
  Animated,
  Modal,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import LinearGradient from 'react-native-linear-gradient';
import theme, {alpha} from '../design/theme';

interface AnimatedPopupProps {
  showPopup: boolean;
  onClose?: () => void;
  title?: string;
  message?: string;
  buttonLabel?: string;
}

const AnimatedPopup: React.FC<AnimatedPopupProps> = ({
  showPopup,
  onClose,
  title = 'Coming soon',
  message = 'This module is being prepared for a polished release.',
  buttonLabel = 'Close',
}) => {
  const [visible, setVisible] = useState(false);
  const scaleValue = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    if (showPopup) {
      setVisible(true);
      openPopup();
    } else {
      closePopup();
    }
  }, [showPopup]);

  const openPopup = () => {
    Animated.spring(scaleValue, {
      toValue: 1,
      friction: 6,
      useNativeDriver: true,
    }).start();
  };

  const closePopup = () => {
    Animated.timing(scaleValue, {
      toValue: 0,
      duration: 300,
      useNativeDriver: true,
    }).start(() => {
      setVisible(false);
      onClose && onClose();
    });
  };

  return (
    <Modal transparent visible={visible} animationType="fade">
      <View style={styles.overlay}>
        <Animated.View style={[styles.popup, {transform: [{scale: scaleValue}]}]}>
          <LinearGradient colors={theme.gradients.surface} style={styles.popupCard}>
            <View style={styles.badge}>
              <Text style={styles.badgeText}>BLOCKRIUM</Text>
            </View>
            <Text style={styles.popupText}>{title}</Text>
            <Text style={styles.popupMessage}>{message}</Text>
            <Pressable onPress={closePopup}>
              <LinearGradient colors={theme.gradients.accent} style={styles.closeButton}>
                <Text style={styles.closeText}>{buttonLabel}</Text>
              </LinearGradient>
            </Pressable>
          </LinearGradient>
        </Animated.View>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: alpha(theme.colors.background, 0.68),
    paddingHorizontal: 24,
  },
  popup: {
    width: '100%',
    maxWidth: 340,
  },
  popupCard: {
    borderRadius: 22,
    borderWidth: 1,
    borderColor: theme.colors.borderStrong,
    padding: 18,
    alignItems: 'center',
    overflow: 'hidden',
  },
  badge: {
    borderRadius: theme.radii.pill,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: alpha(theme.colors.primary, 0.12),
    paddingHorizontal: 12,
    paddingVertical: 6,
    marginBottom: 18,
  },
  badgeText: {
    color: theme.colors.primary,
    fontSize: 11,
    letterSpacing: 1.4,
    fontFamily: theme.typography.bodyBold,
  },
  popupText: {
    fontSize: 24,
    lineHeight: 26,
    color: theme.colors.text,
    textAlign: 'center',
    fontFamily: theme.typography.display,
  },
  popupMessage: {
    marginTop: 12,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 20,
    textAlign: 'center',
    fontFamily: theme.typography.body,
  },
  closeButton: {
    minWidth: 144,
    minHeight: 48,
    borderRadius: theme.radii.pill,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 18,
    marginTop: 22,
  },
  closeText: {
    color: theme.colors.background,
    fontSize: 15,
    fontFamily: theme.typography.bodyBold,
  },
});

export default AnimatedPopup;
