{{-- Date, datetime and time fields, drawn the way Exment's own forms draw them. --}}
<style>
/* The native widget came with its own calendar button; losing it would leave
   a field that gives no sign it is a date. An inline SVG background keeps
   that hint without wrapping the input in extra markup, which would fight
   the layouts these inputs already sit in.
   Every screen that hosts these inputs styles its own fields with a
   `background` shorthand and a padding of its own, at a higher specificity
   than one class - the editor, the comment dock and the project settings
   all did, and the hint showed nowhere. It belongs to the field, not to the
   screen, hence !important; the background colour stays the screen's. */
.pp-date-in{
  background-image:url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 16 16' fill='none' stroke='%23888' stroke-width='1.3'%3E%3Crect x='1.5' y='3' width='13' height='11.5' rx='1.5'/%3E%3Cpath d='M1.5 6.5h13M5 1.5v3M11 1.5v3'/%3E%3C/svg%3E") !important;
  background-repeat:no-repeat !important;background-position:right 6px center !important;padding-right:24px !important;
}
/* Where every calendar is parked: one empty box off <body>, with a position
   of its own because the library refuses to open inside a static container.
   It has no size and paints nothing - the calendar itself is positioned
   against the viewport (see placeWidget). */
.pp-date-host{position:absolute;top:0;left:0;width:0;height:0;}
/* Above the issue overlay, which is z-index 1090. */
.bootstrap-datetimepicker-widget{z-index:1200;}
.flatpickr-calendar.open{z-index:1200;}
</style>
<script>
(function () {
    'use strict';

    // Why not <input type="date">: that widget belongs to the browser, and so
    // does its language. The calendar, the month and weekday names and the
    // "mm/dd/yyyy" hint come from the browser's own locale and cannot be
    // changed from CSS or from script - on a Japanese screen a browser
    // running in English shows an English calendar, and there is no option
    // to fix it.
    //
    // Exment's admin forms use eonasdan's bootstrap-datetimepicker driven by
    // moment (see ExmentAdminCore Form/Field/Date, Datetime, Time). The admin
    // layout these pages live inside loads both, plus the picker's stylesheet
    // and moment's locale data - so this reuses what is there instead of
    // shipping a second date library.
    //
    // Exment 7.3 (admin-core 2.0) has neither: its forms use flatpickr, which
    // the same layout loads with its language file. There this file uses
    // flatpickr. The old picker stays first, so a 7.2 screen is the same as
    // before.
    //
    // The formats are copied from those three field classes exactly, because
    // the value an input holds here is posted straight to the API and read
    // back by the same code: changing the shape of the string would change
    // what the server stores.
    // The one exception is time: hours and minutes only. Nobody sets a due
    // time to the second, and Exment's time column takes HH:mm as well as
    // HH:mm:ss (Validator\TimeRule), so the stored value stays valid.
    var FORMATS = {
        date: 'YYYY-MM-DD',
        datetime: 'YYYY-MM-DD HH:mm:ss',
        time: 'HH:mm'
    };

    // The same formats, written the flatpickr way.
    var FP_FORMATS = {
        date: 'Y-m-d',
        datetime: 'Y-m-d H:i:S',
        time: 'H:i'
    };

    // The time an empty time field starts from when its picker is opened:
    // noon (owner's choice, 2026-11-19), rather than 00:00 or whatever minute
    // it happens to be. The picker cannot show one time while holding none,
    // so opening it puts this in; the clear button takes it out again.
    var TIME_VIEW = { hour: 12, minute: 0 };

    // The same icon set Exment passes, so the picker looks like the one on
    // every other screen rather than like a second widget.
    var ICONS = {
        time: 'fa fa-clock-o',
        date: 'fa fa-calendar',
        up: 'fa fa-chevron-up',
        down: 'fa fa-chevron-down',
        previous: 'fa fa-chevron-left',
        next: 'fa fa-chevron-right',
        today: 'fa fa-calendar-check-o',
        clear: 'fa fa-trash',
        close: 'fa fa-times'
    };

    // Distance between the field and the calendar, and the margin kept from
    // the edge of the window.
    var GAP = 2;
    var EDGE = 4;

    var pending = [];
    var loadBound = false;
    var warned = false;
    var host = null;

    function hasOldPicker() {
        return !!(window.jQuery && window.jQuery.fn && window.jQuery.fn.datetimepicker && window.moment);
    }

    function hasFlatpickr() {
        return typeof window.flatpickr === 'function';
    }

    function available() {
        return hasOldPicker() || hasFlatpickr();
    }

    function warnOnce() {
        if (warned || !window.console) { return; }
        warned = true;
        console.warn('[PmPortal] no date picker (datetimepicker with moment, or flatpickr); date fields stay plain text.');
    }

    /**
     * The flatpickr picker of one input (Exment 7.3).
     *
     * flatpickr writes the input and sends it a native change event, so every
     * listener here hears it as it is; the bridge of the old picker is not
     * needed. Its calendar is put on <body> and placed against the page when
     * it opens, so it only has to follow the field while something scrolls.
     */
    function buildFlatpickr(input, type) {
        if (input._flatpickr) { return; }
        var l10ns = window.flatpickr.l10ns || {};
        var follow = null;
        var escape = null;
        var unfollow = function () {
            if (follow) {
                window.removeEventListener('scroll', follow, true);
                window.removeEventListener('resize', follow);
                follow = null;
            }
            if (escape) {
                document.removeEventListener('keydown', escape, true);
                escape = null;
            }
        };
        var config = {
            dateFormat: FP_FORMATS[type],
            // Typing a date stays possible, and emptying the field clears it:
            // "no due date" is a real value (the old picker's clear button).
            allowInput: true,
            // Clicking anywhere in the field opens it (it does by itself).
            enableTime: type !== 'date',
            noCalendar: type === 'time',
            enableSeconds: type === 'datetime',
            time_24hr: true,
            // An empty time starts from noon (TIME_VIEW) when it is opened.
            defaultHour: TIME_VIEW.hour,
            defaultMinute: TIME_VIEW.minute,
            onOpen: function (dates, text, instance) {
                unfollow();
                follow = function () {
                    // the field is gone or hidden (its pane was closed or redrawn): the
                    // calendar goes with it instead of floating over nothing
                    if (!instance.isOpen || !instance.config) { unfollow(); return; }
                    if (!input.isConnected || input.getClientRects().length === 0) { instance.close(); return; }
                    instance._positionCalendar();
                };
                // Escape closes the calendar even when the pane takes the key first
                // (the comment dock closes on Escape)
                escape = function (e) {
                    if (e.key === 'Escape' && instance.isOpen) { instance.close(); }
                };
                window.addEventListener('scroll', follow, true);
                window.addEventListener('resize', follow);
                document.addEventListener('keydown', escape, true);
            },
            onClose: function () { unfollow(); },
            // destroy() does not call onClose: without this the listeners stay and
            // call the destroyed instance
            onDestroy: function () { unfollow(); }
        };
        // Japanese, as the old picker was told; the layout loads the file of
        // the app's language, and without it the calendar stays English.
        if (l10ns.ja) { config.locale = 'ja'; }
        window.flatpickr(input, config);
    }

    function build(input, type) {
        if (hasOldPicker()) {
            buildOldPicker(input, type);
        } else if (hasFlatpickr()) {
            buildFlatpickr(input, type);
        }
    }

    /**
     * The one box every calendar opens inside, off <body>.
     *
     * Left where the library would put it - as the input's next sibling - a
     * calendar is positioned wrongly and clipped, and both have the same
     * cause: these inputs sit inside scrolling panels. The library measures
     * the input with jQuery .position(), which is the offset you can see, and
     * writes it as a CSS offset, which the browser resolves against the
     * panel's content box. The two differ by exactly how far the panel is
     * scrolled. Measured on the issue form: scrolled 400px, the calendar
     * landed 400px away from its field, above the top of the window. The
     * panel's overflow:auto then clips whatever part still overlaps.
     *
     * Off <body> there is no scroll to count twice and no overflow to clip
     * against, and the stacking order stops depending on which panel the
     * field happens to be in.
     */
    function hostEl() {
        if (host && host.parentNode) { return host; }
        host = document.createElement('div');
        host.className = 'pp-date-host';
        document.body.appendChild(host);

        return host;
    }

    /**
     * Put one open calendar next to its field, in viewport coordinates.
     *
     * Below the field when it fits, above when it does not and there is room
     * up there instead. The side classes are kept in step with the choice
     * because the stylesheet draws the pointer from them.
     */
    function placeWidget(input, widget) {
        if (!widget || !widget.offsetHeight) { return; }
        var box = input.getBoundingClientRect();
        // A field that is not laid out - inside a panel that is still closed,
        // say - has no box to align to, and every edge reads zero. Leave the
        // calendar where it is instead of parking it in the corner of the
        // window.
        if (box.width === 0 && box.height === 0) { return; }
        var vw = document.documentElement.clientWidth;
        var vh = document.documentElement.clientHeight;
        var h = widget.offsetHeight;
        var below = box.bottom + GAP;
        var above = box.top - GAP - h;
        var up = below + h > vh && above >= 0;

        widget.classList.toggle('top', up);
        widget.classList.toggle('bottom', !up);
        // The library may have asked for right alignment while measuring
        // against the empty host; the offsets below are what decides, so the
        // class only has to stop contradicting them.
        widget.classList.remove('pull-right');
        widget.style.position = 'fixed';
        widget.style.top = Math.round(up ? above : below) + 'px';
        widget.style.left = Math.round(
            Math.max(EDGE, Math.min(box.left, vw - widget.offsetWidth - EDGE))
        ) + 'px';
        widget.style.bottom = 'auto';
        widget.style.right = 'auto';
    }

    function buildOldPicker(input, type) {
        var $input = window.jQuery(input);
        if ($input.data('DateTimePicker')) { return; }

        $input.datetimepicker({
            format: FORMATS[type],
            // moment.locale() is "en" on this layout even though the app is
            // Japanese, so the locale has to be said here or the calendar
            // comes out in English - which is the whole point of this file.
            locale: 'ja',
            icons: ICONS,
            widgetParent: window.jQuery(hostEl()),
            // Clicking anywhere in the field opens it, the way Exment's does.
            allowInputToggle: true,
            // An empty field must stay empty when the picker opens. Without
            // this it fills itself with today, silently setting a date the
            // user never chose.
            useCurrent: false,
            // A date has to be removable: "no due date" is a real value, and
            // without the clear button a picker can only ever be changed.
            showClear: true,
            showTodayButton: type !== 'time',
            showClose: true
        });
        if (type === 'time') {
            $input.on('dp.show', function () {
                if (!String(input.value || '').trim()) {
                    $input.data('DateTimePicker').date(window.moment(TIME_VIEW));
                }
            });
        }

        // dp.show is the library's last step, after it has placed the widget
        // itself - so this is where its answer gets replaced rather than
        // raced. While the calendar is open it has to follow the field,
        // which moves whenever anything scrolls, including the panel the
        // field is in - hence capture, which hears scrolls on any element.
        // Both handlers are bound here rather than once at the top so they
        // run after the resize handler the library binds in show().
        var follow = null;
        $input.on('dp.show', function () {
            var widget = hostEl().querySelector('.bootstrap-datetimepicker-widget');
            if (!widget) { return; }
            placeWidget(input, widget);
            follow = function () { placeWidget(input, widget); };
            window.addEventListener('scroll', follow, true);
            window.addEventListener('resize', follow);
        });
        $input.on('dp.hide', function () {
            if (!follow) { return; }
            window.removeEventListener('scroll', follow, true);
            window.removeEventListener('resize', follow);
            follow = null;
        });

        // The library announces a change with jQuery's own custom event and
        // writes the input with .val(), neither of which reaches a listener
        // added through addEventListener - and that is how every caller here
        // listens. So the two are bridged.
        //
        // The dispatched event has its two stop methods knocked out, and that
        // part is not optional. The picker binds "change" on this very input
        // as its own signal that somebody typed into the field, and that
        // handler calls stopImmediatePropagation() - so a plain synthetic
        // change is swallowed before any listener added later can see it.
        // (Measured: a listener on the input never ran, while a capture-phase
        // listener on document did.) Blanking the two methods on this one
        // event says what is true: this change came from the picker itself,
        // not from a keystroke it needs to re-parse.
        //
        // Guarded by the last value seen, because typing into the field by
        // hand produces a native change of its own as well as a dp.change,
        // and because the picker's handler re-parses the field and can emit
        // dp.change again - without the guard that is a loop.
        var last = input.value;
        $input.on('dp.change', function () {
            if (input.value === last) { return; }
            last = input.value;
            var ev = new Event('change', { bubbles: true });
            ev.stopPropagation = function () {};
            ev.stopImmediatePropagation = function () {};
            input.dispatchEvent(ev);
        });
        input.addEventListener('change', function () { last = input.value; });
    }

    // The picker and moment arrive with the admin layout's own script bundle,
    // which can still be loading when a pane renders - measured on the issue
    // screen, where the quick-edit panel is built before they land. Degrading
    // to plain text there would be permanent for the life of the page, so a
    // field that cannot be upgraded yet waits in this list instead.
    function flush() {
        if (!available()) { return; }
        // Once, and then the listener goes: a pjax navigation leaves this
        // script's closure behind on window, and load never fires a second
        // time anyway.
        if (loadBound) {
            loadBound = false;
            window.removeEventListener('load', flush);
        }
        var waiting = pending;
        pending = [];
        waiting.forEach(function (item) { build(item.input, item.type); });
    }

    function defer(input, type) {
        pending.push({ input: input, type: type });

        if (!loadBound) {
            loadBound = true;
            // window.load is the first moment the layout's bundle is
            // certainly complete.
            window.addEventListener('load', flush);
        }

        // ...and a field touched before load still gets a real picker, on
        // the spot, opened as the click asked for.
        var arm = function () {
            if (!available()) { warnOnce(); return; }
            input.removeEventListener('mousedown', arm);
            input.removeEventListener('focus', arm);
            flush();
            var picker = window.jQuery ? window.jQuery(input).data('DateTimePicker') : null;
            if (picker) {
                picker.show();
            } else if (input._flatpickr) {
                input._flatpickr.open();
            }
        };
        input.addEventListener('mousedown', arm);
        input.addEventListener('focus', arm);
    }

    /**
     * Turn one input into a picker, and give it back.
     *
     * Returns the same element it was handed, so a caller can use it in place
     * of the input it was already building.
     *
     * kind: 'date' (the default), 'datetime' or 'time'.
     */
    window.ppDateField = function (input, kind) {
        if (!input) { return input; }
        var type = FORMATS[kind] ? kind : 'date';

        // Never type="date": the point of all of this is not to get the
        // browser's widget. The value stays an ISO string either way, so
        // everything reading .value is unaffected.
        input.type = 'text';
        input.autocomplete = 'off';
        input.setAttribute('data-pp-date', type);

        // A field nobody can edit gets neither a calendar nor the button
        // that offers one. The picker would refuse to open on it anyway
        // (ignoreReadonly is off), so the icon would be an invitation to
        // click something that does nothing.
        if (input.readOnly || input.disabled) { return input; }

        if (!input.placeholder) { input.placeholder = FORMATS[type]; }
        input.classList.add('pp-date-in');

        if (available()) {
            build(input, type);
        } else {
            defer(input, type);
        }

        return input;
    };

    /**
     * Give up the picker attached to an input before its element goes away.
     *
     * The widget is parked off <body>, so dropping the input alone would
     * leave an open calendar behind with nothing to belong to - and the
     * library's own scroll and resize handlers with it. The portal redraws
     * these panes from scratch, so that would accumulate.
     */
    window.ppDateFieldDestroy = function (root) {
        if (!root || !available()) { return; }
        var inputs = root.matches && root.matches('[data-pp-date]')
            ? [root]
            : (root.querySelectorAll ? root.querySelectorAll('[data-pp-date]') : []);
        Array.prototype.forEach.call(inputs, function (input) {
            try {
                var picker = window.jQuery ? window.jQuery(input).data('DateTimePicker') : null;
                if (picker) { picker.destroy(); }
                if (input._flatpickr) { input._flatpickr.destroy(); }
            } catch (e) { /* already gone */ }
        });
    };
})();
</script>
