/* Stock Card & Inventory Management System — shared UI behaviour */
(function () {
    'use strict';

    // ---- Sidebar toggle (mobile) ----
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-sidebar-toggle]')) {
            document.body.classList.toggle('sidebar-open');
        } else if (e.target.closest('.sidebar-backdrop')) {
            document.body.classList.remove('sidebar-open');
        }
    });

    // ---- Toasts for flash messages ----
    document.querySelectorAll('.toast[data-autoshow]').forEach(function (el) {
        bootstrap.Toast.getOrCreateInstance(el, { delay: 6000 }).show();
    });

    // ---- Confirmation dialog for critical actions ----
    // Usage: <form data-confirm="Message" data-confirm-title="Title" data-confirm-variant="danger">
    var modalEl = document.getElementById('confirmModal');
    var pendingForm = null;
    var pendingSubmitter = null;
    if (modalEl) {
        var modal = new bootstrap.Modal(modalEl);
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form.matches('form[data-confirm]') || form.dataset.confirmed === '1') return;
            e.preventDefault();
            if (!form.checkValidity()) { form.reportValidity(); return; }
            pendingForm = form;
            pendingSubmitter = e.submitter || null;
            modalEl.querySelector('.modal-title').textContent = form.dataset.confirmTitle || 'Please confirm';
            modalEl.querySelector('.modal-body p').textContent = form.dataset.confirm;
            var btn = modalEl.querySelector('[data-confirm-ok]');
            btn.className = 'btn btn-' + (form.dataset.confirmVariant || 'primary');
            btn.textContent = form.dataset.confirmButton || 'Confirm';
            modal.show();
        }, true);
        modalEl.querySelector('[data-confirm-ok]').addEventListener('click', function () {
            if (!pendingForm) return;
            pendingForm.dataset.confirmed = '1';
            modal.hide();
            if (pendingForm.requestSubmit) pendingForm.requestSubmit(pendingSubmitter); else pendingForm.submit();
        });
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (pendingForm && pendingForm.dataset.confirmed !== '1') pendingForm = null;
        });
    }

    // ---- Prevent double submission + loading state ----
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (e.defaultPrevented || form.method.toLowerCase() === 'get') return;
        if (form.dataset.submitting === '1') { e.preventDefault(); return; }
        form.dataset.submitting = '1';
        var btn = e.submitter || form.querySelector('[type=submit]');
        if (btn) {
            // Preserve the clicked button's name/value (e.g. action=submit) before disabling it.
            if (btn.name) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden'; hidden.name = btn.name; hidden.value = btn.value;
                form.appendChild(hidden);
            }
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border me-1" role="status" aria-hidden="true"></span>' + (btn.dataset.loadingText || 'Processing…');
            setTimeout(function () { btn.disabled = true; }, 0);
        }
    });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-submitting]').forEach(function (f) {
            delete f.dataset.submitting; delete f.dataset.confirmed;
            f.querySelectorAll('[data-original-html]').forEach(function (b) { b.innerHTML = b.dataset.originalHtml; b.disabled = false; });
        });
    });

    // ---- Auto-submit filter selects ----
    document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () { el.form.requestSubmit ? el.form.requestSubmit() : el.form.submit(); });
    });

    // ---- Dynamic line items (RIS / receipts) ----
    // Container: [data-lines] holding <template data-line-template> and <tbody data-line-body>.
    document.querySelectorAll('[data-lines]').forEach(function (wrap) {
        var tpl = wrap.querySelector('template[data-line-template]');
        var body = wrap.querySelector('[data-line-body]');
        var counter = body.querySelectorAll('tr').length;

        function refresh() {
            var rows = body.querySelectorAll('tr');
            rows.forEach(function (row) {
                var rm = row.querySelector('[data-remove-line]');
                if (rm) rm.disabled = rows.length === 1;
                updateRow(row);
            });
            var selected = Array.from(body.querySelectorAll('select[data-item-select]')).map(function (s) { return s.value; });
            body.querySelectorAll('select[data-item-select]').forEach(function (s) {
                Array.from(s.options).forEach(function (o) {
                    o.disabled = o.value !== '' && o.value !== s.value && selected.indexOf(o.value) !== -1;
                });
            });
        }

        function updateRow(row) {
            var sel = row.querySelector('select[data-item-select]');
            if (!sel) return;
            var opt = sel.options[sel.selectedIndex];
            var unit = row.querySelector('[data-unit]');
            var avail = row.querySelector('[data-available]');
            var qty = row.querySelector('[data-qty]');
            var onhand = row.querySelector('[data-onhand]');
            if (unit) unit.textContent = opt && opt.dataset.unit ? opt.dataset.unit : '—';
            if (onhand) onhand.textContent = opt && opt.value ? opt.dataset.balance : '—';
            if (avail) {
                if (!opt || !opt.value) { avail.innerHTML = '<span class="text-muted">—</span>'; return; }
                var bal = parseInt(opt.dataset.balance || '0', 10);
                var q = parseInt(qty && qty.value ? qty.value : '0', 10);
                var ok = bal > 0 && (!q || q <= bal);
                avail.innerHTML = '<span class="badge text-bg-' + (ok ? 'success' : (bal > 0 ? 'warning' : 'danger')) + '">' +
                    (ok ? 'Yes' : (bal > 0 ? 'Partial' : 'No')) + '</span> <small class="text-muted">' + bal + ' on hand</small>';
            }
        }

        wrap.querySelector('[data-add-line]').addEventListener('click', function () {
            var html = tpl.innerHTML.replace(/__INDEX__/g, String(counter++));
            body.insertAdjacentHTML('beforeend', html);
            refresh();
            var last = body.querySelector('tr:last-child select, tr:last-child input');
            if (last) last.focus();
        });
        body.addEventListener('click', function (e) {
            var rm = e.target.closest('[data-remove-line]');
            if (rm && body.querySelectorAll('tr').length > 1) { rm.closest('tr').remove(); refresh(); }
        });
        body.addEventListener('change', refresh);
        body.addEventListener('input', function (e) { if (e.target.matches('[data-qty]')) updateRow(e.target.closest('tr')); });
        refresh();
    });

    // ---- Reopen a dialog whose form came back with validation errors ----
    document.querySelectorAll('.modal[data-autoshow]').forEach(function (el) {
        bootstrap.Modal.getOrCreateInstance(el).show();
    });

    // ---- Bootstrap tooltips ----
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); });
})();
