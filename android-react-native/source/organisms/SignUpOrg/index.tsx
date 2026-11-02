import React from 'react';
import {StyleSheet, Text, View} from 'react-native';
import FastImage from 'react-native-fast-image';
import {images} from '../../../assets/images';
import useAuthData from '../../storage/client/useAuthData';
import {
  AppScreen,
  AppTextField,
  GlassCard,
  PrimaryButton,
  SecondaryButton,
  SectionEyebrow,
} from '../../design/primitives';
import theme, {alpha} from '../../design/theme';
import {navigate} from '../../utils/helper';
import type {SignupFieldErrors} from '../../utils/signupValidation';

const SignUpOrg = ({
  isSubmitting = false,
  fieldErrors = {},
  onFieldChange,
  onPress,
}: {
  isSubmitting?: boolean;
  fieldErrors?: SignupFieldErrors;
  onFieldChange?: (field: keyof SignupFieldErrors) => void;
  onPress?: () => void | Promise<void>;
}) => {
  const {fields, setField} = useAuthData();
  const clearError = (field: keyof SignupFieldErrors) =>
    fieldErrors[field] ? onFieldChange?.(field) : undefined;

  return (
    <AppScreen
      scrollable
      contentContainerStyle={styles.contentContainer}>
      <View style={styles.heroRow}>
        <View style={styles.logoPanel}>
          <FastImage source={images.logo} style={styles.logo} resizeMode="contain" />
        </View>
        <Text style={styles.heroTitle}>Create your Blockrium identity</Text>
        <Text style={styles.heroSubtitle}>
          Start with a secure profile for rewards, wallet access, squads, and network activity.
        </Text>
      </View>

      <GlassCard style={styles.formCard}>
        <SectionEyebrow text="Create Account" />
        <View style={styles.fieldGroup}>
          <AppTextField
            label="Username"
            leadingIcon="person-outline"
            placeholder="Choose a username"
            value={fields.username}
            onChangeText={text => {
              clearError('username');
              setField('username', text);
            }}
          />
          <FieldError message={fieldErrors.username} />
        </View>
        <View style={styles.fieldGroup}>
          <AppTextField
            label="Phone number"
            leadingIcon="call-outline"
            placeholder="Enter your phone number"
            value={fields.phoneNumber}
            keyboardType="phone-pad"
            onChangeText={text => {
              clearError('phoneNumber');
              setField('phoneNumber', text);
            }}
          />
          <FieldError message={fieldErrors.phoneNumber} />
        </View>
        <View style={styles.fieldGroup}>
          <AppTextField
            label="Email"
            leadingIcon="mail-outline"
            placeholder="you@example.com"
            value={fields.authEmail}
            autoCapitalize="none"
            keyboardType="email-address"
            onChangeText={text => {
              clearError('email');
              setField('authEmail', text);
            }}
          />
          <FieldError message={fieldErrors.email} />
        </View>
        <View style={styles.fieldGroup}>
          <AppTextField
            label="Password"
            leadingIcon="lock-closed-outline"
            placeholder="Create a secure password"
            value={fields.authPassword}
            secureTextEntry
            onChangeText={text => {
              clearError('password');
              setField('authPassword', text);
            }}
          />
          <FieldError message={fieldErrors.password} />
        </View>
        <AppTextField
          label="Referral code"
          leadingIcon="gift-outline"
          placeholder="Optional invite code"
          value={fields.referralCode}
          autoCapitalize="characters"
          onChangeText={text => setField('referralCode', text)}
        />
        <PrimaryButton
          label={isSubmitting ? 'Creating account...' : 'Create account'}
          icon="account-plus-outline"
          onPress={onPress}
          disabled={isSubmitting}
          style={styles.primaryAction}
        />
        <SecondaryButton
          label="Already have an account?"
          icon="login"
          onPress={() => navigate('Login')}
          disabled={isSubmitting}
        />
      </GlassCard>
    </AppScreen>
  );
};

const FieldError = ({message}: {message?: string}) =>
  message ? (
    <Text accessibilityRole="alert" style={styles.fieldError}>
      {message}
    </Text>
  ) : null;

const styles = StyleSheet.create({
  contentContainer: {
    paddingTop: 20,
    paddingBottom: 48,
  },
  heroRow: {
    marginBottom: 16,
  },
  logoPanel: {
    width: 76,
    height: 76,
    borderRadius: 23,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 14,
    backgroundColor: alpha(theme.colors.primary, 0.12),
    borderWidth: 1,
    borderColor: alpha(theme.colors.primary, 0.26),
  },
  logo: {
    width: 60,
    height: 60,
  },
  heroTitle: {
    color: theme.colors.text,
    fontSize: 29,
    lineHeight: 33,
    fontFamily: theme.typography.display,
  },
  heroSubtitle: {
    marginTop: 8,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  formCard: {
    borderColor: alpha(theme.colors.primary, 0.18),
  },
  fieldGroup: {
    marginBottom: 12,
  },
  fieldError: {
    marginTop: 5,
    marginLeft: 4,
    color: theme.colors.danger,
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.bodyMedium,
  },
  primaryAction: {
    marginBottom: 12,
  },
});

export default SignUpOrg;
