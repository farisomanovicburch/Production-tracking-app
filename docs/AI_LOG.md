# AI Log

## Milestone 1

### Tools and tasks

**1. Planning Milestone 1**
- **Tool:** Claude Code (Claude desktop app, Code tab), model Claude Opus 5.5.
- **Task:** read the course requirements PDF and my old app (read-only, for domain
  knowledge and lessons, not code); list what Milestone 1 needs; make the design
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
- **What I kept, changed or rejected:** AI added too many unecessary things for now such as 
how to run this app localy by yourself and stuff like that. I believe for now they are useless.

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
  shows an Update button. I also dropped the EN/BS translations planned in entry 3: I
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
- **How I noticed:** seeing in the git extension that files in vendor were not tracked
so no unstaged changes there.
- **How I fixed it:** I chose to move the files to `frontend/assets/lib/` and keep
  `.gitignore` unchanged. Lesson: after adding files, check that `git status` lists them.

**3. Overstated licence requirements**
- **What was wrong:** the AI told me that "strict MIT compliance means shipping the
  full licence text" next to the library files, so a separate SB Admin `LICENSE`
  file was added and a Bootstrap one was proposed. That was stricter than the
  libraries themselves ask for: Bootstrap's licence page lists one requirement, keeping
  the copyright and licence notice in its CSS and JS files, and Font Awesome's
  `LICENSE.txt` says the comments already in its files are enough attribution.
- **How I noticed:** i made the AI check Bootstrap's licence page and Font Awesome's
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