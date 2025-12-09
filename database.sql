-- Create database (run if not exists)
CREATE DATABASE IF NOT EXISTS library_db;
USE library_db;

-- Users
CREATE TABLE IF NOT EXISTS users (
	id INT AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(100) NOT NULL,
	email VARCHAR(120) NOT NULL UNIQUE,
	phone_country_code VARCHAR(6) NOT NULL DEFAULT '+91',
	phone VARCHAR(20) NOT NULL,
	password VARCHAR(255) NOT NULL,
	role ENUM('admin','teacher','student') NOT NULL,
	status ENUM('pending','active','blacklisted') NOT NULL DEFAULT 'pending',
	email_verified TINYINT(1) NOT NULL DEFAULT 0,
	phone_verified TINYINT(1) NOT NULL DEFAULT 0,
	email_otp VARCHAR(255) DEFAULT NULL,
	email_otp_expires_at DATETIME DEFAULT NULL,
	phone_otp VARCHAR(255) DEFAULT NULL,
	phone_otp_expires_at DATETIME DEFAULT NULL,
	otp_attempts TINYINT DEFAULT 0,
	otp_last_sent_at DATETIME DEFAULT NULL,
	fingerprint_token VARCHAR(255) DEFAULT NULL,
	fingerprint_registered_at DATETIME DEFAULT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Departments
CREATE TABLE IF NOT EXISTS departments (
	id INT AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(100) NOT NULL UNIQUE
);

-- Book Categories
CREATE TABLE IF NOT EXISTS book_categories (
	id INT AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(120) NOT NULL UNIQUE
);

-- Books
CREATE TABLE IF NOT EXISTS books (
	id INT AUTO_INCREMENT PRIMARY KEY,
	title VARCHAR(200) NOT NULL,
	author VARCHAR(150) NOT NULL,
	category VARCHAR(120) DEFAULT NULL,
	department VARCHAR(100) DEFAULT NULL,
	isbn VARCHAR(30) DEFAULT NULL,
	cover_image VARCHAR(255) DEFAULT NULL,
	description TEXT DEFAULT NULL,
	tags VARCHAR(255) DEFAULT NULL,
	quantity INT NOT NULL DEFAULT 1,
	available_copies INT NOT NULL DEFAULT 1,
	status ENUM('available','issued') NOT NULL DEFAULT 'available',
	created_by INT DEFAULT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Book reviews
CREATE TABLE IF NOT EXISTS book_reviews (
	id INT AUTO_INCREMENT PRIMARY KEY,
	book_id INT NOT NULL,
	user_id INT NOT NULL,
	rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
	review TEXT,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uniq_book_user (book_id, user_id),
	FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Issued Books
CREATE TABLE IF NOT EXISTS issued_books (
	id INT AUTO_INCREMENT PRIMARY KEY,
	book_id INT NOT NULL,
	user_id INT NOT NULL,
	issue_date DATE NOT NULL,
	due_date DATE DEFAULT NULL,
	return_date DATE DEFAULT NULL,
	fine DECIMAL(10,2) DEFAULT 0,
	fine_status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
	fine_rate DECIMAL(10,2) NOT NULL DEFAULT 5.00,
	notified_due TINYINT(1) NOT NULL DEFAULT 0,
	notified_overdue TINYINT(1) NOT NULL DEFAULT 0,
	FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tracking email notifications for due/overdue reminders
CREATE TABLE IF NOT EXISTS due_notifications (
	id INT AUTO_INCREMENT PRIMARY KEY,
	issued_book_id INT NOT NULL,
	notification_type ENUM('due','overdue') NOT NULL,
	notified_on DATE NOT NULL,
	sent_via VARCHAR(50) DEFAULT 'email',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uniq_notification (issued_book_id, notification_type, notified_on),
	FOREIGN KEY (issued_book_id) REFERENCES issued_books(id) ON DELETE CASCADE
);

-- Book Requests
CREATE TABLE IF NOT EXISTS book_requests (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	book_id INT DEFAULT NULL,
	title VARCHAR(200) NOT NULL,
	author VARCHAR(150) DEFAULT NULL,
	department VARCHAR(100) DEFAULT NULL,
	status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE SET NULL
);

-- Fingerprints
CREATE TABLE IF NOT EXISTS user_fingerprints (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	fingerprint_hash VARCHAR(255) NOT NULL UNIQUE,
	device_label VARCHAR(120) DEFAULT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notices
CREATE TABLE IF NOT EXISTS notices (
	id INT AUTO_INCREMENT PRIMARY KEY,
	title VARCHAR(200) NOT NULL,
	description TEXT,
	organizer VARCHAR(150) DEFAULT NULL,
	event_date DATE NOT NULL,
	created_by INT DEFAULT NULL,
	approved_by INT DEFAULT NULL,
	status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
	FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
	FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Reports (optional, store metadata)
CREATE TABLE IF NOT EXISTS reports (
	id INT AUTO_INCREMENT PRIMARY KEY,
	type VARCHAR(100) NOT NULL,
	generated_on DATETIME NOT NULL,
	details TEXT
);

-- Seed Departments
INSERT IGNORE INTO departments (id, name) VALUES
(1, 'Computer Science'),
(2, 'Electronics'),
(3, 'Mechanical'),
(4, 'Civil'),
(5, 'Mathematics');

-- Seed Categories
INSERT IGNORE INTO book_categories (id, name) VALUES
(1, 'Computer Science'),
(2, 'Electronics'),
(3, 'Mechanical'),
(4, 'Civil'),
(5, 'Mathematics'),
(6, 'Fiction'),
(7, 'Non Fiction'),
(8, 'Artificial Intelligence'),
(9, 'Data Science'),
(10, 'Physics'),
(11, 'Chemistry'),
(12, 'Biology'),
(13, 'Management'),
(14, 'Economics'),
(15, 'History'),
(16, 'Philosophy'),
(17, 'Psychology');

-- Database Migration for Library Management System Upgrade
-- Run this script to add new features

USE library_db;

-- 1. Add phone column to users table if not exists
ALTER TABLE `users` 
    ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) DEFAULT NULL AFTER `email`;

-- 2. Create trusted_devices table (alternative name for user_fingerprints, but we'll use user_fingerprints)
-- The user_fingerprints table already exists, but let's ensure it has all needed columns
ALTER TABLE `user_fingerprints`
    ADD COLUMN IF NOT EXISTS `device_label` VARCHAR(120) DEFAULT NULL AFTER `fingerprint_hash`,
    ADD COLUMN IF NOT EXISTS `last_used_at` DATETIME DEFAULT NULL AFTER `created_at`,
    ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `last_used_at`;

-- 3. Add subject and teacher_name columns to uploaded_notes for better filtering
ALTER TABLE `uploaded_notes`
    ADD COLUMN IF NOT EXISTS `subject` VARCHAR(200) DEFAULT NULL AFTER `description`,
    ADD COLUMN IF NOT EXISTS `teacher_name` VARCHAR(150) DEFAULT NULL AFTER `subject`;

-- 4. Add index for better performance on uploaded_notes
CREATE INDEX IF NOT EXISTS `idx_uploaded_notes_status` ON `uploaded_notes`(`status`);
CREATE INDEX IF NOT EXISTS `idx_uploaded_notes_subject` ON `uploaded_notes`(`subject`);
CREATE INDEX IF NOT EXISTS `idx_uploaded_notes_teacher` ON `uploaded_notes`(`teacher_name`);

-- 5. Ensure uploaded_notes has uploader_type (can be derived from user role, but adding for convenience)
ALTER TABLE `uploaded_notes`
    ADD COLUMN IF NOT EXISTS `uploader_type` ENUM('student','teacher','admin') DEFAULT NULL AFTER `teacher_name`;

-- Note: After running this migration:
-- 1. Phone field will be available in registration
-- 2. Trusted devices can be managed with device labels
-- 3. Notes can be filtered by subject and teacher name
-- 4. Better indexing for performance

-- Issue a sample book
INSERT IGNORE INTO issued_books (id, book_id, user_id, issue_date, due_date, return_date, fine, fine_status) VALUES
(1, 1, 3, DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_SUB(CURDATE(), INTERVAL 0 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), 10.00, 'paid'),
(2, 1, 2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(DATE_SUB(CURDATE(), INTERVAL 3 DAY), INTERVAL 14 DAY), NULL, 0.00, 'unpaid');

-- Update book stock to match issued copies
UPDATE books SET available_copies = GREATEST(quantity - (SELECT COUNT(*) FROM issued_books ib WHERE ib.book_id = books.id AND ib.return_date IS NULL), 0) WHERE id IN (1,2,3,4,5);
UPDATE books SET status = CASE WHEN available_copies = 0 THEN 'issued' ELSE 'available' END;

-- Sample Notices
INSERT IGNORE INTO notices (id, title, description, organizer, event_date, created_by, approved_by, status) VALUES
(1, 'Research Seminar on AI', 'Join us for an insightful seminar on AI trends.', 'CSE Dept', DATE_ADD(CURDATE(), INTERVAL 7 DAY), 2, 1, 'approved'),
(2, 'Workshop on IoT', 'Hands-on IoT workshop for beginners.', 'ECE Dept', DATE_ADD(CURDATE(), INTERVAL 14 DAY), 2, NULL, 'pending');

-- Database Migrations for New Features
-- Run this script to add tables for new features

-- 1. Table for uploaded notes/PDFs
CREATE TABLE IF NOT EXISTS `uploaded_notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_type` ENUM('image', 'pdf') NOT NULL,
    `file_size` INT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_status` (`user_id`, `status`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table for book suggestions
CREATE TABLE IF NOT EXISTS `book_suggestions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `author` VARCHAR(200) NOT NULL,
    `note` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_status` (`user_id`, `status`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Ensure due_date column exists in issued_books (if not already present)
ALTER TABLE `issued_books` 
    ADD COLUMN IF NOT EXISTS `due_date` DATE DEFAULT NULL AFTER `issue_date`;

-- Note: The 'due_date' column should already exist from previous migrations,
-- but this ensures it's there if the migration wasn't run before.

-- Migration Script: Remove Phone OTP and SMS Functionality
-- This script removes all phone-related OTP columns and SMS functionality from the database
-- Run this script after backing up your database

-- Step 1: Drop phone OTP related columns from users table
ALTER TABLE `users` 
    DROP COLUMN IF EXISTS `phone`,
    DROP COLUMN IF EXISTS `phone_country_code`,
    DROP COLUMN IF EXISTS `phone_verified`,
    DROP COLUMN IF EXISTS `phone_otp`,
    DROP COLUMN IF EXISTS `phone_otp_expires_at`;

-- Step 2: Verify the changes (optional - uncomment to check)
-- DESCRIBE `users`;

-- Note: After running this migration:
-- 1. The system will only use email OTP verification
-- 2. All SMS/Twilio functionality should be removed from PHP code
-- 3. Users will only need to verify their email address
-- 4. Existing users' phone data will be permanently deleted
-- 
-- IMPORTANT: Backup your database before running this script!


-- Seed Users (password = "password" for all below)
SET @hash := '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
INSERT IGNORE INTO users (id, name, email, phone_country_code, phone, password, role, status, email_verified, phone_verified) VALUES
(1, 'Admin User', 'admin123@gmail.com', '+91', '9999999999', @hash, 'admin', 'active', 1, 1),
(2, 'Teacher One', 'teacher1@gmail.com', '+91', '8888888888', @hash, 'teacher', 'active', 1, 1),
(3, 'Student One', 'student1@gmail.com', '+91', '7777777777', @hash, 'student', 'active', 1, 1);

-- Seed Books
INSERT IGNORE INTO books 
(id, title, author, category, department, isbn, quantity, available_copies, status, created_by, description)
VALUES
(1,'Advanced Operating Systems','Rohan Malhotra','Computer Science','Computer Science','9784125637811',7,6,'available',1,'Concepts of OS kernels, scheduling, concurrency and memory.'),
(2,'Data Structures in Depth','Anita Kanwar','Computer Science','Computer Science','9784125637812',5,5,'available',1,'Detailed explanation of trees, graphs and algorithms.'),
(3,'Machine Learning Essentials','Sujata Kulkarni','Artificial Intelligence','Computer Science','9784125637813',8,7,'available',1,'Introduction to ML models with real-world examples.'),
(4,'Deep Learning Foundations','Kunal Mehra','Artificial Intelligence','Computer Science','9784125637814',4,4,'available',1,'Neural networks, CNNs, RNNs and training techniques.'),
(5,'Database Design & Modeling','Sagar Jaiswal','Computer Science','Computer Science','9784125637815',6,6,'available',1,'How to design scalable relational database systems.'),
(6,'Compiler Design Basics','Vishal Soni','Computer Science','Computer Science','9784125637816',3,2,'issued',1,'Lexing, parsing, code generation and optimization.'),
(7,'Cloud Computing Architecture','Anu George','Computer Science','Computer Science','9784125637817',9,9,'available',1,'Cloud models, virtualization and distributed systems.'),
(8,'Cybersecurity Principles','Vikram Rathod','Computer Science','Computer Science','9784125637818',5,5,'available',1,'Covers security attacks, cryptography and defenses.'),
(9,'Ethical Hacking Beginner Guide','Neha Sharma','Computer Science','Computer Science','9784125637819',4,3,'issued',1,'Hands-on approach to penetration testing techniques.'),
(10,'Computer Networks Mastery','Arvind Jain','Computer Science','Computer Science','9784125637820',8,8,'available',1,'Networking fundamentals, TCP/IP and routing.'),
(11,'Analog Electronics Fundamentals','S. N. Deshmukh','Electronics','Electronics','9785126738121',6,6,'available',1,'Transistors, amplifiers and analog circuits.'),
(12,'Digital Electronics Toolkit','Priya Thakur','Electronics','Electronics','9785126738122',9,8,'available',1,'Logic gates, counters and sequential circuits.'),
(13,'VLSI System Design','Dr. Ajay Vardhan','Electronics','Electronics','9785126738123',4,3,'issued',1,'Basics of Very Large Scale Integration.'),
(14,'Signals & Systems Explained','Meera Pillai','Electronics','Electronics','9785126738124',3,3,'available',1,'Signal transformations and system responses.'),
(15,'Embedded Systems Applications','Rajat Bansal','Electronics','Electronics','9785126738125',7,7,'available',1,'Microcontroller-based system design.'),
(16,'Power Electronics Essentials','Shailesh Pandey','Electronics','Electronics','9785126738126',4,4,'available',1,'Converters, inverters and power control.'),
(17,'Communication Engineering','Ritika Saxena','Electronics','Electronics','9785126738127',6,6,'available',1,'Analog and digital communication systems.'),
(18,'RF Circuit Design','Aakash Mukherjee','Electronics','Electronics','9785126738128',2,2,'available',1,'Radio frequency design concepts.'),
(19,'Control Systems Engineering','Dr. K R Singh','Electronics','Electronics','9785126738129',7,6,'available',1,'Control models, transfer functions and stability.'),
(20,'Digital Signal Processing','Arun Prakash','Electronics','Electronics','9785126738130',5,5,'available',1,'DSP fundamentals with examples.'),
(21,'Engineering Thermodynamics','Harshit Patil','Mechanical','Mechanical','9786129837101',8,7,'available',1,'Heat, work and thermodynamic laws.'),
(22,'Fluid Mechanics','Chaitanya Rao','Mechanical','Mechanical','9786129837102',5,5,'available',1,'Fluid properties and applications.'),
(23,'Heat Transfer Operations','Karan Shetty','Mechanical','Mechanical','9786129837103',6,6,'available',1,'Modes of heat transfer and calculations.'),
(24,'Machine Design Elements','Rudra Tiwari','Mechanical','Mechanical','9786129837104',3,3,'available',1,'Designing machine components.'),
(25,'Engineering Mechanics','Varun Mishra','Mechanical','Mechanical','9786129837105',7,6,'available',1,'Statics and dynamics principles.'),
(26,'Automobile Engineering','Prakash Kumar','Mechanical','Mechanical','9786129837106',4,4,'available',1,'Vehicle dynamics and engines.'),
(27,'Robotics for Engineers','Rishabh Vyas','Mechanical','Mechanical','9786129837107',9,9,'available',1,'Robotics motion, sensors and actuators.'),
(28,'Manufacturing Technology','Naveen Gupta','Mechanical','Mechanical','9786129837108',6,6,'available',1,'Casting, welding and machining operations.'),
(29,'Mechanical Vibrations','Amit Purohit','Mechanical','Mechanical','9786129837109',3,3,'available',1,'Vibration theory and damping.'),
(30,'Industrial Engineering','Hitesh Kothari','Mechanical','Mechanical','9786129837110',4,4,'available',1,'Production planning and optimization.'),
(31,'Strength of Materials','Manoj Chakraborty','Civil','Civil','9787126351891',5,5,'available',1,'Stress, strain and material behavior.'),
(32,'Soil Mechanics','Ashwini Narayan','Civil','Civil','9787126351892',7,7,'available',1,'Soil properties and foundations.'),
(33,'Concrete Technology','Mohan Reddy','Civil','Civil','9787126351893',6,6,'available',1,'Concrete mixes and testing.'),
(34,'Transportation Engineering','Girish Vardhan','Civil','Civil','9787126351894',4,4,'available',1,'Road, rail and transportation systems.'),
(35,'Water Resource Engineering','Meenal Singh','Civil','Civil','9787126351895',8,8,'available',1,'Hydrology and irrigation system design.'),
(36,'Surveying and Levelling','Anand Chauhan','Civil','Civil','9787126351896',3,3,'available',1,'Land surveying tools and techniques.'),
(37,'Environmental Engineering','Sanjay Dubey','Civil','Civil','9787126351897',6,6,'available',1,'Pollution control and solid waste mgmt.'),
(38,'Building Construction','Sagar Bhatt','Civil','Civil','9787126351898',5,5,'available',1,'Building materials & structural design.'),
(39,'Structural Analysis','Deepak More','Civil','Civil','9787126351899',6,6,'available',1,'Structural design principles.'),
(40,'Prestressed Concrete','Vinod Gahlot','Civil','Civil','9787126351800',4,4,'available',1,'Prestressing techniques.'),
(41,'Advanced Calculus','Sonam Pande','Mathematics','Mathematics','9788123456701',6,6,'available',1,'Differentiation & integration concepts.'),
(42,'Linear Algebra Made Easy','Raghav Nanda','Mathematics','Mathematics','9788123456702',7,7,'available',1,'Matrices, vectors & transformations.'),
(43,'Probability & Statistics','Harini Iyer','Mathematics','Mathematics','9788123456703',5,5,'available',1,'Stats, distributions and probability.'),
(44,'Discrete Mathematics','Gopal Rathore','Mathematics','Mathematics','9788123456704',9,9,'available',1,'Sets, logic and combinatorics.'),
(45,'Number Theory Concepts','Satyam Agrawal','Mathematics','Mathematics','9788123456705',3,3,'available',1,'Prime numbers and modular arithmetic.'),
(46,'Engineering Mathematics I','Rajeev Menon','Mathematics','Mathematics','9788123456706',8,8,'available',1,'Maths for engineering students.'),
(47,'Graph Theory Essentials','Meghna Nair','Mathematics','Mathematics','9788123456707',4,3,'issued',1,'Graphs, networks and trees.'),
(48,'Differential Equations','Yashwardhan Pathak','Mathematics','Mathematics','9788123456708',6,6,'available',1,'ODEs and PDEs explained.'),
(49,'Applied Statistics','Surbhi Jain','Mathematics','Mathematics','9788123456709',5,5,'available',1,'Statistical modeling techniques.'),
(50,'Business Mathematics','Lakshmi Rao','Mathematics','Mathematics','9788123456710',7,7,'available',1,'Math used in business applications.'),
(51,'Modern Fiction Stories','Mira Sen','Fiction','General','9789546218701',4,4,'available',1,'Collection of contemporary short stories.'),
(52,'The Hidden Door','Ankit Pandey','Fiction','General','9789546218702',6,6,'available',1,'A thrilling mystery novel.'),
(53,'Lost in the City','Rehana Kapoor','Fiction','General','9789546218703',5,5,'available',1,'A dramatic tale of urban life.'),
(54,'Shadows of the Past','Rajat Talwar','Fiction','General','9789546218704',3,3,'available',1,'A suspense story with twists.'),
(55,'Dreamcatcher Tales','Kritika Bose','Fiction','General','9789546218705',7,7,'available',1,'Fantasy world of dreams and magic.'),
(56,'Invisible Threads','Arjun Gulati','Fiction','General','9789546218706',6,6,'available',1,'Stories connecting strangers.'),
(57,'A Walk to Remember','Shreya Vaid','Fiction','General','9789546218707',8,8,'available',1,'A romantic emotional journey.'),
(58,'City of Sparks','Shantanu Joshi','Fiction','General','9789546218708',4,4,'available',1,'Science-fiction adventure.'),
(59,'The Silent River','Tanya Jha','Fiction','General','9789546218709',5,5,'available',1,'A peaceful yet mysterious novel.'),
(60,'Beyond the Horizon','Raghav Dixit','Fiction','General','9789546218710',6,6,'available',1,'A futuristic sci-fi journey.'),
(61,'Sapiens Simplified','Yogesh Bhatia','Non Fiction','General','9789756412301',7,7,'available',1,'Human history explained simply.'),
(62,'Power of Focus','Ramit Kapoor','Non Fiction','General','9789756412302',5,5,'available',1,'Guide to improving focus and discipline.'),
(63,'Winning Habits','Neelam Gogia','Non Fiction','General','9789756412303',6,6,'available',1,'Building productive habits.'),
(64,'Mindset Mastery','Karan Malhotra','Non Fiction','General','9789756412304',3,3,'available',1,'Changing mindset for success.'),
(65,'The Psychology of Success','Geeta Bhave','Non Fiction','General','9789756412305',8,8,'available',1,'Psychological factors behind success.'),
(66,'Minimalism & You','Saloni Arora','Non Fiction','General','9789756412306',4,4,'available',1,'Minimalism for modern lifestyle.'),
(67,'Time Management Guru','Amitabh Shah','Non Fiction','General','9789756412307',6,6,'available',1,'Improve productivity using time strategies.'),
(68,'The Art of Negotiation','Farhan Siddiqui','Non Fiction','General','9789756412308',7,7,'available',1,'Negotiation skills for life.'),
(69,'Financial Literacy 101','Meenal Agarwal','Non Fiction','General','9789756412309',6,6,'available',1,'Basics of personal finance.'),
(70,'Life Lessons Unlocked','Varsha Taneja','Non Fiction','General','9789756412310',5,5,'available',1,'Short life lessons collection.'),
(71,'Big Data Analytics','Pratik Dhawan','Data Science','Computer Science','9789354412701',7,7,'available',1,'Big data concepts & Hadoop ecosystem.'),
(72,'Python for Data Science','Roshni Bajaj','Data Science','Computer Science','9789354412702',9,9,'available',1,'Python tools for data analysis.'),
(73,'R Programming Guide','K. M. Suresh','Data Science','Computer Science','9789354412703',4,4,'available',1,'R programming for statistical computing.'),
(74,'Data Mining Techniques','Sumeet Kalra','Data Science','Computer Science','9789354412704',3,3,'available',1,'Clustering, classification & patterns.'),
(75,'AI & Society','Jasleen Narula','Data Science','Computer Science','9789354412705',8,8,'available',1,'Impact of AI on society & ethics.'),
(76,'Neural Network Projects','Zara Kaif','Data Science','Computer Science','9789354412706',6,5,'issued',1,'Hands-on neural network models.'),
(77,'Data Visualization Essentials','Swapnil Modak','Data Science','Computer Science','9789354412707',7,7,'available',1,'Charts, dashboards and storytelling.'),
(78,'Business Analytics Toolkit','Ritika Anand','Data Science','Computer Science','9789354412708',6,6,'available',1,'Business-focused analytics techniques.'),
(79,'Statistics for Data Science','Himani Arora','Data Science','Computer Science','9789354412709',5,5,'available',1,'Statistical foundations of DS.'),
(80,'Predictive Modeling','Dinesh Trivedi','Data Science','Computer Science','9789354412710',4,4,'available',1,'Predictive models & forecasting.'),
(81,'Physics for Engineers','Sushant Desai','Physics','Science','9789952153101',6,6,'available',1,'Modern physics fundamentals.'),
(82,'Classical Mechanics','Anoop Yadav','Physics','Science','9789952153102',5,5,'available',1,'Newtonian mechanics concepts.'),
(83,'Electromagnetism Explained','Ravi Teja','Physics','Science','9789952153103',7,7,'available',1,'Electromagnetic theory basics.'),
(84,'Quantum Physics 101','Arpita Naik','Physics','Science','9789952153104',2,2,'available',1,'Introduction to quantum mechanics.'),
(85,'Thermal Physics','Gaurav Kulkarni','Physics','Science','9789952153105',4,4,'available',1,'Heat, temperature & thermodynamics.'),
(86,'Modern Chemistry','Shweta Bhalla','Chemistry','Science','9789952153201',6,6,'available',1,'Chemical reactions & bonding.'),
(87,'Organic Chemistry Basics','Parth Agarwal','Chemistry','Science','9789952153202',5,5,'available',1,'Organic molecules & reactions.'),
(88,'Inorganic Chemistry','Sonia Khurana','Chemistry','Science','9789952153203',3,3,'available',1,'Inorganic compounds & structure.'),
(89,'Physical Chemistry','Neeraj Mathur','Chemistry','Science','9789952153204',7,7,'available',1,'Physical chemistry concepts.'),
(90,'Biochemistry Primer','Tanvi Shelar','Chemistry','Science','9789952153205',4,4,'available',1,'Chemistry of living organisms.'),
(91,'Human Biology','Dr. Neena Varma','Biology','Science','9789952153301',6,6,'available',1,'Human anatomy & physiology.'),
(92,'Genetics Essentials','Aarushi Verma','Biology','Science','9789952153302',5,5,'available',1,'DNA, genes & heredity.'),
(93,'Microbiology Basics','Lokesh Sahu','Biology','Science','9789952153303',4,4,'available',1,'Microorganisms & applications.'),
(94,'Plant Biology','Surbhi Maity','Biology','Science','9789952153304',7,7,'available',1,'Plant physiology & genetics.'),
(95,'Cell Biology','Farhan Quadri','Biology','Science','9789952153305',3,3,'available',1,'Cell structure & processes.'),
(96,'Marketing Management','Ashok Menon','Management','General','9789952153401',5,5,'available',1,'Modern marketing principles.'),
(97,'Business Communication','Ridhi Mehra','Management','General','9789952153402',6,6,'available',1,'Corporate communication skills.'),
(98,'Economics for Engineers','Pritam Das','Economics','General','9789952153403',7,7,'available',1,'Basic economic principles.'),
(99,'Indian Polity','V.K. Saxena','Non Fiction','General','9789952153404',4,4,'available',1,'Structure of Indian government.'),
(100,'World History Simplified','Jasmine D\'Souza','History','General','9789952153405',6,6,'available',1,'A global history overview.');


