import {StyleSheet} from 'react-native';
import React from 'react';
import NormalText from '../atoms/NormalText';
import colors from '../utils/colors';
import {hp, wp} from '../utils/styleConstants';
import Input from '../atoms/Input';
import {Box} from '../ui/nativeBaseCompat';
import {customTextInput} from '../types/customTextInput';
import Ionicons from 'react-native-vector-icons/Ionicons';

const CustomInput: React.FC<customTextInput> = ({
  title,
  placeholder,
  value,
  onChangeText,
  keyboardType,
  styles,
  leftIcon,
  rightIcon,
  ...props
}) => {
  return (
    <Box>
      { title &&
      <NormalText
        style={{color: colors.white, padding: wp(3)}}
        text={title}
      />
      }
      {leftIcon && (
        <Ionicons
          name={leftIcon}
          size={20}
          color={colors.white}
          style={iconStyle.leftIcon}
        />
      )}
      <Input
        placeholder={placeholder}
        value={value}
        onChangeText={onChangeText}
        keyboardType={keyboardType ?? 'default'}
        {...props}
      />
       {rightIcon && (
        <Ionicons
          name={rightIcon}
          size={24}
          color={colors.white}
          style={iconStyle.rightIcon}
        />
      )}
    </Box>
  );
};

const iconStyle = StyleSheet.create({
  rightIcon: {
    position: 'absolute',
    right: 25,
    bottom: 20,
    zIndex: 30
  },
  leftIcon: {
    position: 'absolute',
    right: 25,
    bottom: 20,
    zIndex: 30
  },
});

export default CustomInput;
