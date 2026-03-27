Business Forecasting with Time-Series Analysis for DepEd SHS Enrollment and Resource Planning

---

## 📌 Overview

This project is a web-based predictive analytics system designed to forecast Senior High School (SHS) enrollment and estimate required classrooms and teachers using time-series analysis.

It utilizes Facebook Prophet for forecasting and provides an interactive dashboard for data-driven planning, specifically for DepEd NCR.

---

## 🚀 Features

* CSV upload with automatic data validation
* 3-year enrollment forecasting using time-series analysis
* Model accuracy evaluation using MAPE
* Computation of classroom and teacher requirements
* Interactive dashboard (Chart.js visualization)
* Downloadable reports (PDF and CSV)
* Admin panel (user management, logs, system settings)

---

## 🛠️ Tech Stack

* Backend: PHP (XAMPP), MySQL
* Forecasting: Python, Facebook Prophet, Pandas
* Frontend: HTML, CSS, JavaScript (Chart.js)

---

## 📂 Dataset Format

CSV file must contain:

* Year
* Total_Enrollees

Example:
Year,Total_Enrollees
2016,120000
2017,135000

---

## ⚙️ Installation Guide

1. Install XAMPP (Apache + MySQL)
2. Place project folder inside htdocs
3. Start Apache and MySQL in XAMPP
4. Go to XAMPP MySQL Adminn
5. Then create the database and table using this MySQL Code:
  -- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS ncr_forecast;

-- Select the database
USE ncr_forecast;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','user') DEFAULT 'user',
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert an admin user (password already hashed)
INSERT INTO users (username, password_hash, role)
VALUES ('admin', '$2y$10$ORcP0n3fLDaNzbRKdX/1PuX4cKrPeDme4v/SibbzoiJ6fHEx3oIzi', 'admin');

5. Import the database (if provided)
6. Ensure Python is installed with required libraries:

   * prophet
   * pandas
7. Run the project in browser:
   http://localhost/your-project-folder

---

## 📊 How It Works

1. Upload historical enrollment data (CSV)
2. System processes and validates data
3. Forecast is generated using Facebook Prophet
4. Results displayed in dashboard and tables
5. Resource requirements are automatically computed

---

## 🎯 Purpose

To support DepEd planners in shifting from reactive to data-driven decision-making by providing accurate enrollment forecasts and resource estimations.

---

## 👥 Developers

* Jeremy Escoses
* Stephen Kyle V. Laroco
* Brian M. Moulic

---

## 📅 Date

October 2025

---

## 📌 Notes

* Designed for NCR data only
* Local deployment (not cloud-based)
* Future improvements may include nationwide data and labor market integration

---

## 📜 License

For academic purposes only.
