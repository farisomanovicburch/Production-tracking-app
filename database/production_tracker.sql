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

-- ---------------------------------------------------------------------
-- Tables, in foreign-key order: a table comes after every table it points to.
-- Master data (users, machines, products, materials, recipes) is deactivated with
-- is_active instead of deleted, so ON DELETE RESTRICT protects the history that points to it.
-- ---------------------------------------------------------------------

CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,                  -- bcrypt hash, never the password itself
    role          ENUM('admin', 'operator') NOT NULL DEFAULT 'operator',
    is_active     BOOLEAN NOT NULL DEFAULT TRUE,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_email UNIQUE (email)
);

CREATE TABLE machines (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    code         VARCHAR(20)  NOT NULL,
    machine_type ENUM('extrusion', 'conversion') NOT NULL,
    is_active    BOOLEAN NOT NULL DEFAULT TRUE,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_machines_code UNIQUE (code)
);

CREATE TABLE machine_parameters (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    machine_id    INT NOT NULL,
    name          VARCHAR(100) NOT NULL,
    unit          VARCHAR(20)  NULL,                      -- e.g. rpm, °C; NULL for a plain number
    display_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_machine_parameters_machine FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE,
    CONSTRAINT uq_machine_parameters_name UNIQUE (machine_id, name)
);

CREATE TABLE products (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(20)  NOT NULL,
    name         VARCHAR(100) NOT NULL,
    width_mm     INT           NULL,
    thickness_um INT           NULL,
    length_m     DECIMAL(10,2) NULL,
    unit         ENUM('kg', 'pcs') NOT NULL DEFAULT 'kg',
    description  VARCHAR(255) NULL,
    is_active    BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT uq_products_code UNIQUE (code),
    CONSTRAINT chk_products_dimensions_positive
        CHECK ((width_mm IS NULL OR width_mm > 0) AND (thickness_um IS NULL OR thickness_um > 0) AND (length_m IS NULL OR length_m > 0))
);

-- Which products a machine works with: a conversion machine has input (film) and output (bags) products.
CREATE TABLE machine_products (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    machine_id INT NOT NULL,
    product_id INT NOT NULL,
    role       ENUM('input', 'output') NOT NULL,
    CONSTRAINT fk_machine_products_machine FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE,
    CONSTRAINT fk_machine_products_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT uq_machine_products UNIQUE (machine_id, product_id, role)
);

CREATE TABLE materials (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    unit          VARCHAR(10)  NOT NULL DEFAULT 'kg',
    supplier      VARCHAR(100) NULL,
    stock_qty     DECIMAL(12,3) NOT NULL DEFAULT 0,
    min_stock_qty DECIMAL(12,3) NULL,                     -- NULL: no low-stock warning for this material
    is_active     BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT uq_materials_name UNIQUE (name),
    CONSTRAINT chk_materials_stock_not_negative CHECK (stock_qty >= 0),
    CONSTRAINT chk_materials_min_stock_not_negative CHECK (min_stock_qty IS NULL OR min_stock_qty >= 0)
);

CREATE TABLE recipes (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(100) NOT NULL,
    notes     VARCHAR(255) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT uq_recipes_name UNIQUE (name)
);

CREATE TABLE recipe_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id   INT NOT NULL,
    material_id INT NOT NULL,
    percentage  DECIMAL(5,2) NOT NULL,
    CONSTRAINT fk_recipe_items_recipe   FOREIGN KEY (recipe_id)   REFERENCES recipes(id)   ON DELETE CASCADE,
    CONSTRAINT fk_recipe_items_material FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT,
    CONSTRAINT uq_recipe_items UNIQUE (recipe_id, material_id),
    CONSTRAINT chk_recipe_items_percentage CHECK (percentage > 0 AND percentage <= 100)
);

-- Which recipes a product can be made with. "At most one default per product" is checked by the service.
CREATE TABLE recipe_products (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id  INT NOT NULL,
    product_id INT NOT NULL,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_recipe_products_recipe  FOREIGN KEY (recipe_id)  REFERENCES recipes(id)  ON DELETE CASCADE,
    CONSTRAINT fk_recipe_products_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT uq_recipe_products UNIQUE (recipe_id, product_id)
);

