-- Database updates for Instruments Calibration Master Management
-- Execute this script to add the instruments table and related functionality

-- Create instruments table
CREATE TABLE IF NOT EXISTS `instruments` (
  `instrument_id` int(11) NOT NULL AUTO_INCREMENT,
  `instrument_code` varchar(50) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `instrument_name` varchar(255) NOT NULL,
  `instrument_type` enum('Air Capture Hood','Anemometer','Photo Meter','Particle Counter','Other') NOT NULL DEFAULT 'Other',
  `vendor_id` int(11) DEFAULT NULL,
  `manufacturer` varchar(255) DEFAULT NULL,
  `model_number` varchar(100) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `last_calibration_date` date DEFAULT NULL,
  `next_calibration_date` date DEFAULT NULL,
  `calibration_frequency_months` int(11) DEFAULT 12,
  `calibration_status` enum('Valid','Due Soon','Expired','Not Calibrated') DEFAULT 'Not Calibrated',
  `location` varchar(255) DEFAULT NULL,
  `responsible_person` varchar(255) DEFAULT NULL,
  `notes` text,
  `instrument_status` enum('Active','Inactive','Under Maintenance','Decommissioned') DEFAULT 'Active',
  `created_by` int(11) DEFAULT NULL,
  `created_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int(11) DEFAULT NULL,
  `modified_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `unit_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`instrument_id`),
  UNIQUE KEY `instrument_code` (`instrument_code`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_instrument_type` (`instrument_type`),
  KEY `idx_calibration_status` (`calibration_status`),
  KEY `idx_instrument_status` (`instrument_status`),
  KEY `idx_next_calibration_date` (`next_calibration_date`),
  KEY `idx_unit_id` (`unit_id`),
  CONSTRAINT `fk_instruments_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_instruments_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_instruments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_instruments_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample data for testing
INSERT INTO `instruments` (`instrument_code`, `serial_number`, `instrument_name`, `instrument_type`, `vendor_id`, `manufacturer`, `model_number`, `purchase_date`, `last_calibration_date`, `next_calibration_date`, `calibration_frequency_months`, `calibration_status`, `location`, `responsible_person`, `notes`, `instrument_status`, `created_by`, `unit_id`) VALUES
('INST001', 'ACH001', 'Air Capture Hood Model X1', 'Air Capture Hood', 1, 'TSI Inc', 'EBT731', '2023-01-15', '2024-06-15', '2025-06-15', 12, 'Valid', 'Lab Room 101', 'John Smith', 'Primary air capture hood for validation', 'Active', 1, 1),
('INST002', 'ANM002', 'Digital Anemometer Pro', 'Anemometer', 2, 'Extech', 'AN100', '2023-03-20', '2024-03-20', '2025-03-20', 12, 'Valid', 'Lab Room 102', 'Jane Doe', 'High precision anemometer', 'Active', 1, 1),
('INST003', 'PM003', 'Photometer Light Meter', 'Photo Meter', 1, 'Konica Minolta', 'T-10A', '2022-12-10', '2023-12-10', '2024-12-10', 12, 'Due Soon', 'Lab Room 103', 'Bob Johnson', 'Light measurement device', 'Active', 1, 1),
('INST004', 'PC004', 'Particle Counter Advanced', 'Particle Counter', 3, 'Fluke', 'PC500', '2021-08-05', '2023-08-05', '2024-08-05', 12, 'Expired', 'Clean Room A', 'Alice Brown', 'Particle counting for clean room validation', 'Under Maintenance', 1, 1),
('INST005', 'ACH005', 'Backup Air Hood', 'Air Capture Hood', 2, 'TSI Inc', 'EBT732', '2024-01-10', '2024-08-10', '2025-08-10', 12, 'Valid', 'Storage Room', 'Mike Wilson', 'Backup unit for primary hood', 'Active', 1, 1);

-- Create trigger to automatically update calibration_status based on next_calibration_date
DELIMITER $$
CREATE TRIGGER `update_calibration_status` BEFORE UPDATE ON `instruments` FOR EACH ROW
BEGIN
    DECLARE days_until_calibration INT;
    
    IF NEW.next_calibration_date IS NOT NULL THEN
        SET days_until_calibration = DATEDIFF(NEW.next_calibration_date, CURDATE());
        
        IF days_until_calibration < 0 THEN
            SET NEW.calibration_status = 'Expired';
        ELSEIF days_until_calibration <= 30 THEN
            SET NEW.calibration_status = 'Due Soon';
        ELSE
            SET NEW.calibration_status = 'Valid';
        END IF;
    ELSE
        SET NEW.calibration_status = 'Not Calibrated';
    END IF;
END$$
DELIMITER ;

-- Create trigger for INSERT operations
DELIMITER $$
CREATE TRIGGER `update_calibration_status_insert` BEFORE INSERT ON `instruments` FOR EACH ROW
BEGIN
    DECLARE days_until_calibration INT;
    
    IF NEW.next_calibration_date IS NOT NULL THEN
        SET days_until_calibration = DATEDIFF(NEW.next_calibration_date, CURDATE());
        
        IF days_until_calibration < 0 THEN
            SET NEW.calibration_status = 'Expired';
        ELSEIF days_until_calibration <= 30 THEN
            SET NEW.calibration_status = 'Due Soon';
        ELSE
            SET NEW.calibration_status = 'Valid';
        END IF;
    ELSE
        SET NEW.calibration_status = 'Not Calibrated';
    END IF;
END$$
DELIMITER ;