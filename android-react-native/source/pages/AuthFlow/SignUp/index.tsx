import SignUpOrg from '../../../organisms/SignUpOrg';
import * as React from 'react';
import { resetNavigation } from '../../../utils/helper';
import { setStorageId, setStorageToken } from '../../../utils/mmkvStorage';
import useAuthData from '../../../storage/client/useAuthData';
import { callMutation } from '../../../hooks';
import { useThisAlert } from '../../../hooks/AlertContext';
import { attachLeadsTokenInHeader } from '../../../storage/server';
import {normalizeAuthResponse} from '../../../utils/auth';
import {cacheUserProfile, normalizeUserProfile} from '../../../utils/profile';
import {identifyBlockriumNotificationUser} from '../../../services/blockriumNotificationService';
import {identifyAdUser} from '../../../services/adService';
import {identifyRevenueCatUser} from '../../../services/revenueCatService';
import {logAppIssue} from '../../../services/appIssueLogger';
import {queueEngagementEvent} from '../../../services/engagementAnalyticsService';
import {
  getSignupFieldErrors,
  SignupFieldErrors,
  validateSignupFields,
} from '../../../utils/signupValidation';

const getFriendlyAuthError = (error: unknown, fallback: string) => {
  const message = error instanceof Error ? error.message : '';
  return message || fallback;
};

const reportSignupIssue = (payload: Parameters<typeof logAppIssue>[0]) => {
  logAppIssue(payload).catch(error => {
    console.log('Unable to queue signup issue log', error);
  });
};

