/*
 * Muni University EHRMS — interface behaviour shared by every page.
 *
 * Built on what laravel-admin already loads: jQuery, Bootstrap 3 (modals),
 * Select2 4 (people pickers), flatpickr (dates), toastr and PJAX. Pages load
 * through PJAX, so everything here initialises on first load and again on
 * every `pjax:end`, scoped to the new content.
 *
 * Declarative hooks:
 *   form[data-ajax]                 submit in the background; JSON {message, redirect, remove}
 *     data-confirm="…"              ask first (EHR.confirm)
 *     data-remove="selector"        element to fade out on success (e.g. the table row)
 *   select[data-people]             server search of people (Select2)
 *     data-colleagues="1"           only the viewer's own department
 *   input[data-date]                flatpickr date (data-min / data-max: Y-m-d or "today")
 *   [data-countup]                  numbers count up when shown
 *
 * In code:  EHR.toast(text, type) · EHR.confirm(text, opts) · EHR.modal(opts) · EHR.go(url)
 */
(function ($, window, document) {
    'use strict';

    var EHR = window.EHR = window.EHR || {};
    var base = (document.querySelector('meta[name="ehr-base"]') || {}).content || '';
    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' } });

    function esc(text) {
        return $('<div>').text(text == null ? '' : String(text)).html();
    }

    /* ------------------------------------------------------------ toasts */

    EHR.toast = function (text, type) {
        if (window.toastr) {
            toastr.options = { positionClass: 'toast-top-right', timeOut: 3800, progressBar: false, newestOnTop: true, closeButton: true };
            toastr[type || 'success'](text);
        }
    };

    /* ------------------------------------------------------- navigation */

    /** Load a page into the content area (PJAX), falling back to a full load. */
    EHR.go = function (url) {
        if ($.support.pjax && $('#pjax-container').length) {
            $.pjax({ url: url, container: '#pjax-container', timeout: 8000 });
        } else {
            window.location.href = url;
        }
    };
    EHR.reload = function () { EHR.go(window.location.href); };

    /* ----------------------------------------------------------- modals */

    /**
     * Open a modal and resolve with the value of the button pressed (null when dismissed).
     *   EHR.modal({ title, sub, body (html), danger, size: 'sm'|'lg',
     *               buttons: [{ label, value, kind: 'primary'|'default'|'danger' }] })
     */
    EHR.modal = function (opts) {
        return new Promise(function (resolve) {
            var buttons = (opts.buttons || [{ label: 'Close', value: null, kind: 'default' }]).map(function (b, i) {
                return '<button type="button" class="btn btn-' + (b.kind || 'default') + '" data-i="' + i + '">' + esc(b.label) + '</button>';
            }).join('');
            var $m = $(
                '<div class="modal fade' + (opts.danger ? ' ehr-danger' : '') + '" tabindex="-1" role="dialog" aria-modal="true">' +
                '<div class="modal-dialog' + (opts.size ? ' modal-' + opts.size : '') + '" role="document"><div class="modal-content">' +
                '<div class="modal-header"><h4 class="modal-title">' + esc(opts.title) + (opts.sub ? '<small>' + esc(opts.sub) + '</small>' : '') + '</h4>' +
                '<button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button></div>' +
                '<div class="modal-body">' + (opts.body || '') + '</div>' +
                '<div class="modal-footer">' + (opts.hint ? '<span class="ehr-modal-hint">' + esc(opts.hint) + '</span>' : '') + buttons + '</div>' +
                '</div></div></div>'
            ).appendTo(document.body);
            var result = null;
            $m.on('click', '.modal-footer .btn', function () {
                var b = (opts.buttons || [])[+this.getAttribute('data-i')] || {};
                result = b.value === undefined ? null : b.value;
                $m.modal('hide');
            });
            $m.on('shown.bs.modal', function () {
                var focus = $m.find('.modal-body :input:visible:first');
                (focus.length ? focus : $m.find('.modal-footer .btn-primary, .modal-footer .btn-danger').last()).trigger('focus');
            });
            $m.on('hidden.bs.modal', function () { $m.remove(); resolve(result); });
            $m.modal({ backdrop: true, keyboard: true });
        });
    };

    /** Ask a yes/no question. Resolves true or false. */
    EHR.confirm = function (text, opts) {
        opts = opts || {};
        return EHR.modal({
            title: opts.title || 'Please confirm',
            body: '<p style="margin:0;font-size:13px">' + esc(text) + '</p>',
            danger: !!opts.danger,
            size: 'sm',
            buttons: [
                { label: opts.cancel || 'Cancel', value: false, kind: 'default' },
                { label: opts.ok || 'Continue', value: true, kind: opts.danger ? 'danger' : 'primary' }
            ]
        }).then(function (v) { return v === true; });
    };

    /* ------------------------------------------------------- AJAX forms */

    function clearErrors($form) {
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.field-error[data-generated]').remove();
        $form.find('.ehr-form-alert[data-generated]').remove();
    }

    function showErrors($form, errors, message) {
        var firstField = null;
        $.each(errors || {}, function (name, list) {
            var $field = $form.find('[name="' + name + '"], [name="' + name + '[]"]').first();
            if (!$field.length) { return; }
            var $target = $field.next('.select2').length ? $field.next('.select2').find('.select2-selection') : $field;
            if ($field.hasClass('flatpickr-input') && $field.next('input').length) { $target = $field.next('input'); }
            $target.addClass('is-invalid');
            $('<div class="field-error" data-generated></div>').text([].concat(list)[0]).insertAfter($target.closest('.select2').length ? $target.closest('.select2') : $target);
            firstField = firstField || $target;
        });
        if (message && !firstField) {
            $('<div class="ehr-note bad ehr-form-alert" data-generated style="margin-bottom:10px"></div>').text(message).prependTo($form.find('.modal-body').length ? $form.find('.modal-body') : $form);
        }
        if (firstField) { firstField.trigger('focus'); }
    }

    function busy($btn, on) {
        $btn.prop('disabled', on).toggleClass('is-busy', on);
    }

    function submitAjax(form, submitter) {
        var $form = $(form);
        var $btn = $(submitter || $form.find('[type="submit"]').last());
        clearErrors($form);
        busy($btn, true);

        var data = $form.serializeArray();
        if (submitter && submitter.name) { data.push({ name: submitter.name, value: submitter.value }); }

        return $.ajax({
            url: (submitter && submitter.getAttribute('formaction')) || form.getAttribute('action') || window.location.href,
            method: (form.getAttribute('method') || 'post').toUpperCase(),
            data: $.param(data),
            dataType: 'json',
            headers: { Accept: 'application/json' }
        }).done(function (res) {
            res = res || {};
            var $modal = $form.closest('.modal');
            if ($modal.length) { $modal.modal('hide'); }
            if (res.message) { EHR.toast(res.message, res.type || 'success'); }
            var remove = res.remove || form.getAttribute('data-remove');
            if (remove) {
                var $gone = $(remove);
                $gone.addClass('is-gone');
                setTimeout(function () { $gone.remove(); $(document).trigger('ehr:removed', [remove]); }, 260);
            }
            if (res.redirect) {
                setTimeout(function () { EHR.go(res.redirect); }, $modal.length ? 220 : 0);
            } else if (form.hasAttribute('data-reload')) {
                setTimeout(EHR.reload, $modal.length ? 220 : 0);
            }
            if (typeof res.waiting === 'number') { EHR.setApprovals(res.waiting); }
            $form.trigger('ehr:done', [res]);
        }).fail(function (xhr) {
            var res = xhr.responseJSON || {};
            if (xhr.status === 422) {
                showErrors($form, res.errors, res.message);
                if ((!res.errors || !Object.keys(res.errors).length) && !$form.closest('.modal').length) { EHR.toast(res.message || 'Please check the form.', 'error'); }
            } else if (xhr.status === 401) {
                window.location.href = base + '/auth/login';
            } else if (xhr.status === 419) {
                EHR.toast('Your session has expired. The page will reload.', 'error');
                setTimeout(function () { window.location.reload(); }, 1500);
            } else {
                EHR.toast(res.message || res.error || 'Something went wrong. Please try again.', 'error');
            }
        }).always(function () {
            busy($btn, false);
        });
    }

    $(document).on('submit', 'form[data-ajax]', function (e) {
        e.preventDefault();
        var form = this;
        var submitter = e.originalEvent && e.originalEvent.submitter;
        var question = form.getAttribute('data-confirm');
        if (question) {
            EHR.confirm(question, { danger: form.hasAttribute('data-danger'), ok: form.getAttribute('data-confirm-ok') || 'Yes, continue' })
                .then(function (yes) { if (yes) { submitAjax(form, submitter); } });
        } else {
            submitAjax(form, submitter);
        }
    });

    /* Plain forms: show a busy button so nothing is submitted twice. */
    $(document).on('submit', 'form.ehr-busy-submit', function () {
        busy($(this).find('[type="submit"]').last(), true);
    });

    /** Update every "waiting for you" count on the page (sidebar badge, queue heading). */
    EHR.setApprovals = function (n) {
        var $link = $('#side-nav li[data-uri="leave/approvals"] > a');
        var $badge = $link.find('.count');
        if (n > 0) {
            if (!$badge.length) { $badge = $('<span class="count"></span>').appendTo($link); }
            $badge.text(n);
        } else {
            $badge.remove();
        }
        $('[data-approvals-count]').text(n);
    };

    /* -------------------------------------------------- people pickers */

    function initPeople(root) {
        if (!$.fn.select2) { return; }
        $(root).find('select[data-people]').each(function () {
            var $s = $(this);
            if ($s.data('select2')) { return; }
            $s.select2({
                width: '100%',
                placeholder: $s.attr('data-placeholder') || 'Type a name or staff number…',
                allowClear: !$s.prop('required'),
                minimumInputLength: 0,
                dropdownParent: $s.closest('.modal').length ? $s.closest('.modal') : $(document.body),
                ajax: {
                    url: base + '/lookup/people',
                    dataType: 'json',
                    delay: 220,
                    data: function (p) { return { q: p.term || '', page: p.page || 1, colleagues: $s.attr('data-colleagues') || '' }; },
                    processResults: function (d) { return { results: d.results, pagination: { more: d.more } }; },
                    cache: true
                },
                templateResult: function (item) {
                    if (item.loading || !item.id) { return item.text; }
                    return $('<span class="s2-person"><b>' + esc(item.text) + '</b><span class="s2-sub">' + esc(item.sub || '') + '</span></span>');
                },
                templateSelection: function (item) { return item.text; },
                language: {
                    searching: function () { return 'Searching…'; },
                    noResults: function () { return 'Nobody matches'; },
                    inputTooShort: function () { return 'Type to search'; }
                }
            });
        });
    }

    /* ---------------------------------------------------- date pickers */

    function initDates(root) {
        if (!window.flatpickr) { return; }
        $(root).find('input[data-date]').each(function () {
            if (this._flatpickr) { return; }
            var value = function (v) { return v === 'today' ? 'today' : (v || null); };
            var fp = window.flatpickr(this, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'D j M Y',
                allowInput: false,
                disableMobile: true,
                locale: { firstDayOfWeek: 1 },
                minDate: value(this.getAttribute('data-min')),
                maxDate: value(this.getAttribute('data-max')),
                // A native event, so plain addEventListener('change') handlers hear it too.
                onChange: function (dates, str, instance) { instance.input.dispatchEvent(new Event('change', { bubbles: true })); }
            });
            if (fp && fp.altInput) { fp.altInput.className = this.className.replace('flatpickr-input', '') + ' ehr-date-alt'; }
        });
    }

    /* --------------------------------------------------- number count-up */

    function initCountUp(root) {
        $(root).find('[data-countup]').each(function () {
            var el = this;
            var target = parseFloat(el.getAttribute('data-countup'));
            if (isNaN(target) || el._counted) { return; }
            el._counted = true;
            var decimals = (el.getAttribute('data-countup').split('.')[1] || '').length;
            var suffix = el.getAttribute('data-suffix') || '';
            var start = performance.now();
            var dur = 650;
            (function frame(now) {
                var t = Math.min(1, (now - start) / dur);
                var eased = 1 - Math.pow(1 - t, 3);
                el.textContent = (target * eased).toLocaleString('en-GB', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + suffix;
                if (t < 1) { requestAnimationFrame(frame); }
            })(start);
        });
    }

    /* --------------------------------------------------- type selectors */

    $(document).on('change', '.ehr-types input[type="radio"], .ehr-types input[type="checkbox"]', function () {
        var $group = $(this).closest('.ehr-types');
        if (this.type === 'radio') { $group.find('label').removeClass('on'); }
        $(this).closest('label').toggleClass('on', this.checked);
    });

    /* ----------------------------------------------------------- charts */
    /*
     * <div class="ehr-chart" data-chart='{...}'> → an ApexCharts chart in the house style.
     * Kinds: "columns" (optionally stacked), "lines", "donut". The server supplies data
     * only; look and behaviour live here so every chart in the system matches.
     */
    var charts = [];
    var FONT = 'Inter, -apple-system, "Segoe UI", Roboto, Arial, sans-serif';

    function hmText(h) { var m = Math.round(h * 60); return Math.floor(m / 60) + 'h ' + (m % 60 < 10 ? '0' : '') + (m % 60) + 'm'; }

    function chartOptions(c) {
        var base = {
            chart: {
                type: c.kind === 'lines' ? 'line' : (c.kind === 'donut' ? 'donut' : 'bar'),
                height: c.height || 260, stacked: !!c.stacked, fontFamily: FONT, foreColor: '#727272',
                toolbar: { show: false }, zoom: { enabled: false }, parentHeightOffset: 0,
                animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches, speed: 450 },
                events: c.links ? { dataPointSelection: function (e, ctx, o) { var u = c.links[o.dataPointIndex]; if (u) { EHR.go(u); } } } : {}
            },
            colors: c.series.map ? c.series.map(function (s) { return s.color; }) : c.colors,
            dataLabels: { enabled: false },
            legend: { show: c.legend !== false, position: 'bottom', horizontalAlign: 'left', fontSize: '11.5px', fontWeight: 500,
                markers: { width: 9, height: 9, radius: 2 }, itemMargin: { horizontal: 10, vertical: 2 }, labels: { colors: '#3f3f3f' } },
            grid: { borderColor: '#efefef', strokeDashArray: 0, padding: { left: 4, right: 8, top: -6 }, xaxis: { lines: { show: false } } },
            tooltip: { theme: 'light', shared: c.kind !== 'donut', intersect: false, style: { fontSize: '12px', fontFamily: FONT },
                y: { formatter: function (v) { return v === null || v === undefined ? '—' : (c.suffix ? v.toFixed(1) + c.suffix : (c.suffixUnit ? hmText(v) : v.toLocaleString('en-GB'))); } } },
            states: { hover: { filter: { type: 'darken', value: 0.9 } }, active: { filter: { type: 'none' } } },
            noData: { text: 'No figures for this period yet', style: { color: '#9b9b9b', fontSize: '12px' } },
            responsive: [{ breakpoint: 768, options: { chart: { height: Math.min(c.height || 260, c.kind === 'donut' ? 220 : 250) } } }]
        };
        if (c.kind === 'donut') {
            return $.extend(true, base, {
                series: c.series.map(function (s) { return s.data; }), labels: c.series.map(function (s) { return s.name; }),
                stroke: { width: 2, colors: ['#fff'] },
                plotOptions: { pie: { expandOnClick: false, donut: { size: '72%', labels: { show: true,
                    name: { fontSize: '11.5px', fontWeight: 600, color: '#727272', offsetY: 18 },
                    value: { fontSize: '22px', fontWeight: 700, color: '#161616', offsetY: -14, formatter: function (v) { return (+v).toLocaleString('en-GB'); } },
                    total: { show: true, showAlways: true, label: c.centerLabel || 'Total', fontSize: '11.5px', fontWeight: 600, color: '#727272',
                        formatter: function () { return c.centerValue; } } } } } },
                tooltip: { shared: false, y: { formatter: function (v, o) {
                    var tot = o.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0);
                    return v.toLocaleString('en-GB') + (tot ? ' · ' + (100 * v / tot).toFixed(1) + '%' : ''); } } }
            });
        }
        var opts = $.extend(true, base, {
            series: c.series.map(function (s) { return { name: s.name, data: s.data }; }),
            xaxis: { categories: c.categories, tickPlacement: 'between', axisBorder: { color: '#dedede' }, axisTicks: { show: false },
                labels: { rotate: 0, hideOverlappingLabels: true, trim: false, style: { fontSize: '11px' } },
                tooltip: { enabled: false } },
            yaxis: { min: c.yMin, max: c.yMax, tickAmount: c.ticks || 4, forceNiceScale: c.yMin === undefined,
                labels: { style: { fontSize: '11px' }, formatter: function (v) { return v === null ? '' : Math.round(v) + (c.suffix || c.suffixUnit || ''); } } }
        });
        if (c.titles) {
            opts.tooltip.x = { formatter: function (v, o) { return c.titles[o.dataPointIndex] || v; } };
        }
        if (c.clockBase !== undefined) {
            // Values are minutes from a clock time (e.g. the late time): show them as clock times.
            var clock = function (v) { var m = Math.round(c.clockBase + v), h = Math.floor(m / 60), n = m % 60; return (h < 10 ? '0' : '') + h + ':' + (n < 10 ? '0' : '') + n; };
            opts.yaxis.labels.formatter = clock;
            opts.tooltip.y = { formatter: function (v) { return clock(v) + (v > 0 ? '  ·  ' + v + ' min late' : (v < 0 ? '  ·  ' + (-v) + ' min early' : '  ·  on the dot')); } };
        }
        if (c.kind === 'columns') {
            opts.plotOptions = { bar: { columnWidth: c.width || '62%', borderRadius: 3, borderRadiusApplication: 'end', borderRadiusWhenStacked: 'last',
                colors: c.ranges ? { ranges: c.ranges } : {} } };
            opts.stroke = { show: true, width: 2, colors: ['#fff'] };
        } else {
            opts.stroke = { width: 2, curve: 'straight' };
            opts.markers = { size: 4, strokeWidth: 2, strokeColors: '#fff', hover: { size: 6 } };
        }
        if (c.markX) {
            opts.annotations = { xaxis: [{ x: c.markX, borderColor: '#935600', strokeDashArray: 4,
                label: { text: c.markXLabel || c.markX, orientation: 'horizontal', borderWidth: 0, offsetY: -4,
                    style: { background: '#fcf1df', color: '#935600', fontSize: '10.5px', fontWeight: 600, fontFamily: FONT } } }] };
        }
        if (c.markY !== undefined) {
            opts.annotations = { yaxis: [{ y: c.markY, borderColor: '#9b9b9b', strokeDashArray: 4,
                label: { text: c.markYLabel || '', borderWidth: 0, position: 'right', textAnchor: 'end', offsetX: -2,
                    style: { background: 'transparent', color: '#727272', fontSize: '10.5px', fontFamily: FONT } } }] };
        }
        return opts;
    }

    function initCharts(root) {
        if (typeof ApexCharts === 'undefined') { return; }
        $(root).find('[data-chart]').each(function () {
            if (this.getAttribute('data-chart-done') || !this.offsetParent) { return; } // hidden ones render when shown
            var cfg;
            try { cfg = JSON.parse(this.getAttribute('data-chart')); } catch (e) { return; }
            this.setAttribute('data-chart-done', '1');
            var chart = new ApexCharts(this, chartOptions(cfg));
            chart.render();
            charts.push(chart);
        });
    }
    EHR.charts = initCharts;

    // Segmented switches: <div class="ehr-seg" data-switch="name"><button data-show="a">…  with  [data-pane-of="name"][data-pane-id="a"]
    $(document).on('click', '.ehr-seg [data-show]', function () {
        var show = this.getAttribute('data-show');
        var group = $(this).closest('.ehr-seg').attr('data-switch');
        $(this).addClass('on').attr('aria-pressed', 'true').siblings().removeClass('on').attr('aria-pressed', 'false');
        $('[data-pane-of="' + group + '"]').each(function () { this.hidden = this.getAttribute('data-pane-id') !== show; });
        initCharts(document);
    });

    $(document).on('pjax:start', function () {
        charts.forEach(function (c) { try { c.destroy(); } catch (e) {} });
        charts = [];
    });

    // Ordinary (non-AJAX) forms that ask first: <form data-confirm="…" [data-danger] [data-confirm-ok="…"]>.
    $(document).on('submit', 'form[data-confirm]:not([data-ajax])', function (e) {
        var form = this;
        if (form.getAttribute('data-confirmed')) { return; }
        e.preventDefault();
        EHR.confirm(form.getAttribute('data-confirm'), { danger: form.hasAttribute('data-danger'), ok: form.getAttribute('data-confirm-ok') || 'Yes, continue' })
            .then(function (yes) {
                if (!yes) { return; }
                form.setAttribute('data-confirmed', '1');
                $(form).find('button[type=submit]').prop('disabled', true).prepend('<i class="fa fa-spinner fa-spin"></i> ');
                form.submit();
            });
    });

    // Table rows that lead somewhere: the whole row is the link (links and buttons inside keep their own behaviour).
    $(document).on('click', 'tr[data-href]', function (e) {
        if ($(e.target).closest('a, button, input, select, label').length) { return; }
        EHR.go(this.getAttribute('data-href'));
    });

    /* ------------------------------------------------------------ clock */

    function tick() {
        var el = document.getElementById('header-clock');
        if (!el) { return; }
        var now = new Date();
        el.textContent = now.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }) + ' · ' +
            now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
        $('[data-clock-short]').text(now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }));
    }

    /* ------------------------------------------------------------- boot */

    EHR.init = function (root) {
        root = root || document;
        initPeople(root);
        initDates(root);
        initCountUp(root);
        initCharts(root);
        $(root).find('[data-toggle="tooltip"]').tooltip({ container: 'body' });
    };

    $(function () {
        EHR.init(document);
        tick();
        setInterval(tick, 20000);
    });
    $(document).on('pjax:end', function () { EHR.init(document.getElementById('pjax-container') || document); });
    // A modal written in the page sits under AdminLTE's content wrapper (z-index 820) and
    // would open beneath its own backdrop: move it to <body> as it opens, and drop the
    // moved ones when PJAX loads the next page.
    $(document).on('show.bs.modal', '.modal', function () {
        if (this.parentNode !== document.body) { this.setAttribute('data-ehr-moved', '1'); document.body.appendChild(this); }
    });
    $(document).on('pjax:start', function () { $('.modal[data-ehr-moved]').modal('hide').remove(); $('.modal-backdrop').remove(); $(document.body).removeClass('modal-open'); });
    // Modals opened from page markup get their pickers once visible (Select2 needs a laid-out parent).
    $(document).on('shown.bs.modal', '.modal', function () { EHR.init(this); });
})(jQuery, window, document);
