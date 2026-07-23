// src/components/common/Header.tsx
import { useNavigation } from "@react-navigation/native";
import React from "react";
import {
    StatusBar,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from "react-native";

interface HeaderProps {
  title: string;
  showBack?: boolean;
  rightComponent?: React.ReactNode;
}

const Header: React.FC<HeaderProps> = ({
  title,
  showBack = false,
  rightComponent,
}) => {
  const navigation = useNavigation();

  return (
    <>
      <StatusBar barStyle="dark-content" backgroundColor="#FFFCF2" />
      <View style={styles.container}>
        <View style={styles.left}>
          {showBack && (
            <TouchableOpacity
              onPress={() => navigation.goBack()}
              style={styles.backBtn}
            >
              <Text style={styles.backText}>←</Text>
            </TouchableOpacity>
          )}
          <Text style={styles.title}>{title}</Text>
        </View>
        {rightComponent && <View style={styles.right}>{rightComponent}</View>}
      </View>
    </>
  );
};

const styles = StyleSheet.create({
  container: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: "#FFFCF2",
    borderBottomWidth: 1,
    borderBottomColor: "rgba(255, 214, 46, 0.2)",
  },
  left: {
    flexDirection: "row",
    alignItems: "center",
  },
  backBtn: {
    padding: 4,
    marginRight: 8,
  },
  backText: {
    fontSize: 24,
    color: "#3E2C1C",
  },
  title: {
    fontSize: 18,
    fontWeight: "700",
    color: "#3E2C1C",
  },
  right: {
    flexDirection: "row",
    alignItems: "center",
  },
});

export default Header;
