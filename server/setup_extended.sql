-- Create table for Devices
CREATE TABLE IF NOT EXISTS `devices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `mac_address` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) DEFAULT 'New Device',
  `ip_address` VARCHAR(50),
  `last_seen` DATETIME DEFAULT NULL
);

-- Create table for Pins associated with devices
-- Controls modes: MANUAL (Default), TIMER, DURATION
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

-- Optional: Logs for Telegram notifications
CREATE TABLE IF NOT EXISTS `logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_id` INT,
  `pin_number` INT,
  `action` TEXT,
  `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP
);
