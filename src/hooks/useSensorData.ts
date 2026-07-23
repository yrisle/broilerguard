// src/hooks/useSensorData.ts
import { useCallback, useEffect, useState } from "react";
import { sensors } from "../api/endpoints/sensors";
import { SensorData } from "../api/types";

export const useSensorData = (refreshInterval: number = 30000) => {
  const [data, setData] = useState<SensorData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchData = useCallback(async () => {
    try {
      const response = await sensors.getCurrent();
      if (response.data.success) {
        setData(response.data.data);
        setError(null);
      } else {
        setError(response.data.message || "Failed to fetch sensor data");
      }
    } catch (err: any) {
      setError(err.message || "Network error");
      console.error("Sensor data error:", err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
    const interval = setInterval(fetchData, refreshInterval);
    return () => clearInterval(interval);
  }, [fetchData, refreshInterval]);

  const refresh = useCallback(() => {
    setLoading(true);
    fetchData();
  }, [fetchData]);

  return { data, loading, error, refresh };
};
