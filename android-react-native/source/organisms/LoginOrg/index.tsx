import React from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';
import FastImage from 'react-native-fast-image';
import {images} from '../../../assets/images';
import useAuthData from '../../storage/client/useAuthData';
import {
  AppScreen,
  AppTextField,
  GlassCard,
  PrimaryButton,
  SectionEyebrow,
} from '../../design/primitives';
import theme, {alpha} from '../../design/theme';
import {navigate} from '../../utils/helper';

const LoginOrg = ({isSubmitting = false, onPressLogin, onPressSignUp}: loginUiProps) => {
  const {fields, setField} = useAuthData();

  return (
    <AppScreen
      scrollable
      contentContainerStyle={styles.contentContainer}>
      <View style={styles.authHeader}>
        <View style={styles.logoPanel}>
          <FastImage source={images.logo} style={styles.logo} resizeMode="contain" />
        </View>
        <Text style={styles.brandTitle}>Blockrium</Text>
        <Text style={styles.brandSubtitle}>Web3 rewards and wallet network</Text>
        <View style={styles.signalRow}>
          <Text style={styles.signalText}>Mining</Text>
          <Text style={styles.signalDot}>•</Text>
          <Text style={styles.signalText}>Wallet</Text>
          <Text style={styles.signalDot}>•</Text>
          <Text style={styles.signalText}>Squads</Text>
        </View>
      </View>

      <GlassCard style={styles.formCard}>
        <SectionEyebrow text="Sign in" />
        <Text style={styles.formTitle}>Welcome back</Text>
        <AppTextField
          label="Email"
          leadingIcon="mail-outline"
          placeholder="you@example.com"
          value={fields.loginEmail}
          autoCapitalize="none"
          keyboardType="email-address"
          onChangeText={text => setField('loginEmail', text)}
        />
        <AppTextField
          label="Password"
          leadingIcon="lock-closed-outline"
          placeholder="Enter your password"
          value={fields.loginPassword}
          secureTextEntry
          onChangeText={text => setField('loginPassword', text)}
        />
        <Pressable onPress={() => navigate('ForgotPassword')}>
          <Text style={styles.forgotText}>Forgot your password?</Text>
        </Pressable>
        <PrimaryButton
          label={isSubmitting ? 'Signing in...' : 'Sign in'}
          icon="login"
          onPress={onPressLogin}
          disabled={isSubmitting}
          style={styles.primaryAction}
        />
      </GlassCard>

      <View style={styles.signupRow}>
        <Text style={styles.signupText}>New to Blockrium?</Text>
        <Pressable
          disabled={isSubmitting}
          hitSlop={8}
          onPress={onPressSignUp}>
          <Text style={styles.signupAction}>Create account</Text>
        </Pressable>
      </View>
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingTop: 28,
    paddingBottom: 54,
  },
  authHeader: {
    alignItems: 'center',
    marginBottom: 22,
  },
  logoPanel: {
    width: 82,
    height: 82,
    borderRadius: 24,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 14,
    backgroundColor: alpha(theme.colors.primary, 0.12),
    borderWidth: 1,
    borderColor: alpha(theme.colors.primary, 0.26),
  },
  logo: {
    width: 64,
    height: 64,
  },
  brandTitle: {
    color: theme.colors.text,
    fontSize: 31,
    lineHeight: 35,
    fontFamily: theme.typography.display,
  },
  brandSubtitle: {
    marginTop: 6,
    color: theme.colors.textMuted,
    fontSize: 14,
    fontFamily: theme.typography.body,
  },
  signalRow: {
    marginTop: 12,
    minHeight: 32,
    paddingHorizontal: 14,
    borderRadius: 17,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.46),
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  signalText: {
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  signalDot: {
    color: theme.colors.primary,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  formCard: {
    marginBottom: 16,
    borderColor: alpha(theme.colors.primary, 0.18),
  },
  formTitle: {
    marginBottom: 18,
    color: theme.colors.text,
    fontSize: 22,
    lineHeight: 26,
    fontFamily: theme.typography.heading,
  },
  forgotText: {
    marginTop: -4,
    marginBottom: 16,
    textAlign: 'right',
    color: theme.colors.accent,
    fontSize: 14,
    fontFamily: theme.typography.bodyBold,
  },
  primaryAction: {
    marginTop: 2,
  },
  signupRow: {
    minHeight: 48,
    borderRadius: 20,
    paddingHorizontal: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.42),
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  signupText: {
    color: theme.colors.textMuted,
    fontSize: 14,
    fontFamily: theme.typography.body,
  },
  signupAction: {
    color: theme.colors.primary,
    fontSize: 14,
    fontFamily: theme.typography.bodyBold,
  },
});

export default LoginOrg;
