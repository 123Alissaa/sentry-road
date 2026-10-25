-- Create a database and three tables for the Sentry Road project.

CREATE DATABASE IF NOT EXISTS sentry_road;
USE sentry_road;

DROP TABLE IF EXISTS completions;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS courses;

-- One row per employee
CREATE TABLE employees (
	employee_id VARCHAR(10) NOT NULL, 
    first_name VARCHAR(50) NOT NULL, 
    last_name VARCHAR(50) NOT NULL, 
    terminal VARCHAR(50) NOT NULL, 
    role VARCHAR(20) NOT NULL,
    hire_date DATE NOT NULL, 
    status VARCHAR(20) NOT NULL, 
    PRIMARY KEY (employee_id) 
    );

-- One row per course    
CREATE TABLE courses (
	course_name VARCHAR(100) NOT NULL, 
    renewal_months INT NOT NULL, 
    required_for VARCHAR(20) NOT NULL, 
    PRIMARY KEY (course_name)
);

-- One row per course completion
CREATE TABLE completions (
	completion_id INT NOT NULL AUTO_INCREMENT, 
    employee_id VARCHAR(10) NOT NULL, 
    course_name VARCHAR(100) NOT NULL, 
    completed_date DATE NOT NULL, 
    PRIMARY KEY (completion_id), 
    -- the same person can't finish the same course twice on the same day
    UNIQUE KEY one_per_day (employee_id, course_name, completed_date), 
    -- every completion must belong to a real employee and a real course. 
    FOREIGN KEY (employee_id) REFERENCES employees (employee_id), 
    FOREIGN KEY(course_name) REFERENCES courses(course_name)
    );
    

