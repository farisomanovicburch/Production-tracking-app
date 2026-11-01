# AI Log

## Milestone 1

### Tools and tasks

**1. Planning Milestone 1**
- **Tool:** Claude Code (Claude desktop app, Code tab), model Claude Opus 5.5.
- **Task:** read the course requirements PDF and my old app
  ([Production-Tracking-App](https://github.com/farisomanovic/Production-Tracking-App),
  read-only, for domain knowledge and lessons, not code); list what Milestone 1 needs; make the design
  decisions the ERD depends on; set up the folder structure, `.gitignore` and the
  one-branch-per-milestone rules in CLAUDE.md.
- **Main prompt:** "List me the things i should do for milestone one. And how we should
  do them etc." Sent with the course PDF, the old repository and my list of new
  features (machine maintenance, EN/BS languages, tracking bag-making machines,
  low-stock warnings, a Bootstrap template).
- **What was generated:** a Milestone 1 checklist, design questions with options, the
  folder structure with `.gitkeep` placeholders, `.gitignore`, CLAUDE.md changes and
  the commit order for the static frontend.
- **What I kept, changed or rejected:** I made the domain decisions from the options
  myself: users only (no separate operators table), conversion runs as their own
  entity, maintenance as a log entered after the work, the potential-buyer field
  dropped, Excel export postponed until after the course. After comparing templates
  with the AI I chose SB Admin (free, MIT); BootstrapMade's AppDashboard is paid and
  its licence doesn't fit a public repository. I rejected the extra `frontend/js/views`
  folder (see below). I kept the folder structure, `.gitignore` and the commit order.

**2. Customising the SB Admin template (Static frontend)**
- **Tool:** Claude Code (Claude desktop app, Code tab), model Claude Opus 5.5.
- **Task:** download SB Admin's CSS and JS, plus local copies of Bootstrap's JavaScript
  and Font Awesome so the app works without internet; build the layout shell
  (`frontend/index.html`) and the colour theme (`frontend/css/app.css`).
- **Main prompt:** a handoff prompt the AI drafted at the end of the planning session
  and I edited, ending with "Start bullet 3."
- **What was generated:** the layout (top bar with EN/BS switch and user menu, sidebar
  with every planned page and the admin-only sections marked, footer), the theme
  overrides, and four colour palettes with contrast checks.
- **What I kept, changed or rejected:** I picked the "Indigo night" palette and the
  name "Production Tracker" from the AI's options. I chose to move the template from
  `assets/vendor/` to `assets/lib/` (see below). I chose to rely on the licence
  headers inside the library files instead of separate licence files (see below).
  I decided to keep SB Admin's 11,000-line CSS whole for now and trim unused rules
  with PurgeCSS once the frontend is finished.

**3. Dashboard, navigation and build order (Static frontend)**
- **Tool:** Claude Code (VS Code extension), model Claude Opus 5.5.
- **Task:** decide the order for building the pages, spapp and translations; fix the
  top bar; turn the dashboard into the first real page (date and time, weather card
  for the external API, quick actions, low-stock card); decide between a sidebar and
  a bottom navigation bar like in my old app.
- **Main prompts:** "Now is it better to create a single main page first, implement
  spapp then rest of the pages, or implement all pages then connect them with spapp",
  then a list of six changes (move the sidebar toggle out of the app name, add a
  phone-width rule to CLAUDE.md, date and time on the dashboard, a place for the
  weather API, the user's name in the top bar instead of the sidebar, no footer), and
  "I am not sure to keep it as sidebar. I am thinking about buttons at the bottom like
  in the old project".
- **What was generated:** explanations of what each library file does (minified
  Bootstrap JS and Font Awesome CSS, SB Admin's sidebar script, why `styles.css` holds
  all of Bootstrap), the dashboard markup, a clock in `frontend/js/app.js`, a
  comparison of sidebar, bottom bar and a hybrid, and a list of possible dashboard
  widgets.
- **What I kept, changed or rejected:** I chose spapp before the pages, with EN/BS
  translations as their own commit right after spapp so every page gets translation
  keys from the start. I kept the sidebar: I log runs on a PC after they finish, so a
  phone-style bottom bar would not help me. The AI argued against putting navigation
  buttons on the dashboard because the sidebar already has them; I still added list
  buttons (Runs, Conversions, Maintenance) next to the create buttons. I made the
  low-stock card visible to everyone, not only admins, so an operator can warn me when
  I am away. I rejected the extra dashboard widgets it proposed (KPI tiles, recent runs,
  output chart, machine overview) and dropped "in progress" runs, because in practice I
  always log a run once it is finished. I removed the breadcrumb and made the clock bigger.

**4. README draft**
- **Tool:** Claude Code (VS Code extension), model Claude Opus 5.5.
- **Task:** write the README draft (a Milestone 1 deliverable) with every section the
  course requires: project description, entities and relationships, roles and test
  accounts, the external API and its feature, running the project locally, the Swagger
  link and the live link.
- **Main prompt:** "Update the now empty readme file according to those", sent with the
  README requirements from the course PDF.
- **What was generated:** the full `README.md`. Entities, roles and the weather feature
  come from the design decisions made earlier in Milestone 1; anything that does not
  exist yet (the `.sql` file, `config.example.php`, test accounts, Swagger, the live
  link) is a placeholder that names the milestone that adds it.
- **What I kept, changed or rejected:** I kept the description, the entities, the roles
  and the external API section. I removed the sections on running the app with the
  database, the test accounts, Swagger and the live link for now, because none of those
  exist yet; each one is added in the milestone that creates it (2, 3 and 5), since the
  final README has to contain them.

**5. Final page list**
- **Tool:** Claude Code (VS Code extension), model Claude Opus 5.5.
- **Task:** settle every page of the application before finishing the sidebar and
  adding spapp.
- **Main prompt:** my list of pages (dashboard, new and list pages for runs,
  conversions and maintenance, users, machines, products, materials, recipes,
  login/signup, a details page for finished jobs and a Machine Setup page), with "If
  you think i am missing any pages let me know."
- **What was generated:** a review of the list with the pages it was missing and the
  design questions behind them.
- **What I kept, changed or rejected:** I added the pages it suggested: Recipe Setup
  (a recipe is a list of materials, like a machine's parameters), Profile (the user menu
  already linked to it), a 404 page, and separate Login and Register pages. I chose
  three details pages (run, conversion, maintenance) instead of one shared page. For
  material deliveries I chose an "Add delivery" button with a modal on the Materials
  page, because I do not need a delivery history. The AI suggested correcting a
  finished job by opening the "New" form filled in with its values; I rejected that as
  too complex and chose an Edit button on the details page that unlocks its fields and
  shows an Update button. I took the AI's suggestion that the sidebar lists only areas,
  not actions: "New run" was removed from it, and new records are created from the
  dashboard or the "New" button on each list page. That also lets every page highlight
  the area it belongs to. I also dropped the EN/BS translations planned in entry 3: I
  am the only person using the app, so they only added complexity. The language switch
  was removed from the top bar; I may add translations after the course.

**6. Single-page navigation with spapp**
- **Tool:** Claude Code (VS Code extension), model Claude Opus 5.5.
- **Task:** make the frontend a single-page application with spapp, the jQuery router
  from the course: add jQuery and spapp locally, split `index.html` into a shell and one
  view file per page, pass a record id in the address for the details and setup pages,
  show a "does not exist" message for a missing or invalid id, highlight the current
  area in the sidebar, and show Login and Register without the top bar and sidebar.
- **Main prompts:** "do you have the link i gave you earlier for the jquery spapp ... and
  does it use AJAX under the hood", "make sure i understand it properly dont let me vibe
  code it", and "In each message were only doing one step".
- **What was generated:** a line-by-line explanation of spapp's source, five options for
  passing a record id (a variable, `sessionStorage`, the address, modals, our own
  router) with their effect on refresh, the back button and bookmarks, the view files,
  the code in `js/app.js` and `css/app.css`, and browser test scripts run before and
  after each change (kept outside the repository).
- **What I kept, changed or rejected:** I chose to keep the id in the address
  (`#run-detail?id=17`), because it is the only option where refresh, the back button
  and bookmarks all show the right record. I read spapp's source myself and explained it
  back before changing anything, and I typed the change to spapp myself: the route name
  is the part of the address before `?`, and the visible page is marked with a class
  instead of the CSS `:target` rule. I committed the original spapp first, so the next
  commit shows exactly what I changed. When I commented out the old spapp CSS, the AI
  pointed out that Git already keeps the original, so I deleted it instead. I dropped
  the plan to trim the template's unused CSS.

**7. Login and Register pages**
- **Tool:** Claude Code (VS Code extension), model Claude Opus 5.5.
- **Task:** build the Login and Register pages: a card centred on the app's brand
  colour (these pages have no top bar), forms with Bootstrap's validation styles, and a
  check that both passwords match. Until the backend exists, a valid form only moves to
  the next page.
- **Main prompt:** "I have commited start working on 3a".
- **What was generated:** both views, the form helpers in `js/app.js` (validation,
  clearing a form on every visit, matching passwords), the background colour in
  `css/app.css`, and browser tests written before the code.
- **What I kept, changed or rejected:** I kept everything.

**8. Admin pages**
- **Tool:** Claude Code (VS Code extension), model Claude Opus 5.5.
- **Task:** build Machines, Machine setup, Products, Materials (with "Add delivery"),
  Recipes, Recipe setup and Users: list tables with Setup, Edit and Delete buttons, one
  dialog per entity used for both Add and Edit, and small "Add" forms under the tables
  on the setup pages.
- **Main prompt:** "3b get to work".
- **What was generated:** the seven views with generic sample data, the dialog and form
  handlers in `js/app.js` (attached once to the whole document so they also serve
  pages loaded later), and browser tests written before the code.
- **What I kept, changed or rejected:** I kept everything.

**9. Runs, conversions, maintenance, profile and 404**
- **Tool:** Claude Code (VS Code extension), model Claude Opus 5.5.
- **Task:** build the remaining pages: lists with filters for runs, conversions and
  maintenance; a four-step wizard for a new run with a recipe calculator; forms for a
  new conversion (scrap shown as input minus output) and a new maintenance record;
  details pages that open locked and unlock with Edit (Update and Cancel); the profile
  page and the 404 page.
- **Main prompt:** "Build 3c now. Since i dont have time i will not try to understand
  anything ... i just need this 3c built so tommorow i can focus on understanding".
- **What was generated:** eleven views with generic sample data, the wizard, calculator,
  field-order checks and edit handlers in `js/app.js`, and browser tests written before
  the code.
- **What I kept, changed or rejected:** I kept everything.

**10. Draft ERD**
- **Tool:** Claude Code (VS Code extension), model Claude Opus 5.5.
- **Task:** turn the design decisions, my old app's schema and the fields on the new
  pages into a list of tables, columns and relationships, and draw it as the Milestone 1
  ERD.
- **Main prompt:** "list all the tables, their individual collumns and all the
  relationship ... would you be able to generate a picture as ERD", sent with "Take a
  deep look at the old project and all the new incoming changes. Feel free to suggest
  some ideas or changes."
- **What was generated:** a proposal of 14 tables with columns, keys and checks, the
  relationships, the rules only the backend can enforce, six questions for me, and after
  my answers `docs/erd.md` (a Mermaid diagram GitHub draws itself) with SVG and PNG
  exports.
- **What I kept, changed or rejected:** I kept the proposal and answered its questions:
  I kept the table that links recipes to products (the New run form does not use it
  yet), kept the warm-up and "stable" times and the net and gross weight per unit from
  my old app, added the product length, and chose date-and-time columns for when a run
  starts and ends. I removed the energy meter readings, which I do not need. Operators
  see all runs, because they often need older runs as a reference. The pages still have
  to be brought in line with these changes after Milestone 1.

### Where the AI was wrong

**1. Two folders called `views`**
- **What was wrong:** the AI created `frontend/js/views/` (page JavaScript) next to
  `frontend/views/` (page HTML). Two folders with the same name held different things;
  in MVC terms the JavaScript one held controllers, not views. When I asked, the AI
  first suggested renaming it to `js/controllers/`.
- **How I noticed:** I saw two `views` folders in the project and asked why.
- **How I fixed it:** I asked whether we need that folder at all, since last year page
  logic lived in spapp's `onCreate`/`onReady` callbacks. The AI agreed it isn't needed
  yet. I deleted the folder and its line in CLAUDE.md; page logic stays in `js/app.js`
  until that file grows too big.

**2. Template files in a folder that `.gitignore` ignores**
- **What was wrong:** the plan put the SB Admin files in `frontend/assets/vendor/`.
  The `.gitignore` from the same planning session has the rule `vendor/`, meant for
  Composer. With no slash at the start, it matches every folder named `vendor` at any
  depth, so Git silently ignored the template. The page looked fine on my machine, but
  a fresh clone (what the reviewer gets) would have been unstyled.
- **How I noticed:** the git extension showed no changes for the files in `vendor`, so
  Git was not tracking them.
- **How I fixed it:** I chose to move the files to `frontend/assets/lib/` and keep
  `.gitignore` unchanged. Lesson: after adding files, check that `git status` lists them.

**3. Overstated licence requirements**
- **What was wrong:** the AI told me that "strict MIT compliance means shipping the
  full licence text" next to the library files, so a separate SB Admin `LICENSE`
  file was added and a Bootstrap one was proposed. That was stricter than the
  libraries themselves ask for: Bootstrap's licence page lists one requirement, keeping
  the copyright and licence notice in its CSS and JS files, and Font Awesome's
  `LICENSE.txt` says the comments already in its files are enough attribution.
- **How I noticed:** I made the AI check Bootstrap's licence page and Font Awesome's
  `LICENSE.txt` before answering whether Bootstrap's licence file was really needed.
- **How I fixed it:** I removed the separate licence file and kept the minimum: the
  unmodified licence header at the top of every library file. Lesson: check what a
  licence actually requires in the author's own words, and never strip those headers
  (for example when minifying or trimming CSS later).

**4. Advice that would have caused rework (translations)**
- **What was wrong:** the AI first recommended building the EN/BS translation
  mechanism separately from spapp, implying it could wait because translations are not
  graded in this part. If I had built all pages first, every finished page would have
  needed translation keys added afterwards, the same rework the AI had just used as
  its argument for doing spapp before the pages.
- **How I noticed:** I asked the AI to remind me what we had decided about EN/BS. While
  explaining it again, the AI saw the contradiction and corrected its recommendation.
- **How I fixed it:** I chose to build translations as a separate commit right after
  spapp and before any page, so every page is written with translation keys from the
  start. Lesson: when the AI gives a reason for one decision, check that it applies the
  same reason to the next one.

**5. A Bootstrap class from the wrong version**
- **What was wrong:** for the dashboard header the AI used `column-gap-3`, a class that
  exists only in Bootstrap 5.3. The template uses Bootstrap 5.2.3, so the class would
  have done nothing, without any error in the browser.
- **How I noticed:** the AI searched the local `styles.css` for every class it had used
  and found no `column-gap-3` rule.
- **How I fixed it:** it was replaced with `gap-2`, which exists in 5.2. Lesson: check
  classes against the CSS file we actually ship, not against the latest Bootstrap
  documentation.

**6. Dashboard that broke at phone width**
- **What was wrong:** the first version of the low-stock table was wider than a 360px
  screen (the Status column was cut off and "500 kg" broke onto two lines), and the
  quick-action buttons came out with uneven widths and two-line labels on a phone.
- **How I noticed:** the AI took screenshots of the page at 360px wide, which the new
  CLAUDE.md rule requires for every frontend change.
- **How I fixed it:** the Minimum column is hidden on phones (the status badge already
  shows how short a material is), numbers no longer wrap, and each pair of quick-action
  buttons is stacked at full width. Lesson: a page that looks right on my PC proves
  nothing about a phone; check it at phone width every time.

**7. Editing a finished job through the "New" form**
- **What was wrong:** to correct a finished run, conversion or maintenance record, the
  AI suggested an Edit button that opens the "New" form filled in with the saved values,
  so every "New" form would work in two modes. New Run is a multi-step wizard, and
  filling a wizard with saved values, while keeping its steps and dependent fields
  correct, is far more complex than the problem needs.
- **How I noticed:** I thought about how it would work for the New Run wizard, saw that
  it added unnecessary complexity, and asked for a different approach.
- **How I fixed it:** the Edit button on the details page unlocks the fields on that
  same page and shows Update and Cancel buttons. All fields sit in one
  `<fieldset disabled>`, so one attribute locks or unlocks them all. Fields that
  other fields depend on (the machine, product and recipe of a run; the machine and
  products of a conversion) stay locked; if one of them is wrong, the record is deleted
  and logged again. The "New" pages and the details pages share one validation function
  per entity. Lesson: the AI's first idea reused existing code, but reusing it was more
  complex than building a simpler page; ask how a suggestion works for the hardest case.

**8. A bug described as always happening when it only happens sometimes**
- **What was wrong:** the AI reported that opening a long page straight from its address
  (a bookmark, a new tab) always scrolls the page so its heading hides under the fixed
  top bar, and that it happens whenever the page is taller than the window. In fact it
  only happens sometimes: the browser jumps to the section named in the address only if
  spapp has loaded that page before the browser finishes loading, which happened in 1
  of 4 tries.
- **How I noticed:** I tried to reproduce it on a phone-sized window in a new tab and
  it did not happen, so I told the AI. It measured again and found the timing race.
- **How I fixed it:** one CSS rule, `scroll-margin-top: 80px` on the page sections, makes
  the jump, whenever it happens, stop where the page normally sits (56px top bar plus
  the 24px space above each heading). The test performs the jump itself instead of
  waiting for the race, so it fails without the rule every time. Lesson: reproduce a
  reported bug myself before accepting it, and be suspicious of bugs that only one
  person can see.

## Milestone 2

### Tools and tasks

**1. Planning Milestone 2**
- **Tool:** Claude Code (VS Code extension), model Claude Opus 5.5.
- **Task:** turn the Milestone 2 requirements into steps with one commit each, using the
  course repository's week-03 DAO code as the reference for syntax.
- **Main prompt:** "We now need to implement milestone 2. First we will create steps that i
  will need to do in order to finish the milestone … For dao layer syntax and context use
  this github repo … First plan all the steps and write where each commit should be for
  clean history".
- **What was generated:** a step plan with eight commits, and questions about the test
  tool, method naming, when the database may be dropped, and who creates the branch.
- **What I kept, changed or rejected:** I kept everything the plan was good.

**2. PHP, Composer and the database server**
- **Tool:** Claude Code (VS Code extension), model Claude Opus 5.5.
- **Task:** install Composer, decide which PHP version to develop on, and make the local
  PHP and database usable for the backend and its tests.
- **Main prompt:** "do you think we should use newer version of php and phpunit. I also
  heard about php2shell and the vunreablitys about php so what do you suggest".
- **What was generated:** an assessment of the PHP2Shell flaws and of PHP 8.2's end of
  support, two options (keep XAMPP's PHP 8.2 or install PHP 8.4), step-by-step install
  instructions, and the finding that the database on port 3306 is a separate MySQL 8.0
  service, not XAMPP's MariaDB.
- **What I kept, changed or rejected:** I choose to move to MySQL intead of MariaDB and newer PHP version.

**3. Database schema and sample data**
- **Tool:** Claude Code (VS Code extension), model Claude Opus 5.5.
- **Task:** write `database/production_tracker.sql` from the ERD, with sample data matching
  the Milestone 1 pages, and tests written before each constraint and before the data.
- **Main prompts:** my answers to its questions: which constraints get a test, the order of
  a run's times ("warmup started at < started at < stable at < ended at and always like
  this"), unique recipe names, and the test passwords.
- **What was generated:** 15 tables with named keys and checks, the sample data, a test
  bootstrap that rebuilds the database before every run, and tests for six constraints and
  for the sample data.
- **What I kept, changed or rejected:** The database looked okay so i left everything.

**4. DAO layer**
- **Tool:** Claude Code (VS Code extension), model Claude Opus 5.5.
- **Task:** BaseDao with generic CRUD and one DAO per table, test-first.
- **Main prompt:** "Okay lets move to step 4 while you start working on it just give me all
  the substeps of it".
- **What was generated:** `BaseDao` (shared connection, prepared statements, a column
  whitelist), 15 DAOs with JOIN queries for the list and setup pages, tests for each, and
  sabotage runs showing that each important test can fail.
- **What I kept, changed or rejected:** Everything.

**5. Documentation**
- **Tool:** Claude Code (VS Code extension), model Claude Opus 5.5.
- **Task:** bring the README, the ERD and CLAUDE.md in line with the database and DAOs.
- **Main prompt:** "Okay start working on commit 7 as always write the substeps of it".
- **What was generated:** README sections on test accounts and running the project, the ERD
  matching the SQL with re-rendered pictures, and new backend rules in CLAUDE.md.
- **What I kept, changed or rejected:** Everything.

### Where the AI was wrong

**1. The wrong database server**
- **What was wrong:** the AI assumed the database was XAMPP's MariaDB and had me limit
  MariaDB to my own computer. The server on port 3306 was a separate MySQL 8.0 service
  installed a year earlier, so the change did nothing and the database stayed open to the
  network.
- **How I noticed:** the port check I ran afterwards still showed port 3306 open on all
  network addresses, and the AI traced it to the MySQL80 service.
- **How I fixed it:** I chose to use MySQL 8 (the course requires MySQL), limited that
  server and its second port (33060) to 127.0.0.1, and left XAMPP's MariaDB unused.
  Lesson: check which program is really listening before changing a configuration file.

**2. A PHP version picked without checking its security status**
- **What was wrong:** the AI's first `composer.json` targeted XAMPP's PHP 8.2 and PHPUnit 11
  without checking whether PHP 8.2 was safe. XAMPP's 8.2.12 is affected by the PHP2Shell
  flaws, and PHP 8.2 stops getting security fixes on 31.12.2026, before the Milestone 5
  deployment.
- **How I noticed:** I asked about PHP2Shell before running `composer install`.
- **How I fixed it:** I installed PHP 8.4.27, moved to PHPUnit 13, and limited XAMPP's Apache
  to my own computer. Lesson: check the support status of a runtime before building on it.

**3. A wrong prediction of the assertion count**
- **What was wrong:** the AI predicted "OK (7 tests, 8 assertions)"; PHPUnit reported 13,
  because `expectException` and `expectExceptionMessageMatches` count as assertions too.
- **How I noticed:** I compared the number in the output with the prediction and asked.
- **How I fixed it:** nothing in the code was wrong; the AI corrected its explanation.
  Lesson: compare the output with the prediction, not only "OK" with "FAIL".

**4. Test data that collided with the sample data**
- **What was wrong:** the schema tests created a test user `operator@example.com`. Once the
  sample data seeded the same email, four schema tests failed on the unique email rule. One
  of them would even have passed for the wrong reason without its check on the exception
  message.
- **How I noticed:** the green run after the sample data commit had four failures.
- **How I fixed it:** the tests use their own emails, and later all test data comes from
  helpers that number every code and email. Lesson: test data must never reuse values that
  real or sample data can contain.

**5. A test file cut off halfway**
- **What was wrong:** the AI wrote several test files in one shell command, which stopped
  partway. `ConversionRunParameterValueDaoTest.php` ended in the middle of a line and
  `MaintenanceRecordDaoTest.php` was missing.
- **How I noticed:** VS Code showed "Unclosed '['" in that test file.
- **How I fixed it:** the AI rewrote both files with its file tool and checked every test file
  with `php -l`. Lesson: after generated code is written, check that the files are complete.