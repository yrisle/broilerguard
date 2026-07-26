#include <WiFi.h>
#include <WebServer.h>
#include <DHT.h>
#include <Servo.h>

//==================== WiFi ====================
const char* ssid = "PLDTHOMEFIBR602f8";
const char* password = "Gayos_181828";

//==================== Relay Pins ====================
#define FAN_RELAY     23
#define PUMP_RELAY    22
#define LIGHT_RELAY   21
#define DYNAMO_RELAY  19   // Dynamo motor (16V)

//==================== DHT11 ====================
#define DHTPIN 4
#define DHTTYPE DHT11
DHT dht(DHTPIN, DHTTYPE);

WebServer server(80);

//==================== State Variables ====================
bool fanState = false;
bool pumpState = false;
bool lightState = false;
bool dynamoState = false;

//==================== SERVO GATE ====================
#define GATE_SERVO1 13
#define GATE_SERVO2 14
Servo gateServo1;
Servo gateServo2;
bool gateOpen = false;

//==================== SENSOR JSON ENDPOINT ====================
void handleSensor() {
  float temp = dht.readTemperature();
  float hum  = dht.readHumidity();
  
  if (isnan(temp)) temp = 0.0;
  if (isnan(hum))  hum  = 0.0;

  String json = "{";
  json += "\"temperature\":" + String(temp, 1) + ",";
  json += "\"humidity\":" + String(hum, 1) + ",";
  json += "\"fan\":" + String(fanState ? 1 : 0) + ",";
  json += "\"pump\":" + String(pumpState ? 1 : 0) + ",";
  json += "\"light\":" + String(lightState ? 1 : 0) + ",";
  json += "\"gate\":" + String(gateOpen ? 1 : 0) + ",";
  json += "\"dynamo\":" + String(dynamoState ? 1 : 0);
  json += "}";
  
  server.send(200, "application/json", json);
}

