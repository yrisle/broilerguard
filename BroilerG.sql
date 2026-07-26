-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 26, 2026 at 08:17 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `broilerguard`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_auto_dispense_feed` (IN `p_user_id` INT, IN `p_amount` DECIMAL(5,2))   BEGIN
    DECLARE v_current_level DECIMAL(8,2);
    DECLARE v_capacity DECIMAL(8,2);
    DECLARE v_new_level DECIMAL(8,2);
    DECLARE v_unit VARCHAR(10);
    
    -- Get current feed level
    SELECT current_level, capacity, unit
    INTO v_current_level, v_capacity, v_unit
    FROM feed_inventory
    WHERE user_id = p_user_id;
    
    -- Check if enough feed is available
    IF v_current_level >= p_amount THEN
        SET v_new_level = v_current_level - p_amount;
        
        -- Update inventory
        UPDATE feed_inventory 
        SET current_level = v_new_level
        WHERE user_id = p_user_id;
        
        -- Log transaction
        INSERT INTO feed_transactions (
            user_id, type, amount, source, notes, new_level, timestamp
        ) VALUES (
            p_user_id, 'consumption', p_amount, 'auto_dispenser', 
            'Automated feed dispensing', v_new_level, NOW()
        );
        
    ELSE
        -- Log warning
        INSERT INTO notifications (user_id, title, message, type, timestamp)
        VALUES (
            p_user_id,
            'Insufficient Feed for Auto-Dispense',
            CONCAT('Not enough feed to dispense. Required: ', p_amount, v_unit, ', Available: ', v_current_level, v_unit),
            'danger',
            NOW()
        );
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_auto_water_pump` (IN `p_user_id` INT, IN `p_duration` INT)   BEGIN
    DECLARE v_current_level DECIMAL(8,2);
    DECLARE v_new_level DECIMAL(8,2);
    DECLARE v_capacity DECIMAL(8,2);
    DECLARE v_flow_rate DECIMAL(5,2);
    DECLARE v_amount_used DECIMAL(8,2);
    
    -- Get current water level and settings
    SELECT current_level, capacity, flow_rate
    INTO v_current_level, v_capacity, v_flow_rate
    FROM water_inventory
    WHERE user_id = p_user_id;
    
    -- Calculate water used based on flow rate and duration (seconds)
    SET v_amount_used = (v_flow_rate * p_duration) / 60;
    
    -- Check if enough water is available
    IF v_current_level >= v_amount_used THEN
        SET v_new_level = v_current_level - v_amount_used;
        
        -- Update inventory
        UPDATE water_inventory 
        SET current_level = v_new_level
        WHERE user_id = p_user_id;
        
        -- Log transaction
        INSERT INTO water_transactions (
            user_id, type, amount, source, notes, new_level, timestamp
        ) VALUES (
            p_user_id, 'consumption', v_amount_used, 'auto_pump', 
            CONCAT('Automated watering for ', p_duration, ' seconds'), v_new_level, NOW()
        );
        
        -- Log pump action
        INSERT INTO activity_logs (user_id, action, details, timestamp)
        VALUES (
            p_user_id,
            'Water Pump ON',
            CONCAT('Pumped ', v_amount_used, ' liters for ', p_duration, ' seconds. New level: ', v_new_level, ' liters.'),
            NOW()
        );
        
    ELSE
        -- Critical notification
        INSERT INTO notifications (user_id, title, message, type, timestamp)
        VALUES (
            p_user_id,
            'Insufficient Water!',
            CONCAT('Not enough water. Required: ', v_amount_used, 'L, Available: ', v_current_level, 'L. Pump stopped.'),
            'danger',
            NOW()
        );
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_clean_old_data` (IN `p_retention_days` INT)   BEGIN
    DECLARE v_cutoff_date DATETIME;
    SET v_cutoff_date = DATE_SUB(NOW(), INTERVAL p_retention_days DAY);
    
    -- Delete old sensor readings (keep at least 7 days)
    DELETE FROM sensor_readings 
    WHERE timestamp < v_cutoff_date;
    
    -- Delete old activity logs
    DELETE FROM activity_logs 
    WHERE timestamp < v_cutoff_date;
    
    -- Delete old notifications (already read)
    DELETE FROM notifications 
    WHERE timestamp < v_cutoff_date AND `read` = 1;
    
    -- Delete old detection logs
    DELETE FROM detection_logs 
    WHERE timestamp < v_cutoff_date;
    
    -- Delete old feed transactions
    DELETE FROM feed_transactions 
    WHERE timestamp < v_cutoff_date;
    
    -- Delete old water transactions
    DELETE FROM water_transactions 
    WHERE timestamp < v_cutoff_date;
    
    -- Log the cleanup
    INSERT INTO activity_logs (user_id, action, details, timestamp)
    VALUES (
        1,  -- System user
        'Data Cleanup',
        CONCAT('Cleaned data older than ', p_retention_days, ' days. Cutoff date: ', v_cutoff_date),
        NOW()
    );
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_daily_report` (IN `p_user_id` INT, IN `p_date` DATE)   BEGIN
    -- Temperature Summary
    SELECT 
        AVG(temperature) as avg_temp,
        MAX(temperature) as max_temp,
        MIN(temperature) as min_temp,
        AVG(humidity) as avg_humidity
    FROM sensor_readings
    WHERE user_id = p_user_id 
        AND DATE(timestamp) = p_date;
    
    -- Feed Summary
    SELECT 
        SUM(CASE WHEN type = 'consumption' THEN amount ELSE 0 END) as total_consumption,
        SUM(CASE WHEN type = 'refill' THEN amount ELSE 0 END) as total_refill,
        COUNT(*) as total_transactions
    FROM feed_transactions
    WHERE user_id = p_user_id 
        AND DATE(timestamp) = p_date;
    
    -- Water Summary
    SELECT 
        SUM(CASE WHEN type = 'consumption' THEN amount ELSE 0 END) as total_consumption,
        SUM(CASE WHEN type = 'refill' THEN amount ELSE 0 END) as total_refill,
        COUNT(*) as total_transactions
    FROM water_transactions
    WHERE user_id = p_user_id 
        AND DATE(timestamp) = p_date;
    
    -- Health Summary
    SELECT 
        status,
        COUNT(*) as count
    FROM detection_logs
    WHERE user_id = p_user_id 
        AND DATE(timestamp) = p_date
    GROUP BY status;
    
    -- Notifications
    SELECT 
        type,
        COUNT(*) as count
    FROM notifications
    WHERE user_id = p_user_id 
        AND DATE(timestamp) = p_date
    GROUP BY type;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_system_health` (IN `p_user_id` INT)   BEGIN
    -- Latest sensor reading
    SELECT 
        temperature,
        humidity,
        feed_level,
        water_level,
        fan_status,
        water_pump,
        timestamp as last_reading
    FROM sensor_readings
    WHERE user_id = p_user_id
    ORDER BY timestamp DESC
    LIMIT 1;
    
    -- Feed status
    SELECT 
        current_level,
        capacity,
        ROUND((current_level / capacity) * 100, 1) as percentage,
        alert_threshold,
        critical_threshold,
        unit
    FROM feed_inventory
    WHERE user_id = p_user_id;
    
    -- Water status
    SELECT 
        current_level,
        capacity,
        ROUND((current_level / capacity) * 100, 1) as percentage,
        alert_threshold,
        critical_threshold,
        unit
    FROM water_inventory
    WHERE user_id = p_user_id;
    
    -- Recent health detections (last 24 hours)
    SELECT 
        status,
        COUNT(*) as count
    FROM detection_logs
    WHERE user_id = p_user_id
        AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY status;
    
    -- Unread notifications count
    SELECT 
        COUNT(*) as unread_count,
        SUM(CASE WHEN type = 'danger' THEN 1 ELSE 0 END) as critical_count
    FROM notifications
    WHERE user_id = p_user_id AND `read` = 0;
    
    -- Fan status
    SELECT 
        auto_mode,
        temp_threshold,
        humidity_threshold,
        fan_speed
    FROM fan_settings
    WHERE user_id = p_user_id;
    
    -- Light status
    SELECT 
        status,
        mode,
        brightness
    FROM light_settings
    WHERE user_id = p_user_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_weekly_trends` (IN `p_user_id` INT)   BEGIN
    -- Temperature trend (last 7 days)
    SELECT 
        DATE(timestamp) as date,
        AVG(temperature) as avg_temp,
        MAX(temperature) as max_temp,
        MIN(temperature) as min_temp,
        AVG(humidity) as avg_humidity
    FROM sensor_readings
    WHERE user_id = p_user_id
        AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(timestamp)
    ORDER BY date ASC;
    
    -- Feed consumption trend
    SELECT 
        DATE(timestamp) as date,
        SUM(amount) as total_consumption
    FROM feed_transactions
    WHERE user_id = p_user_id
        AND type = 'consumption'
        AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(timestamp)
    ORDER BY date ASC;
    
    -- Water consumption trend
    SELECT 
        DATE(timestamp) as date,
        SUM(amount) as total_consumption
    FROM water_transactions
    WHERE user_id = p_user_id
        AND type = 'consumption'
        AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(timestamp)
    ORDER BY date ASC;
    
    -- Health status trend
    SELECT 
        DATE(timestamp) as date,
        status,
        COUNT(*) as count
    FROM detection_logs
    WHERE user_id = p_user_id
        AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(timestamp), status
    ORDER BY date ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_mark_all_notifications_read` (IN `p_user_id` INT)   BEGIN
    UPDATE notifications
    SET `read` = 1
    WHERE user_id = p_user_id AND `read` = 0;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_mark_notification_read` (IN `p_notification_id` INT, IN `p_user_id` INT)   BEGIN
    UPDATE notifications
    SET `read` = 1
    WHERE id = p_notification_id AND user_id = p_user_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_refill_feed` (IN `p_user_id` INT, IN `p_amount` DECIMAL(8,2), IN `p_cost` DECIMAL(10,2), IN `p_notes` TEXT)   BEGIN
    DECLARE v_current_level DECIMAL(8,2);
    DECLARE v_capacity DECIMAL(8,2);
    DECLARE v_new_level DECIMAL(8,2);
    
    -- Get current level and capacity
    SELECT current_level, capacity
    INTO v_current_level, v_capacity
    FROM feed_inventory
    WHERE user_id = p_user_id;
    
    -- Calculate new level (can't exceed capacity)
    SET v_new_level = LEAST(v_current_level + p_amount, v_capacity);
    
    -- Update inventory
    UPDATE feed_inventory 
    SET current_level = v_new_level,
        last_refill = NOW()
    WHERE user_id = p_user_id;
    
    -- Log transaction
    INSERT INTO feed_transactions (
        user_id, type, amount, source, notes, new_level, cost, timestamp
    ) VALUES (
        p_user_id, 'refill', p_amount, 'manual', p_notes, v_new_level, p_cost, NOW()
    );
    
    -- Success notification
    INSERT INTO notifications (user_id, title, message, type, timestamp)
    VALUES (
        p_user_id,
        'Feed Refilled',
        CONCAT('Feed inventory refilled with ', p_amount, 'kg. New level: ', v_new_level, 'kg.'),
        'success',
        NOW()
    );
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_refill_water` (IN `p_user_id` INT, IN `p_amount` DECIMAL(8,2), IN `p_cost` DECIMAL(10,2), IN `p_notes` TEXT)   BEGIN
    DECLARE v_current_level DECIMAL(8,2);
    DECLARE v_capacity DECIMAL(8,2);
    DECLARE v_new_level DECIMAL(8,2);
    
    -- Get current level and capacity
    SELECT current_level, capacity
    INTO v_current_level, v_capacity
    FROM water_inventory
    WHERE user_id = p_user_id;
    
    -- Calculate new level (can't exceed capacity)
    SET v_new_level = LEAST(v_current_level + p_amount, v_capacity);
    
    -- Update inventory
    UPDATE water_inventory 
    SET current_level = v_new_level,
        last_refill = NOW()
    WHERE user_id = p_user_id;
    
    -- Log transaction
    INSERT INTO water_transactions (
        user_id, type, amount, source, notes, new_level, cost, timestamp
    ) VALUES (
        p_user_id, 'refill', p_amount, 'manual', p_notes, v_new_level, p_cost, NOW()
    );
    
    -- Success notification
    INSERT INTO notifications (user_id, title, message, type, timestamp)
    VALUES (
        p_user_id,
        'Water Refilled',
        CONCAT('Water inventory refilled with ', p_amount, 'liters. New level: ', v_new_level, 'liters.'),
        'success',
        NOW()
    );
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `user` varchar(50) DEFAULT NULL,
  `ip` varchar(50) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','staff') DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `full_name`, `role`, `created_at`) VALUES
(1, 'admin', 'broilerguard2025', 'System Administrator', 'admin', '2026-07-15 16:26:00');

--
-- Triggers `admins`
--
DELIMITER $$
CREATE TRIGGER `after_admin_update` AFTER UPDATE ON `admins` FOR EACH ROW BEGIN
    IF OLD.username != NEW.username OR OLD.role != NEW.role OR OLD.full_name != NEW.full_name THEN
        INSERT INTO `activity_logs` (`user_id`, `action`, `details`, `user`, `timestamp`)
        VALUES (
            NEW.id,
            'Admin Updated',
            CONCAT('Admin ', OLD.username, ' updated. Role: ', OLD.role, ' -> ', NEW.role),
            NEW.username,
            NOW()
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `camera_snapshots`
--

CREATE TABLE `camera_snapshots` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `detection_logs`
--

CREATE TABLE `detection_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `chick_id` varchar(20) NOT NULL,
  `status` enum('healthy','weak','unhealthy') NOT NULL,
  `confidence` decimal(5,2) NOT NULL,
  `respiratory_severity` enum('none','moderate','severe') DEFAULT 'none',
  `heat_stress_level` enum('normal','moderate','high','critical') DEFAULT 'normal',
  `activity` varchar(50) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detection_logs`
--

INSERT INTO `detection_logs` (`id`, `user_id`, `chick_id`, `status`, `confidence`, `respiratory_severity`, `heat_stress_level`, `activity`, `image_url`, `timestamp`) VALUES
(1, 1, 'CHK-003', 'weak', 88.70, 'none', 'normal', 'Lethargic', NULL, '2026-07-22 02:26:22'),
(2, 1, 'CHK-001', 'unhealthy', 78.80, 'none', 'normal', 'Scratching', NULL, '2026-07-22 19:26:22'),
(3, 1, 'CHK-003', 'weak', 83.60, 'none', 'normal', 'Feeding', NULL, '2026-07-22 05:26:22'),
(4, 1, 'CHK-003', 'unhealthy', 71.60, 'none', 'normal', 'Resting', NULL, '2026-07-23 16:26:22'),
(5, 1, 'CHK-004', 'healthy', 97.00, 'none', 'normal', 'Resting', NULL, '2026-07-23 02:26:22'),
(6, 1, 'CHK-002', 'unhealthy', 73.80, 'none', 'normal', 'Active', NULL, '2026-07-23 02:26:22'),
(7, 1, 'CHK-004', 'weak', 82.90, 'none', 'normal', 'Active', NULL, '2026-07-22 13:26:22'),
(8, 1, 'CHK-002', 'unhealthy', 75.00, 'none', 'normal', 'Resting', NULL, '2026-07-23 16:26:22'),
(9, 1, 'CHK-005', 'weak', 82.10, 'none', 'normal', 'Feeding', NULL, '2026-07-23 12:26:22'),
(10, 1, 'CHK-002', 'unhealthy', 79.10, 'none', 'normal', 'Lethargic', NULL, '2026-07-23 04:26:22'),
(11, 1, 'CHK-001', 'unhealthy', 75.80, 'none', 'normal', 'Scratching', NULL, '2026-07-23 00:26:22'),
(12, 1, 'CHK-003', 'unhealthy', 76.30, 'none', 'normal', 'Scratching', NULL, '2026-07-22 09:26:22'),
(13, 1, 'CHK-002', 'weak', 82.80, 'none', 'normal', 'Scratching', NULL, '2026-07-22 19:26:22'),
(14, 1, 'CHK-005', 'healthy', 98.60, 'none', 'normal', 'Scratching', NULL, '2026-07-22 11:26:22'),
(15, 1, 'CHK-002', 'weak', 88.20, 'none', 'normal', 'Resting', NULL, '2026-07-21 19:26:22'),
(16, 1, 'CHK-001', 'healthy', 95.60, 'none', 'normal', 'Active', NULL, '2026-07-21 20:26:22'),
(17, 1, 'CHK-001', 'unhealthy', 77.60, 'none', 'normal', 'Active', NULL, '2026-07-22 22:26:22'),
(18, 1, 'CHK-005', 'healthy', 98.40, 'none', 'normal', 'Lethargic', NULL, '2026-07-22 06:26:22'),
(19, 1, 'CHK-002', 'weak', 86.90, 'none', 'normal', 'Feeding', NULL, '2026-07-22 19:26:22'),
(20, 1, 'CHK-003', 'healthy', 95.00, 'none', 'normal', 'Scratching', NULL, '2026-07-21 23:26:22');

--
-- Triggers `detection_logs`
--
DELIMITER $$
CREATE TRIGGER `after_detection_log_insert` AFTER INSERT ON `detection_logs` FOR EACH ROW BEGIN
    -- Create notification for unhealthy detection
    IF NEW.status = 'unhealthy' OR NEW.status = 'weak' THEN
        INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `timestamp`)
        VALUES (
            NEW.user_id,
            CONCAT('Chick Health Alert: ', NEW.status),
            CONCAT('Chick ID: ', NEW.chick_id, ' is ', NEW.status, ' with ', NEW.confidence, '% confidence. Activity: ', NEW.activity),
            IF(NEW.status = 'unhealthy', 'danger', 'warning'),
            NOW()
        );
    END IF;
    
    -- Log to activity
    INSERT INTO `activity_logs` (`user_id`, `action`, `details`, `user`, `timestamp`)
    VALUES (
        NEW.user_id,
        'Health Detection',
        CONCAT('Chick ', NEW.chick_id, ' detected as ', NEW.status, ' (', NEW.confidence, '%)'),
        'system',
        NOW()
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `fan_logs`
--

CREATE TABLE `fan_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('ON','OFF') NOT NULL,
  `trigger` enum('manual','auto') NOT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fan_settings`
--

CREATE TABLE `fan_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `auto_mode` enum('auto','manual','schedule') DEFAULT 'auto',
  `temp_threshold` decimal(4,1) DEFAULT 32.0,
  `humidity_threshold` decimal(4,1) DEFAULT 75.0,
  `schedule_start` time DEFAULT '08:00:00',
  `schedule_end` time DEFAULT '20:00:00',
  `fan_speed` int(11) DEFAULT 80,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fan_settings`
--

INSERT INTO `fan_settings` (`id`, `user_id`, `auto_mode`, `temp_threshold`, `humidity_threshold`, `schedule_start`, `schedule_end`, `fan_speed`, `updated_at`) VALUES
(1, 1, 'auto', 32.0, 75.0, '08:00:00', '20:00:00', 80, '2026-07-23 12:01:14');

--
-- Triggers `fan_settings`
--
DELIMITER $$
CREATE TRIGGER `after_fan_settings_update` AFTER UPDATE ON `fan_settings` FOR EACH ROW BEGIN
    IF OLD.auto_mode != NEW.auto_mode OR OLD.temp_threshold != NEW.temp_threshold THEN
        INSERT INTO `fan_logs` (`user_id`, `action`, `trigger`, `temperature`, `timestamp`)
        VALUES (
            NEW.user_id,
            'SETTINGS_CHANGED',
            'manual',
            (SELECT temperature FROM sensor_readings WHERE user_id = NEW.user_id ORDER BY timestamp DESC LIMIT 1),
            NOW()
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `feed_inventory`
--

CREATE TABLE `feed_inventory` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `current_level` decimal(8,2) DEFAULT 100.00,
  `capacity` decimal(8,2) DEFAULT 200.00,
  `unit` varchar(10) DEFAULT 'kg',
  `last_refill` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `alert_threshold` decimal(8,2) DEFAULT 20.00,
  `critical_threshold` decimal(8,2) DEFAULT 10.00,
  `supplier` varchar(100) DEFAULT 'Local Feed Supply',
  `feed_type` varchar(50) DEFAULT 'Broiler Starter'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feed_inventory`
--

INSERT INTO `feed_inventory` (`id`, `user_id`, `current_level`, `capacity`, `unit`, `last_refill`, `alert_threshold`, `critical_threshold`, `supplier`, `feed_type`) VALUES
(1, 1, 100.00, 200.00, 'kg', '2026-07-15 17:03:08', 20.00, 10.00, 'Local Feed Supply', 'Broiler Starter');

--
-- Triggers `feed_inventory`
--
DELIMITER $$
CREATE TRIGGER `after_feed_inventory_update` AFTER UPDATE ON `feed_inventory` FOR EACH ROW BEGIN
    -- Check if feed level is below alert threshold
    IF NEW.current_level <= NEW.alert_threshold AND OLD.current_level > NEW.alert_threshold THEN
        INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `timestamp`)
        VALUES (
            NEW.user_id,
            'Feed Level Alert',
            CONCAT('Feed level is at ', ROUND(NEW.current_level, 1), ' ', NEW.unit, '. Below alert threshold of ', ROUND(NEW.alert_threshold, 1), ' ', NEW.unit, '.'),
            'warning',
            NOW()
        );
    END IF;
    
    -- Check if feed level is below critical threshold
    IF NEW.current_level <= NEW.critical_threshold AND OLD.current_level > NEW.critical_threshold THEN
        INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `timestamp`)
        VALUES (
            NEW.user_id,
            'Feed Level Critical!',
            CONCAT('Feed level is at ', ROUND(NEW.current_level, 1), ' ', NEW.unit, '. BELOW CRITICAL THRESHOLD of ', ROUND(NEW.critical_threshold, 1), ' ', NEW.unit, '!'),
            'danger',
            NOW()
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `feed_schedules`
--

CREATE TABLE `feed_schedules` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `time` time NOT NULL,
  `amount` decimal(5,2) NOT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `label` varchar(50) DEFAULT 'Feeding'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feed_schedules`
--

INSERT INTO `feed_schedules` (`id`, `user_id`, `time`, `amount`, `enabled`, `label`) VALUES
(1, 1, '08:00:00', 1.00, 1, 'Morning Feed'),
(2, 1, '12:00:00', 1.00, 1, 'Afternoon Feed'),
(3, 1, '17:00:00', 1.00, 1, 'Evening Feed');

-- --------------------------------------------------------

--
-- Table structure for table `feed_settings`
--

CREATE TABLE `feed_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `auto_mode` enum('manual','schedule','auto') DEFAULT 'schedule',
  `schedule_interval` int(11) DEFAULT 4,
  `dispense_amount` decimal(5,2) DEFAULT 0.50,
  `low_level_threshold` decimal(5,2) DEFAULT 5.00,
  `schedule_times` varchar(255) DEFAULT '08:00,12:00,16:00,20:00',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feed_settings`
--

INSERT INTO `feed_settings` (`id`, `user_id`, `auto_mode`, `schedule_interval`, `dispense_amount`, `low_level_threshold`, `schedule_times`, `updated_at`) VALUES
(1, 1, 'schedule', 4, 0.50, 5.00, '08:00,12:00,16:00,20:00', '2026-07-23 16:21:36');

-- --------------------------------------------------------

--
-- Table structure for table `feed_transactions`
--

CREATE TABLE `feed_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('refill','consumption') NOT NULL,
  `amount` decimal(8,2) NOT NULL,
  `source` enum('auto_dispenser','manual') DEFAULT 'manual',
  `notes` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `remaining` decimal(8,2) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `new_level` decimal(8,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `feed_transactions`
--
DELIMITER $$
CREATE TRIGGER `after_feed_transaction_update` AFTER INSERT ON `feed_transactions` FOR EACH ROW BEGIN
    UPDATE `feed_inventory`
    SET `current_level` = NEW.new_level,
        `last_refill` = IF(NEW.type = 'refill', NOW(), `last_refill`)
    WHERE `user_id` = NEW.user_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `light_logs`
--

CREATE TABLE `light_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `light_settings`
--

CREATE TABLE `light_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('ON','OFF') DEFAULT 'OFF',
  `mode` enum('manual','schedule','auto') DEFAULT 'manual',
  `brightness` int(11) DEFAULT 100,
  `schedule_on` time DEFAULT '06:00:00',
  `schedule_off` time DEFAULT '18:00:00',
  `auto_temp_threshold` decimal(4,1) DEFAULT 30.0,
  `last_changed` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `light_settings`
--

INSERT INTO `light_settings` (`id`, `user_id`, `status`, `mode`, `brightness`, `schedule_on`, `schedule_off`, `auto_temp_threshold`, `last_changed`) VALUES
(1, 1, 'OFF', 'manual', 100, '06:00:00', '18:00:00', 30.0, '2026-07-23 15:57:15');

--
-- Triggers `light_settings`
--
DELIMITER $$
CREATE TRIGGER `after_light_settings_update` AFTER UPDATE ON `light_settings` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status OR OLD.mode != NEW.mode OR OLD.brightness != NEW.brightness THEN
        INSERT INTO `light_logs` (`user_id`, `action`, `details`, `timestamp`)
        VALUES (
            NEW.user_id,
            CONCAT('Light ', NEW.status),
            CONCAT('Mode: ', NEW.mode, ', Brightness: ', NEW.brightness, '%'),
            NOW()
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('success','warning','danger','info') DEFAULT 'info',
  `link` varchar(255) DEFAULT NULL,
  `read` tinyint(1) DEFAULT 0,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `link`, `read`, `timestamp`) VALUES
(1, 1, 'Welcome to BroilerGuard', 'Your farm management system is now ready.', 'success', NULL, 0, '2026-07-23 16:16:02'),
(2, 1, 'Temperature Check', 'Current temperature is 32.5°C. Normal range is 20-35°C.', 'info', NULL, 0, '2026-07-23 16:16:02'),
(3, 1, 'Feed Level Alert', 'Feed level is at 15.2 kg. Consider refilling soon.', 'warning', NULL, 0, '2026-07-23 16:16:02'),
(4, 1, 'Water Level Critical', 'Water level is at 18%. Immediate refill recommended.', 'danger', NULL, 0, '2026-07-23 16:16:02');

-- --------------------------------------------------------

--
-- Table structure for table `notification_settings`
--

CREATE TABLE `notification_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `browser_enabled` tinyint(1) DEFAULT 1,
  `sound_enabled` tinyint(1) DEFAULT 1,
  `alert_temp_high` tinyint(1) DEFAULT 1,
  `temp_high_threshold` decimal(4,1) DEFAULT 35.0,
  `alert_temp_low` tinyint(1) DEFAULT 1,
  `temp_low_threshold` decimal(4,1) DEFAULT 20.0,
  `alert_humidity` tinyint(1) DEFAULT 1,
  `humidity_threshold` decimal(4,1) DEFAULT 80.0,
  `alert_feed_low` tinyint(1) DEFAULT 1,
  `feed_low_threshold` decimal(8,2) DEFAULT 10.00,
  `alert_water_low` tinyint(1) DEFAULT 1,
  `water_low_threshold` int(11) DEFAULT 20
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_settings`
--

INSERT INTO `notification_settings` (`id`, `user_id`, `browser_enabled`, `sound_enabled`, `alert_temp_high`, `temp_high_threshold`, `alert_temp_low`, `temp_low_threshold`, `alert_humidity`, `humidity_threshold`, `alert_feed_low`, `feed_low_threshold`, `alert_water_low`, `water_low_threshold`) VALUES
(1, 1, 1, 1, 1, 35.0, 1, 20.0, 1, 80.0, 1, 10.00, 1, 20);

-- --------------------------------------------------------

--
-- Table structure for table `pump_settings`
--

CREATE TABLE `pump_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `auto_mode` enum('auto','manual','schedule') DEFAULT 'auto',
  `low_level_threshold` int(11) DEFAULT 25,
  `high_level_threshold` int(11) DEFAULT 95,
  `pump_duration` int(11) DEFAULT 45,
  `schedule_interval` int(11) DEFAULT 3,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pump_settings`
--

INSERT INTO `pump_settings` (`id`, `user_id`, `auto_mode`, `low_level_threshold`, `high_level_threshold`, `pump_duration`, `schedule_interval`, `updated_at`) VALUES
(1, 1, 'auto', 25, 95, 45, 3, '2026-07-23 16:21:36');

-- --------------------------------------------------------

--
-- Table structure for table `sensor_readings`
--

CREATE TABLE `sensor_readings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `humidity` int(11) DEFAULT NULL,
  `feed_level` decimal(5,2) DEFAULT NULL,
  `water_level` int(11) DEFAULT NULL,
  `fan_status` enum('ON','OFF') DEFAULT 'OFF',
  `water_pump` enum('ON','OFF') DEFAULT 'OFF',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sensor_readings`
--

INSERT INTO `sensor_readings` (`id`, `user_id`, `temperature`, `humidity`, `feed_level`, `water_level`, `fan_status`, `water_pump`, `timestamp`) VALUES
(1, 1, 31.3, 41, NULL, NULL, 'OFF', 'OFF', '2026-07-23 16:12:43'),
(2, 1, 31.0, 41, NULL, NULL, 'OFF', 'OFF', '2026-07-23 15:12:43'),
(3, 1, 30.0, 40, NULL, NULL, 'OFF', 'OFF', '2026-07-23 14:12:43'),
(4, 1, 29.0, 47, NULL, NULL, 'OFF', 'OFF', '2026-07-23 13:12:43'),
(5, 1, 28.1, 51, NULL, NULL, 'OFF', 'OFF', '2026-07-23 12:12:43'),
(6, 1, 27.1, 52, NULL, NULL, 'OFF', 'OFF', '2026-07-23 11:12:43'),
(7, 1, 25.8, 53, NULL, NULL, 'OFF', 'OFF', '2026-07-23 10:12:43'),
(8, 1, 25.2, 60, NULL, NULL, 'OFF', 'OFF', '2026-07-23 09:12:43'),
(9, 1, 24.7, 60, NULL, NULL, 'OFF', 'OFF', '2026-07-23 08:12:43'),
(10, 1, 24.1, 67, NULL, NULL, 'OFF', 'OFF', '2026-07-23 07:12:43'),
(11, 1, 23.9, 69, NULL, NULL, 'OFF', 'OFF', '2026-07-23 06:12:43'),
(12, 1, 24.3, 72, NULL, NULL, 'OFF', 'OFF', '2026-07-23 05:12:43'),
(13, 1, 24.7, 70, NULL, NULL, 'OFF', 'OFF', '2026-07-23 04:12:43'),
(14, 1, 25.0, 68, NULL, NULL, 'OFF', 'OFF', '2026-07-23 03:12:43'),
(15, 1, 26.2, 71, NULL, NULL, 'OFF', 'OFF', '2026-07-23 02:12:43'),
(16, 1, 27.0, 69, NULL, NULL, 'OFF', 'OFF', '2026-07-23 01:12:43'),
(17, 1, 27.9, 64, NULL, NULL, 'OFF', 'OFF', '2026-07-23 00:12:43'),
(18, 1, 29.1, 57, NULL, NULL, 'OFF', 'OFF', '2026-07-22 23:12:43'),
(19, 1, 30.2, 58, NULL, NULL, 'OFF', 'OFF', '2026-07-22 22:12:43'),
(20, 1, 30.6, 53, NULL, NULL, 'OFF', 'OFF', '2026-07-22 21:12:43');

--
-- Triggers `sensor_readings`
--
DELIMITER $$
CREATE TRIGGER `before_sensor_readings_insert` BEFORE INSERT ON `sensor_readings` FOR EACH ROW BEGIN
    SET NEW.timestamp = NOW();
    
    -- Check temperature and create notification if too high
    IF NEW.temperature > 35.0 THEN
        INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `timestamp`)
        VALUES (
            NEW.user_id,
            'High Temperature Alert',
            CONCAT('Temperature is at ', NEW.temperature, '°C. Above threshold of 35°C.'),
            'warning',
            NOW()
        );
    END IF;
    
    -- Check temperature if too low
    IF NEW.temperature < 20.0 THEN
        INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `timestamp`)
        VALUES (
            NEW.user_id,
            'Low Temperature Alert',
            CONCAT('Temperature is at ', NEW.temperature, '°C. Below threshold of 20°C.'),
            'warning',
            NOW()
        );
    END IF;
    
    -- Check humidity
    IF NEW.humidity > 80 THEN
        INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `timestamp`)
        VALUES (
            NEW.user_id,
            'High Humidity Alert',
            CONCAT('Humidity is at ', NEW.humidity, '%. Above threshold of 80%.'),
            'warning',
            NOW()
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `timezone` varchar(50) DEFAULT 'Asia/Manila',
  `date_format` varchar(20) DEFAULT 'F d, Y',
  `temperature_unit` enum('celsius','fahrenheit') DEFAULT 'celsius',
  `language` varchar(5) DEFAULT 'en',
  `refresh_interval` int(11) DEFAULT 30,
  `enable_sound` tinyint(1) DEFAULT 1,
  `enable_browser_notifications` tinyint(1) DEFAULT 1,
  `alert_duration` int(11) DEFAULT 5,
  `session_timeout` int(11) DEFAULT 30,
  `two_factor_auth` tinyint(1) DEFAULT 0,
  `login_attempts` int(11) DEFAULT 5,
  `auto_backup` tinyint(1) DEFAULT 1,
  `backup_frequency` enum('daily','weekly') DEFAULT 'daily',
  `data_retention_days` int(11) DEFAULT 7,
  `theme` enum('light','dark') DEFAULT 'light',
  `compact_view` tinyint(1) DEFAULT 0,
  `show_charts` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `user_id`, `timezone`, `date_format`, `temperature_unit`, `language`, `refresh_interval`, `enable_sound`, `enable_browser_notifications`, `alert_duration`, `session_timeout`, `two_factor_auth`, `login_attempts`, `auto_backup`, `backup_frequency`, `data_retention_days`, `theme`, `compact_view`, `show_charts`) VALUES
(1, 1, 'Asia/Manila', 'F d, Y', 'celsius', 'en', 30, 1, 1, 5, 30, 0, 5, 1, 'daily', 7, 'light', 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@broilerguard.com', '2026-07-08 15:18:51', '2026-07-08 15:18:51');

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `after_user_update` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    IF OLD.username != NEW.username OR OLD.email != NEW.email THEN
        INSERT INTO `activity_logs` (`user_id`, `action`, `details`, `user`, `timestamp`)
        VALUES (
            NEW.id,
            'User Profile Updated',
            CONCAT('Updated user: ', OLD.username, ' -> ', NEW.username),
            NEW.username,
            NOW()
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_activity_logs`
-- (See below for the actual view)
--
CREATE TABLE `v_activity_logs` (
`id` int(11)
,`user_id` int(11)
,`action` varchar(100)
,`details` text
,`user` varchar(50)
,`ip` varchar(50)
,`timestamp` timestamp
,`username` varchar(50)
,`email` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_chick_health_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_chick_health_summary` (
`chick_id` varchar(20)
,`total_detections` bigint(21)
,`healthy_count` decimal(22,0)
,`weak_count` decimal(22,0)
,`unhealthy_count` decimal(22,0)
,`avg_confidence` decimal(9,6)
,`last_detection` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_dashboard`
-- (See below for the actual view)
--
CREATE TABLE `v_dashboard` (
`user_id` int(11)
,`temperature` decimal(4,1)
,`humidity` int(11)
,`feed_level` decimal(8,2)
,`water_level` decimal(8,2)
,`feed_capacity` decimal(8,2)
,`water_capacity` decimal(8,2)
,`feed_percentage` decimal(13,1)
,`water_percentage` decimal(13,1)
,`unread_notifications` bigint(21)
,`unhealthy_count` bigint(21)
,`fan_mode` enum('auto','manual','schedule')
,`light_status` enum('ON','OFF')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_feed_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_feed_summary` (
`user_id` int(11)
,`date` date
,`total_consumption` decimal(30,2)
,`total_refills` decimal(30,2)
,`total_transactions` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_health_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_health_summary` (
`user_id` int(11)
,`status` enum('healthy','weak','unhealthy')
,`count` bigint(21)
,`avg_confidence` decimal(9,6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_latest_sensor`
-- (See below for the actual view)
--
CREATE TABLE `v_latest_sensor` (
`id` int(11)
,`user_id` int(11)
,`temperature` decimal(4,1)
,`humidity` int(11)
,`feed_level` decimal(5,2)
,`water_level` int(11)
,`fan_status` enum('ON','OFF')
,`water_pump` enum('ON','OFF')
,`timestamp` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_today_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_today_summary` (
`user_id` int(11)
,`date` date
,`avg_temp` decimal(8,5)
,`max_temp` decimal(4,1)
,`min_temp` decimal(4,1)
,`avg_humidity` decimal(14,4)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_unread_notifications`
-- (See below for the actual view)
--
CREATE TABLE `v_unread_notifications` (
`id` int(11)
,`user_id` int(11)
,`title` varchar(255)
,`message` text
,`type` enum('success','warning','danger','info')
,`link` varchar(255)
,`read` tinyint(1)
,`timestamp` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_water_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_water_summary` (
`user_id` int(11)
,`date` date
,`total_consumption` decimal(30,2)
,`total_refills` decimal(30,2)
,`total_transactions` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `water_inventory`
--

CREATE TABLE `water_inventory` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `current_level` decimal(8,2) DEFAULT 1500.00,
  `capacity` decimal(8,2) DEFAULT 2000.00,
  `unit` varchar(10) DEFAULT 'liters',
  `last_refill` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `alert_threshold` decimal(8,2) DEFAULT 300.00,
  `critical_threshold` decimal(8,2) DEFAULT 150.00,
  `supplier` varchar(100) DEFAULT 'Local Water District',
  `water_type` varchar(50) DEFAULT 'Clean Water',
  `flow_rate` decimal(5,2) DEFAULT 2.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `water_inventory`
--

INSERT INTO `water_inventory` (`id`, `user_id`, `current_level`, `capacity`, `unit`, `last_refill`, `alert_threshold`, `critical_threshold`, `supplier`, `water_type`, `flow_rate`) VALUES
(1, 1, 1500.00, 2000.00, 'liters', '2026-07-23 16:34:48', 300.00, 150.00, 'Local Water District', 'Clean Water', 2.00);

--
-- Triggers `water_inventory`
--
DELIMITER $$
CREATE TRIGGER `after_water_inventory_update` AFTER UPDATE ON `water_inventory` FOR EACH ROW BEGIN
    IF NEW.current_level <= NEW.alert_threshold AND OLD.current_level > NEW.alert_threshold THEN
        INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `timestamp`)
        VALUES (
            NEW.user_id,
            'Water Level Alert',
            CONCAT('Water level is at ', ROUND(NEW.current_level, 1), ' ', NEW.unit, '. Below alert threshold of ', ROUND(NEW.alert_threshold, 1), ' ', NEW.unit, '.'),
            'warning',
            NOW()
        );
    END IF;
    
    IF NEW.current_level <= NEW.critical_threshold AND OLD.current_level > NEW.critical_threshold THEN
        INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `timestamp`)
        VALUES (
            NEW.user_id,
            'Water Level Critical!',
            CONCAT('Water level is at ', ROUND(NEW.current_level, 1), ' ', NEW.unit, '. BELOW CRITICAL THRESHOLD of ', ROUND(NEW.critical_threshold, 1), ' ', NEW.unit, '!'),
            'danger',
            NOW()
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `water_schedules`
--

CREATE TABLE `water_schedules` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `time` time NOT NULL,
  `duration` int(11) NOT NULL COMMENT 'seconds',
  `enabled` tinyint(1) DEFAULT 1,
  `label` varchar(50) DEFAULT 'Watering'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `water_schedules`
--

INSERT INTO `water_schedules` (`id`, `user_id`, `time`, `duration`, `enabled`, `label`) VALUES
(1, 1, '06:00:00', 30, 1, 'Morning Watering'),
(2, 1, '12:00:00', 25, 1, 'Afternoon Watering'),
(3, 1, '18:00:00', 30, 1, 'Evening Watering');

-- --------------------------------------------------------

--
-- Table structure for table `water_transactions`
--

CREATE TABLE `water_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('refill','consumption') NOT NULL,
  `amount` decimal(8,2) NOT NULL,
  `source` enum('auto_pump','manual') DEFAULT 'manual',
  `notes` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `remaining` decimal(8,2) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `new_level` decimal(8,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `water_transactions`
--

INSERT INTO `water_transactions` (`id`, `user_id`, `type`, `amount`, `source`, `notes`, `timestamp`, `remaining`, `cost`, `new_level`) VALUES
(1, 1, 'consumption', 9.00, 'auto_pump', 'Sample water usage', '2026-07-21 18:36:41', NULL, NULL, NULL),
(2, 1, 'consumption', 17.00, 'auto_pump', 'Sample water usage', '2026-07-22 00:36:41', NULL, NULL, NULL),
(3, 1, 'consumption', 14.00, 'auto_pump', 'Sample water usage', '2026-07-21 21:36:41', NULL, NULL, NULL),
(4, 1, 'consumption', 14.00, 'auto_pump', 'Sample water usage', '2026-07-23 05:36:41', NULL, NULL, NULL),
(5, 1, 'consumption', 22.00, 'auto_pump', 'Sample water usage', '2026-07-22 16:36:41', NULL, NULL, NULL),
(6, 1, 'consumption', 6.00, 'auto_pump', 'Sample water usage', '2026-07-23 16:36:41', NULL, NULL, NULL),
(7, 1, 'consumption', 24.00, 'auto_pump', 'Sample water usage', '2026-07-23 07:36:41', NULL, NULL, NULL),
(8, 1, 'consumption', 23.00, 'auto_pump', 'Sample water usage', '2026-07-21 19:36:41', NULL, NULL, NULL),
(9, 1, 'consumption', 12.00, 'auto_pump', 'Sample water usage', '2026-07-23 07:36:41', NULL, NULL, NULL),
(10, 1, 'consumption', 9.00, 'auto_pump', 'Sample water usage', '2026-07-23 05:36:41', NULL, NULL, NULL),
(11, 1, 'consumption', 11.00, 'auto_pump', 'Sample water usage', '2026-07-21 16:36:41', NULL, NULL, NULL),
(12, 1, 'consumption', 13.00, 'auto_pump', 'Sample water usage', '2026-07-22 08:36:41', NULL, NULL, NULL),
(13, 1, 'consumption', 18.00, 'auto_pump', 'Sample water usage', '2026-07-22 17:36:41', NULL, NULL, NULL),
(14, 1, 'consumption', 7.00, 'auto_pump', 'Sample water usage', '2026-07-22 07:36:41', NULL, NULL, NULL),
(15, 1, 'consumption', 22.00, 'auto_pump', 'Sample water usage', '2026-07-22 07:36:41', NULL, NULL, NULL),
(16, 1, 'consumption', 13.00, 'auto_pump', 'Sample water usage', '2026-07-21 19:36:41', NULL, NULL, NULL),
(17, 1, 'consumption', 12.00, 'auto_pump', 'Sample water usage', '2026-07-22 01:36:41', NULL, NULL, NULL),
(18, 1, 'consumption', 15.00, 'auto_pump', 'Sample water usage', '2026-07-22 12:36:41', NULL, NULL, NULL),
(19, 1, 'consumption', 18.00, 'auto_pump', 'Sample water usage', '2026-07-23 14:36:41', NULL, NULL, NULL),
(20, 1, 'consumption', 19.00, 'auto_pump', 'Sample water usage', '2026-07-22 11:36:41', NULL, NULL, NULL);

--
-- Triggers `water_transactions`
--
DELIMITER $$
CREATE TRIGGER `after_water_transaction_update` AFTER INSERT ON `water_transactions` FOR EACH ROW BEGIN
    UPDATE `water_inventory`
    SET `current_level` = NEW.new_level,
        `last_refill` = IF(NEW.type = 'refill', NOW(), `last_refill`)
    WHERE `user_id` = NEW.user_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure for view `v_activity_logs`
--
DROP TABLE IF EXISTS `v_activity_logs`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_activity_logs`  AS SELECT `al`.`id` AS `id`, `al`.`user_id` AS `user_id`, `al`.`action` AS `action`, `al`.`details` AS `details`, `al`.`user` AS `user`, `al`.`ip` AS `ip`, `al`.`timestamp` AS `timestamp`, `u`.`username` AS `username`, `u`.`email` AS `email` FROM (`activity_logs` `al` left join `users` `u` on(`al`.`user_id` = `u`.`id`)) ORDER BY `al`.`timestamp` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_chick_health_summary`
--
DROP TABLE IF EXISTS `v_chick_health_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_chick_health_summary`  AS SELECT `detection_logs`.`chick_id` AS `chick_id`, count(0) AS `total_detections`, sum(case when `detection_logs`.`status` = 'healthy' then 1 else 0 end) AS `healthy_count`, sum(case when `detection_logs`.`status` = 'weak' then 1 else 0 end) AS `weak_count`, sum(case when `detection_logs`.`status` = 'unhealthy' then 1 else 0 end) AS `unhealthy_count`, avg(`detection_logs`.`confidence`) AS `avg_confidence`, max(`detection_logs`.`timestamp`) AS `last_detection` FROM `detection_logs` GROUP BY `detection_logs`.`chick_id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_dashboard`
--
DROP TABLE IF EXISTS `v_dashboard`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_dashboard`  AS SELECT `s`.`user_id` AS `user_id`, `s`.`temperature` AS `temperature`, `s`.`humidity` AS `humidity`, `fi`.`current_level` AS `feed_level`, `wi`.`current_level` AS `water_level`, `fi`.`capacity` AS `feed_capacity`, `wi`.`capacity` AS `water_capacity`, round(`fi`.`current_level` / `fi`.`capacity` * 100,1) AS `feed_percentage`, round(`wi`.`current_level` / `wi`.`capacity` * 100,1) AS `water_percentage`, (select count(0) from `notifications` `n` where `n`.`user_id` = `s`.`user_id` and `n`.`read` = 0) AS `unread_notifications`, (select count(0) from `detection_logs` `dl` where `dl`.`user_id` = `s`.`user_id` and `dl`.`status` in ('unhealthy','weak')) AS `unhealthy_count`, `fs`.`auto_mode` AS `fan_mode`, `ls`.`status` AS `light_status` FROM ((((`sensor_readings` `s` join `feed_inventory` `fi` on(`s`.`user_id` = `fi`.`user_id`)) join `water_inventory` `wi` on(`s`.`user_id` = `wi`.`user_id`)) join `fan_settings` `fs` on(`s`.`user_id` = `fs`.`user_id`)) join `light_settings` `ls` on(`s`.`user_id` = `ls`.`user_id`)) WHERE `s`.`timestamp` = (select max(`s2`.`timestamp`) from `sensor_readings` `s2` where `s2`.`user_id` = `s`.`user_id`) ;

-- --------------------------------------------------------

--
-- Structure for view `v_feed_summary`
--
DROP TABLE IF EXISTS `v_feed_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_feed_summary`  AS SELECT `feed_transactions`.`user_id` AS `user_id`, cast(`feed_transactions`.`timestamp` as date) AS `date`, sum(case when `feed_transactions`.`type` = 'consumption' then `feed_transactions`.`amount` else 0 end) AS `total_consumption`, sum(case when `feed_transactions`.`type` = 'refill' then `feed_transactions`.`amount` else 0 end) AS `total_refills`, count(0) AS `total_transactions` FROM `feed_transactions` GROUP BY `feed_transactions`.`user_id`, cast(`feed_transactions`.`timestamp` as date) ORDER BY cast(`feed_transactions`.`timestamp` as date) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_health_summary`
--
DROP TABLE IF EXISTS `v_health_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_health_summary`  AS SELECT `detection_logs`.`user_id` AS `user_id`, `detection_logs`.`status` AS `status`, count(0) AS `count`, avg(`detection_logs`.`confidence`) AS `avg_confidence` FROM `detection_logs` WHERE `detection_logs`.`timestamp` >= current_timestamp() - interval 24 hour GROUP BY `detection_logs`.`user_id`, `detection_logs`.`status` ;

-- --------------------------------------------------------

--
-- Structure for view `v_latest_sensor`
--
DROP TABLE IF EXISTS `v_latest_sensor`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_latest_sensor`  AS SELECT `s1`.`id` AS `id`, `s1`.`user_id` AS `user_id`, `s1`.`temperature` AS `temperature`, `s1`.`humidity` AS `humidity`, `s1`.`feed_level` AS `feed_level`, `s1`.`water_level` AS `water_level`, `s1`.`fan_status` AS `fan_status`, `s1`.`water_pump` AS `water_pump`, `s1`.`timestamp` AS `timestamp` FROM (`sensor_readings` `s1` join (select `sensor_readings`.`user_id` AS `user_id`,max(`sensor_readings`.`timestamp`) AS `max_timestamp` from `sensor_readings` group by `sensor_readings`.`user_id`) `s2` on(`s1`.`user_id` = `s2`.`user_id` and `s1`.`timestamp` = `s2`.`max_timestamp`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_today_summary`
--
DROP TABLE IF EXISTS `v_today_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_today_summary`  AS SELECT `sensor_readings`.`user_id` AS `user_id`, cast(`sensor_readings`.`timestamp` as date) AS `date`, avg(`sensor_readings`.`temperature`) AS `avg_temp`, max(`sensor_readings`.`temperature`) AS `max_temp`, min(`sensor_readings`.`temperature`) AS `min_temp`, avg(`sensor_readings`.`humidity`) AS `avg_humidity` FROM `sensor_readings` WHERE cast(`sensor_readings`.`timestamp` as date) = curdate() GROUP BY `sensor_readings`.`user_id`, cast(`sensor_readings`.`timestamp` as date) ;

-- --------------------------------------------------------

--
-- Structure for view `v_unread_notifications`
--
DROP TABLE IF EXISTS `v_unread_notifications`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_unread_notifications`  AS SELECT `notifications`.`id` AS `id`, `notifications`.`user_id` AS `user_id`, `notifications`.`title` AS `title`, `notifications`.`message` AS `message`, `notifications`.`type` AS `type`, `notifications`.`link` AS `link`, `notifications`.`read` AS `read`, `notifications`.`timestamp` AS `timestamp` FROM `notifications` WHERE `notifications`.`read` = 0 ORDER BY `notifications`.`timestamp` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_water_summary`
--
DROP TABLE IF EXISTS `v_water_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_water_summary`  AS SELECT `water_transactions`.`user_id` AS `user_id`, cast(`water_transactions`.`timestamp` as date) AS `date`, sum(case when `water_transactions`.`type` = 'consumption' then `water_transactions`.`amount` else 0 end) AS `total_consumption`, sum(case when `water_transactions`.`type` = 'refill' then `water_transactions`.`amount` else 0 end) AS `total_refills`, count(0) AS `total_transactions` FROM `water_transactions` GROUP BY `water_transactions`.`user_id`, cast(`water_transactions`.`timestamp` as date) ORDER BY cast(`water_transactions`.`timestamp` as date) DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `camera_snapshots`
--
ALTER TABLE `camera_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `detection_logs`
--
ALTER TABLE `detection_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `fan_logs`
--
ALTER TABLE `fan_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `fan_settings`
--
ALTER TABLE `fan_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `feed_inventory`
--
ALTER TABLE `feed_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `feed_schedules`
--
ALTER TABLE `feed_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `feed_settings`
--
ALTER TABLE `feed_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `feed_transactions`
--
ALTER TABLE `feed_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `light_logs`
--
ALTER TABLE `light_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `light_settings`
--
ALTER TABLE `light_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `pump_settings`
--
ALTER TABLE `pump_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sensor_readings`
--
ALTER TABLE `sensor_readings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `water_inventory`
--
ALTER TABLE `water_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `water_schedules`
--
ALTER TABLE `water_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `water_transactions`
--
ALTER TABLE `water_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `camera_snapshots`
--
ALTER TABLE `camera_snapshots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `detection_logs`
--
ALTER TABLE `detection_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `fan_logs`
--
ALTER TABLE `fan_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fan_settings`
--
ALTER TABLE `fan_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feed_inventory`
--
ALTER TABLE `feed_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feed_schedules`
--
ALTER TABLE `feed_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `feed_settings`
--
ALTER TABLE `feed_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feed_transactions`
--
ALTER TABLE `feed_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `light_logs`
--
ALTER TABLE `light_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `light_settings`
--
ALTER TABLE `light_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notification_settings`
--
ALTER TABLE `notification_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pump_settings`
--
ALTER TABLE `pump_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sensor_readings`
--
ALTER TABLE `sensor_readings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `water_inventory`
--
ALTER TABLE `water_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `water_schedules`
--
ALTER TABLE `water_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `water_transactions`
--
ALTER TABLE `water_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `camera_snapshots`
--
ALTER TABLE `camera_snapshots`
  ADD CONSTRAINT `camera_snapshots_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `detection_logs`
--
ALTER TABLE `detection_logs`
  ADD CONSTRAINT `detection_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fan_logs`
--
ALTER TABLE `fan_logs`
  ADD CONSTRAINT `fan_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fan_settings`
--
ALTER TABLE `fan_settings`
  ADD CONSTRAINT `fan_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feed_inventory`
--
ALTER TABLE `feed_inventory`
  ADD CONSTRAINT `feed_inventory_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feed_schedules`
--
ALTER TABLE `feed_schedules`
  ADD CONSTRAINT `feed_schedules_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feed_settings`
--
ALTER TABLE `feed_settings`
  ADD CONSTRAINT `feed_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feed_transactions`
--
ALTER TABLE `feed_transactions`
  ADD CONSTRAINT `feed_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `light_logs`
--
ALTER TABLE `light_logs`
  ADD CONSTRAINT `light_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `light_settings`
--
ALTER TABLE `light_settings`
  ADD CONSTRAINT `light_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD CONSTRAINT `notification_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pump_settings`
--
ALTER TABLE `pump_settings`
  ADD CONSTRAINT `pump_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sensor_readings`
--
ALTER TABLE `sensor_readings`
  ADD CONSTRAINT `sensor_readings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `water_inventory`
--
ALTER TABLE `water_inventory`
  ADD CONSTRAINT `water_inventory_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `water_schedules`
--
ALTER TABLE `water_schedules`
  ADD CONSTRAINT `water_schedules_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `water_transactions`
--
ALTER TABLE `water_transactions`
  ADD CONSTRAINT `water_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
