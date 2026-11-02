import React, {useEffect, useState} from 'react';
import {
  BackHandler,
  Image,
  Modal,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import {images} from '../../assets/images';
import theme from '../design/theme';
import {getStorageToken, setStorageToken} from '../utils/mmkvStorage';

export const DATA_CONSENT_STORAGE_KEY = '@blockrium:consent';

export const hasDataConsent = async () =>
  (await getStorageToken(DATA_CONSENT_STORAGE_KEY)) === 'agreed';

type ConsentAlertProps = {
  onConsentGranted?: () => void;
};

const ConsentAlert = ({onConsentGranted}: ConsentAlertProps) => {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const checkConsent = async () => {
      try {
        const consent = await getStorageToken(DATA_CONSENT_STORAGE_KEY);
        setVisible(consent !== 'agreed');
      } catch (error) {
        console.warn('Error reading consent:', error);
        setVisible(true);
      }
    };

    checkConsent();
  }, []);

  const handleAllow = async () => {
    try {
      await setStorageToken(DATA_CONSENT_STORAGE_KEY, 'agreed');
      onConsentGranted?.();
    } catch (error) {
      console.warn('Failed saving consent:', error);
    } finally {
      setVisible(false);
    }
  };

  const handleDecline = async () => {
    try {
      await setStorageToken(DATA_CONSENT_STORAGE_KEY, 'disagreed');
    } catch (error) {
      console.warn('Failed saving consent:', error);
    }

    setVisible(false);
    BackHandler.exitApp();
  };

  return (
    <Modal
      animationType="fade"
      transparent
      visible={visible}
      onRequestClose={handleDecline}>
      <View style={styles.overlay}>
        <View style={styles.sheet}>
          <View style={styles.brandRow}>
            <Image
              source={images.logo}
              style={styles.logo}
              resizeMode="contain"
            />
            <View style={styles.brandCopy}>
              <Text style={styles.eyebrow}>Privacy & Ads</Text>
              <Text style={styles.title}>Blockrium data permission</Text>
            </View>
          </View>

          <Text style={styles.body}>
            Blockrium uses limited device and app signals to load relevant ads,
            protect rewards from abuse, and keep the mining network sustainable.
            Your data is not sold, and it is only shared with trusted ads and
            analytics partners when needed for app operation.
          </Text>

          <Text style={styles.caption}>
            You can continue using the app after allowing, or close the app if
            you do not want ad-supported features enabled.
          </Text>

          <View style={styles.actions}>
            <Pressable
              onPress={handleDecline}
              style={[styles.actionButton, styles.secondaryButton]}>
              <Text style={styles.secondaryText}>Decline</Text>
            </Pressable>
            <Pressable
              onPress={handleAllow}
              style={[styles.actionButton, styles.primaryButton]}>
              <Text style={styles.primaryText}>Allow</Text>
            </Pressable>
          </View>
        </View>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(4, 10, 18, 0.8)',
    justifyContent: 'center',
    paddingHorizontal: 20,
  },
  sheet: {
    backgroundColor: theme.colors.surface,
    borderRadius: 28,
    borderWidth: 1,
    borderColor: theme.colors.borderStrong,
    paddingHorizontal: 20,
    paddingVertical: 22,
  },
  brandRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  logo: {
    width: 48,
    height: 48,
    borderRadius: 18,
  },
  brandCopy: {
    flex: 1,
    marginLeft: 14,
  },
  eyebrow: {
    color: theme.colors.primary,
    fontSize: 12,
    letterSpacing: 1.2,
    textTransform: 'uppercase',
    fontFamily: theme.typography.bodyBold,
  },
  title: {
    marginTop: 6,
    color: theme.colors.text,
    fontSize: 24,
    lineHeight: 28,
    fontFamily: theme.typography.display,
  },
  body: {
    marginTop: 18,
    color: theme.colors.text,
    fontSize: 15,
    lineHeight: 22,
    fontFamily: theme.typography.body,
  },
  caption: {
    marginTop: 12,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  actions: {
    flexDirection: 'row',
    marginTop: 22,
    justifyContent: 'space-between',
  },
  actionButton: {
    flex: 1,
    minHeight: 50,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 14,
  },
  secondaryButton: {
    marginRight: 6,
    backgroundColor: 'rgba(24, 52, 87, 0.88)',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  primaryButton: {
    marginLeft: 6,
    backgroundColor: theme.colors.primary,
  },
  secondaryText: {
    color: theme.colors.text,
    fontSize: 15,
    fontFamily: theme.typography.bodyBold,
  },
  primaryText: {
    color: '#04111D',
    fontSize: 15,
    fontFamily: theme.typography.bodyBold,
  },
});

export default ConsentAlert;
