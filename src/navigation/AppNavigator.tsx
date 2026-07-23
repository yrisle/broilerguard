// src/navigation/AppNavigator.tsx
import Icon from "@expo/vector-icons/Ionicons";
import { createBottomTabNavigator } from "@react-navigation/bottom-tabs";
import { createStackNavigator } from "@react-navigation/stack";
import React from "react";

// Auth Screens
import AuthNavigator from "./AuthNavigator";

// Main Screens
import DashboardScreen from "../screens/Main/DashboardScreen";
import TemperatureScreen from "../screens/Main/TemperatureScreen";

// AI Screens
import CameraScreen from "../screens/AI/CameraScreen";

// Automation Screens
import FanControlScreen from "../screens/Automation/FanControlScreen";
import FeedDispenserScreen from "../screens/Automation/FeedDispenserScreen";
import WaterPumpScreen from "../screens/Automation/WaterPumpScreen";

// Settings Screens
import NotificationsScreen from "../screens/Settings/NotificationsScreen";
import SettingsScreen from "../screens/Settings/SettingsScreen";

import { useAuth } from "../context/AuthContext";

const Stack = createStackNavigator();
const Tab = createBottomTabNavigator();

// Main Tab Navigator
const MainTabs = () => {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        tabBarIcon: ({ focused, color, size }) => {
          let iconName: "home" | "thermometer" | "camera" | "settings" = "home";
          if (route.name === "Home") iconName = "home";
          else if (route.name === "Sensors") iconName = "thermometer";
          else if (route.name === "Camera") iconName = "camera";
          else if (route.name === "Settings") iconName = "settings";
          const resolvedName = (
            focused ? iconName : `${iconName}-outline`
          ) as any;
          return <Icon name={resolvedName} size={size} color={color} />;
        },
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
          shadowColor: "transparent",
          elevation: 0,
        },
        headerTitleStyle: {
          fontWeight: "600",
          color: "#3E2C1C",
        },
      })}
    >
      <Tab.Screen name="Home" component={DashboardScreen} />
      <Tab.Screen name="Sensors" component={TemperatureScreen} />
      <Tab.Screen name="Camera" component={CameraScreen} />
      <Tab.Screen name="Settings" component={SettingsScreen} />
    </Tab.Navigator>
  );
};

// Stack Navigator for nested screens
const AppNavigator = () => {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return null;
  }

  return (
    <Stack.Navigator screenOptions={{ headerShown: false }}>
      {isAuthenticated ? (
        <>
          <Stack.Screen name="Main" component={MainTabs} />
          <Stack.Screen name="Notifications" component={NotificationsScreen} />
          <Stack.Screen name="FanControl" component={FanControlScreen} />
          <Stack.Screen name="FeedDispenser" component={FeedDispenserScreen} />
          <Stack.Screen name="WaterPump" component={WaterPumpScreen} />
          <Stack.Screen name="Camera" component={CameraScreen} />
        </>
      ) : (
        <Stack.Screen name="Auth" component={AuthNavigator} />
      )}
    </Stack.Navigator>
  );
};

export default AppNavigator;
