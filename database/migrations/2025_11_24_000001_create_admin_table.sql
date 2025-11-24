-- Migration: Create admin table
USE `inventory_system`;

INSERT INTO `admin` (`staff_id`, `name`, `email`, `password`)
VALUES ('ADM001', 'Default Admin', 'admin@example.com', 'admin123');

