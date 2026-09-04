// src/services/AutomationScheduler.ts

import AsyncStorage from "@react-native-async-storage/async-storage";
import { automation } from "../api/endpoints";

class AutomationScheduler {
  private intervals: { [key: string]: number } = {};
  private isRunning: { [key: string]: boolean } = {};

  getPhilippineTime(): Date {
    const now = new Date();
    return new Date(now.getTime() + 8 * 60 * 60 * 1000);
  }

  convertTo24Hour(timeStr: string): string {
    const parts = timeStr.split(" ");
    if (parts.length !== 2) return timeStr;

    const time = parts[0];
    const period = parts[1];
    let [hours, minutes] = time.split(":").map(Number);

    if (period === "PM" && hours !== 12) {
      hours += 12;
    } else if (period === "AM" && hours === 12) {
      hours = 0;
    }

    return `${hours.toString().padStart(2, "0")}:${minutes.toString().padStart(2, "0")}`;
  }

  async startFanScheduler() {
    if (this.isRunning.fan) return;
    this.isRunning.fan = true;

    const checkFan = async () => {
      try {
        const autoMode = await AsyncStorage.getItem("@fan_auto_mode");
        if (!autoMode || !JSON.parse(autoMode)) {
          this.stopFanScheduler();
          return;
        }

        const tempOn = await AsyncStorage.getItem("@fan_temp_on");
        const tempOff = await AsyncStorage.getItem("@fan_temp_off");
        const onTemp = tempOn ? JSON.parse(tempOn) : 32;
        const offTemp = tempOff ? JSON.parse(tempOff) : 28;

        const response = await automation.fan.getStatus();
        const data = response.data;
        const currentTemp = data.temperature || 0;
        const fanStatus = data.fan === 1 ? "ON" : "OFF";

        if (currentTemp >= onTemp && fanStatus === "OFF") {
          console.log(
            `🔥 Auto: Temperature ${currentTemp}°C >= ${onTemp}°C - Turning FAN ON`,
          );
          await automation.fan.toggle("ON");
        } else if (currentTemp <= offTemp && fanStatus === "ON") {
          console.log(
            `❄️ Auto: Temperature ${currentTemp}°C <= ${offTemp}°C - Turning FAN OFF`,
          );
          await automation.fan.toggle("OFF");
        }
      } catch (error) {
        console.error("❌ Fan scheduler error:", error);
      }
    };

    await checkFan(); // Run immediately
    this.intervals.fan = setInterval(checkFan, 10000);
  }

  async startLightScheduler() {
    if (this.isRunning.light) return;
    this.isRunning.light = true;

    const checkLight = async () => {
      try {
        const autoMode = await AsyncStorage.getItem("@light_auto_mode");
        if (!autoMode || !JSON.parse(autoMode)) {
          this.stopLightScheduler();
          return;
        }

        const onTime = await AsyncStorage.getItem("@light_on_time");
        const offTime = await AsyncStorage.getItem("@light_off_time");
        const on = onTime || "7:00 PM";
        const off = offTime || "6:00 AM";

        const now = this.getPhilippineTime();
        const currentMinutes = now.getHours() * 60 + now.getMinutes();

        const on24 = this.convertTo24Hour(on);
        const off24 = this.convertTo24Hour(off);
        const [onHours, onMinutes] = on24.split(":").map(Number);
        const [offHours, offMinutes] = off24.split(":").map(Number);
        const onMin = onHours * 60 + onMinutes;
        const offMin = offHours * 60 + offMinutes;

        let shouldBeOn = false;
        if (onMin < offMin) {
          if (currentMinutes >= onMin && currentMinutes < offMin) {
            shouldBeOn = true;
          }
        } else {
          if (currentMinutes >= onMin || currentMinutes < offMin) {
            shouldBeOn = true;
          }
        }

        const response = await automation.light.getStatus();
        const data = response.data;
        const lightStatus = data.light === 1 ? "ON" : "OFF";

        if (shouldBeOn && lightStatus === "OFF") {
          console.log(
            `💡 Auto: Turning Light ON at ${now.toLocaleTimeString()}`,
          );
          await automation.light.toggle("ON");
        } else if (!shouldBeOn && lightStatus === "ON") {
          console.log(
            `💡 Auto: Turning Light OFF at ${now.toLocaleTimeString()}`,
          );
          await automation.light.toggle("OFF");
        }
      } catch (error) {
        console.error("❌ Light scheduler error:", error);
      }
    };

    await checkLight(); // Run immediately
    this.intervals.light = setInterval(checkLight, 10000);
  }

