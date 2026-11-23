# Todo

Line numbers are exact for commit 0957df5 (main, after the Milestone 3 merge). They move as
Milestone 4 edits those files, so search for the quoted text if a line no longer matches.
Every entry points at the place in the code, README or Swagger that shows the problem. No
`// TODO` comments were added to the code.

## Milestone 4 decisions

- Stale tokens: Option B. `AuthMiddleware` reads `is_active` and `role` from the database on
  every request instead of trusting the token's claims. One extra query, but a deactivated
  user or a changed role takes effect at once. (Departs from the lecture, which is stateless.)
- Role matrix and "may an operator edit someone else's run?": decided after lecture 8.
- `GET /weather` is protected: the dashboard shows only after login.

## Debt

### Authentication and logging (lecture 7)

1. `user_id` is taken from the request body on runs, conversions and maintenance records. It
   must come from the token.
   - README.md:181-182 ("Until login exists (Milestone 4) ... take the `user_id` from the request body")
   - backend/public/v1/docs/doc_setup.php:363, :456, :508 (`user_id` in the `required` lists);
     :369, :460, :512 (the "from the login token in Milestone 4" descriptions). Lines :393, :478,
     :525 are the response schemas and stay.
   - backend/rest/services/ProductionRunService.php:37 (`IDENTITY_FIELDS`), :140-141 (required and integer checks), :228-232 (user lookup and `is_active`)
   - backend/rest/services/ConversionRunService.php:26 (`IDENTITY_FIELDS`), :88 (`$idFields`), :132-136 (user lookup and `is_active`)
   - backend/rest/services/MaintenanceRecordService.php:51 (required), :53 (integer), :80-85 (lookup, `$userIsNewOnRecord`, `is_active`)
   - Tests that build a body with `user_id`: backend/tests/api/ApiTestCase.php:81, :101, :126, :134;
     backend/tests/api/MaintenanceRecordRoutesTest.php:13;
     backend/tests/services/ProductionRunServiceTest.php, ConversionRunServiceTest.php, MaintenanceRecordServiceTest.php (search `user_id`)
2. A deactivated user must not be able to log in once login exists.
   - backend/tests/services/UserServiceTest.php:165 (comment that already expects it)
   - backend/public/v1/docs/doc_setup.php:44 (Swagger already promises it: "A deactivated user cannot log in.")
   - README.md:128 (`former@example.com`: "logging in is refused")
3. Errors go to PHP's `error_log()`, not to `backend/logs/error.log`.
   - backend/index.php:62 (`error_log((string) $exception)`)
   - backend/rest/services/WeatherService.php:192 (`error_log("Weather API (Open-Meteo) unavailable: ...")`)
   - backend/tests/services/WeatherServiceTest.php:20-29 (the test redirects `ini_set('error_log', ...)`, so it must change with the logger)
   - README.md:164-165 ("the details go only to the server log")
4. Nothing writes `backend/logs/info.log`, including the required external-API-call lines.
   - backend/rest/services/WeatherService.php:131 (`curl_init`), :140 (`curl_exec`), :145 (`curl_getinfo` status): status and duration of the Open-Meteo call belong here
   - backend/index.php:106 (`Flight::start()`): request logging has to be registered before this line
   - .gitignore (`backend/logs/*`) and the empty backend/logs/: the logger must create the folder on a fresh clone
5. The error handler and the 404 handler live in `index.php`; they move into middleware, and
   401/403/404/500 get logged there.
   - backend/index.php:49-70 (comment and `Flight::map('error', ...)`), :72-83 (comment and `Flight::map('notFound', ...)`)
   - backend/tests/api/ErrorHandlerTest.php:7, :16, :30 (test the current handler)
   - CLAUDE.md:33 (says "the error handler in index.php")
6. `.htaccess` must pass the `Authorization` header to PHP, or Apache drops the token.
   - backend/.htaccess:3-6
7. The JWT secret and expiry have no config yet.
   - backend/config/config.example.php:4 (comment: "the JWT secret later"); config.php is git-ignored, so add placeholders to the example only

### Frontend that does not match the database (the "step C" moved to M4)

8. The run pages still have energy-meter (kWh) fields. The schema dropped energy.
   - frontend/views/run-new.html:149-163 (`runNewEnergyStart`, `runNewEnergyEnd`)
   - frontend/views/run-detail.html:116-125 (`runDetailEnergyStart`, `runDetailEnergyEnd`)
9. The run pages lack fields the schema has: warm-up start, stable time, net and gross weight
   per unit (production_runs columns).
   - database/production_tracker.sql:138 (`warmup_started_at`), :140 (`stable_at`), :143-144 (net/gross weight)
   - database/production_tracker.sql:155-161 (the CHECKs: warm-up before start, stable between start and end, gross >= net)
   - to add to: frontend/views/run-new.html (Basics fieldset starts at :19, Output fieldset at :131) and frontend/views/run-detail.html
10. The products page has no length field. `products.length_m` exists in the schema and in the DAO.
    - database/production_tracker.sql:69 (`length_m`), :75 (CHECK)
    - backend/rest/dao/ProductDao.php:9 (writable columns)
    - frontend/views/products.html:84-122 (the modal has width, thickness and unit, but no length)
