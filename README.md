# Production Tracker

> **Status: Milestone 2.** The MySQL database (15 tables with sample data) and the data
> access layer (one DAO per table, covered by PHPUnit tests) exist. The frontend is still
> static: every page exists and navigation works as a single-page application, but the
> pages show sample data written into the HTML. The REST API, login and the connection
> between pages and database come in Milestones 3 and 4.

## Project description

Production Tracker is a web application for a small family business. It replaces
paper notes with one place where every finished job is recorded and can be looked up
later:

- **Production runs:** which machine made which product, with which recipe,
  the machine parameters used, the materials consumed, the output and the scrap.
- **Conversions:** which product was turned into which product (for example
  film into bags), on which machine and with which parameters, with input and output
  quantities.
- **Maintenance log:** preventive and corrective work done on each machine.
- **Material stock:** current stock of every raw material, with a low-stock warning on
  the dashboard when a material drops to its minimum. Production runs use stock up;
  deliveries are added on the Materials page.
- **Master data:** machines, products, materials and recipes, managed by an admin.

Jobs are logged after they are finished, mostly from a PC. The interface is in English
and works on phones as well as desktops.

It is a single-page application (SPA) with a REST backend, built for the IT 2001 Web
Programming course at International Burch University.

### Background

This is a rewrite, for the course's technology stack, of my earlier app
([Production-Tracking-App](https://github.com/farisomanovic/Production-Tracking-App),
React, Express and PostgreSQL). No code was carried over; the old app was used for
domain knowledge and for lessons about what to do differently.

### Technology stack

| Layer | Technology |
|---|---|
| Frontend | HTML, CSS, JavaScript, jQuery 3.7, Bootstrap 5.2 ([SB Admin](https://startbootstrap.com/template/sb-admin) template), Font Awesome, [spapp](https://github.com/amiletti/spapp) (SPA routing, slightly modified) |
| Backend | PHP 8.4, FlightPHP (REST API, from Milestone 3), PDO, Composer |
| Database | MySQL 8 |
| Authentication | JWT (Milestone 4) |
| Tests | PHPUnit 13 |

All frontend libraries are stored in `frontend/assets/lib/`, so the app runs without
internet access.

## Pages

| Area | Pages |
|---|---|
| Start | Dashboard (quick actions, low-stock materials, weather) |
| Production | Runs, New run (four-step form with a recipe calculator), Run details; Conversions, New conversion, Conversion details |
| Maintenance | Maintenance log, New maintenance, Maintenance details |
| Master data (admin) | Machines, Machine setup (linked products and parameters), Products, Materials (with "Add delivery"), Recipes, Recipe setup (materials and their shares) |
| Administration (admin) | Users |
| Account | Login, Register, Profile |
| Other | Page not found |

Details and setup pages carry the record in the address, for example
`#run-detail?id=17`, so refreshing, the back button and bookmarks keep the same record.
A details page opens locked; Edit unlocks it, and Cancel returns to the saved values.

## Entities and relationships

The database is created by [database/production_tracker.sql](database/production_tracker.sql).
The full diagram, with every column, is in [docs/erd.md](docs/erd.md) (also as
[SVG](docs/erd.svg) and [PNG](docs/erd.png)).

| Entity (table) | What it stores |
|---|---|
| `users` | Everyone who logs in. Each user has one role: `admin` or `operator`. |
| `machines` | Extruders and bag-making machines. `machine_type` is `extrusion` or `conversion`. |
| `machine_parameters` | The settings recorded for a machine (name, unit), e.g. temperatures or speeds. |
| `products` | What the factory makes: film and bags, with dimensions and unit. |
| `machine_products` | Which products a machine works with. `role` is `input` or `output`. |
| `materials` | Raw materials with current stock and an optional minimum stock. |
| `recipes` and `recipe_items` | A recipe is a mix of materials; each item is one material and its percentage. |
| `recipe_products` | Which recipes a product can be made with, and which one is its default. |
| `production_runs` | One finished extrusion job, with its times, output, scrap and weights per unit. |
| `run_parameter_values` | The value of each machine parameter during a production run. |
| `material_usages` | How much of each material a production run consumed. |
| `conversion_runs` | One finished bag-making job. |
| `conversion_run_parameter_values` | The value of each machine parameter during a conversion run. |
| `maintenance_records` | One maintenance job on a machine. |

Relationships:

- A **machine** has many **machine parameters**, and is linked to many **products**
  through **machine products** (many-to-many, with an input/output role).
- A **recipe** has many **recipe items**; each item belongs to one **material**
  (recipes and materials are many-to-many). Recipes and **products** are also
  many-to-many, through **recipe products**.
- A **production run** belongs to one **user**, **machine**, **product** and **recipe**.
  It has many **run parameter values** (one per machine parameter) and many **material
  usages** (one per material).
- A **conversion run** belongs to one **user** and one **conversion machine**, and
  references two **products**: the input and the output. It has many **conversion run
  parameter values**. Scrap is not stored; it is input weight minus output weight.
- A **maintenance record** belongs to one **machine** and one **user**.
- Machines, products, materials, recipes and users are deactivated rather than deleted,
  so old runs keep pointing at what they used.
- A material whose `min_stock_qty` is empty never shows a low-stock warning.

## Roles and test accounts

| Role | Can do |
|---|---|
| **Admin** | Everything: manage machines, products, materials, recipes and users, plus everything an operator can do. |
| **Operator** | Log production runs, conversions and maintenance; view all runs, conversions and maintenance records, including other operators' (they are often needed as a reference); see recipes (including material percentages, needed for mixing), the low-stock warnings and the weather. |

Registering creates an active operator; the first admin account is created by the
database script.

Test accounts (created by `database/production_tracker.sql`):

| Email | Password | Role | Note |
|---|---|---|---|
| `admin@example.com` | `admin123` | Admin | |
| `operator@example.com` | `operator456` | Operator | |
| `former@example.com` | `former789` | Operator | Deactivated: logging in is refused, but the runs logged with this account stay visible. |

## External API

**[OpenWeather](https://openweathermap.org/api)**: current weather and the forecast for
the next hours, shown in a card on the dashboard. Bad weather, a thunderstorm in
particular, is a reason not to start a production run, so the forecast helps decide
whether to start one.

In Milestone 1 the card shows sample values. From Milestone 3 the backend calls
OpenWeather (the browser never sees the API key), and if OpenWeather is unavailable the
card shows a message while the rest of the app keeps working.

## Running the project locally

### Requirements

- PHP 8.4 with the `pdo_mysql`, `mbstring`, `openssl`, `curl` and `zip` extensions enabled
- MySQL 8
- [Composer](https://getcomposer.org/)

### Database and backend

1. **Create the database** by importing the script:
   ```
   mysql -u root -p < database/production_tracker.sql
   ```
   (or open the file in MySQL Workbench or DataGrip and run it). The script drops and
   recreates the whole `production_tracker` database, so never keep data you need in it.
2. **Create the configuration file:** copy `backend/config/config.example.php` to
   `backend/config/config.php` and enter your MySQL user and password. `config.php` is
   ignored by Git because it holds secrets.
3. **Install the backend dependencies:**
   ```
   cd backend
   composer install
   ```
4. **Run the tests** (from `backend/`):
   ```
   php vendor/bin/phpunit
   ```
   Before every run the tests rebuild `production_tracker` from the script, so every run
   starts from the same sample data.

### Frontend

The pages are loaded with AJAX, so opening `frontend/index.html` straight from disk
(`file://`) does not work; the `frontend` folder has to be served over HTTP. Either:

- in VS Code, open the project with the **Live Server** extension and go to
  `/frontend/`, or
- from the project folder, run `php -S localhost:8080 -t frontend` and open
  <http://localhost:8080>.

## Coming in later milestones

| Milestone | Adds to this README |
|---|---|
| 3 | The link to the Swagger API documentation |
| 5 | The link to the deployed application |
