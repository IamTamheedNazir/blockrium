import { useEffect } from "react";
import { Alert, Platform } from "react-native";
import { check, request, PERMISSIONS, RESULTS } from "react-native-permissions";

export const ContactPermission = ({ onDone }: { onDone: () => void }) => {
  useEffect(() => {
    const askContactPermission = async () => {
      const result = await request(
        Platform.select({
          ios: PERMISSIONS.IOS.CONTACTS,
          android: PERMISSIONS.ANDROID.READ_CONTACTS,
        })!
      );

      if (result === RESULTS.GRANTED) {
        console.log("Contacts permission granted");
      } else {
        Alert.alert("Permission needed", "Contacts access is required.");
      }
      onDone(); // call next permission
    };

    askContactPermission();
  }, []);

  return null;
};
