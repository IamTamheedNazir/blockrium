import React from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import {useNavigation} from '@react-navigation/native';
import theme from '../design/theme';

const Header: React.FC<{name?: string; text: string; textColor?: string}> = ({
  name,
  text,
  textColor,
}) => {
  const navigation = useNavigation();

  return (
    <View style={styles.container}>
      {name ? (
        <Pressable
          onPress={() => navigation.goBack()}
          style={styles.backButton}>
          <Ionicons name={name} size={20} color={theme.colors.text} />
        </Pressable>
      ) : null}
      <Text style={[styles.title, textColor ? {color: textColor} : null]}>
        {text}
      </Text>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 4,
    paddingVertical: 8,
  },
  backButton: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(24, 52, 87, 0.72)',
    borderWidth: 1,
    borderColor: theme.colors.border,
    marginRight: 12,
  },
  title: {
    color: theme.colors.text,
    fontSize: 28,
    lineHeight: 30,
    fontFamily: theme.typography.display,
  },
});

export default Header;
