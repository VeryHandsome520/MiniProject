#include <Arduino.h>
#include <ArduinoJson.h>
#include <HTTPClient.h>
#include <Preferences.h>
#include <WiFi.h>
#include <WiFiManager.h>


// ==================== ตั้งค่าระบบ ====================
String serverUrl = "http://smartbot-ai.com/3R/Earth/api_device.php";
const String API_KEY = "smartbot-default-key-2026";
const String DEVICE_TYPE = "FULL"; // บอร์ดนี้มีเซนเซอร์
const char* ssid = "YOUR_SSID";   // ใส่ SSID ของคุณ
const char* password = "YOUR_PASSWORD"; // ใส่รหัสผ่าน WiFi ของคุณ

// ==================== ขา GPIO ====================
// ขาเซนเซอร์ (อินพุต - ADC)
const int PIN_LDR = 34;     // เซนเซอร์แสง (LDR)
const int PIN_VOLTAGE = 35; // เซนเซอร์แรงดันไฟฟ้า MAX471

// LED แสดงสถานะ
const int PIN_STATUS_LED = 2; // LED บนบอร์ด

// ขา GPIO แบบเอาต์พุต (รายการที่ใช้ได้อย่างปลอดภัย)
const int kOutputPins[] = {4,  5,  12, 13, 14, 15, 16, 17, 18,
                           19, 21, 22, 23, 25, 26, 27, 32, 33};
const int kPinCount = sizeof(kOutputPins) / sizeof(kOutputPins[0]);

// ==================== ตัวแปรสถานะ ====================
String macAddress;
Preferences preferences;

// ค่าเซนเซอร์
volatile int lightLevel = 0;
volatile float voltage = 0.0;

// สถานะ Edge Computing (แคชจากเซิร์ฟเวอร์)
volatile bool lightAutoMode = false;
volatile int lightThreshold = 500;
volatile bool isOnline = false;

// แคช Timer/Duration สำหรับ Edge Computing
struct PinState {
  int pin;
  String mode;  // "MANUAL", "TIMER", "DURATION"
  String state; // "ON", "OFF"
  String timerOn;
  String timerOff;
  unsigned long durationEndMillis; // นับเวลาด้วย millis สำหรับโหมดออฟไลน์
  bool hasDuration;
};
PinState cachedPins[20];
int cachedPinCount = 0;

// ==================== LED แสดงสถานะ ====================
volatile int ledMode =
    0; // 0=กะพริบเร็ว (กำลังเชื่อมต่อ), 1=ติดค้าง (เชื่อมต่อแล้ว), 2=กะพริบช้า (ออฟไลน์)

void StatusLedTask(void *pvParameters) {
  pinMode(PIN_STATUS_LED, OUTPUT);
  while (1) {
    switch (ledMode) {
    case 0: // กะพริบเร็ว - กำลังเชื่อมต่อ WiFi
      digitalWrite(PIN_STATUS_LED, HIGH);
      vTaskDelay(100 / portTICK_PERIOD_MS);
      digitalWrite(PIN_STATUS_LED, LOW);
      vTaskDelay(100 / portTICK_PERIOD_MS);
      break;
    case 1: // ติดค้าง - เชื่อมต่อสำเร็จ
      digitalWrite(PIN_STATUS_LED, HIGH);
      vTaskDelay(500 / portTICK_PERIOD_MS);
      break;
    case 2: // กะพริบช้า - ออฟไลน์/โหมด Edge
      digitalWrite(PIN_STATUS_LED, HIGH);
      vTaskDelay(1000 / portTICK_PERIOD_MS);
      digitalWrite(PIN_STATUS_LED, LOW);
      vTaskDelay(1000 / portTICK_PERIOD_MS);
      break;
    }
  }
}

