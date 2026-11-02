import {Modal, StyleSheet, Text, View} from 'react-native';
import React from 'react';

const OfflineBanner = ({isOffline}: any) => {
  return (
    <Modal
      visible={isOffline}
      transparent>
      <View style={styles.container}>
        <Text style={styles.text}>Your device is Offline</Text>
      </View>
    </Modal>
  );
};

export default OfflineBanner;

const styles = StyleSheet.create({
  container: {
    width: '100%',
    justifyContent: 'center',
    backgroundColor: 'rgba(236, 59, 49, 1)',
    alignItems: 'center',
    padding: 8,
  },
  text: {color: '#EEE'},
});
