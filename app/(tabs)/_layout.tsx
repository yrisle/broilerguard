// app/(tabs)/_layout.tsx
import { Tabs } from "expo-router";
import React from "react";
import { Text } from "react-native";

export default function TabLayout() {
  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: "#FFD62E",
        tabBarInactiveTintColor: "#8B7355",
        tabBarStyle: {
          backgroundColor: "#FFFFFF",
          borderTopWidth: 1,
          borderTopColor: "rgba(255, 214, 46, 0.2)",
          paddingBottom: 8,
          height: 60,
        },
        headerStyle: {
          backgroundColor: "#FFFCF2",
        },
        headerTitleStyle: {
          fontWeight: "600",
          color: "#3E2C1C",
        },
      }}
    >
      <Tabs.Screen
        name="home"
        options={{
          title: "Home",
          tabBarIcon: ({ size }) => <Text style={{ fontSize: size }}>🏠</Text>,
        }}
      />
      <Tabs.Screen
        name="sensors"
        options={{
          title: "Sensors",
          tabBarIcon: ({ size }) => <Text style={{ fontSize: size }}>🌡️</Text>,
        }}
      />
      <Tabs.Screen
        name="camera"
        options={{
          title: "Camera",
          tabBarIcon: ({ size }) => <Text style={{ fontSize: size }}>📷</Text>,
        }}
      />
      <Tabs.Screen
        name="settings"
        options={{
          title: "Settings",
          tabBarIcon: ({ size }) => <Text style={{ fontSize: size }}>⚙️</Text>,
        }}
      />
    </Tabs>
  );
}
