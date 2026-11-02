import { useEffect } from "react";
import { Alert, Platform } from "react-native";
import { request, PERMISSIONS, RESULTS } from "react-native-permissions";

export const LocationPermission = ({ onDone }: { onDone: () => void }) => {
  useEffect(() => {
    const askLocationPermission = async () => {
      const result = await request(
        Platform.select({
          ios: PERMISSIONS.IOS.LOCATION_WHEN_IN_USE,
          android: PERMISSIONS.ANDROID.ACCESS_FINE_LOCATION,
        })!
      );

      if (result === RESULTS.GRANTED) {
        console.log("Location permission granted");
      } else {
        Alert.alert("Permission needed", "Location access is required.");
      }
      onDone();
    };

    askLocationPermission();
  }, []);

  return null;
};
