import {StyleSheet, Text, View} from 'react-native';
import React from 'react';
import LinearGradient from 'react-native-linear-gradient';
import {widthPercentageToDP} from 'react-native-responsive-screen';

const Card = ({children}: any) => {
  return (
    <LinearGradient
      start={{x: 0, y: 0.5}}
      end={{x: 1, y: 0.4}}
      colors={['#0A1A2F', '#0A1A2F', '#0E4258']}
      style={{
        width: '95%',
        alignSelf: 'center',
        borderRadius: 20,
        opacity: 0.7,
        elevation: 10,
        shadowColor: 'white',
        shadowOffset: {width: 0, height: 4},
        shadowOpacity: 0.25,
        shadowRadius: 40,
        borderWidth: 1.5,
        borderColor: '#06D5C7',
        marginVertical: 10,
        flexWrap: 'wrap',
        padding: widthPercentageToDP(4),
        flexDirection: 'row',
      }}>
      {children}
    </LinearGradient>
  );
};

export default Card;

const styles = StyleSheet.create({});
