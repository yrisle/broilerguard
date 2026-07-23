// src/components/common/Button.tsx
import React from "react";
import {
    ActivityIndicator,
    StyleSheet,
    Text,
    TextStyle,
    TouchableOpacity,
    ViewStyle,
} from "react-native";

interface ButtonProps {
  title: string;
  onPress: () => void;
  variant?: "primary" | "secondary" | "danger" | "success";
  size?: "small" | "medium" | "large";
  loading?: boolean;
  disabled?: boolean;
  style?: ViewStyle;
  textStyle?: TextStyle;
}

const Button: React.FC<ButtonProps> = ({
  title,
  onPress,
  variant = "primary",
  size = "medium",
  loading = false,
  disabled = false,
  style,
  textStyle,
}) => {
  const getVariantStyles = (): ViewStyle => {
    switch (variant) {
      case "secondary":
        return {
          backgroundColor: "#FFFFFF",
          borderWidth: 1,
          borderColor: "rgba(255, 214, 46, 0.3)",
        };
      case "danger":
        return { backgroundColor: "#E74C3C" };
      case "success":
        return { backgroundColor: "#27AE60" };
      default:
        return { backgroundColor: "#FFD62E" };
    }
  };

  const getSizeStyles = (): ViewStyle => {
    switch (size) {
      case "small":
        return { paddingHorizontal: 12, paddingVertical: 6 };
      case "large":
        return { paddingHorizontal: 32, paddingVertical: 16 };
      default:
        return { paddingHorizontal: 20, paddingVertical: 12 };
    }
  };

  const getTextColor = (): string => {
    switch (variant) {
      case "secondary":
        return "#3E2C1C";
      case "danger":
        return "#FFFFFF";
      case "success":
        return "#FFFFFF";
      default:
        return "#3E2C1C";
    }
  };

  return (
    <TouchableOpacity
      style={[
        styles.button,
        getVariantStyles(),
        getSizeStyles(),
        disabled && styles.disabled,
        style,
      ]}
      onPress={onPress}
      disabled={disabled || loading}
      activeOpacity={0.8}
    >
      {loading ? (
        <ActivityIndicator color={getTextColor()} />
      ) : (
        <Text style={[styles.text, { color: getTextColor() }, textStyle]}>
          {title}
        </Text>
      )}
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  button: {
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
  },
  text: {
    fontSize: 14,
    fontWeight: "600",
  },
  disabled: {
    opacity: 0.6,
  },
});

export default Button;
