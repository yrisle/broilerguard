// src/context/AutomationContext.tsx

import AsyncStorage from "@react-native-async-storage/async-storage";
import React, { createContext, useContext, useEffect, useState } from "react";

interface AutomationState {
  fanAutoMode: boolean;
  gateAutoMode: boolean;
  pumpAutoMode: boolean;
  lightAutoMode: boolean;
  setFanAutoMode: (value: boolean) => void;
  setGateAutoMode: (value: boolean) => void;
  setPumpAutoMode: (value: boolean) => void;
  setLightAutoMode: (value: boolean) => void;
  loadAutoModes: () => Promise<void>;
}

const AutomationContext = createContext<AutomationState | undefined>(undefined);

export const useAutomation = () => {
  const context = useContext(AutomationContext);
  if (!context) {
    throw new Error("useAutomation must be used within AutomationProvider");
  }
  return context;
};

export const AutomationProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const [fanAutoMode, setFanAutoModeState] = useState(false);
  const [gateAutoMode, setGateAutoModeState] = useState(false);
  const [pumpAutoMode, setPumpAutoModeState] = useState(false);
  const [lightAutoMode, setLightAutoModeState] = useState(false);

  const loadAutoModes = async () => {
    try {
      const [fanMode, gateMode, pumpMode, lightMode] = await Promise.all([
        AsyncStorage.getItem("@fan_auto_mode"),
        AsyncStorage.getItem("@gate_auto_mode"),
        AsyncStorage.getItem("@pump_auto_mode"),
        AsyncStorage.getItem("@light_auto_mode"),
      ]);

      setFanAutoModeState(fanMode ? JSON.parse(fanMode) : false);
      setGateAutoModeState(gateMode ? JSON.parse(gateMode) : false);
      setPumpAutoModeState(pumpMode ? JSON.parse(pumpMode) : false);
      setLightAutoModeState(lightMode ? JSON.parse(lightMode) : false);
    } catch (error) {
      console.log("Error loading auto modes:", error);
    }
  };

  const setFanAutoMode = async (value: boolean) => {
    setFanAutoModeState(value);
    await AsyncStorage.setItem("@fan_auto_mode", JSON.stringify(value));
  };

  const setGateAutoMode = async (value: boolean) => {
    setGateAutoModeState(value);
    await AsyncStorage.setItem("@gate_auto_mode", JSON.stringify(value));
  };

  const setPumpAutoMode = async (value: boolean) => {
    setPumpAutoModeState(value);
    await AsyncStorage.setItem("@pump_auto_mode", JSON.stringify(value));
  };

  const setLightAutoMode = async (value: boolean) => {
    setLightAutoModeState(value);
    await AsyncStorage.setItem("@light_auto_mode", JSON.stringify(value));
  };

  useEffect(() => {
    loadAutoModes();
  }, []);

  return (
    <AutomationContext.Provider
      value={{
        fanAutoMode,
        gateAutoMode,
        pumpAutoMode,
        lightAutoMode,
        setFanAutoMode,
        setGateAutoMode,
        setPumpAutoMode,
        setLightAutoMode,
        loadAutoModes,
      }}
    >
      {children}
    </AutomationContext.Provider>
  );
};
