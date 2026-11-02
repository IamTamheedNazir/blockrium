import {
  KeyboardAvoidingView,
  Platform,
  StyleSheet,
  TextInput,
  TextInputProps,
} from 'react-native';
import React from 'react';
import colors from '../utils/colors';
const NormalInput: React.FC<TextInputProps> = ({
  value,
  onChangeText,
  placeholder,
  style,
  ...props
}) => {
  const defaultTheme: 'dark' | 'light' = 'dark';
  const inputStyle = StyleSheet.flatten(style);
  const customBackgroundColor = inputStyle?.backgroundColor;
  
  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      style={{flex: 1}}
      keyboardVerticalOffset={Platform.OS === 'ios' ? 100 : 0}>
      <TextInput
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor={
          defaultTheme === 'dark' ? colors.black : colors.white
        }
        style={[
          {
            backgroundColor:
              defaultTheme === 'dark'
                ? customBackgroundColor || 'white'
                : customBackgroundColor || colors.lightBlack,
          },
          style,
        ]}
        {...props}
      />
    </KeyboardAvoidingView>
  );
};

export default NormalInput;
