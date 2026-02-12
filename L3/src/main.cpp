#ifndef LED_CONTROL_H
#define LED_CONTROL_H

#include <Arduino.h>
#include <ArduinoJson.h> // Make sure to add ArduinoJson to lib_deps if not present (User might need to add it)
#include <HTTPClient.h>  // Required for HTTPClient
#include <WiFi.h>

// Config
const char *ssid = "VeryHandsome";
const char *password = "aaaaaaaa";
String serverUrl = "http://smartbot-ai.com/3R/Earth/api_device.php";

String macAddress;

// Output-capable GPIOs (Safe list, excluding input-only 34-39 and flash pins
// 6-11)
const int kOutputPins[] = {2,  4,  5,  12, 13, 14, 15, 16, 17, 18,
                           19, 21, 22, 23, 25, 26, 27, 32, 33};
const int kPinCount = sizeof(kOutputPins) / sizeof(kOutputPins[0]);

void NetworkTask(void *pvParameters) {
  int failCount = 0; // Track consecutive failures

  while (1) {
    if (WiFi.status() == WL_CONNECTED) {
      HTTPClient http;

      Serial.print("[HTTP] Connecting... Heap: ");
      Serial.println(ESP.getFreeHeap()); // Debug Memory

      // Disable 'reuse' to force fresh connection every time (Stability)
      http.setReuse(false);
      http.begin(serverUrl);
      http.addHeader("Content-Type", "application/json");
      http.setUserAgent("ESP32-SmartBot/1.0");
      http.setConnectTimeout(10000);
      http.setTimeout(10000);

      String jsonPayload = "{\"mac\":\"" + macAddress + "\", \"ip\":\"" +
                           WiFi.localIP().toString() + "\"}";

      int httpCode = http.POST(jsonPayload);

      if (httpCode > 0) {
        // Success or Server Error (but connected)
        failCount = 0; // Reset failure counter
        Serial.print("[HTTP] Code: ");
        Serial.println(httpCode);

        if (httpCode == HTTP_CODE_OK ||
            httpCode == HTTP_CODE_MOVED_PERMANENTLY) {
          String response = http.getString();
          if (response.indexOf("commands") > 0) {
            for (int i = 0; i < kPinCount; i++) {
              int pin = kOutputPins[i];
              String onCommand = "\"" + String(pin) + "\":\"ON\"";
              String offCommand = "\"" + String(pin) + "\":\"OFF\"";

              if (response.indexOf(onCommand) > 0)
                digitalWrite(pin, HIGH);
              if (response.indexOf(offCommand) > 0)
                digitalWrite(pin, LOW);
            }
          }
        }
      } else {
        // Connection Failed
        failCount++;
        Serial.print("[HTTP] Failed. Count: ");
        Serial.print(failCount);
        Serial.print(" Error: ");
        Serial.println(http.errorToString(httpCode));
      }
      http.end();
    } else {
      // WiFi physically disconnected
      failCount = 10; // Force reconnect logic next
    }

    // Logic to handle persistent failures
    if (failCount >= 3) {
      Serial.println("[WiFi] Too many failures. Reconnecting WiFi...");
      WiFi.disconnect();
      WiFi.reconnect();
      failCount = 0;
      vTaskDelay(5000 / portTICK_PERIOD_MS); // Wait longer for WiFi
    }

    vTaskDelay(
        15000 /
        portTICK_PERIOD_MS); // Poll every 15 seconds (Avoid Rate Limiting)
  }
}

void setup() {
  Serial.begin(115200);

  // Setup all Output Pins
  for (int i = 0; i < kPinCount; i++) {
    pinMode(kOutputPins[i], OUTPUT);
    digitalWrite(kOutputPins[i], LOW); // Default OFF
  }

  // Connect WiFi
  WiFi.begin(ssid, password);
  unsigned long lastPrintTime = 0;
  while (WiFi.status() != WL_CONNECTED) {
    if (millis() - lastPrintTime >= 500) {
      Serial.print(".");
      lastPrintTime = millis();
    }
  }
  Serial.println("\nConnected to WiFi");
  macAddress = WiFi.macAddress();
  Serial.println("MAC: " + macAddress);

  // Indicate Connection Success (Blink D2 3 times using millis)
  for (int i = 0; i < 3; i++) {
    digitalWrite(2, HIGH);
    unsigned long start = millis();
    while (millis() - start < 100)
      ; // Busy wait using millis

    digitalWrite(2, LOW);
    start = millis();
    while (millis() - start < 100)
      ; // Busy wait using millis
  }

  // Create Tasks
  xTaskCreate(NetworkTask, "NetTask", 4096, NULL, 1, NULL);
}

void loop() {
  // Empty
}

#endif