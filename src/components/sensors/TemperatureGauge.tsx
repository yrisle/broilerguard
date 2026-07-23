// src/components/sensors/TemperatureGauge.tsx
import React from "react";
import { StyleSheet, View } from "react-native";
import Svg, { Circle, G, Text as SvgText } from "react-native-svg";

interface TemperatureGaugeProps {
  temperature: number;
  min?: number;
  max?: number;
  size?: number;
}

const TemperatureGauge: React.FC<TemperatureGaugeProps> = ({
  temperature,
  min = 20,
  max = 45,
  size = 120,
}) => {
  const radius = size / 2 - 20;
  const circumference = 2 * Math.PI * radius;

  const getPercentage = () => {
    return Math.min(Math.max((temperature - min) / (max - min), 0), 1);
  };

  const getColor = () => {
    const percent = getPercentage();
    if (percent < 0.3) return "#2980B9";
    if (percent < 0.5) return "#27AE60";
    if (percent < 0.7) return "#FFD62E";
    if (percent < 0.85) return "#F39C12";
    return "#E74C3C";
  };

  const strokeDashoffset = circumference * (1 - getPercentage());

  return (
    <View style={[styles.container, { width: size, height: size }]}>
      <Svg width={size} height={size}>
        <G transform={`rotate(-90, ${size / 2}, ${size / 2})`}>
          <Circle
            cx={size / 2}
            cy={size / 2}
            r={radius}
            stroke="#F0E8D8"
            strokeWidth={12}
            fill="none"
          />
          <Circle
            cx={size / 2}
            cy={size / 2}
            r={radius}
            stroke={getColor()}
            strokeWidth={12}
            fill="none"
            strokeDasharray={circumference}
            strokeDashoffset={strokeDashoffset}
            strokeLinecap="round"
          />
        </G>
        <SvgText
          x={size / 2}
          y={size / 2 - 8}
          textAnchor="middle"
          fontSize={28}
          fontWeight="800"
          fill="#3E2C1C"
        >
          {temperature.toFixed(1)}°
        </SvgText>
        <SvgText
          x={size / 2}
          y={size / 2 + 24}
          textAnchor="middle"
          fontSize={12}
          fill="#8B7355"
        >
          C
        </SvgText>
      </Svg>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    alignItems: "center",
    justifyContent: "center",
  },
});

export default TemperatureGauge;
