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

const app = $.spapp({
    defaultView: 'dashboard',
    templateDir: 'views/',
    pageNotFound: 'not-found'
});

app.route({
    view: 'dashboard',
    onReady: startDashboardClock
});

app.run();
