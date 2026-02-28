-- สร้างตารางอุปกรณ์
CREATE TABLE IF NOT EXISTS `devices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `mac_address` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) DEFAULT 'New Device',
  `ip_address` VARCHAR(50),
  `last_seen` DATETIME DEFAULT NULL,
  `device_type` ENUM('FULL','BASIC') DEFAULT 'BASIC',
  `api_key` VARCHAR(64) DEFAULT NULL,
  `light_auto_mode` ENUM('ON','OFF') DEFAULT 'OFF',
  `light_threshold` INT DEFAULT 500
);

-- สร้างตาราง Pin ที่เชื่อมกับอุปกรณ์
-- โหมดการควบคุม: MANUAL (ค่าเริ่มต้น), TIMER (ตั้งเวลา), DURATION (นับถอยหลัง)
CREATE TABLE IF NOT EXISTS `device_pins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_id` INT NOT NULL,
  `pin_number` INT NOT NULL,
  `mode` ENUM('MANUAL', 'TIMER', 'DURATION') DEFAULT 'MANUAL',
  `state` ENUM('ON', 'OFF') DEFAULT 'OFF',
  `timer_on` TIME DEFAULT NULL,
  `timer_off` TIME DEFAULT NULL,
  `duration_end` DATETIME DEFAULT NULL,
  `last_control` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_pin` (`device_id`, `pin_number`)
);

-- ตารางบันทึกการแจ้งเตือน Telegram (ไม่บังคับ)
CREATE TABLE IF NOT EXISTS `logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_id` INT,
  `pin_number` INT,
  `action` TEXT,
  `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ตารางข้อมูลเซนเซอร์สำหรับค่าแสงและแรงดันไฟฟ้า
CREATE TABLE IF NOT EXISTS `sensor_data` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_id` INT NOT NULL,
  `light_level` INT DEFAULT NULL,
  `voltage` FLOAT DEFAULT NULL,
  `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
  INDEX `idx_device_time` (`device_id`, `timestamp`)
);

-- คำสั่ง Migration สำหรับฐานข้อมูลที่มีอยู่แล้ว (รันด้วยตนเองถ้าตารางมีอยู่แล้ว):
-- ALTER TABLE devices ADD COLUMN device_type ENUM('FULL','BASIC') DEFAULT 'BASIC';
-- ALTER TABLE devices ADD COLUMN api_key VARCHAR(64) DEFAULT NULL;
-- ALTER TABLE devices ADD COLUMN light_auto_mode ENUM('ON','OFF') DEFAULT 'OFF';
-- ALTER TABLE devices ADD COLUMN light_threshold INT DEFAULT 500;
