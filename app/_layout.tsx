// app/_layout.tsx
import { Stack } from "expo-router";
import React from "react";
import { AuthProvider } from "../src/context/AuthContext";
import { useTheme } from "../src/hooks/useTheme";

export default function RootLayout() {
  const { colors } = useTheme();

  return (
    <AuthProvider>
      <Stack
        screenOptions={{
          headerShown: false,
          animation: "fade",
        }}
      >
        {/* Main Screens - No Header */}
        <Stack.Screen name="index" />
        <Stack.Screen name="(tabs)" />

        {/* Automation Screens - With Header */}
        <Stack.Screen
          name="fan-control"
          options={{
            headerShown: true,
            title: "Fan Control",
            headerStyle: {
              backgroundColor: colors?.card || "#FFFFFF",
            },
            headerTintColor: colors?.text || "#000000",
            headerBackTitle: "Back",
          }}
        />

        <Stack.Screen
          name="water-pump"
          options={{
            headerShown: true,
            title: "Water Pump",
            headerStyle: {
              backgroundColor: colors?.card || "#FFFFFF",
            },
            headerTintColor: colors?.text || "#000000",
            headerBackTitle: "Back",
          }}
        />

        <Stack.Screen
          name="light-control"
          options={{
            headerShown: true,
            title: "Light Control",
            headerStyle: {
              backgroundColor: colors?.card || "#FFFFFF",
            },
            headerTintColor: colors?.text || "#000000",
            headerBackTitle: "Back",
          }}
        />

        {/* 🚪 GATE CONTROL - Add this */}
        <Stack.Screen
          name="gate-control"
          options={{
            headerShown: true,
            title: "Gate Control",
            headerStyle: {
              backgroundColor: colors?.card || "#FFFFFF",
            },
            headerTintColor: colors?.text || "#000000",
            headerBackTitle: "Back",
          }}
        />

        <Stack.Screen
          name="feed-dispenser"
          options={{
            headerShown: true,
            title: "Feed Dispenser",
            headerStyle: {
              backgroundColor: colors?.card || "#FFFFFF",
            },
            headerTintColor: colors?.text || "#000000",
            headerBackTitle: "Back",
          }}
        />

        <Stack.Screen
          name="camera"
          options={{
            headerShown: true,
            title: "AI Camera",
            headerStyle: {
              backgroundColor: colors?.card || "#FFFFFF",
            },
            headerTintColor: colors?.text || "#000000",
            headerBackTitle: "Back",
          }}
        />

        <Stack.Screen
          name="notifications"
          options={{
            headerShown: true,
            title: "Notifications",
            headerStyle: {
              backgroundColor: colors?.card || "#FFFFFF",
            },
            headerTintColor: colors?.text || "#000000",
            headerBackTitle: "Back",
          }}
        />

        <Stack.Screen
          name="settings"
          options={{
            headerShown: true,
            title: "Settings",
            headerStyle: {
              backgroundColor: colors?.card || "#FFFFFF",
            },
            headerTintColor: colors?.text || "#000000",
            headerBackTitle: "Back",
          }}
        />
      </Stack>
    </AuthProvider>
  );
}