//==================== HOME PAGE (HTML) ====================
void handleRoot() {
  float temp = dht.readTemperature();
  float hum  = dht.readHumidity();
  bool dhtOk = !(isnan(temp) || isnan(hum));

  String html = "<!DOCTYPE html><html><head><meta name='viewport' content='width=device-width, initial-scale=1'>";
  html += "<meta http-equiv='refresh' content='10'>";
  html += "<title>Poultry House Control</title><style>";
  html += "*{box-sizing:border-box;margin:0;padding:0}";
  html += "body{font-family:'Segoe UI',sans-serif;background:#e8f0fe;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px}";
  html += ".container{max-width:700px;width:100%;background:#fff;padding:20px 25px;border-radius:20px;box-shadow:0 8px 24px rgba(0,0,0,0.12)}";
  html += "h1{color:#1e3c5c;text-align:center;font-size:26px;border-bottom:2px solid #d0e0f0;padding-bottom:12px}";
  html += ".sensor-box{background:#f0f7ff;border-radius:12px;padding:15px;margin:15px 0;display:flex;justify-content:space-around;flex-wrap:wrap;border:1px solid #d0e0f0}";
  html += ".sensor-item{text-align:center;padding:5px 15px}";
  html += ".sensor-item .value{font-size:28px;font-weight:bold;color:#0b3b5c}";
  html += ".sensor-item .label{font-size:14px;color:#4a6a8a}";
  html += ".sensor-error{color:#c0392b;text-align:center;padding:10px;background:#ffe5e5;border-radius:8px}";
  html += ".device{background:#fafcff;border-radius:12px;padding:12px 18px;margin:12px 0;border:1px solid #e0ecf5;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between}";
  html += ".device h2{font-size:18px;color:#1e3c5c;margin:0;flex:1 1 120px}";
  html += ".device .status{font-weight:bold;padding:4px 12px;border-radius:20px;font-size:14px;margin:0 10px}";
  html += ".device .status.on{background:#27ae60;color:#fff}";
  html += ".device .status.off{background:#e74c3c;color:#fff}";
  html += ".device .status.open{background:#27ae60;color:#fff}";
  html += ".device .status.closed{background:#e74c3c;color:#fff}";
  html += ".button-group{display:flex;gap:8px;flex-wrap:wrap}";
  html += "button{background:#3498db;border:none;color:#fff;padding:8px 18px;border-radius:30px;font-size:15px;cursor:pointer;transition:0.2s;font-weight:600}";
  html += "button:hover{background:#2176ae;transform:scale(1.02)}";
  html += "button.off{background:#95a5a6}";
  html += "button.off:hover{background:#7f8c8d}";
  html += "button.open-btn{background:#27ae60}";
  html += "button.open-btn:hover{background:#1e8449}";
  html += "button.close-btn{background:#e67e22}";
  html += "button.close-btn:hover{background:#ca6f1e}";
  html += ".footer{text-align:center;color:#7f8c8d;font-size:12px;margin-top:20px;border-top:1px solid #d0e0f0;padding-top:12px}";
  html += "@media(max-width:500px){.device{flex-direction:column;align-items:stretch;gap:8px}.button-group{justify-content:center}}";
  html += "</style></head><body><div class='container'>";
  html += "<h1>🐔 Poultry House Control</h1>";
  
  html += "<div class='sensor-box'>";
  if (dhtOk) {
    html += "<div class='sensor-item'><div class='value'>" + String(temp,1) + "°C</div><div class='label'>Temperature</div></div>";
    html += "<div class='sensor-item'><div class='value'>" + String(hum,1) + "%</div><div class='label'>Humidity</div></div>";
  } else {
    html += "<div class='sensor-error'>⚠️ DHT11 error – check wiring</div>";
  }
  html += "</div>";

  // ===== FAN =====
  html += "<div class='device'><h2>Fan</h2>";
  html += "<span class='status " + String(fanState?"on":"off") + "'>" + String(fanState?"ON":"OFF") + "</span>";
  html += "<div class='button-group'><a href='/fan_on'><button>ON</button></a><a href='/fan_off'><button class='off'>OFF</button></a></div></div>";

  // ===== WATER PUMP =====
  html += "<div class='device'><h2>Water Pump</h2>";
  html += "<span class='status " + String(pumpState?"on":"off") + "'>" + String(pumpState?"ON":"OFF") + "</span>";
  html += "<div class='button-group'><a href='/pump_on'><button>ON</button></a><a href='/pump_off'><button class='off'>OFF</button></a></div></div>";

  // ===== LIGHT =====
  html += "<div class='device'><h2>Light</h2>";
  html += "<span class='status " + String(lightState?"on":"off") + "'>" + String(lightState?"ON":"OFF") + "</span>";
  html += "<div class='button-group'><a href='/light_on'><button>ON</button></a><a href='/light_off'><button class='off'>OFF</button></a></div></div>";

  // ===== GATE (Servo) =====
  html += "<div class='device'><h2>Gate</h2>";
  html += "<span class='status " + String(gateOpen?"open":"closed") + "'>" + String(gateOpen?"OPEN":"CLOSED") + "</span>";
  html += "<div class='button-group'><a href='/gate_open'><button class='open-btn'>OPEN</button></a><a href='/gate_close'><button class='close-btn'>CLOSE</button></a></div></div>";

  // ===== DYNAMO FEEDER =====
  html += "<div class='device'><h2>Dynamo Feeder</h2>";
  html += "<span class='status " + String(dynamoState?"on":"off") + "'>" + String(dynamoState?"ON":"OFF") + "</span>";
  html += "<div class='button-group'><a href='/dynamo_on'><button>ON</button></a><a href='/dynamo_off'><button class='off'>OFF</button></a></div></div>";

  html += "<div class='footer'>Auto‑refresh 10s &bull; ESP32 Poultry System</div></div></body></html>";
  server.send(200, "text/html", html);
}

//==================== CONTROL HANDLERS ====================

// ----- FAN -----
void fanON() { 
  digitalWrite(FAN_RELAY, LOW);  
  fanState = true;  
  Serial.println("FAN ON");
  handleRoot(); 
}
void fanOFF() { 
  digitalWrite(FAN_RELAY, HIGH); 
  fanState = false; 
  Serial.println("FAN OFF");
  handleRoot(); 
}

// ----- WATER PUMP -----
void pumpON() { 
  digitalWrite(PUMP_RELAY, LOW);  
  pumpState = true;  
  Serial.println("PUMP ON");
  handleRoot(); 
}
void pumpOFF() { 
  digitalWrite(PUMP_RELAY, HIGH); 
  pumpState = false; 
  Serial.println("PUMP OFF");
  handleRoot(); 
}

