    /* ------------------------------------------------ burndown ---------- */
    function renderBurndownSelect() {
        var sel = document.getElementById('pp-bd-select');
        sel.innerHTML = '';
        (D.burndown || []).forEach(function (b, i) {
            sel.add(new Option(b.l + (b.start || b.due ? '（' + d2(b.start) + '〜' + d2(b.due) + '）' : ''), i));
        });
        if (!(D.burndown || []).length) {
            document.getElementById('pp-bd-body').innerHTML = '<div style="color:var(--bl-faint);">' + esc(L.bd_no_ms) + '</div>';
            return;
        }
        renderBurndown(0);
    }
    function renderBurndown(index) {
        var b = D.burndown[index];
        if (!b) { return; }
        var W = 760, H = 320, PL = 44, PR = 16, PT = 18, PB = 40;
        function parse(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
        var points = b.points.map(function (p) { return [parse(p[0]), p[1]]; });
        var today = parse(D.today);
        var x0 = b.start ? parse(b.start) : points[0][0];
        var xEnd = b.due ? parse(b.due) : today;
        var x1 = new Date(Math.max(xEnd, today, points.length ? points[points.length - 1][0] : today));
        if (x1 <= x0) { x1 = new Date(x0.getTime() + 7 * 86400000); }
        var days = (x1 - x0) / 86400000;
        function X(d) { return PL + (d - x0) / 86400000 / days * (W - PL - PR); }
        function Y(v) { return PT + (1 - v / ymax) * (H - PT - PB); }
        function valueAt(d) {
            var v = 0;
            points.forEach(function (p) { if (p[0] <= d) { v = p[1]; } });
            return v;
        }
        // planned: from the remaining count at the start straight to zero on the due day
        var startValue = valueAt(x0) || b.total;
        // The scale holds the planned line as well. It can start above anything
        // the actual line reached, and was then drawn off the top of the chart.
        var ymax = Math.max(4, Math.max.apply(null, b.points.map(function (p) { return p[1]; })
            .concat(b.due ? [startValue] : [])) + 1);

        var s = '';
        var ystep = ymax <= 8 ? 2 : Math.ceil(ymax / 5);
        for (var v = 0; v <= ymax; v += ystep) {
            s += '<line class="' + (v ? 'grid' : 'axis') + '" x1="' + PL + '" y1="' + Y(v) + '" x2="' + (W - PR) + '" y2="' + Y(v) + '"/>' +
                '<text x="' + (PL - 8) + '" y="' + (Y(v) + 4) + '" text-anchor="end">' + v + '</text>';
        }
        var tickStep = Math.max(1, Math.ceil(days / 63)) * 7;
        for (var t = new Date(x0); t <= x1; t = new Date(t.getTime() + tickStep * 86400000)) {
            s += '<line class="grid" x1="' + X(t) + '" y1="' + PT + '" x2="' + X(t) + '" y2="' + Y(0) + '"/>' +
                '<text x="' + X(t) + '" y="' + (H - PB + 16) + '" text-anchor="middle">' + (t.getMonth() + 1) + '/' + t.getDate() + '</text>';
        }
        s += '<text x="14" y="12" text-anchor="start" font-weight="bold">' + esc(L.bd_y) + '</text>';
        if (today >= x0 && today <= x1) {
            s += '<line x1="' + X(today) + '" y1="' + PT + '" x2="' + X(today) + '" y2="' + Y(0) + '" stroke="#d9534f" stroke-width="1.5" stroke-dasharray="3 3"/>' +
                '<text x="' + (X(today) + 4) + '" y="' + (PT + 10) + '" fill="#d9534f" font-weight="bold">' + esc(L.bd_today) + '</text>';
        }
        if (b.due) {
            s += '<line x1="' + X(x0) + '" y1="' + Y(startValue) + '" x2="' + X(parse(b.due)) + '" y2="' + Y(0) + '" stroke="#4488c5" stroke-width="2"/>';
        }
        // actual: step line through the event days, extended to today
        var line = [];
        var previous = null;
        points.forEach(function (p) {
            if (previous !== null) { line.push(X(p[0]) + ',' + Y(previous)); }
            line.push(X(p[0]) + ',' + Y(p[1]));
            previous = p[1];
        });
        if (previous !== null && today > points[points.length - 1][0]) {
            line.push(X(today) + ',' + Y(previous));
        }
        s += '<polyline points="' + line.join(' ') + '" fill="none" stroke="#c9483d" stroke-width="2.5" stroke-linejoin="round"/>';
        points.forEach(function (p) {
            s += '<circle cx="' + X(p[0]) + '" cy="' + Y(p[1]) + '" r="4.5" fill="#c9483d" stroke="#fff" stroke-width="2">' +
                svgTip((p[0].getMonth() + 1) + '/' + p[0].getDate() + '　' + p[1]) + '</circle>';
        });

        // forecast from the last 3 weeks' pace
        var remaining = previous === null ? b.total : previous;
        var finish = null;
        var horizon = new Date(today.getTime() - 21 * 86400000);
        var base = null;
        points.forEach(function (p) { if (p[0] <= horizon) { base = p; } });
        if (!base && points.length) { base = points[0]; }
        if (base && remaining > 0) {
            var spentDays = Math.max(1, (today - base[0]) / 86400000);
            var rate = (base[1] - remaining) / spentDays;
            if (rate > 0.01) {
                finish = new Date(today.getTime() + remaining / rate * 86400000);
                var fx = Math.min(X(x1) + 40, X(finish));
                s += '<line x1="' + X(today) + '" y1="' + Y(remaining) + '" x2="' + fx + '" y2="' +
                    Y(Math.max(0, remaining - rate * ((fx - X(today)) / (W - PL - PR) * days))) +
                    '" stroke="#98a5b1" stroke-width="2" stroke-dasharray="5 4"/>';
            }
        }
        s += '<g font-size="11">' +
            '<line x1="' + (W - 250) + '" y1="' + (PT + 2) + '" x2="' + (W - 226) + '" y2="' + (PT + 2) + '" stroke="#4488c5" stroke-width="2"/><text x="' + (W - 220) + '" y="' + (PT + 6) + '">' + esc(L.bd_plan) + '</text>' +
            '<line x1="' + (W - 168) + '" y1="' + (PT + 2) + '" x2="' + (W - 144) + '" y2="' + (PT + 2) + '" stroke="#c9483d" stroke-width="2.5"/><text x="' + (W - 138) + '" y="' + (PT + 6) + '">' + esc(L.bd_actual) + '</text>' +
            '<line x1="' + (W - 92) + '" y1="' + (PT + 2) + '" x2="' + (W - 68) + '" y2="' + (PT + 2) + '" stroke="#98a5b1" stroke-width="2" stroke-dasharray="5 4"/><text x="' + (W - 62) + '" y="' + (PT + 6) + '">' + esc(L.bd_forecast) + '</text></g>';
        document.getElementById('pp-bd-svg').innerHTML = s;

        var side = '<div class="tile"><div class="n">' + esc(remaining) +
            '<small style="font-size:12px;font-weight:400;color:var(--bl-sub);"> / ' + esc(b.total) + esc(L.unit_cnt) + '</small></div>' +
            '<div class="l">' + esc(L.bd_left) + '（' + esc(fmt(L.bd_done, b.done)) + '）</div></div>';
        if (b.due) {
            side += '<div class="tile"><div class="n">' + Math.max(0, daysTo(b.due)) +
                '<small style="font-size:12px;font-weight:400;color:var(--bl-sub);"> ' + esc(L.unit_day) + '</small></div>' +
                '<div class="l">' + esc(fmt(L.bd_days_left, d2(b.due), Math.max(0, daysTo(b.due)))) + '</div></div>';
        }
        if (finish && remaining > 0) {
            var fdate = (finish.getMonth() + 1) + '/' + finish.getDate();
            var over = b.due && finish > parse(b.due);
            side += '<div class="tile ' + (over ? 'warn' : 'ok') + '"><div class="n">' +
                (over ? '<i class="fa fa-exclamation-triangle"></i> ' : '<i class="fa fa-check"></i> ') +
                esc(over ? fmt(L.bd_forecast_warn, fdate) : L.bd_forecast_ok) + '</div></div>';
        }
        // The server reads only so many revisions; past that the line is drawn
        // from a cut history and the early part of the curve may be wrong.
        // Said rather than left to read as the whole story.
        if (b.partial) {
            side += '<div class="tile"><div class="l" style="color:var(--bl-amber);">' +
                '<i class="fa fa-info-circle"></i> ' + esc(L.bd_partial) + '</div></div>';
        }
        document.getElementById('pp-bd-side').innerHTML = side;
    }

