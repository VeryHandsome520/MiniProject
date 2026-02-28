#include <Arduino.h>
#include <ArduinoJson.h>
#include <HTTPClient.h>
#include <WiFi.h>
#include <WiFiManager.h>

// ==================== ตั้งค่าระบบ ====================
String serverUrl = "http://smartbot-ai.com/3R/Earth/api_device.php";
const String API_KEY = "smartbot-default-key-2026";
const String DEVICE_TYPE = "BASIC"; // บอร์ดนี้ไม่มีเซนเซอร์

// ==================== ขา GPIO ====================
// LED แสดงสถานะ
const int PIN_STATUS_LED = 2; // LED บนบอร์ด

// ขา GPIO แบบเอาต์พุต (รายการที่ใช้ได้อย่างปลอดภัย)
const int kOutputPins[] = {4,  5,  12, 13, 14, 15, 16, 17, 18,
                           19, 21, 22, 23, 25, 26, 27, 32, 33};
const int kPinCount = sizeof(kOutputPins) / sizeof(kOutputPins[0]);

// ==================== ตัวแปรสถานะ ====================
String macAddress;
volatile bool isOnline = false;

// แคช Timer/Duration สำหรับ Edge Computing
struct PinState {
  int pin;
  String mode;  // "MANUAL", "TIMER", "DURATION"
  String state; // "ON", "OFF"
  unsigned long durationEndMillis;
  bool hasDuration;
};
PinState cachedPins[20];
int cachedPinCount = 0;

// ==================== LED แสดงสถานะ ====================
volatile int ledMode = 0; // 0=กะพริบเร็ว, 1=ติดค้าง, 2=กะพริบช้า

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

// ==================== Edge Computing ====================
void EdgeComputingTask(void *pvParameters) {
  while (1) {
    // ประมวลผล Timer/Duration ขณะออฟไลน์
    if (!isOnline) {
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

    vTaskDelay(1000 / portTICK_PERIOD_MS);
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

      // สร้าง JSON payload (ไม่มีข้อมูลเซนเซอร์สำหรับบอร์ด BASIC)
      StaticJsonDocument<256> doc;
      doc["mac"] = macAddress;
      doc["ip"] = WiFi.localIP().toString();
      doc["device_type"] = DEVICE_TYPE;

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

          if (!error && resDoc.containsKey("commands")) {
            JsonObject commands = resDoc["commands"];
            cachedPinCount = 0;

            for (int i = 0; i < kPinCount; i++) {
              int pin = kOutputPins[i];
              String pinStr = String(pin);

              if (commands.containsKey(pinStr)) {
                String cmd = commands[pinStr].as<String>();

                if (cmd == "ON")
                  digitalWrite(pin, HIGH);
                if (cmd == "OFF")
                  digitalWrite(pin, LOW);

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
      ledMode = 2;
    }

    // เชื่อมต่อ WiFi ใหม่ถ้าล้มเหลวหลายครั้ง
    if (failCount >= 3) {
      isOnline = false;
      ledMode = 2;
      Serial.println("[WiFi] Too many failures. Reconnecting WiFi...");
      WiFi.disconnect();
      WiFi.reconnect();
      failCount = 0;
      vTaskDelay(5000 / portTICK_PERIOD_MS);
    }

    vTaskDelay(15000 / portTICK_PERIOD_MS);
  }
}

// ==================== ตั้งค่าเริ่มต้น ====================
void setup() {
  Serial.begin(115200);
  Serial.println("\n=== SmartBot IoT - Hardware2 (BASIC) ===");

  // ตั้งค่าขาเอาต์พุตทั้งหมด
  for (int i = 0; i < kPinCount; i++) {
    pinMode(kOutputPins[i], OUTPUT);
    digitalWrite(kOutputPins[i], LOW);
  }

  // Task LED แสดงสถานะ
  ledMode = 0;
  xTaskCreate(StatusLedTask, "LedTask", 1024, NULL, 1, NULL);

  // WiFiManager - หน้าตั้งค่า WiFi
  WiFiManager wm;
  wm.setConfigPortalTimeout(180);
  wm.setAPStaticIPConfig(IPAddress(192, 168, 4, 1), IPAddress(192, 168, 4, 1),
                         IPAddress(255, 255, 255, 0));

  Serial.println("[WiFi] Starting WiFiManager...");
  if (!wm.autoConnect("SmartBot2-Setup")) {
    Serial.println("[WiFi] Failed to connect. Running in offline mode.");
    ledMode = 2;
  } else {
    Serial.println("[WiFi] Connected!");
    ledMode = 1;
  }

  macAddress = WiFi.macAddress();
  Serial.println("MAC: " + macAddress);

  // สร้าง FreeRTOS Tasks
  xTaskCreate(EdgeComputingTask, "EdgeTask", 4096, NULL, 1, NULL);
  xTaskCreate(NetworkTask, "NetTask", 8192, NULL, 1, NULL);
}

void loop() {
  // ว่าง - งานทั้งหมดทำใน FreeRTOS tasks
}