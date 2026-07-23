// src/components/sensors/CircularProgress.tsx
import React from "react";
import { StyleSheet, Text, View } from "react-native";
import Svg, { Circle, G } from "react-native-svg";

interface CircularProgressProps {
  value: number;
  maxValue?: number;
  size?: number;
  strokeWidth?: number;
  color?: string;
  label?: string;
  suffix?: string;
}

const CircularProgress: React.FC<CircularProgressProps> = ({
  value,
  maxValue = 100,
  size = 100,
  strokeWidth = 8,
  color = "#FFD62E",
  label = "",
  suffix = "%",
}) => {
  const radius = (size - strokeWidth) / 2;
  const circumference = 2 * Math.PI * radius;
  const percentage = Math.min((value / maxValue) * 100, 100);
  const strokeDashoffset = circumference * (1 - percentage / 100);

  return (
    <View style={[styles.container, { width: size, height: size }]}>
      <Svg width={size} height={size}>
        <G transform={`rotate(-90, ${size / 2}, ${size / 2})`}>
          <Circle
            cx={size / 2}
            cy={size / 2}
            r={radius}
            stroke="#F0E8D8"
            strokeWidth={strokeWidth}
            fill="none"
          />
          <Circle
            cx={size / 2}
            cy={size / 2}
            r={radius}
            stroke={color}
            strokeWidth={strokeWidth}
            fill="none"
            strokeDasharray={circumference}
            strokeDashoffset={strokeDashoffset}
            strokeLinecap="round"
          />
        </G>
      </Svg>
      <View style={styles.center}>
        <Text style={styles.value}>{percentage.toFixed(0)}</Text>
        <Text style={styles.suffix}>{suffix}</Text>
        {label && <Text style={styles.label}>{label}</Text>}
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    alignItems: "center",
    justifyContent: "center",
  },
  center: {
    position: "absolute",
    alignItems: "center",
    justifyContent: "center",
  },
  value: {
    fontSize: 24,
    fontWeight: "800",
    color: "#3E2C1C",
  },
  suffix: {
    fontSize: 12,
    color: "#8B7355",
  },
  label: {
    fontSize: 11,
    color: "#8B7355",
    marginTop: 2,
  },
});

export default CircularProgress;
