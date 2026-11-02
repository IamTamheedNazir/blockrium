import React, {useState} from 'react';
import LoginOrg from '../../../organisms/LoginOrg';
import useAuthData from '../../../storage/client/useAuthData';
import {callMutation} from '../../../hooks';
import {navigate, resetNavigation} from '../../../utils/helper';
import {useThisAlert} from '../../../hooks/AlertContext';
import { setStorageId, setStorageToken } from '../../../utils/mmkvStorage';
import { attachLeadsTokenInHeader } from '../../../storage/server';
import {normalizeAuthResponse} from '../../../utils/auth';
import {cacheUserProfile, normalizeUserProfile} from '../../../utils/profile';
import {identifyBlockriumNotificationUser} from '../../../services/blockriumNotificationService';
import {identifyAdUser} from '../../../services/adService';
import {identifyRevenueCatUser} from '../../../services/revenueCatService';
import {logAppIssue} from '../../../services/appIssueLogger';
import {queueEngagementEvent} from '../../../services/engagementAnalyticsService';

const getFriendlyAuthError = (error: unknown, fallback: string) => {
  const message = error instanceof Error ? error.message : '';
  return message || fallback;
};

const reportAuthIssue = (payload: Parameters<typeof logAppIssue>[0]) => {
  logAppIssue(payload).catch(error => {
    console.log('Unable to queue login issue log', error);
  });
};

const Login = () => {
  const {fields} = useAuthData();
  const {showAlert} = useThisAlert();
  const [isSubmittingLogin, setIsSubmittingLogin] = useState(false);

  const jsonBody = {
    email: fields.loginEmail?.trim() || '',
    password: fields.loginPassword || '',
  };

  const {isLoading, mutateAsync}: any = callMutation('auth/login', jsonBody);

  const onPressLogin = async () => {
    if (isSubmittingLogin || isLoading) {
      return;
    }

    if (!jsonBody.email || !jsonBody.password) {
      showAlert('Enter both email and password to continue.', 'ERROR');
      return;
    }

    setIsSubmittingLogin(true);
    const startedAt = Date.now();
    queueEngagementEvent({
      eventName: 'login_attempt',
      area: 'login',
      status: 'attempted',
      route: 'Login',
      endpoint: 'auth/login',
      metadata: {hasEmail: Boolean(jsonBody.email)},
    });

    try {
      const response = await mutateAsync?.();

      if (!response?.success) {
        queueEngagementEvent({
          eventName: 'login_failed',
          area: 'login',
          status: 'failed',
          route: 'Login',
          endpoint: 'auth/login',
          durationMs: Date.now() - startedAt,
          metadata: {hasEmail: Boolean(jsonBody.email)},
        });
        reportAuthIssue({
          action: 'login_failed',
          endpoint: 'auth/login',
          method: 'POST',
          message: response?.error || 'Login API returned failure.',
          metadata: {hasEmail: Boolean(jsonBody.email)},
        });
        showAlert(response?.error || 'Unable to log in right now.', 'ERROR');
        return;
      }

      const {accessToken, successMessage, userId, userProfile} = normalizeAuthResponse(
        response,
      );

      if (!accessToken) {
        queueEngagementEvent({
          eventName: 'login_failed_missing_token',
          area: 'login',
          status: 'failed',
          route: 'Login',
          endpoint: 'auth/login',
          durationMs: Date.now() - startedAt,
          metadata: {hasUserId: userId !== undefined},
        });
        reportAuthIssue({
          action: 'login_missing_token',
          endpoint: 'auth/login',
          method: 'POST',
          message: 'Login response did not include access token.',
          metadata: {hasUserId: userId !== undefined},
        });
        showAlert(
          'Login response is missing the access token. Please try again.',
          'ERROR',
        );
        console.log('Unexpected login response shape', response?.data);
        return;
      }

      attachLeadsTokenInHeader(accessToken);

      if (userId !== undefined) {
        await setStorageId('userId', userId);
        identifyAdUser(userId);
        identifyRevenueCatUser(userId).catch(error => {
          console.log('Unable to identify RevenueCat user after login', error);
        });
      }

      const normalizedProfile = normalizeUserProfile(userProfile, {
        email: jsonBody.email,
        id: userId !== undefined ? String(userId) : undefined,
        uid: userId !== undefined ? String(userId) : undefined,
      });

      await cacheUserProfile(normalizedProfile);
      identifyBlockriumNotificationUser(normalizedProfile).catch(error => {
        console.log('Unable to identify OneSignal user after login', error);
      });
      await setStorageToken('authToken', accessToken);
      queueEngagementEvent({
        eventName: 'login_success',
        area: 'login',
        status: 'succeeded',
        route: 'Login',
        endpoint: 'auth/login',
        durationMs: Date.now() - startedAt,
        metadata: {hasUserId: userId !== undefined},
      });
      showAlert(successMessage || 'Login successful.', 'SUCCESS');
      resetNavigation('BottomStack');
    } catch (error) {
      console.log('Login submit error', error);
      queueEngagementEvent({
        eventName: 'login_exception',
        area: 'login',
        status: 'failed',
        route: 'Login',
        endpoint: 'auth/login',
        durationMs: Date.now() - startedAt,
        metadata: {error: getFriendlyAuthError(error, 'Login exception.')},
      });
      reportAuthIssue({
        action: 'login_exception',
        endpoint: 'auth/login',
        method: 'POST',
        message: getFriendlyAuthError(error, 'Login exception.'),
        errorCode: error instanceof Error ? error.name : undefined,
      });
      showAlert(
        getFriendlyAuthError(error, 'Unable to log in right now. Please try again.'),
        'ERROR',
      );
    } finally {
      setIsSubmittingLogin(false);
    }
  };

  return (
    <LoginOrg
      isSubmitting={isLoading || isSubmittingLogin}
      onPressLogin={onPressLogin}
      onPressSignUp={() => {
        navigate('SignUp');
      }}
    />
  );
};

export default Login;
