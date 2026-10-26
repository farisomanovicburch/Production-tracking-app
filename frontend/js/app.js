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

// Bootstrap colours fields red or green only inside a form with the class
// was-validated; novalidate on the form turns off the browser's own pop-ups,
// so these styles are the only feedback.
function validateForm(form) {
    form.addClass('was-validated');
    return form[0].checkValidity();
}

function bindValidatedForm(form, onValid) {
    form.on('submit', function (event) {
        event.preventDefault();
        if (validateForm(form)) {
            onValid();
        }
    });
}

// reset() puts every field back to the value written in the HTML. It does not
// touch custom errors (setCustomValidity) or red outlines, so those are cleared too.
function resetForm(form) {
    form[0].reset();
    form.find('.was-validated').addBack().removeClass('was-validated');
    form.find('input, select, textarea').each(function () {
        this.setCustomValidity('');
    });
}

// HTML has no attribute for "must equal another field", so the confirmation
// field is marked invalid by hand whenever either password changes.
function requireMatchingPasswords(password, confirmation) {
    function compare() {
        const matches = confirmation.val() === password.val();
        confirmation[0].setCustomValidity(matches ? '' : 'Type the same password again.');
    }
    password.on('input', compare);
    confirmation.on('input', compare);
}

// The handlers below sit on document instead of on each form ("event
// delegation"): an event on a form bubbles up to document, where jQuery checks
// whether it came from an element matching the selector. One handler then
// serves every matching form, including forms in views spapp has not loaded yet.

// Add and Edit open the same dialog. It starts empty each time, and takes its
// title from the button that opened it (data-modal-title).
$(document).on('show.bs.modal', '.modal', function (event) {
    const modal = $(this);
    const form = modal.find('form');
    const title = $(event.relatedTarget).data('modal-title');

    if (form.length) {
        resetForm(form);
    }
    if (title) {
        modal.find('.modal-title').text(title);
    }
});

// Until the API exists (M4) a valid dialog has nothing to save it to, so it just closes.
$(document).on('submit', '.modal form', function (event) {
    event.preventDefault();
    const form = $(this);

    if (validateForm(form)) {
        bootstrap.Modal.getInstance(form.closest('.modal')[0]).hide();
    }
});

// The small "Add" forms under the tables on the setup pages: valid input is
// cleared for the next entry (in M4 it is also added as a row).
$(document).on('submit', 'form[data-inline-add]', function (event) {
    event.preventDefault();
    const form = $(this);

    if (validateForm(form)) {
        resetForm(form);
    }
});

// Filter and profile forms only validate for now. Without preventDefault a
// submit would reload the whole app; filtering and saving arrive with the API (M4).
$(document).on('submit', 'form[data-validate-only]', function (event) {
    event.preventDefault();
    validateForm($(this));
});

// New run, conversion and maintenance: once valid, go back to the list the
// record belongs to (data-after-save). In M4 the record is sent to the API first.
$(document).on('submit', 'form[data-new-form]', function (event) {
    event.preventDefault();
    const form = $(this);

    if (validateForm(form)) {
        location.hash = form.data('after-save');
    }
});

// ---------- Wizard (New run) ----------

// Steps are fieldsets with data-wizard-step; only one is visible at a time.
function showWizardStep(wizard, index) {
    const steps = wizard.find('[data-wizard-step]');
    const last = steps.length - 1;

    steps.addClass('d-none').eq(index).removeClass('d-none');
    wizard.data('step', index);
    wizard.find('[data-wizard-back]').toggleClass('d-none', index === 0);
    wizard.find('[data-wizard-next]').toggleClass('d-none', index === last);
    wizard.find('[data-wizard-submit]').toggleClass('d-none', index !== last);
    wizard.find('[data-wizard-progress] li').each(function (i) {
        $(this).toggleClass('text-primary fw-semibold', i === index)
            .toggleClass('text-success', i < index)
            .attr('aria-current', i === index ? 'step' : null);
    });
}

// Next checks only the fields of the current step, so an error is shown where
// the user can see and fix it, not in a step that is hidden.
$(document).on('click', '[data-wizard-next]', function () {
    const wizard = $(this).closest('[data-wizard]');
    const index = wizard.data('step') || 0;
    const step = wizard.find('[data-wizard-step]').eq(index);
    const fields = step.find('input, select, textarea').toArray();

    step.addClass('was-validated');
    if (fields.every(function (field) { return field.checkValidity(); })) {
        showWizardStep(wizard, index + 1);
    }
});

$(document).on('click', '[data-wizard-back]', function () {
    const wizard = $(this).closest('[data-wizard]');
    showWizardStep(wizard, (wizard.data('step') || 0) - 1);
});

// ---------- Calculated and compared fields ----------

// Recipe calculator: each material's amount is its share (data-share, in %)
// of the batch total. The operator can still overwrite what was really used.
$(document).on('input', '[data-batch-total]', function () {
    const total = Number($(this).val());

    $(this).closest('form').find('[data-share]').each(function () {
        $(this).val(total > 0 ? (total * $(this).data('share') / 100).toFixed(1) : '');
    });
});