const SignUp = () => {
  const { fields } = useAuthData();
  const [isSubmittingSignup, setIsSubmittingSignup] = React.useState(false);
  const [fieldErrors, setFieldErrors] = React.useState<SignupFieldErrors>({});
  const username = fields.username?.trim() || '';
  const email = fields.authEmail?.trim().toLowerCase() || '';
  const phoneNumber = fields.phoneNumber?.trim() || '';
  const password = fields.authPassword || '';
  const referralCode = fields.referralCode?.trim().toUpperCase() || undefined;

  const jsonBody = {
    username,
    name: username,
    email,
    phone_number: phoneNumber,
    password,
    password_confirmation: password,
    country: 'India',
    referred_by: referralCode,
  };

  const { isLoading, mutateAsync } : any = callMutation('auth/register', jsonBody);

  const {showAlert} = useThisAlert();

  return (
    <SignUpOrg
      isSubmitting={isLoading || isSubmittingSignup}
      fieldErrors={fieldErrors}
      onFieldChange={field =>
        setFieldErrors(current => ({...current, [field]: undefined}))
      }
      onPress={async () => {
        if (isLoading || isSubmittingSignup) {
          return;
        }

        const clientErrors = validateSignupFields({
          username,
          phoneNumber,
          email,
          password,
        });
        if (Object.keys(clientErrors).length > 0) {
          setFieldErrors(clientErrors);
          const firstInvalidField = Object.keys(clientErrors)[0];
          showAlert(
            clientErrors[firstInvalidField as keyof SignupFieldErrors] ||
              'Please review the highlighted signup fields.',
            'ERROR',
          );
          return;
        }

        setFieldErrors({});
        setIsSubmittingSignup(true);
        const startedAt = Date.now();
        queueEngagementEvent({
          eventName: 'signup_attempt',
          area: 'signup',
          status: 'attempted',
          route: 'SignUp',
          endpoint: 'auth/register',
          metadata: {
            hasEmail: Boolean(email),
            hasPhoneNumber: Boolean(phoneNumber),
            hasReferralCode: Boolean(referralCode),
          },
        });

        try {
          const response = await mutateAsync?.();

          if (!response?.success) {
            const serverFieldErrors = getSignupFieldErrors(response?.data);
            if (Object.keys(serverFieldErrors).length > 0) {
              setFieldErrors(serverFieldErrors);
            }
            queueEngagementEvent({
              eventName: 'signup_failed',
              area: 'signup',
              status: 'failed',
              route: 'SignUp',
              endpoint: 'auth/register',
              durationMs: Date.now() - startedAt,
              metadata: {
                hasEmail: Boolean(email),
                hasReferralCode: Boolean(referralCode),
              },
            });
            reportSignupIssue({
              action: 'signup_failed',
              endpoint: 'auth/register',
              method: 'POST',
              message: response?.error || 'Signup API returned failure.',
              metadata: {
                hasEmail: Boolean(email),
                hasReferralCode: Boolean(referralCode),
              },
            });
            const fieldErrorSummary = Object.entries(serverFieldErrors)
              .map(([field, message]) => {
                const label = {
                  username: 'Username',
                  phoneNumber: 'Phone number',
                  email: 'Email',
                  password: 'Password',
                }[field as keyof SignupFieldErrors];
                return `${label}: ${message}`;
              })
              .join('\n');
            showAlert(
              fieldErrorSummary ||
                response?.error ||
                'Unable to create account right now. Please try again.',
              'ERROR',
            );
            return;
          }

          const {accessToken, successMessage, userId, userProfile} = normalizeAuthResponse(
            response,
          );

          if (!accessToken) {
            queueEngagementEvent({
              eventName: 'signup_failed_missing_token',
              area: 'signup',
              status: 'failed',
              route: 'SignUp',
              endpoint: 'auth/register',
              durationMs: Date.now() - startedAt,
              metadata: {hasUserId: userId !== undefined},
            });
            reportSignupIssue({
              action: 'signup_missing_token',
              endpoint: 'auth/register',
              method: 'POST',
              message: 'Signup response did not include access token.',
              metadata: {hasUserId: userId !== undefined},
            });
            showAlert(
              'Signup response is missing the access token. Please try again.',
              'ERROR',
            );
            console.log('Unexpected signup response shape', response?.data);
            return;
          }

          if (userId !== undefined) {
            await setStorageId('userId', userId);
            identifyAdUser(userId);
            identifyRevenueCatUser(userId).catch(error => {
              console.log('Unable to identify RevenueCat user after signup', error);
            });
          }

          const normalizedProfile = normalizeUserProfile(userProfile, {
            name: username,
            email,
            id: userId !== undefined ? String(userId) : undefined,
            uid: userId !== undefined ? String(userId) : undefined,
            phone_number: phoneNumber,
          });

          await cacheUserProfile(normalizedProfile);
          identifyBlockriumNotificationUser(normalizedProfile).catch(error => {
            console.log('Unable to identify OneSignal user after signup', error);
          });
          await setStorageToken('authToken', accessToken);
          attachLeadsTokenInHeader(accessToken);
          queueEngagementEvent({
            eventName: 'signup_success',
            area: 'signup',
            status: 'succeeded',
            route: 'SignUp',
            endpoint: 'auth/register',
            durationMs: Date.now() - startedAt,
            metadata: {
              hasUserId: userId !== undefined,
              hasReferralCode: Boolean(referralCode),
            },
          });
          showAlert(successMessage || 'Account created successfully.', 'SUCCESS');
          resetNavigation('BottomStack');
        } catch (error) {
          console.log('Signup submit error', error);
          queueEngagementEvent({
            eventName: 'signup_exception',
            area: 'signup',
            status: 'failed',
            route: 'SignUp',
            endpoint: 'auth/register',
            durationMs: Date.now() - startedAt,
            metadata: {error: getFriendlyAuthError(error, 'Signup exception.')},
          });
          reportSignupIssue({
            action: 'signup_exception',
            endpoint: 'auth/register',
            method: 'POST',
            message: getFriendlyAuthError(error, 'Signup exception.'),
            errorCode: error instanceof Error ? error.name : undefined,
          });
          showAlert(
            getFriendlyAuthError(error, 'Unable to create account right now.'),
            'ERROR',
          );
        } finally {
          setIsSubmittingSignup(false);
        }
      }}
    />
  );
};

export default SignUp;