  async startGateScheduler() {
    if (this.isRunning.gate) return;
    this.isRunning.gate = true;

    const checkGate = async () => {
      try {
        const autoMode = await AsyncStorage.getItem("@gate_auto_mode");
        if (!autoMode || !JSON.parse(autoMode)) {
          this.stopGateScheduler();
          return;
        }

        const timesStr = await AsyncStorage.getItem("@gate_feed_times");
        const amountStr = await AsyncStorage.getItem("@gate_feed_amount");
        const feedTimes: string[] = timesStr ? JSON.parse(timesStr) : [];
        const feedAmount = parseFloat(amountStr || "0.5");

        const now = this.getPhilippineTime();
        const currentMinutes = now.getHours() * 60 + now.getMinutes();
        const currentSeconds = now.getSeconds();

        for (const time of feedTimes) {
          const time24 = this.convertTo24Hour(time);
          const [hours, minutes] = time24.split(":").map(Number);
          const timeMinutes = hours * 60 + minutes;

          const timeDiff = Math.abs(
            currentMinutes * 60 + currentSeconds - timeMinutes * 60,
          );

          if (timeDiff <= 10) {
            console.log(`⏰ Auto: Gate dispense at ${time} - TRIGGERING!`);

            // Open gate
            await automation.gate.open();
            await new Promise((resolve) => setTimeout(resolve, 3000));
            await automation.gate.close();

            console.log(`✅ Auto: Dispensed ${feedAmount} kg of feed`);
            break;
          }
        }
      } catch (error) {
        console.error("❌ Gate scheduler error:", error);
      }
    };

    await checkGate(); // Run immediately
    this.intervals.gate = setInterval(checkGate, 10000);
  }

  async startPumpScheduler() {
    if (this.isRunning.pump) return;
    this.isRunning.pump = true;

    const checkPump = async () => {
      try {
        const autoMode = await AsyncStorage.getItem("@pump_auto_mode");
        if (!autoMode || !JSON.parse(autoMode)) {
          this.stopPumpScheduler();
          return;
        }

        const amountStr = await AsyncStorage.getItem("@pump_dispense_amount");
        const amount = parseFloat(amountStr || "10");

        const now = this.getPhilippineTime();
        const currentMinutes = now.getHours() * 60 + now.getMinutes();

        if (currentMinutes % 30 === 0 && now.getSeconds() < 5) {
          console.log(`⏰ Auto: Pump dispense at ${now.toLocaleTimeString()}`);
          await automation.pump.release(amount * 2);
          console.log(`💧 Auto: Dispensed ${amount} L of water`);
        }
      } catch (error) {
        console.error("❌ Pump scheduler error:", error);
      }
    };

    await checkPump(); // Run immediately
    this.intervals.pump = setInterval(checkPump, 10000);
  }

  stopFanScheduler() {
    if (this.intervals.fan) {
      clearInterval(this.intervals.fan);
      delete this.intervals.fan;
    }
    this.isRunning.fan = false;
  }

  stopLightScheduler() {
    if (this.intervals.light) {
      clearInterval(this.intervals.light);
      delete this.intervals.light;
    }
    this.isRunning.light = false;
  }

  stopGateScheduler() {
    if (this.intervals.gate) {
      clearInterval(this.intervals.gate);
      delete this.intervals.gate;
    }
    this.isRunning.gate = false;
  }

  stopPumpScheduler() {
    if (this.intervals.pump) {
      clearInterval(this.intervals.pump);
      delete this.intervals.pump;
    }
    this.isRunning.pump = false;
  }

  stopAll() {
    this.stopFanScheduler();
    this.stopLightScheduler();
    this.stopGateScheduler();
    this.stopPumpScheduler();
  }

  async startAllSchedulers() {
    const fanMode = await AsyncStorage.getItem("@fan_auto_mode");
    const lightMode = await AsyncStorage.getItem("@light_auto_mode");
    const gateMode = await AsyncStorage.getItem("@gate_auto_mode");
    const pumpMode = await AsyncStorage.getItem("@pump_auto_mode");

    if (fanMode && JSON.parse(fanMode)) {
      await this.startFanScheduler();
    }
    if (lightMode && JSON.parse(lightMode)) {
      await this.startLightScheduler();
    }
    if (gateMode && JSON.parse(gateMode)) {
      await this.startGateScheduler();
    }
    if (pumpMode && JSON.parse(pumpMode)) {
      await this.startPumpScheduler();
    }
  }
}

export const automationScheduler = new AutomationScheduler();