CREATE TABLE production_runs (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    machine_id               INT NOT NULL,                -- an extrusion machine (checked by the service)
    product_id               INT NOT NULL,
    recipe_id                INT NOT NULL,
    user_id                  INT NOT NULL,                -- who logged the run
    warmup_started_at        DATETIME NULL,
    started_at               DATETIME NOT NULL,
    stable_at                DATETIME NULL,               -- production became stable
    ended_at                 DATETIME NOT NULL,
    quantity_produced        DECIMAL(12,3) NOT NULL,      -- in the product's unit
    net_weight_per_unit_kg   DECIMAL(10,4) NULL,
    gross_weight_per_unit_kg DECIMAL(10,4) NULL,
    scrap_kg                 DECIMAL(12,3) NOT NULL DEFAULT 0,
    notes                    TEXT NULL,
    created_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_production_runs_machine FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE RESTRICT,
    CONSTRAINT fk_production_runs_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_production_runs_recipe  FOREIGN KEY (recipe_id)  REFERENCES recipes(id)  ON DELETE RESTRICT,
    CONSTRAINT fk_production_runs_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE RESTRICT,
    CONSTRAINT chk_production_runs_ends_after_start CHECK (ended_at > started_at),
    -- The machine warms up before the run starts; production becomes stable during the run.
    CONSTRAINT chk_production_runs_warmup_before_start CHECK (warmup_started_at IS NULL OR warmup_started_at < started_at),
    CONSTRAINT chk_production_runs_stable_during_run CHECK (stable_at IS NULL OR (stable_at > started_at AND stable_at < ended_at)),
    CONSTRAINT chk_production_runs_amounts_not_negative CHECK (quantity_produced >= 0 AND scrap_kg >= 0),
    CONSTRAINT chk_production_runs_weights
        CHECK ((net_weight_per_unit_kg IS NULL OR net_weight_per_unit_kg > 0)
           AND (gross_weight_per_unit_kg IS NULL OR gross_weight_per_unit_kg > 0)
           AND (net_weight_per_unit_kg IS NULL OR gross_weight_per_unit_kg IS NULL OR gross_weight_per_unit_kg >= net_weight_per_unit_kg))
);

CREATE TABLE run_parameter_values (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    production_run_id    INT NOT NULL,
    machine_parameter_id INT NOT NULL,
    value                DECIMAL(12,3) NOT NULL,
    CONSTRAINT fk_run_parameter_values_run       FOREIGN KEY (production_run_id)    REFERENCES production_runs(id)    ON DELETE CASCADE,
    CONSTRAINT fk_run_parameter_values_parameter FOREIGN KEY (machine_parameter_id) REFERENCES machine_parameters(id) ON DELETE RESTRICT,
    CONSTRAINT uq_run_parameter_values UNIQUE (production_run_id, machine_parameter_id)
);

CREATE TABLE material_usages (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    production_run_id INT NOT NULL,
    material_id       INT NOT NULL,
    quantity_kg       DECIMAL(12,3) NOT NULL,
    CONSTRAINT fk_material_usages_run      FOREIGN KEY (production_run_id) REFERENCES production_runs(id) ON DELETE CASCADE,
    CONSTRAINT fk_material_usages_material FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT,
    CONSTRAINT uq_material_usages UNIQUE (production_run_id, material_id),
    CONSTRAINT chk_material_usages_quantity_positive CHECK (quantity_kg > 0)
);

CREATE TABLE conversion_runs (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    machine_id        INT NOT NULL,                       -- a conversion machine (checked by the service)
    user_id           INT NOT NULL,
    input_product_id  INT NOT NULL,                       -- the film that went in
    output_product_id INT NOT NULL,                       -- the bags that came out
    started_at        DATETIME NOT NULL,
    ended_at          DATETIME NOT NULL,
    input_kg          DECIMAL(12,3) NOT NULL,
    output_kg         DECIMAL(12,3) NOT NULL,             -- scrap = input_kg - output_kg, never stored
    bag_count         INT NOT NULL,
    notes             TEXT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_conversion_runs_machine        FOREIGN KEY (machine_id)        REFERENCES machines(id) ON DELETE RESTRICT,
    CONSTRAINT fk_conversion_runs_user           FOREIGN KEY (user_id)           REFERENCES users(id)    ON DELETE RESTRICT,
    CONSTRAINT fk_conversion_runs_input_product  FOREIGN KEY (input_product_id)  REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_conversion_runs_output_product FOREIGN KEY (output_product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT chk_conversion_runs_ends_after_start CHECK (ended_at > started_at),
    CONSTRAINT chk_conversion_runs_amounts CHECK (input_kg > 0 AND output_kg >= 0 AND bag_count >= 0),
    CONSTRAINT chk_conversion_runs_output_not_above_input CHECK (output_kg <= input_kg)
);

CREATE TABLE conversion_run_parameter_values (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    conversion_run_id    INT NOT NULL,
    machine_parameter_id INT NOT NULL,
    value                DECIMAL(12,3) NOT NULL,
    CONSTRAINT fk_conversion_run_parameter_values_run       FOREIGN KEY (conversion_run_id)    REFERENCES conversion_runs(id)    ON DELETE CASCADE,
    CONSTRAINT fk_conversion_run_parameter_values_parameter FOREIGN KEY (machine_parameter_id) REFERENCES machine_parameters(id) ON DELETE RESTRICT,
    CONSTRAINT uq_conversion_run_parameter_values UNIQUE (conversion_run_id, machine_parameter_id)
);

CREATE TABLE maintenance_records (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    machine_id       INT NOT NULL,
    user_id          INT NOT NULL,
    maintenance_type ENUM('preventive', 'corrective') NOT NULL,
    started_at       DATETIME NOT NULL,
    ended_at         DATETIME NOT NULL,
    description      TEXT NOT NULL,                       -- the work that was done
    notes            TEXT NULL,                           -- spare parts and anything else
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_maintenance_records_machine FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_records_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE RESTRICT,
    CONSTRAINT chk_maintenance_records_ends_after_start CHECK (ended_at >= started_at)
);
