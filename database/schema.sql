-- =========================================================
-- EduSphere LMS — Schema v4
-- Adds user approval workflow, notifications, audit trail
-- =========================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS status_history;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS exam_answers;
DROP TABLE IF EXISTS exam_attempts;
DROP TABLE IF EXISTS exam_questions;
DROP TABLE IF EXISTS exams;
DROP TABLE IF EXISTS past_papers;
DROP TABLE IF EXISTS notes;
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS assignments;
DROP TABLE IF EXISTS lesson_progress;
DROP TABLE IF EXISTS lessons;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS departments;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------
-- DEPARTMENTS
-- ---------------------------------------------------------
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    head_name VARCHAR(120) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- USERS  (with approval workflow)
-- status:
--   pending   -> signed up, awaiting admin approval
--   active    -> approved, can log in
--   rejected  -> denied by admin
--   suspended -> was active, admin disabled
-- ---------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(60) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','instructor','admin') NOT NULL DEFAULT 'student',
    department_id INT DEFAULT NULL,
    staff_id VARCHAR(40) DEFAULT NULL,
    admission_no VARCHAR(40) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    status ENUM('pending','active','rejected','suspended') NOT NULL DEFAULT 'pending',
    rejection_reason VARCHAR(255) DEFAULT NULL,
    approved_by INT DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    last_login_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- STATUS HISTORY  (audit trail for approvals/suspensions)
-- ---------------------------------------------------------
CREATE TABLE status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    old_status VARCHAR(20) DEFAULT NULL,
    new_status VARCHAR(20) NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    changed_by INT DEFAULT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- NOTIFICATIONS
-- ---------------------------------------------------------
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- COURSES
-- ---------------------------------------------------------
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    instructor_id INT NOT NULL,
    department_id INT DEFAULT NULL,
    category VARCHAR(80) DEFAULT NULL,
    level ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
    duration_hours INT DEFAULT 0,
    thumbnail VARCHAR(255) DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- ENROLLMENTS
