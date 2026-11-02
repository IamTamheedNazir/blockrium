import React, {useEffect} from 'react';
import {StyleSheet} from 'react-native';
import {useRoute} from '@react-navigation/native';
import {
  AppScreen,
  AppTextField,
  GlassCard,
  PrimaryButton,
  ScreenHeader,
  SectionEyebrow,
} from '../../design/primitives';
import {callMutation} from '../../hooks';
import useEditData from '../../storage/client/useEditData';
import {useThisAlert} from '../../hooks/AlertContext';
import {navigate} from '../../utils/helper';

const EditScreen = () => {
  const route: any = useRoute();
  const user = route?.params?.user;
  const {fields, setField} = useEditData();
  const {showAlert} = useThisAlert();

  useEffect(() => {
    setField('fullName', user?.name || '');
    setField('fatherName', user?.fatherName || '');
    setField('phoneNumber', user?.phone_number || '');
    setField('gender', user?.gender || '');
    setField('referralCode', user?.refferal_code || '');
    setField('email', user?.email || '');
  }, [setField, user]);

  const payload = {
    name: fields.fullName,
    email: fields.email,
    phone_number: fields.phoneNumber,
    refferal_code: fields.referralCode,
    user_image: '',
  };

  const {data, isLoading, mutate}: any = callMutation('auth/profile_update', payload);

  useEffect(() => {
    if (!data) {
      return;
    }

    if (data?.status !== 1 && !data?.success) {
      showAlert(data?.error || 'Unable to update profile.', 'ERROR');
      return;
    }

    showAlert(data?.data?.message || 'Profile updated successfully.', 'SUCCESS');
    navigate('ProfileFlow');
  }, [data, showAlert]);

  return (
    <AppScreen scrollable contentContainerStyle={styles.contentContainer}>
      <ScreenHeader
        title="Edit profile"
        subtitle="Update account details used across Blockrium."
        showBack
        onBackPress={() => navigate('ProfileFlow')}
      />

      <GlassCard>
        <SectionEyebrow text="Profile Details" />
        <AppTextField
          label="Full name"
          leadingIcon="person-outline"
          placeholder="Enter your full name"
          value={fields.fullName}
          onChangeText={text => setField('fullName', text)}
        />
        <AppTextField
          label="Father name"
          leadingIcon="people-outline"
          placeholder="Enter father name"
          value={fields.fatherName}
          onChangeText={text => setField('fatherName', text)}
        />
        <AppTextField
          label="Phone number"
          leadingIcon="call-outline"
          placeholder="Enter your phone number"
          value={fields.phoneNumber}
          keyboardType="phone-pad"
          onChangeText={text => setField('phoneNumber', text)}
        />
        <AppTextField
          label="Gender"
          leadingIcon="body-outline"
          placeholder="Enter gender"
          value={fields.gender}
          onChangeText={text => setField('gender', text)}
        />
        <AppTextField
          label="Referral code"
          leadingIcon="gift-outline"
          placeholder="Enter referral code"
          value={fields.referralCode}
          onChangeText={text => setField('referralCode', text)}
        />
        <PrimaryButton
          label={isLoading ? 'Saving changes...' : 'Save changes'}
          icon="content-save-outline"
          disabled={isLoading}
          onPress={() => mutate()}
        />
      </GlassCard>
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {
    paddingTop: 4,
    paddingBottom: 126,
  },
});

export default EditScreen;
