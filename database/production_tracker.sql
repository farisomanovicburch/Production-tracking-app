-- =====================================================================
-- Production Tracker database (MySQL 8)
-- Import:  mysql -u root -p < database/production_tracker.sql
--          (or MySQL Workbench: File > Open SQL Script > Execute)
--
-- WARNING: this script DROPS and recreates the whole production_tracker
-- database. The backend tests run it before every test run, so never keep
-- data you care about in this database.
-- =====================================================================

-- Read this file as UTF-8 (names with č, ć, š, đ, ž)
SET NAMES utf8mb4;

DROP DATABASE IF EXISTS production_tracker;
-- utf8mb4_unicode_ci instead of MySQL 8's default utf8mb4_0900_ai_ci: MariaDB, which many hosts run,
-- does not know the 0900 collations, and the import would fail there.
CREATE DATABASE production_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE production_tracker;