11. The pages do not enforce what the database requires for times (`ended_at > started_at`).
    The earlier audit said "equal times are allowed" for runs too. That is only true for
    maintenance; runs and conversions have no start/end check at all.
    - Maintenance (equal times pass, because `checkFieldOrder` only rejects `later < earlier`):
      frontend/views/maintenance-new.html:32, :37; frontend/views/maintenance-detail.html:43, :48; frontend/js/app.js:204-215 (the `<` comparison)
    - Runs and conversions (separate date + `type="time"` fields, no `data-not-before`):
      frontend/views/run-new.html:23, :28, :33; frontend/views/run-detail.html:45, :50, :55;
      frontend/views/conversion-new.html:16, :21, :26; frontend/views/conversion-detail.html:45, :50, :55
    - Database rules: database/production_tracker.sql:153 (runs), :203 (conversions), :231 (maintenance)
    - Open question for Faris: a shift that crosses midnight (22:00 to 06:00) has an end time
      "before" its start time on a single date field. Decide how the form expresses it.
12. All 21 views hold sample data written into the HTML, and `frontend/js/services/` is empty.
    - frontend/views/ (21 files); frontend/js/app.js is the only script; frontend/js/services/.gitkeep
13. The dashboard weather card shows sample values; the real call takes about 2.6 s, so the
    card must load on its own request (the rest of the dashboard must not wait for it).
    - frontend/views/dashboard.html:76-77 (the comment "Sample values until Milestone 4 ..." and `#weatherCard`)
    - backend/rest/services/WeatherService.php:140 (`curl_exec`, the slow call)
    - README.md:201 ("The card in the frontend still shows sample values")
14. The dashboard low-stock card is sample data and no endpoint feeds it.
    - frontend/views/dashboard.html:36-38 (`#lowStockCard`)
    - frontend/views/materials.html:152-155 (`min_stock_qty` is optional; NULL means "no warning")

### List endpoints

15. The list routes have no filters, paging or sorting, but the pages already show filter forms.
    - Routes: backend/rest/routes/ProductionRunRoutes.php:3, ConversionRunRoutes.php:3,
      MaintenanceRecordRoutes.php:2, MaterialRoutes.php:2, UserRoutes.php:6
    - Fixed `ORDER BY` in the DAOs: backend/rest/dao/ProductionRunDao.php:31, ConversionRunDao.php:31,
      MaintenanceRecordDao.php:21, UserDao.php:16; `getAll()` at backend/rest/dao/BaseDao.php:127
    - Filter forms waiting for it: frontend/views/runs.html:11-40 (from, to, machine, product, search notes),
      frontend/views/conversions.html:11-28 (from, to, output product), frontend/views/maintenance.html:11-28 (machine, type)
    - No route reads the query string yet (`request()->query` appears nowhere in backend/rest/routes/)

### Housekeeping

16. Delete these `.gitkeep` files when the first real file lands in the folder.
    - backend/middleware/.gitkeep (first real file: the auth middleware)
    - frontend/js/services/.gitkeep (first real file: the API client)
17. README.md still describes Milestone 3 and needs a 401/403 section.
    - README.md:3-8 (status says "Milestone 3"), :49 ("JWT (Milestone 4)"), :166 ("`401` and `403` come with login in Milestone 4")
    - README.md:181-182 (the `user_id` note, see 1), :199-201 (weather leftovers), :263-267 (the "Coming in later milestones" table)
    - Test accounts per role already exist at README.md:124-128, so this part of the earlier audit was wrong
18. docs/AI_LOG.md needs a Milestone 4 section with at least 2 cases where the AI was wrong.
    - docs/AI_LOG.md:529 is the last line; the Milestone 3 section starts at :412 ("Where the AI was wrong" at :497)
    - Already known from this milestone's planning: the audit that said runs allow equal start/end times (see 11) and that README lacks test accounts (see 17)
19. CLAUDE.md needs updating when the architecture changes.
    - CLAUDE.md:19 (folder list: `middleware/`), :20 (`utils/`: add the Logger), :33 (error handler "in index.php")

### Later (not Milestone 4)

20. Give the app its own MySQL user before deployment (Milestone 5). Sonar is still skipped.
    - backend/config/config.example.php (database user and password placeholders)

## Milestone 4 commit plan

| # | Commit | Status |
|---|---|---|
| 0 | docs: log the Milestone 4 debt in todo.md | this file |
| 1 | feat: add the Logger (info/error, creates backend/logs/, never logs passwords) | |
| 2 | feat: add the error middleware (items 3, 5) | |
| 3 | feat: add request logging (item 4) | |
| 4 | feat: log external API calls (item 4) | |
| 5 | feat: register, login, JWT, GET /auth/me (items 2, 6, 7) | |
| 6a | feat: protect the API with AuthMiddleware (stale-token decision: Option B) | |
| 6b | docs: document 401 and the Authorize button in Swagger | |
| 7 | refactor: take the user from the token (item 1) | |
| 8 | feat: RoleMiddleware, 403 and the role matrix | waits for lecture 8 |
| 9 | feat: list filters, paging and sorting (item 15) | |
| 10 | feat: low-stock and dashboard endpoints (item 14) | |
| 11 | fix: sync the pages with the schema (items 8-11, checked at 360px) | |
| 12-16 | Frontend API client, then login, master data, runs/conversions/maintenance and dashboard connected (items 12-14) | waits for the lecture that covers the frontend wiring |
| 17 | docs: README, AI_LOG, CLAUDE.md, then the PR description (items 17-19) | last |
