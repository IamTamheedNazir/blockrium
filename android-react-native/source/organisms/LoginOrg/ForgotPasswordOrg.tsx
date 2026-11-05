import React, {useEffect, useMemo, useState} from 'react';
import {BackHandler, StyleSheet, Text} from 'react-native';
import {AppScreen, AppTextField, GlassCard, PrimaryButton, SectionEyebrow} from '../../design/primitives';
import theme from '../../design/theme';
import {navigate} from '../../utils/helper';
import useAuthData from '../../storage/client/useAuthData';
import {callMutation} from '../../hooks';
import {useThisAlert} from '../../hooks/AlertContext';

const ForgotPasswordOrg = () => {
  const {fields, setField} = useAuthData();
  const {showAlert} = useThisAlert();
  const [otpSent, setOtpSent] = useState(false);
  const email = useMemo(() => (fields.loginEmail ?? '').trim().toLowerCase(), [fields.loginEmail]);
  const {mutateAsync: sendOtp, isLoading: sendingOtp}: any = callMutation('send-otp-update-password', {
    email,
  });
  const {mutateAsync: updatePassword, isLoading: updatingPassword}: any = callMutation(
    'update-password',
    {
      email,
      otp: (fields.code ?? '').trim(),
      new_password: fields.updatedPassword,
    },
  );

  useEffect(() => {
    const backAction = () => {
      setField('code', '');
      setField('updatedPassword', '');
      navigate('Login');
      return true;
    };

    const backHandler = BackHandler.addEventListener('hardwareBackPress', backAction);
    return () => backHandler.remove();
  }, [setField]);

  const getApiSuccess = (response: any) =>
    Boolean(response?.success && (response?.data?.success === undefined || response?.data?.success));

  const getApiMessage = (response: any, fallback: string) => {
    const message =
      response?.data?.message ||
      response?.data?.message_title ||
      response?.message ||
      response?.error;

    return typeof message === 'string' && message.trim() ? message.trim() : fallback;
  };

  const onPressBtn = async () => {
    if (sendingOtp || updatingPassword) {
      return;
    }

    if (!email) {
      showAlert('Enter your email first.', 'ERROR');
      return;
    }

    if (otpSent) {
      if (!fields.code || !fields.updatedPassword) {
        showAlert('Enter the OTP and your new password.', 'ERROR');
        return;
      }

      if (fields.updatedPassword.length < 6) {
        showAlert('Password must be at least 6 characters.', 'ERROR');
        return;
      }

      const response = await updatePassword();
      if (getApiSuccess(response)) {
        showAlert(getApiMessage(response, 'Password updated.'), 'SUCCESS');
        setField('code', '');
        setField('updatedPassword', '');
        setOtpSent(false);
        navigate('Login');
      } else {
        showAlert(getApiMessage(response, 'Unable to update password.'), 'ERROR');
      }
      return;
    }

    const response = await sendOtp();
    if (getApiSuccess(response)) {
      setOtpSent(true);
      showAlert(getApiMessage(response, 'OTP sent to your email.'), 'SUCCESS');
    } else {
      showAlert(getApiMessage(response, 'Unable to send OTP.'), 'ERROR');
    }
  };

  return (
    <AppScreen scrollable contentContainerStyle={styles.contentContainer}>
      <Text style={styles.screenTitle}>Reset password</Text>
      <Text style={styles.screenSubtitle}>
        The recovery flow now reads like a proper account operation instead of a quick debug screen.
      </Text>

      <GlassCard>
        <SectionEyebrow text="Recovery" />
        <AppTextField
          label="Email"
          leadingIcon="mail-outline"
          placeholder="you@example.com"
          value={fields.loginEmail}
          autoCapitalize="none"
          editable={!otpSent}
          onChangeText={text => setField('loginEmail', text)}
        />
        {otpSent ? (
          <>
            <AppTextField
              label="OTP code"
              leadingIcon="key-outline"
              placeholder="Enter the code"
              value={fields.code}
              keyboardType="numeric"
              onChangeText={text => setField('code', text)}
            />
            <AppTextField
              label="New password"
              leadingIcon="lock-closed-outline"
              placeholder="Create a new password"
              value={fields.updatedPassword}
              secureTextEntry
              onChangeText={text => setField('updatedPassword', text)}
            />
          </>
        ) : null}
        <PrimaryButton
          label={
            updatingPassword
              ? 'Updating...'
              : sendingOtp
                ? 'Sending...'
                : otpSent
                  ? 'Update Password'
                  : 'Send OTP'
          }
          icon={otpSent ? 'lock-reset' : 'send'}
          onPress={onPressBtn}
        />
      </GlassCard>
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {
    paddingTop: 22,
    paddingBottom: 32,
  },
  screenTitle: {
    color: theme.colors.text,
    fontSize: 27,
    lineHeight: 32,
    fontFamily: theme.typography.display,
  },
  screenSubtitle: {
    marginTop: 4,
    marginBottom: 14,
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
});

export default ForgotPasswordOrg;
