# Production Tracker

> **Status: Milestone 3.** The backend is complete as a REST API: a service for every
> entity with the business rules, FlightPHP routes for all create, read, update and delete
> operations, OpenAPI documentation shown with Swagger UI, and the weather from the external
> Open-Meteo API. The frontend is still static: every page exists and navigation works as a
> single-page application, but the pages show sample data written into the HTML. Milestone 4
> connects the pages to the API and adds login.

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
| Backend | PHP 8.4, FlightPHP (REST API), PDO, Composer |
| API documentation | OpenAPI 3 generated with swagger-php, shown with Swagger UI |
| External API | [Open-Meteo](https://open-meteo.com/) (weather) |
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

## REST API

The backend is a REST API built with FlightPHP: 70 endpoints that create, read, update and
delete the data of all 15 tables, plus the weather.

**Documentation:** Swagger UI at <http://localhost:8000/backend/public/v1/docs/> once the
project runs locally (see [Running the project locally](#running-the-project-locally)).
Every endpoint shows its request body, its possible answers and examples, and *Try it out*
sends real requests.

| Resource | Endpoints |
|---|---|
| Users | `GET, POST /users` · `GET, PUT, DELETE /users/{id}` |
| Machines | `GET, POST /machines` · `GET, PUT, DELETE /machines/{id}` |
| Machine parameters | `GET, POST /machines/{id}/parameters` · `GET, PUT, DELETE /machine-parameters/{id}` |
| Machine products | `GET, POST /machines/{id}/products` · `DELETE /machine-products/{id}` |
| Products | `GET, POST /products` · `GET, PUT, DELETE /products/{id}` |
| Materials | `GET, POST /materials` · `GET, PUT, DELETE /materials/{id}` · `PUT /materials/{id}/delivery` |
| Recipes | `GET, POST /recipes` · `GET, PUT, DELETE /recipes/{id}` |
| Recipe items | `GET, POST /recipes/{id}/items` · `PUT, DELETE /recipe-items/{id}` |
| Recipe products | `GET, POST /recipes/{id}/products` · `PUT, DELETE /recipe-products/{id}` |
| Production runs | `GET, POST /production-runs` · `GET, PUT, DELETE /production-runs/{id}` |
| Run parameter values | `GET, POST /production-runs/{id}/parameter-values` · `PUT, DELETE /run-parameter-values/{id}` |
| Material usages | `GET, POST /production-runs/{id}/material-usages` · `PUT, DELETE /material-usages/{id}` |
| Conversion runs | `GET, POST /conversion-runs` · `GET, PUT, DELETE /conversion-runs/{id}` |
| Conversion parameter values | `GET, POST /conversion-runs/{id}/parameter-values` · `PUT, DELETE /conversion-run-parameter-values/{id}` |
| Maintenance records | `GET, POST /maintenance-records` · `GET, PUT, DELETE /maintenance-records/{id}` |
| Weather | `GET /weather` |

How the API behaves:

- Requests and answers are JSON. A successful create answers `201`, every other success `200`.
- Every error has the same shape, for example
  `{"status": 400, "error": "The selected machine is not an extrusion machine."}`:
  `400` for invalid input or a broken business rule, `404` for an unknown id or address,
  `500` for an unexpected server error (the details go only to the server log), `503` when
  the weather service is unavailable. `401` and `403` come with login in Milestone 4.
- Times can be sent as `2026-11-02T06:00` (what a `datetime-local` field sends) or as
  `2026-11-02 06:00:00`; answers always use the second form. Decimal amounts are returned as
  strings with fixed decimals (`"500.000"`), so no precision is lost on the way.
- The services enforce the rules the database cannot check, listed in
  [docs/erd.md](docs/erd.md#rules-the-database-cannot-check): for example, a run's product
  must be an output of its machine, its recipe must be linked to that product and add up to
  100 %, and every active parameter of the machine needs a value.
- A production run is created with one request that includes its parameter values and
  material usages. Everything is saved in one transaction together with the stock
  decrease: if any part is refused (for example, not enough stock), nothing is saved.
  Editing a usage moves the stock by the difference; deleting a run puts its material back.
- Master data that runs still use cannot be deleted (answer `400`); it is deactivated
  instead. Deactivated machines, products, recipes, materials and users cannot be used for
  new runs and conversions, but old records that use them stay editable.
- Until login exists (Milestone 4), runs, conversions and maintenance records take the
  `user_id` from the request body.

## External API

**[Open-Meteo](https://open-meteo.com/)** (free for non-commercial use, no account or API
key): the current weather and the forecast for the next hours at the factory, for a card on
the dashboard. A thunderstorm is a reason not to start a production run, so `GET /weather`
also says whether one is expected at any time in the next 12 hours.

- The backend calls Open-Meteo, never the browser. The API address and the factory's
  location are settings (`WEATHER_*`) in `backend/config/config.php`. Open-Meteo needs no
  key, so there is no secret to store.
- The request times out after 3 seconds to connect and 5 seconds in total. If Open-Meteo is
  unreachable, too slow or answers with an error, the server logs the reason and
  `GET /weather` answers `503` with a short message; every other endpoint keeps working.
- Weather data by [Open-Meteo.com](https://open-meteo.com/), licensed under
  [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/); the dashboard card credits it.
- Milestone 1 planned OpenWeather. It was replaced by Open-Meteo in Milestone 3 because
  OpenWeather requires an account.
- The card in the frontend still shows sample values; Milestone 4 connects it to the endpoint.

## Running the project locally

### Requirements

- PHP 8.4 with the `pdo_mysql`, `mbstring`, `openssl`, `curl` and `zip` extensions enabled
- MySQL 8
- [Composer](https://getcomposer.org/)
- For the weather, PHP must be able to check HTTPS certificates. Linux and macOS usually can
  already. On Windows, PHP has no certificate list of its own: download
  [cacert.pem](https://curl.se/ca/cacert.pem) (for example to `C:\php\8.4\extras\ssl\`)
  and point `curl.cainfo` and `openssl.cafile` in `php.ini` to it. Without it, `GET /weather`
  always answers `503` and the server log mentions an SSL certificate.

### Database and backend

1. **Create the database** by importing the script:
   ```
   mysql -u root -p < database/production_tracker.sql
   ```
   (or open the file in MySQL Workbench or DataGrip and run it). The script drops and
   recreates the whole `production_tracker` database, so never keep data you need in it.
2. **Create the configuration file:** copy `backend/config/config.example.php` to
   `backend/config/config.php` and enter your MySQL user and password (the weather
   settings can stay as they are). `config.php` is ignored by Git because it holds secrets.
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
   starts from the same sample data. The API tests start their own PHP servers and a fake
   weather service, so no server has to be running and no internet is needed. The one test
   that calls the real Open-Meteo runs only on request:
   ```
   php vendor/bin/phpunit --group internet
   ```
5. **Start the server** from the project folder (not `backend/`):
   ```
   php -S localhost:8000 server.php
   ```

| Address | What |
|---|---|
| <http://localhost:8000/frontend/> | The app (<http://localhost:8000> redirects there) |
| <http://localhost:8000/backend/> | The REST API, for example <http://localhost:8000/backend/machines> |
| <http://localhost:8000/backend/public/v1/docs/> | Swagger UI |

With Apache (XAMPP), `backend/.htaccess` routes the API requests instead of `server.php`.

### Frontend

The pages are loaded with AJAX, so opening `frontend/index.html` straight from disk
(`file://`) does not work; the `frontend` folder has to be served over HTTP. The server from
step 5 does that. VS Code's **Live Server** extension also works for the pages (go to
`/frontend/`), but it does not run the API.

## Coming in later milestones

| Milestone | Adds to this README |
|---|---|
| 4 | Login with JWT, roles enforced by the API, the pages connected to the API |
| 5 | The link to the deployed application and its Swagger documentation |
