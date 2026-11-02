import React from 'react';
import {hp, wp} from '../utils/styleConstants';
import LinearGradient from 'react-native-linear-gradient';
import colors from '../utils/colors';
import {InputProps} from '../types/Input';
import {KeyboardTypeOptions, Text, TextInput, View} from 'react-native';
import fonts from '../../assets/fonts';

const Input: React.FC<InputProps> = ({
  value,
  onChangeText,
  placeholder,
  keyboardType,
  styles,
  editable = true,
  ...props
}) => {
  return (
    <View
      style={{
        width: '98%',
        height: hp(6),
        borderRadius: hp(3),
        shadowColor: 'transparent',
        paddingHorizontal: wp(3),
        shadowOpacity: 0.2,
        shadowRadius: 5,
        backgroundColor: 'rgba(0,0,0,0.3)',
        ...styles,
      }}>
      {!value && (
        <Text
          style={{
            position: 'absolute',
            left: 15,
            top: 12,
            fontSize: 16,
            color: colors.white,
            fontFamily: fonts.jaro_regular,
          }}>
          {placeholder}
        </Text>
      )}
      <TextInput
        editable={editable}
        value={value}
        onChangeText={onChangeText}
        keyboardType={keyboardType as KeyboardTypeOptions}
        style={[
          {color: colors.white, fontFamily: fonts.jaro_regular},
          props.style,
        ]}
        {...props}
      />
    </View>
  );
};

export default Input;
