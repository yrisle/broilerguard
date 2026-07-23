// src/components/sensors/LevelIndicator.tsx
import React from "react";
import { StyleSheet, Text, View } from "react-native";

interface LevelIndicatorProps {
  label: string;
  value: number;
  maxValue?: number;
  color?: string;
  consumed?: number;
  unit?: string;
}

const LevelIndicator: React.FC<LevelIndicatorProps> = ({
  label,
  value,
  maxValue = 100,
  color = "#FFD62E",
  consumed = 0,
  unit = "",
}) => {
  const percentage = Math.min((value / maxValue) * 100, 100);
  const isLow = percentage < 20;

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.label}>{label}</Text>
        <Text style={[styles.value, { color }]}>{value.toFixed(0)}%</Text>
      </View>
      <View style={styles.progressBar}>
        <View
          style={[
            styles.progressFill,
            {
              width: `${percentage}%`,
              backgroundColor: isLow ? "#E74C3C" : color,
            },
          ]}
        />
      </View>
      {consumed > 0 && (
        <Text style={styles.subtext}>
          {consumed} {unit} consumed today
        </Text>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    paddingVertical: 8,
  },
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 6,
  },
  label: {
    fontSize: 14,
    fontWeight: "600",
    color: "#3E2C1C",
  },
  value: {
    fontSize: 16,
    fontWeight: "700",
  },
  progressBar: {
    height: 8,
    backgroundColor: "#F0E8D8",
    borderRadius: 4,
    overflow: "hidden",
  },
  progressFill: {
    height: "100%",
    borderRadius: 4,
  },
  subtext: {
    fontSize: 11,
    color: "#8B7355",
    marginTop: 4,
  },
});

export default LevelIndicator;