// ----- LIGHT -----
void lightON() { 
  digitalWrite(LIGHT_RELAY, LOW);  
  lightState = true;  
  Serial.println("LIGHT ON");
  handleRoot(); 
}
void lightOFF() { 
  digitalWrite(LIGHT_RELAY, HIGH); 
  lightState = false; 
  Serial.println("LIGHT OFF");
  handleRoot(); 
}

// ----- GATE (Servo) -----
void gateOPEN() { 
  gateServo1.write(0);    
  gateServo2.write(0); 
  gateOpen = true; 
  Serial.println("GATE OPEN (0°)");
  handleRoot(); 
}
void gateCLOSE() { 
  gateServo1.write(180);  
  gateServo2.write(180); 
  gateOpen = false; 
  Serial.println("GATE CLOSED (180°)");
  handleRoot(); 
}

// ----- DYNAMO FEEDER (with Serial Debug) -----
void dynamoON() { 
  digitalWrite(DYNAMO_RELAY, LOW);   
  dynamoState = true;  
  Serial.println("*** DYNAMO TURNED ON (GPIO 19 = LOW) ***");
  handleRoot(); 
}
void dynamoOFF() { 
  digitalWrite(DYNAMO_RELAY, HIGH);  
  dynamoState = false; 
  Serial.println("*** DYNAMO TURNED OFF (GPIO 19 = HIGH) ***");
  handleRoot(); 
}

//==================== SETUP ====================
void setup() {
  Serial.begin(115200);
  dht.begin();

  pinMode(FAN_RELAY, OUTPUT);   
  pinMode(PUMP_RELAY, OUTPUT);  
  pinMode(LIGHT_RELAY, OUTPUT); 
  pinMode(DYNAMO_RELAY, OUTPUT);
  
  digitalWrite(FAN_RELAY, HIGH);
  digitalWrite(PUMP_RELAY, HIGH);
  digitalWrite(LIGHT_RELAY, HIGH);
  digitalWrite(DYNAMO_RELAY, HIGH);  // Start OFF

  Serial.println();
  Serial.println("===== ESP32 STARTING =====");
  
  // ===== HARDWARE TEST: Dynamo blink 3 times =====
  Serial.println("--- Dynamo Hardware Test ---");
  for (int i = 0; i < 3; i++) {
    Serial.print("ON  (LOW) ");
    digitalWrite(DYNAMO_RELAY, LOW);
    delay(800);
    Serial.print("OFF (HIGH) ");
    digitalWrite(DYNAMO_RELAY, HIGH);
    delay(800);
  }
  Serial.println("--- Test finished. If dynamo did not move, check wiring/relay/power. ---");
  
  // Connect to WiFi
  Serial.print("Connecting to WiFi");
  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);
  
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < 20000) {
    delay(500);
    Serial.print(".");
  }
  Serial.println();
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi Connected!");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("Failed to connect. Check credentials.");
  }

  // Servo Gate - default CLOSED
  gateServo1.attach(GATE_SERVO1);
  gateServo2.attach(GATE_SERVO2);
  gateServo1.write(180);
  gateServo2.write(180);
  gateOpen = false;

  // Web Server Routes
  server.on("/", handleRoot);
  server.on("/sensor", handleSensor);
  server.on("/fan_on", fanON);
  server.on("/fan_off", fanOFF);
  server.on("/pump_on", pumpON);
  server.on("/pump_off", pumpOFF);
  server.on("/light_on", lightON);
  server.on("/light_off", lightOFF);
  server.on("/gate_open", gateOPEN);
  server.on("/gate_close", gateCLOSE);
  server.on("/dynamo_on", dynamoON);
  server.on("/dynamo_off", dynamoOFF);

  server.begin();
  Serial.println("Web Server Started");
  Serial.println("Devices: Fan, Pump, Light, Gate, Dynamo Feeder");
  Serial.println("===== Ready =====");
  Serial.println("Type 'd' in Serial Monitor to toggle dynamo manually.");
}

//==================== LOOP ====================
void loop() {
  server.handleClient();

  // Serial manual control (for testing)
  if (Serial.available()) {
    char c = Serial.read();
    if (c == 'd' || c == 'D') {
      if (dynamoState) {
        dynamoOFF();
      } else {
        dynamoON();
      }
      Serial.print("Dynamo now ");
      Serial.println(dynamoState ? "ON" : "OFF");
    }
  }
}