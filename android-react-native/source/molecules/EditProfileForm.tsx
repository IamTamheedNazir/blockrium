import React from 'react';
import {Card} from '../ui/nativeBaseCompat';
import CustomInput from './CustomInput';
import useEditData from '../storage/client/useEditData';
import {useFocusEffect} from '@react-navigation/native';
import { heightPercentageToDP } from 'react-native-responsive-screen';

const EditProfileForm = ({user}: {user: any}) => {
  const {fields, setField} = useEditData();

  useFocusEffect(
    React.useCallback(() => {
      setField('fullName', user?.name);
      setField('fatherName', user?.fatherName);
      setField('phoneNumber', user?.phone_number);
      setField('gender', user?.gender);
      setField('referralCode', user?.refferal_code);
      setField('email', user?.email);
    }, [setField, user]),
  );

  return (
    <Card style={{width: '95%', paddingVertical: heightPercentageToDP(2), alignSelf: 'center'}}>
        <CustomInput
          title="Name"
          placeholder="Enter Your Name"
          value={fields.fullName}
          onChangeText={text => setField('fullName', text)}
        />
        <CustomInput
          title="Father Name"
          placeholder="Enter Your Father Name"
          value={fields.fatherName}
          onChangeText={text => setField('fatherName', text)}
        />
        <CustomInput
          title="Phone Number"
          placeholder="Enter Your Phone Number"
          value={fields.phoneNumber}
          onChangeText={text => setField('phoneNumber', text)}
        />
        <CustomInput
          title="Gender"
          placeholder="Enter Your Gender"
          value={fields.gender}
          onChangeText={text => setField('gender', text)}
        />
        <CustomInput
          title="Referral Code"
          placeholder="Enter Your Referral Code"
          value={fields.referralCode}
          onChangeText={text => setField('referralCode', text)}
        />
    </Card>
  );
};

export default EditProfileForm;
