# BROILER GUARD
# 🐔 Development of an IoT-Based Environmental Monitoring and Automation System for Broiler Chickens in a Small-Scale Tunnel-Ventilated House

## 👥 Team Members

- **Gayos, Princess Iris A.**
- **Perey, Alessandra Mae A.**
- **Olino, Marklee B.**

---

# 📖 Introduction

## Purpose

This document presents the **Software Requirements Specification (SRS)** for the *IoT-Based Environmental Monitoring and Automation System for Broiler Chickens in a Small-Scale Tunnel-Ventilated House.*

It defines the project's objectives, scope, architecture, and major system components. The document serves as a reference for developers, researchers, advisers, and future researchers who may maintain or enhance the system.

The project aims to improve poultry management by integrating **Internet of Things (IoT)** technologies, environmental monitoring, AI-assisted chicken observation, and automated control mechanisms into a centralized web-based monitoring platform.

---

## Scope

The proposed system is designed specifically for a **small-scale tunnel-ventilated poultry house** containing **five (5) broiler chickens**.

### The system provides:

- 🌡️ Real-time temperature monitoring
- 💧 Real-time humidity monitoring
- 🌬️ Automated ventilation control
- 🍗 Automated feeding
- 🚰 Automated water dispensing
- 📷 AI-assisted chicken condition monitoring using ESP32-CAM
- 📊 Historical environmental data logging
- 💻 Web-based monitoring dashboard
- 🔔 Notification and alert system
- 📦 Feed inventory monitoring

### Technologies Used

#### Hardware

- ESP32
- ESP32-CAM
- DHT11 Temperature and Humidity Sensor
- Relay Modules
- Servo Motor
- Water Pump
- Ventilation Fan
- 25W Bulb

#### Software

- PHP
- MySQL
- Arduino IDE
- HTML
- CSS
- JavaScript
- Bootstrap
- Chart.js

#### Communication

- Wi-Fi

The system can be accessed through desktop and mobile web browsers connected to the Internet.

---

## Definitions, Acronyms, and Abbreviations

| Term | Description |
|------|-------------|
| **AI (Artificial Intelligence)** | Analyzes images captured by the ESP32-CAM to identify visible abnormalities in broiler chickens. |
| **Automation System** | Automatically activates hardware devices based on environmental thresholds. |
| **IoT (Internet of Things)** | Connects sensors, microcontrollers, and web technologies for real-time monitoring. |
| **IoT Sensors** | Devices used to measure temperature and humidity inside the poultry house. |
| **ESP32-CAM** | Microcontroller with an integrated camera used for live monitoring. |
| **Tunnel-Ventilated Poultry House** | The controlled poultry housing environment used in this project. |
| **Ventilation System** | Automated airflow mechanism used to regulate temperature inside the poultry house. |

---

# 🏗️ Overall Description

## System Overview

The proposed system is an **IoT-based environmental monitoring and automation solution** developed to improve broiler chicken management within a small-scale tunnel-ventilated poultry house.

Environmental data are continuously collected using the **DHT11 sensor** connected to an **ESP32 microcontroller**. The collected readings are transmitted through Wi-Fi to a PHP-based backend server.

The backend stores the information in a **MySQL database**, where it becomes available for visualization through a web dashboard.

Meanwhile, the **ESP32-CAM** captures images of the chickens, which are processed by an AI model to assist in detecting possible abnormal chicken conditions.

Based on predefined environmental thresholds, the system automatically activates different hardware components, including:

- 🌬️ Ventilation Fan
- 🍗 Automatic Feeder
- 🚰 Water Pump

Users can access the web dashboard to:

- Monitor environmental conditions
- View historical records
- Receive notifications
- Remotely control selected devices

---

## System Architecture

```text
                 +----------------------+
                 |      DHT11 Sensor    |
                 +----------+-----------+
                            |
                            |
                      Temperature &
                        Humidity
                            |
                            v
                    +---------------+
                    |     ESP32     |
                    +-------+-------+
                            |
                     Wi-Fi Communication
                            |
                            v
                  +---------------------+
                  |     PHP Backend     |
                  +----------+----------+
                             |
                             |
                             v
                   +---------------------+
                   |   MySQL Database    |
                   +----------+----------+
                              |
                              |
                              v
                 +--------------------------+
                 |   Web Dashboard (Users)  |
                 +--------------------------+

ESP32-CAM
     |
     v
AI Chicken Detection
     |
     v
Web Dashboard
```

---

# ⚙️ Specific Requirements

The following features are currently functional and available for the project defense.

---

## ✅ Functional Features

- ESP32 successfully integrated and programmed.
- DHT11 sensor integrated with real-time temperature and humidity monitoring.
- Real-time temperature and humidity data successfully transmitted and logged to the database.
- Automated ventilation fan control implemented.
- Automated watering system implemented.
- Centralized AC-to-DC power supply installed and integrated.
- Web dashboard successfully connected to the MySQL database.
- Real-time communication established between the ESP32 and the web dashboard.
- Initial ESP32-CAM programming completed.
- AI dataset for chicken disease detection prepared for training.
- Feeder mechanism assembled and currently undergoing mechanical refinement.

---

## 🔗 Connected APIs and Services

| Service | Status |
|----------|--------|
| PHP Backend | ✅ Connected |
| MySQL Database | ✅ Connected |
| ESP32 Data Transmission | ✅ Functional |
| Web Dashboard | ✅ Functional |

---

## 🔧 Hardware Components Status

| Component | Status |
|-----------|--------|
| ESP32 | ✅ Functional |
| DHT11 Sensor | ✅ Functional |
| Ventilation Fan | ✅ Functional |
| Water Pump | ✅ Functional |
| AC-to-DC Power Supply | ✅ Functional |
| Web Dashboard | ✅ Functional |
| MySQL Database | ✅ Functional |
| ESP32-CAM | 🟡 Initial Programming Completed |
| Automatic Feeder Mechanism | 🟡 Under Mechanical Refinement |
| Servo-Controlled Feed Gate | 🟡 Under Development |
| 16V Feeder Motor | 🟡 Pending Integration |
| 25W Bulb | ✅ Functional |

---

## 🚧 Features Under Development

The following modules are currently under development and planned for future integration:

- Train the AI model for chicken disease detection.
- Integrate AI-based disease detection with the ESP32-CAM.
- Complete automatic feeder integration.
- Finalize servo-controlled feed gate operation.
- Improve AI detection accuracy.
- Enhance dashboard analytics and reporting.

---

# 📌 Current Project Progress

### Completed

- Environmental monitoring
- Database integration
- Web dashboard
- Temperature and humidity automation
- Water pump automation
- Ventilation automation
- ESP32 communication

### Ongoing

- AI model training
- ESP32-CAM integration
- Automatic feeder refinement
- Servo gate mechanism

---


# 📄 License

This project was developed as a Capstone Project for the Bachelor of Science in Information Technology Major in Network Technology.

© 2026 Gayos, Perey, and Olino. All rights reserved.
