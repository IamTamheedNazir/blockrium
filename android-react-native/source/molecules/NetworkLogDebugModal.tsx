import React from "react";
import { useState } from "react";
import { StyleSheet, Text, TouchableOpacity } from "react-native";
import { Modal } from "react-native";
import NetworkLogger from "react-native-network-logger";
import { SafeAreaView } from "react-native-safe-area-context";

interface Props {
    onPress?: () => void;
  }
  
  const NetworkLogDebugModal: React.FC<Props> = (props: Props) => {
    const [isNetworkModalVisible, setIsNetworkVIsible] = useState(false);
  
    return (
      <>
        <Modal
          style={styles.modal}
          visible={isNetworkModalVisible}
          onRequestClose={() => setIsNetworkVIsible(false)}>
          <SafeAreaView style={styles.contentContainer}>
            <TouchableOpacity
              style={styles.closeButton}
              onPress={() => setIsNetworkVIsible(false)}>
              <Text style={styles.closeButtonTitle}>{'CLOSE'}</Text>
            </TouchableOpacity>
            <NetworkLogger />
          </SafeAreaView>
        </Modal>
        <TouchableOpacity
          style={styles.container}
          onPress={() => {
            props.onPress && props.onPress();
            setIsNetworkVIsible(true);
          }}>
          <Text style={styles.content}>{'Network Logs'}</Text>
        </TouchableOpacity>
      </>
    );
  };
  
  const styles = StyleSheet.create({
    modal: {
      margin: 0,
  
      backgroundColor: 'white',
    },
  
    container: {
      width: 45,
  
      height: 45,
  
      position: 'absolute',
  
      left: 24,
  
      bottom: '20%',
  
      borderRadius: 45,
  
      backgroundColor: 'red',
  
      alignItems: 'center',
  
      justifyContent: 'center',
    },
  
    content: {
      fontSize: 9,
  
      textAlign: 'center',
  
      color: 'white',
    },
  
    contentContainer: {
      flex: 1,
    },
  
    closeButton: {
      paddingVertical: 16,
  
      borderBottomWidth: 1,
    },
  
    closeButtonTitle: {
      textAlign: 'center',
    },
  });

  export default NetworkLogDebugModal;