-- ---------------------------------------------------------
CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    progress_percentage INT NOT NULL DEFAULT 0,
    completed_at DATETIME NULL,
    UNIQUE KEY unique_enrollment (user_id, course_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- LESSONS
-- ---------------------------------------------------------
CREATE TABLE lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    video_url VARCHAR(500) DEFAULT NULL,
    attachment VARCHAR(255) DEFAULT NULL,
    lesson_order INT DEFAULT 0,
    duration_minutes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- COURSE NOTES
-- ---------------------------------------------------------
CREATE TABLE notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    file_size INT DEFAULT 0,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- LESSON PROGRESS
-- ---------------------------------------------------------
CREATE TABLE lesson_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    completed TINYINT(1) DEFAULT 0,
    completed_at DATETIME NULL,
    UNIQUE KEY unique_progress (user_id, lesson_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- ASSIGNMENTS
-- ---------------------------------------------------------
CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    instructions TEXT,
    due_date DATETIME DEFAULT NULL,
    allow_late TINYINT(1) NOT NULL DEFAULT 0,
    attachment VARCHAR(255) DEFAULT NULL,
    max_points INT DEFAULT 100,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- SUBMISSIONS
-- ---------------------------------------------------------
CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT,
    file_path VARCHAR(255) DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_late TINYINT(1) NOT NULL DEFAULT 0,
    grade INT DEFAULT NULL,
    feedback TEXT,
    graded_by INT DEFAULT NULL,
    graded_at DATETIME NULL,
    UNIQUE KEY unique_submission (assignment_id, user_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- PAST PAPERS
-- ---------------------------------------------------------
CREATE TABLE past_papers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    year INT DEFAULT NULL,
    exam_type ENUM('cat','midterm','final','supplementary') DEFAULT 'final',
    file_path VARCHAR(255) DEFAULT NULL,
    external_url VARCHAR(500) DEFAULT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- EXAMS
-- ---------------------------------------------------------
CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    instructions TEXT,
    duration_minutes INT DEFAULT 60,
    total_points INT DEFAULT 100,
    start_at DATETIME DEFAULT NULL,
    end_at DATETIME DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE exam_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) DEFAULT NULL,
    option_d VARCHAR(500) DEFAULT NULL,
    correct_option CHAR(1) NOT NULL,
    points INT DEFAULT 1,
    question_order INT DEFAULT 0,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE exam_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    user_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME NULL,
    score INT DEFAULT NULL,
    status ENUM('in_progress','submitted','graded') DEFAULT 'in_progress',
    UNIQUE KEY unique_attempt (exam_id, user_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE exam_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option CHAR(1) DEFAULT NULL,
    is_correct TINYINT(1) DEFAULT NULL,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES exam_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- ANNOUNCEMENTS
-- ---------------------------------------------------------
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- SEED DATA  (all passwords = "password")
-- =========================================================
INSERT INTO departments (code, name, description, head_name) VALUES
('CS',   'Computer Science',         'Software engineering, algorithms, and computing theory.', 'Dr. Alice Mwangi'),
('IT',   'Information Technology',   'Networking, systems administration, and IT infrastructure.', 'Prof. Brian Otieno'),
('BUS',  'Business Administration',  'Management, entrepreneurship, and organizational studies.', 'Dr. Grace Njeri'),
('ENG',  'Engineering',              'Electrical, mechanical, and civil engineering fundamentals.', 'Eng. Samuel Kariuki'),
('MATH', 'Mathematics & Statistics', 'Pure and applied mathematics, statistics, and data analysis.', 'Dr. Peter Ochieng');

-- Admins & instructors: status = active
INSERT INTO users (full_name, username, email, password, role, department_id, staff_id, status, approved_at) VALUES
('System Administrator','admin','admin@demo.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin',1,'ADM-0001','active',NOW()),
('Dr. Alice Mwangi','amwangi','instructor@demo.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','instructor',1,'STF-1001','active',NOW()),
('Prof. Brian Otieno','botieno','brian@demo.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','instructor',2,'STF-1002','active',NOW()),
('Dr. Grace Njeri','gnjeri','grace@demo.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','instructor',3,'STF-1003','active',NOW()),
('Eng. Samuel Kariuki','skariuki','samuel@demo.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','instructor',4,'STF-1004','active',NOW()),
('Dr. Peter Ochieng','pochieng','peter@demo.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','instructor',5,'STF-1005','active',NOW());

-- Students: mix of active and pending to demo the queue
INSERT INTO users (full_name, username, email, password, role, department_id, admission_no, status, approved_at) VALUES
('Jane Wanjiku','jwanjiku','student@demo.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,'CS/2024/001','active',NOW()),
('Kevin Kimani','kkimani','kevin@demo.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,'CS/2024/002','active',NOW()),
('Mary Achieng','machieng','mary@demo.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',2,'IT/2024/001','active',NOW()),
('David Mutua','dmutua','david@demo.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',3,'BUS/2024/001','active',NOW()),
('Faith Wairimu','fwairimu','faith@demo.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,'CS/2024/003','active',NOW());

-- Two pending applicants to demo the approval queue
INSERT INTO users (full_name, username, email, password, role, department_id, admission_no, status) VALUES
('Alex Njoroge','anjoroge','alex@demo.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,'CS/2026/004','pending'),
('Winnie Kerubo','wkerubo','winnie@demo.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',2,'IT/2026/002','pending');

INSERT INTO status_history (user_id, old_status, new_status, reason, changed_by)
SELECT id, NULL, 'pending', 'Self-registration', NULL FROM users WHERE status = 'pending';

-- Courses, lessons, assignments, exams, papers, announcements (unchanged from v3)
INSERT INTO courses (code, title, description, instructor_id, department_id, category, level, duration_hours, is_published) VALUES
('CS101','Introduction to Programming','Foundations of programming using Python: variables, control flow, functions, and file I/O.',2,1,'Programming','beginner',40,1),
('CS201','Data Structures and Algorithms','Arrays, linked lists, trees, graphs, sorting, searching, and complexity analysis.',2,1,'Programming','intermediate',50,1),
('CS301','Database Systems','Relational modeling, SQL, normalization, transactions, and indexing.',2,1,'Databases','intermediate',45,1),
('CS401','Operating Systems','Processes, threads, memory management, file systems, and concurrency.',2,1,'Systems','advanced',55,1),
('CS205','Web Application Development','Full-stack web development with HTML, CSS, JS, PHP, and MySQL.',2,1,'Web','intermediate',48,1),
('IT110','Web Development Fundamentals','HTML5, CSS3, JavaScript, and responsive design for the modern web.',3,2,'Web','beginner',45,1),
('IT210','Computer Networks','OSI model, TCP/IP, routing, switching, and network security basics.',3,2,'Networking','intermediate',50,1),
('IT310','Cloud Computing','Virtualization, containers, IaaS/PaaS/SaaS, and cloud architecture.',3,2,'Cloud','advanced',42,1),
('IT120','Systems Administration','Linux/Windows administration, users, permissions, and automation.',3,2,'Systems','beginner',38,1),
('BUS101','Principles of Management','Planning, organizing, leading, and controlling in modern organizations.',4,3,'Management','beginner',36,1),
('BUS210','Financial Accounting','Recording, classifying, and summarizing financial transactions.',4,3,'Finance','intermediate',40,1),
('BUS320','Entrepreneurship','Identifying opportunities, business models, and venture creation.',4,3,'Business','intermediate',38,1),
('ENG101','Engineering Mathematics','Calculus, linear algebra, and differential equations for engineers.',6,4,'Mathematics','beginner',50,1),
('ENG220','Circuit Analysis','DC/AC circuits, Kirchhoff laws, network theorems, and transients.',5,4,'Electrical','intermediate',48,1),
('MATH201','Probability and Statistics','Descriptive statistics, probability distributions, and hypothesis testing.',6,5,'Statistics','intermediate',45,1);

INSERT INTO lessons (course_id, title, content, video_url, lesson_order, duration_minutes) VALUES
(1,'Setting Up Your Python Environment','Install Python 3.12 and VS Code. Write and run your first script.','https://www.youtube.com/embed/rfscVS0vtbw',1,30),
(1,'Variables and Data Types','Integers, floats, strings, booleans, and type conversion.','https://www.youtube.com/embed/kqtD5dpn9C8',2,45),
(1,'Control Flow: if, elif, else','Conditional logic and Boolean expressions.','https://www.youtube.com/embed/Zp5MuPOtsSY',3,40),
(1,'Loops: for and while','Iteration, range, break, continue, and nested loops.','https://www.youtube.com/embed/94UHCEmprCY',4,50),
(1,'Functions and Modules','Defining functions, parameters, return values, and imports.','https://www.youtube.com/embed/9Os0o3wzS_I',5,55),
(2,'Arrays and Lists','Static and dynamic arrays; Python lists and their operations.','https://www.youtube.com/embed/W8K8RAPHG9c',1,45),
(2,'Linked Lists','Singly and doubly linked lists; insertion and deletion.','https://www.youtube.com/embed/NobHlGUjV3g',2,55),
(2,'Stacks and Queues','LIFO and FIFO structures and their applications.','https://www.youtube.com/embed/wjI1WNcIntg',3,45),
(2,'Trees and Traversals','Binary trees, BST, and depth-first/breadth-first traversals.','https://www.youtube.com/embed/oSWTXtMglKE',4,60),
(3,'Introduction to Relational Databases','Tables, rows, columns, and keys.','https://www.youtube.com/embed/ztHopE5Wnpc',1,40),
(3,'SQL SELECT Deep Dive','Filtering, sorting, joining, and aggregating results.','https://www.youtube.com/embed/7S_tz1z_5bA',2,55),
(5,'HTML Foundations','Document structure, semantic tags, and forms.','https://www.youtube.com/embed/qz0aGYrrlhU',1,40),
(5,'CSS Layouts with Flexbox and Grid','Modern CSS layout systems.','https://www.youtube.com/embed/JJSoEo8JSnc',2,50),
(5,'PHP and MySQL Basics','Connecting PHP to MySQL and performing CRUD.','https://www.youtube.com/embed/OK_JCtrrv-c',3,60),
(6,'HTML Basics','Structure of an HTML document.','https://www.youtube.com/embed/qz0aGYrrlhU',1,30),
(6,'CSS Styling Basics','Selectors, the box model, and typography.','https://www.youtube.com/embed/1Rs2ND1ryYc',2,45),
(6,'JavaScript Fundamentals','Variables, functions, and DOM manipulation.','https://www.youtube.com/embed/W6NZfCO5SIk',3,60),
(7,'The OSI Model','Seven layers and their responsibilities.','https://www.youtube.com/embed/vv4y_uOneC0',1,45),
(7,'TCP/IP Essentials','IP addressing, subnets, and routing basics.','https://www.youtube.com/embed/AEaKrq3SpW8',2,50),
(9,'Linux Command Line Essentials','Navigation, files, permissions, and pipes.','https://www.youtube.com/embed/oxuRxtrO2Ag',1,45),
(10,'The Four Functions of Management','Planning, organizing, leading, controlling.','https://www.youtube.com/embed/Zp5MuPOtsSY',1,35),
(11,'The Accounting Equation','Assets = Liabilities + Equity.','https://www.youtube.com/embed/yYX4bvQSqbo',1,40),
(14,'Ohm''s Law and Kirchhoff''s Laws','Foundations of DC circuit analysis.','https://www.youtube.com/embed/HsLLq6Rm5tU',1,50),
(15,'Descriptive Statistics','Mean, median, mode, variance, and standard deviation.','https://www.youtube.com/embed/sxQaBpKfDRk',1,45);

INSERT INTO assignments (course_id, title, instructions, due_date, allow_late, max_points) VALUES
(1,'Hello World Program','Write a Python script that prints "Hello, [your name]" and the current date. Submit a .py file.','2026-12-31 23:59:00',0,20),
(1,'Calculator with Functions','Build a command-line calculator with add/sub/mul/div functions. Handle division by zero.','2026-12-31 23:59:00',1,50),
(2,'Linked List Implementation','Implement a singly linked list with insert, delete, and search operations.','2026-12-31 23:59:00',0,80),
(3,'Normalize a Schema','Given an unnormalized table, normalize it to 3NF and submit the resulting DDL.','2026-12-31 23:59:00',1,60),
(5,'Personal Portfolio Page','Create a responsive portfolio using HTML, CSS, and JavaScript.','2026-12-31 23:59:00',1,100),
(6,'Responsive Landing Page','Build a landing page that adjusts gracefully from 320px to 1920px.','2026-12-31 23:59:00',1,80),
(7,'Subnetting Exercise','Given a /24 block, subnet it into 6 subnets and provide the range for each.','2026-12-31 23:59:00',0,40),
(10,'Case Study Analysis','Analyze a company''s management structure in 800 words.','2026-12-31 23:59:00',1,50);

INSERT INTO past_papers (course_id, title, year, exam_type, external_url, uploaded_by) VALUES
(1,'CS101 End of Semester Exam',2024,'final','https://example.com/papers/cs101-2024.pdf',2),
(1,'CS101 CAT 1',2024,'cat','https://example.com/papers/cs101-cat1.pdf',2),
(2,'CS201 Final Exam',2024,'final','https://example.com/papers/cs201-2024.pdf',2),
(3,'CS301 Database Systems Final',2023,'final','https://example.com/papers/cs301-2023.pdf',2),
(7,'IT210 Networks Final',2024,'final','https://example.com/papers/it210-2024.pdf',3);

INSERT INTO exams (course_id, title, instructions, duration_minutes, total_points, start_at, end_at, is_published) VALUES
(1,'CS101 Mid-Semester Exam','Answer all questions. No calculators. Auto-submits at time.',30,20,NOW(),DATE_ADD(NOW(),INTERVAL 90 DAY),1),
(3,'CS301 Database Quiz','Short multiple-choice quiz on normalization and SQL.',20,15,NOW(),DATE_ADD(NOW(),INTERVAL 90 DAY),1);

INSERT INTO exam_questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_option, points, question_order) VALUES
(1,'Which keyword defines a function in Python?','func','define','def','function','C',2,1),
(1,'What data type is the result of 5 / 2 in Python 3?','int','float','str','bool','B',2,2),
(1,'Which of these is NOT a Python data type?','list','tuple','array','dict','C',2,3),
(1,'What does the len() function return?','The size in bytes','The number of items','The first element','The last element','B',2,4),
(1,'Which loop runs at least once?','for','while','do-while','None of these','C',2,5),
(2,'What does 1NF require?','No transitive dependencies','Atomic values only','No partial dependencies','A primary key','B',2,1),
(2,'Which normal form removes partial dependencies?','1NF','2NF','3NF','BCNF','B',2,2),
(2,'Which SQL clause filters rows?','ORDER BY','GROUP BY','WHERE','HAVING','C',2,3);

INSERT INTO announcements (course_id, user_id, title, content) VALUES
(1,2,'Lab session moved','This week''s lab session has been moved to Friday 10:00 AM in Lab 3.'),
(2,2,'Extra reading on trees','Please review chapter 6 before Thursday.'),
(5,2,'Deployment demo','A live demo of deploying to shared hosting will be held next Tuesday.'),
(7,3,'Network lab this week','Bring your laptops with Wireshark installed.');