// A field with data-not-before="#otherField" may not be smaller than that
// field: an end time before its start, a meter reading lower than the first
// one, more output than input. Rechecked whenever anything in the form changes.
function checkFieldOrder(form) {
    form.find('[data-not-before]').each(function () {
        const earlier = $($(this).data('not-before')).val();
        const later = $(this).val();
        const isNumber = this.type === 'number';
        const tooSmall = earlier !== '' && later !== '' &&
            (isNumber ? Number(later) < Number(earlier) : later < earlier);

        this.setCustomValidity(tooSmall ? $(this).data('order-message') : '');
    });
}

// Scrap is never stored (decided for the ERD): it is input minus output.
function showScrap(form) {
    form.find('[data-scrap-from]').each(function () {
        const input = $($(this).data('scrap-from')).val();
        const output = $($(this).data('scrap-minus')).val();

        $(this).text(input !== '' && output !== '' ? (Number(input) - Number(output)).toFixed(1) + ' kg' : '—');
    });
}

$(document).on('input change', 'form', function () {
    checkFieldOrder($(this));
    showScrap($(this));
});

// ---------- Details pages: view, Edit, Cancel, Update ----------

// A saved record is shown locked. Edit unlocks the fieldset; fields that other
// fields depend on (machine, products, recipe) are plain text outside it, so they
// stay locked. Cancel puts back the saved values: reset() returns every field to
// the value written in the HTML, which on these pages is the saved record.
function setEditing(form, editing) {
    form.find('fieldset[data-editable]').prop('disabled', !editing);
    form.find('[data-edit-start]').toggleClass('d-none', editing);
    form.find('[data-edit-actions]').toggleClass('d-none', !editing);
}

function lockEditForms(section) {
    section.find('form[data-edit-form]').each(function () {
        resetForm($(this));
        setEditing($(this), false);
    });
}

$(document).on('click', '[data-edit-start]', function () {
    setEditing($(this).closest('form'), true);
});

$(document).on('click', '[data-edit-cancel]', function () {
    const form = $(this).closest('form');
    resetForm(form);
    setEditing(form, false);
});

// Until the API exists (M4), a valid Update only locks the form again.
$(document).on('submit', 'form[data-edit-form]', function (event) {
    event.preventDefault();
    const form = $(this);

    if (validateForm(form)) {
        form.removeClass('was-validated');
        setEditing(form, false);
    }
});

const app = $.spapp({
    defaultView: 'dashboard',
    templateDir: 'views/',
    pageNotFound: 'not-found'
});

app.route({
    view: 'dashboard',
    onReady: startDashboardClock
});

// Handlers are attached in onCreate, which runs once per view. In onReady,
// which runs on every visit, each visit would add another handler and one
// click would submit the form several times. The form is cleared on every
// visit instead, so Logout never shows the previous user's email.
// Until the backend exists (M4), a valid form only moves to the next page.
app.route({
    view: 'login',
    onCreate: function () {
        bindValidatedForm($('#loginForm'), function () {
            location.hash = 'dashboard';
        });
    },
    onReady: function () {
        resetForm($('#loginForm'));
    }
});

app.route({
    view: 'register',
    onCreate: function () {
        requireMatchingPasswords($('#registerPassword'), $('#registerPasswordConfirm'));
        bindValidatedForm($('#registerForm'), function () {
            location.hash = 'login';
        });
    },
    onReady: function () {
        resetForm($('#registerForm'));
    }
});

const recordViews = ['run-detail', 'conversion-detail', 'maintenance-detail', 'machine-setup', 'recipe-setup'];
recordViews.forEach(function (view) {
    app.route({
        view: view,
        onReady: function () {
            showRecordOrMissing(view);
            // Leaving a details page halfway through an edit must not leave
            // it unlocked, or showing unsaved values, on the next visit.
            lockEditForms($('#' + view));
        }
    });
});

// A New page starts empty on every visit, the wizard on its first step.
['run-new', 'conversion-new', 'maintenance-new'].forEach(function (view) {
    app.route({
        view: view,
        onReady: function () {
            const section = $('#' + view);
            section.find('form').each(function () {
                resetForm($(this));
            });
            section.find('[data-wizard]').each(function () {
                showWizardStep($(this), 0);
            });
            section.find('form').each(function () {
                showScrap($(this));
            });
        }
    });
});

app.route({
    view: 'profile',
    onCreate: function () {
        requireMatchingPasswords($('#profileNewPassword'), $('#profileNewPasswordConfirm'));
    }
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

// A dialog lives inside its page, but Bootstrap puts the dark backdrop behind
// it on <body>. Leaving the page with a dialog open (the Back button) would
// hide the dialog along with its page and leave the backdrop over the next one.
function closeOpenDialogs() {
    $('.modal.show').each(function () {
        bootstrap.Modal.getInstance(this).hide();
    });
}

function updateShell() {
    const view = currentView();
    highlightSidebar(view);
    toggleAuthLayout(view);
    closeOpenDialogs();
}

// spapp has no hook that runs on every page change, so the shell listens to
// the same hashchange event itself. The direct call covers the first page,
// which is shown without a hashchange when the address already has a hash.
window.addEventListener('hashchange', updateShell);
updateShell();
