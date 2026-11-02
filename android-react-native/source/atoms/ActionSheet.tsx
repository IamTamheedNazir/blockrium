import React from "react";
import {Actionsheet, Box, Center, Text} from '../ui/nativeBaseCompat';
import MaterialIcons from "react-native-vector-icons/MaterialIcons";
import { RFValue } from "react-native-responsive-fontsize";
import colors from "../utils/colors";

interface ActionButton {
  label: string;
  icon: string;
  isCancel?: boolean;
}

interface ActionSheetProps {
  title?: string;
  buttons?: ActionButton[];
  onActionPress?: (button: ActionButton) => void;
  isOpen: boolean;
  onClose: () => void;
}

const ActionSheet: React.FC<ActionSheetProps> = ({
  title = "Are you sure you want to logout?",
  buttons = [
    { label: "Logout", icon: "logout" },
    { label: "Cancel", icon: "cancel", isCancel: true },
  ],
  onActionPress,
  isOpen,
  onClose,
}) => {
  const handlePress = (button: ActionButton) => {
    if (button.isCancel) {
      onClose();
    } else {
      onActionPress?.(button);
      onClose();
    }
  };

  const defaultTheme: 'dark' | 'light' = 'dark';

  return (
    <Center>
      <Actionsheet isOpen={isOpen} onClose={onClose}>
        <Actionsheet.Content backgroundColor={defaultTheme === "dark" ? colors.black : colors.white}>
          <Box w="100%" h={60} px={4} justifyContent="center" backgroundColor={defaultTheme === "dark" ? colors.black : colors.white}>
            <Text fontSize={RFValue(16)} color={colors.primary} fontWeight={"bold"} textAlign={'center'}>
              {title}
            </Text>
          </Box>
          {buttons.map((button, index) => (
            <Actionsheet.Item
              key={index}
              startIcon={<MaterialIcons color={colors.primary} name={button.icon} size={30} />}
              onPress={() => handlePress(button)}
              color={colors.primary}
              fontSize={RFValue(20)}
              fontWeight={"bold"}
              _text={{ color: colors.primary, fontWeight: "bold", fontSize: RFValue(16) }}
              backgroundColor={defaultTheme === "dark" ? colors.black : colors.white}
            >
              {button.label}
            </Actionsheet.Item>
          ))}
        </Actionsheet.Content>
      </Actionsheet>
    </Center>
  );
};

export default ActionSheet;
