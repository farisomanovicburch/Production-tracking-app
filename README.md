# Production Tracker

> **Status: Milestone 1 draft.** Sections marked _(Milestone N)_ describe parts that do
> not exist yet; they are filled in when that milestone is delivered.

## Project description

Production Tracker is a web application for a small family business. It replaces
paper notes with one place where every finished job is recorded and can be looked up
later:

- **Production runs:** which machine made which product, with which recipe,
  the machine parameters used, the materials consumed, the output, scrap and the energy
  meter readings.
- **Conversions:** which product was turned into which product (for example
  film into bags), on which machine and with which parameters, with input and output
  quantities.
- **Maintenance log:** preventive and corrective work done on each machine.
- **Material stock:** current stock of every raw material, with a low-stock warning on
  the dashboard when a material drops to its minimum.
- **Master data:** machines, products, materials and recipes, managed by an admin.

Jobs are logged after they are finished, mostly from a PC. The interface is available in
English and Bosnian, and works on phones as well as desktops.

It is a single-page application (SPA) with a REST backend, built for the IT 2001 Web
Programming course at International Burch University.

### Technology stack

| Layer | Technology |
|---|---|
| Frontend | HTML, CSS, JavaScript, jQuery, Bootstrap 5.2 ([SB Admin](https://startbootstrap.com/template/sb-admin) template), Font Awesome, spapp (SPA routing) |
| Backend | PHP 8.2, FlightPHP (REST API), PDO |
| Database | MySQL (developed on MariaDB 10.4, shipped with XAMPP) |
| Authentication | JWT |

## Entities and relationships

> Draft from Milestone 1. The ERD and the `.sql` file are the source of truth once they
> exist; this section is updated to match them.

| Entity (table) | What it stores |
|---|---|
| `users` | Everyone who logs in. Each user has one role: `admin` or `operator`. |
| `machines` | Extruders and bag-making machines. `machine_type` is `extrusion` or `conversion`. |
| `machine_parameters` | The settings recorded for a machine (name, unit), e.g. temperatures or speeds. |
| `products` | What the factory makes: film and bags, with dimensions and unit. |
| `machine_products` | Which products a machine works with. `role` is `input` or `output`. |
| `materials` | Raw materials with current stock and an optional minimum stock. |
| `recipes` and `recipe_items` | A recipe is a mix of materials; each item is one material and its percentage. |
| `production_runs` | One finished extrusion job. |
| `run_parameter_values` | The value of each machine parameter during a production run. |
| `material_usages` | How much of each material a production run consumed. |
| `conversion_runs` | One finished bag-making job. |
| `conversion_run_parameter_values` | The value of each machine parameter during a conversion run. |
| `maintenance_records` | One maintenance job on a machine. |

Relationships:

- A **machine** has many **machine parameters**, and is linked to many **products**
  through **machine products** (many-to-many, with an input/output role).
- A **recipe** has many **recipe items**; each item belongs to one **material**
  (recipes and materials are many-to-many).
- A **production run** belongs to one **user**, **machine**, **product** and **recipe**.
  It has many **run parameter values** (one per machine parameter) and many **material
  usages** (one per material).
- A **conversion run** belongs to one **user** and one **conversion machine**, and
  references two **products**: the input and the output. It has many **conversion run
  parameter values**. Scrap is not stored; it is input weight minus output weight.
- A **maintenance record** belongs to one **machine** and one **user**.
- A material whose `min_stock_qty` is empty never shows a low-stock warning.

## Roles and test accounts

| Role | Can do |
|---|---|
| **Admin** | Everything: manage machines, products, materials, recipes and users, plus everything an operator can do. |
| **Operator** | Log production runs, conversions and maintenance; view runs, conversions and maintenance records; see recipes (including material percentages, needed for mixing), the low-stock warnings and the weather. |

## External API

**[OpenWeather](https://openweathermap.org/api)**: current weather and the forecast for
the next hours, shown in a card on the dashboard. Bad weather, a thunderstorm in
particular, is a reason not to start a production run, so the forecast helps decide
whether to start one.