// ==================== อ่านค่าเซนเซอร์ ====================
void SensorTask(void *pvParameters) {
  while (1) {
    // อ่านค่า LDR (0-4095, ยิ่งสูง = ยิ่งสว่าง)
    lightLevel = analogRead(PIN_LDR);

    // อ่านค่าแรงดันไฟฟ้าจาก MAX471
    // MAX471: แรงดันขาออกแปรผันตามแรงดันที่วัดได้
    // ADC: 0-4095 แมปเป็น 0-3.3V, คูณตัวคูณแปลงเป็นแรงดันจริง
    int rawVoltage = analogRead(PIN_VOLTAGE);
    voltage = (rawVoltage / 4095.0) * 3.3 *
              5.0; // ตัวคูณสำหรับ MAX471 (ปรับได้ตามต้องการ)

    Serial.printf("[Sensor] Light: %d, Voltage: %.2fV\n", lightLevel, voltage);

    vTaskDelay(5000 / portTICK_PERIOD_MS); // อ่านค่าทุก 5 วินาที
  }
}

// ==================== Edge Computing ====================
void EdgeComputingTask(void *pvParameters) {
  while (1) {
    // --- โหมดไฟอัตโนมัติ (ทำงานได้แม้ออฟไลน์) ---
    if (lightAutoMode) {
      // ถ้ามืด (ค่าแสงต่ำกว่าเกณฑ์) -> เปิดขาเอาต์พุตทั้งหมด
      // ถ้าสว่าง -> ปิด
      bool shouldBeOn = (lightLevel < lightThreshold);
      for (int i = 0; i < kPinCount; i++) {
        // ในโหมดไฟอัตโนมัติ จะควบคุมขาเอาต์พุตทั้งหมด
        digitalWrite(kOutputPins[i], shouldBeOn ? HIGH : LOW);
      }
      if (!isOnline) {
        Serial.printf("[Edge] Auto-Light: light=%d, threshold=%d -> %s\n",
                      lightLevel, lightThreshold, shouldBeOn ? "ON" : "OFF");
      }
    }

    // --- Timer/Duration ออฟไลน์ (Edge Computing) ---
    if (!isOnline && !lightAutoMode) {
      // ใช้ค่าแคชของ Pin สำหรับลอจิกตั้งเวลา
      unsigned long nowMillis = millis();

      for (int i = 0; i < cachedPinCount; i++) {
        PinState &ps = cachedPins[i];

        if (ps.mode == "DURATION" && ps.hasDuration) {
          if (nowMillis >= ps.durationEndMillis) {
            // หมดเวลา Duration
            digitalWrite(ps.pin, LOW);
            ps.state = "OFF";
            ps.mode = "MANUAL";
            ps.hasDuration = false;
            Serial.printf("[Edge] Duration ended for pin %d\n", ps.pin);
          } else {
            digitalWrite(ps.pin, HIGH);
          }
        }
      }
    }

    vTaskDelay(1000 / portTICK_PERIOD_MS); // ตรวจสอบทุก 1 วินาที
  }
}

