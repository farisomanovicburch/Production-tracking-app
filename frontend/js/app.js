// Outside the function so a second call can stop the first timer. Once spapp
// loads the dashboard, every visit to it calls startDashboardClock again, and
// without this each visit would leave one more timer running forever.
let dashboardClockTimer = null;

function startDashboardClock() {
    clearInterval(dashboardClockTimer);

    const clock = document.getElementById('dashboardClock');
    if (!clock) {
        return;
    }

    const locale = document.documentElement.lang === 'bs' ? 'bs-BA' : 'en-GB';
    const dateFormat = new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const timeFormat = new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit' });

    function render() {
        const now = new Date();
        clock.textContent = dateFormat.format(now) + ' · ' + timeFormat.format(now);
        clock.dateTime = now.toISOString();
    }

    render();
    // Ticks every second although only minutes are shown, so a new minute
    // appears at most one second late instead of up to a minute late.
    dashboardClockTimer = setInterval(render, 1000);
}

// spapp routes on the part of the hash before "?"; the part after it carries
// the page's parameters, e.g. the record id in "#run-detail?id=17".
function getHashParam(name) {
    const query = location.hash.split('?')[1] || '';
    return new URLSearchParams(query).get(name);
}

// Record ids are positive whole numbers. Anything else in the address cannot
// name a record, so the page reports it instead of showing an empty record.
function getRecordId() {
    const id = getHashParam('id');
    return /^[1-9]\d*$/.test(id) ? Number(id) : null;
}

// A page that shows one record holds two blocks, the record and a "does not
// exist" message, and exactly one of them is visible. Searching inside the
// section keeps the blocks of the five record pages apart, since all loaded
// views share one document.
function showRecordOrMissing(view) {
    const section = $('#' + view);
    const id = getRecordId();

    section.find('[data-record-missing]').toggleClass('d-none', id !== null);
    section.find('[data-record-content]').toggleClass('d-none', id === null);
    section.find('[data-record-id]').text(id);
}

const app = $.spapp({
    defaultView: 'dashboard',
    templateDir: 'views/',
    pageNotFound: 'not-found'
});

app.route({
    view: 'dashboard',
    onReady: startDashboardClock
});

const recordViews = ['run-detail', 'conversion-detail', 'maintenance-detail', 'machine-setup', 'recipe-setup'];
recordViews.forEach(function (view) {
    app.route({
        view: view,
        onReady: function () {
            showRecordOrMissing(view);
        }
    });
});

app.run();

// Pages a logged-out user sees. They stand alone, without the top bar and sidebar.
const authViews = ['login', 'register'];

// Pages with no sidebar item of their own light up the item of their area.
// A page in neither list nor the sidebar (profile, not-found) lights up nothing.
const sidebarAreaOf = {
    'run-new': 'runs',
    'run-detail': 'runs',
    'conversion-new': 'conversions',
    'conversion-detail': 'conversions',
    'maintenance-new': 'maintenance',
    'maintenance-detail': 'maintenance',
    'machine-setup': 'machines',
    'recipe-setup': 'recipes'
};

// The same rule spapp uses to pick the route: the part of the hash before "?".
function currentView() {
    return location.hash.slice(1).split('?')[0];
}

function highlightSidebar(view) {
    const area = sidebarAreaOf[view] || view;
    const links = $('#sidenavAccordion .nav-link');

    links.removeClass('active').removeAttr('aria-current');
    links.filter('[href="#' + area + '"]').addClass('active').attr('aria-current', 'page');
}

function toggleAuthLayout(view) {
    const isAuthPage = authViews.includes(view);

    $('body').toggleClass('pt-auth-page', isAuthPage);
    // A sidebar left open on a phone would leave its dark backdrop over the page.
    if (isAuthPage) {
        $('body').removeClass('sb-sidenav-toggled');
    }
}

function updateShell() {
    const view = currentView();
    highlightSidebar(view);
    toggleAuthLayout(view);
}

// spapp has no hook that runs on every page change, so the shell listens to
// the same hashchange event itself. The direct call covers the first page,
// which is shown without a hashchange when the address already has a hash.
window.addEventListener('hashchange', updateShell);
updateShell();
