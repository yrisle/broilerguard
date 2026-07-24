import { createStackNavigator } from "@react-navigation/stack";
import React from "react";
import SplashScreen from "../screens/Auth/SplashScreen";

const Stack = createStackNavigator();

const AuthNavigator = () => (
  <Stack.Navigator screenOptions={{ headerShown: false }}>
    <Stack.Screen name="Splash" component={SplashScreen} />
  </Stack.Navigator>
);

export default AuthNavigator;