// ==================== เครือข่าย ====================
void NetworkTask(void *pvParameters) {
  int failCount = 0;

  while (1) {
    if (WiFi.status() == WL_CONNECTED) {
      HTTPClient http;

      Serial.print("[HTTP] Connecting... Heap: ");
      Serial.println(ESP.getFreeHeap());

      http.setReuse(false);
      http.begin(serverUrl);
      http.addHeader("Content-Type", "application/json");
      http.addHeader("X-API-Key", API_KEY);
      http.setUserAgent("ESP32-SmartBot/2.0");
      http.setConnectTimeout(10000);
      http.setTimeout(10000);

      // สร้าง JSON payload พร้อมข้อมูลเซนเซอร์
      StaticJsonDocument<256> doc;
      doc["mac"] = macAddress;
      doc["ip"] = WiFi.localIP().toString();
      doc["device_type"] = DEVICE_TYPE;
      doc["light"] = lightLevel;
      doc["voltage"] = voltage;

      String jsonPayload;
      serializeJson(doc, jsonPayload);

      int httpCode = http.POST(jsonPayload);

      if (httpCode > 0) {
        failCount = 0;
        isOnline = true;
        ledMode = 1; // LED ติดค้าง

        Serial.print("[HTTP] Code: ");
        Serial.println(httpCode);

        if (httpCode == HTTP_CODE_OK ||
            httpCode == HTTP_CODE_MOVED_PERMANENTLY) {
          String response = http.getString();
          Serial.println("[HTTP] Response: " + response);

          // แปลง JSON Response
          StaticJsonDocument<1024> resDoc;
          DeserializationError error = deserializeJson(resDoc, response);

          if (!error) {
            // อัปเดตโหมดไฟอัตโนมัติจากเซิร์ฟเวอร์
            if (resDoc.containsKey("light_auto_mode")) {
              lightAutoMode =
                  (String(resDoc["light_auto_mode"].as<const char*>()) == "ON");
            }
            if (resDoc.containsKey("light_threshold")) {
              lightThreshold = resDoc["light_threshold"].as<int>();
            }

            // ประมวลผลคำสั่ง Pin
            if (resDoc.containsKey("commands")) {
              JsonObject commands = resDoc["commands"];
              cachedPinCount = 0;

              for (int i = 0; i < kPinCount; i++) {
                int pin = kOutputPins[i];
                String pinStr = String(pin);

                if (commands.containsKey(pinStr)) {
                  String cmd = commands[pinStr].as<String>();

                  // ควบคุม GPIO เฉพาะเมื่อไม่ได้อยู่ในโหมดไฟอัตโนมัติ
                  if (!lightAutoMode) {
                    if (cmd == "ON")
                      digitalWrite(pin, HIGH);
                    if (cmd == "OFF")
                      digitalWrite(pin, LOW);
                  }

                  // แคชไว้สำหรับ Edge Computing
                  if (cachedPinCount < 20) {
                    cachedPins[cachedPinCount].pin = pin;
                    cachedPins[cachedPinCount].state = cmd;
                    cachedPinCount++;
                  }
                }
              }
            }
          }
        }
      } else {
        failCount++;
        Serial.print("[HTTP] Failed. Count: ");
        Serial.print(failCount);
        Serial.print(" Error: ");
        Serial.println(http.errorToString(httpCode));
      }
      http.end();
    } else {
      failCount = 10;
      isOnline = false;
      ledMode = 2; // กะพริบช้า - ออฟไลน์
    }

    // เชื่อมต่อ WiFi ใหม่ถ้าล้มเหลวหลายครั้ง
    if (failCount >= 3) {
      isOnline = false;
      ledMode = 2; // กะพริบช้า
      Serial.println("[WiFi] Too many failures. Reconnecting WiFi...");
      WiFi.disconnect();
      WiFi.reconnect();
      failCount = 0;
      vTaskDelay(5000 / portTICK_PERIOD_MS);
    }

    vTaskDelay(15000 / portTICK_PERIOD_MS); // ส่งข้อมูลทุก 15 วินาที
  }
}

// ==================== ตั้งค่าเริ่มต้น ====================
void setup() {
  Serial.begin(115200);
  Serial.println("\n=== SmartBot IoT - Hardware (FULL) ===");

  // ตั้งค่าขาเอาต์พุตทั้งหมด
  for (int i = 0; i < kPinCount; i++) {
    pinMode(kOutputPins[i], OUTPUT);
    digitalWrite(kOutputPins[i], LOW);
  }

  // Task LED แสดงสถานะ (เริ่มทันทีด้วยกะพริบเร็ว)
  ledMode = 0; // กะพริบเร็วระหว่างเชื่อมต่อ
  xTaskCreate(StatusLedTask, "LedTask", 1024, NULL, 1, NULL);

  // Connect to Wi‑Fi using provided credentials
  WiFi.begin(ssid, password);
  Serial.print("[WiFi] Connecting...");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print('.');
  }
  Serial.println();
  Serial.print("[WiFi] Connected! IP: ");
  Serial.println(WiFi.localIP());
  ledMode = 1; // ติดค้าง - เชื่อมต่อสำเร็จ

  macAddress = WiFi.macAddress();
  Serial.println("MAC: " + macAddress);

  // สร้าง FreeRTOS Tasks
  xTaskCreate(SensorTask, "SensorTask", 2048, NULL, 1, NULL);
  xTaskCreate(EdgeComputingTask, "EdgeTask", 4096, NULL, 1, NULL);
  xTaskCreate(NetworkTask, "NetTask", 8192, NULL, 1, NULL);
}

void loop() {
  // ว่าง - งานทั้งหมดทำใน FreeRTOS tasks
}