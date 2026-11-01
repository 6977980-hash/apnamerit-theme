/* Apna Merit front-end (vanilla, deferred, no dependencies).
 * Engines (pure, testable in Node): aggregate(), chance(), predict().
 * UI: calculators, predictor, merit-table filter/sort, share, localStorage.
 */
(function (root) {
  'use strict';

  // ---------------------------------------------------------------- engines
  function num(v) {
    var n = parseFloat(String(v == null ? '' : v).replace(',', '.'));
    return isFinite(n) ? n : NaN;
  }

  /**
   * Weighted aggregate. formula = {parts:[{key,total,weight,pool?,scale?}], bonus:[{key,part,marks}]}
   * Parts sharing a `pool` are added up first (each multiplied by its `scale`, default 1)
   * and the pool gets the sum of their weights: PU uses (Matric/4 + Inter) / (total/4 + total).
   * values = {o_<key>: obtained, t_<key>: total, b_<key>: true}
   * Returns {aggregate, parts:[{key,label,obtained,total,weight,share}]} or {error}.
   */
  // "ECAT marks" + " marks" would read "ECAT marks marks", "score in %" + " marks" is no better (audit F-24).
  function marksOf(label) { return /marks\s*$|score|%/i.test(label) ? label : label + ' marks'; }

  function aggregate(formula, values) {
    var out = { aggregate: 0, parts: [] };
    var extra = {};
    var pools = {};
    (formula.bonus || []).forEach(function (b) {
      if (values['b_' + b.key]) extra[b.part] = (extra[b.part] || 0) + num(b.marks);
    });
    for (var i = 0; i < formula.parts.length; i++) {
      var p = formula.parts[i];
      var obtained = num(values['o_' + p.key]);
      var total = num(values['t_' + p.key] != null && values['t_' + p.key] !== '' ? values['t_' + p.key] : p.total);
      if (isNaN(obtained) || obtained < 0) return { error: 'Enter your ' + marksOf(p.label) + '.', key: p.key };
      if (isNaN(total) || total <= 0) return { error: 'Enter the total marks for ' + p.label + '.', key: p.key };
      if (obtained > total) return { error: marksOf(p.label) + ' cannot be more than ' + total + '.', key: p.key };
      var got = Math.min(obtained + (extra[p.key] || 0), total);
      var row = { key: p.key, label: p.label, obtained: got, raw: obtained, total: total, weight: num(p.weight), share: (got / total) * num(p.weight) };
      if (p.pool) {
        var sc = p.scale == null ? 1 : num(p.scale);
        var pl = pools[p.pool] || (pools[p.pool] = { w: 0, den: 0, rows: [] });
        pl.w += row.weight;
        pl.den += sc * total;
        row.scaled = sc * got;
        pl.rows.push(row);
      }
      out.parts.push(row);
    }
    Object.keys(pools).forEach(function (k) {
      var pl = pools[k];
      pl.rows.forEach(function (r) { r.share = pl.den > 0 ? pl.w * r.scaled / pl.den : 0; delete r.scaled; });
    });
    out.parts.forEach(function (r) { out.aggregate += r.share; });
    out.aggregate = Math.round(out.aggregate * 10000) / 10000;
    return out;
  }

  /** Chance label against a reference closing merit with a ± band. */
  function chance(agg, ref, band) {
    if (agg > ref + band) return 'strong';
    if (agg >= ref - band) return 'borderline';
    return 'unlikely';
  }

  /** Rows [{ref,...}] → rows with chance + gap, filtered, best first. */
  function predict(agg, rows, band, filter) {
    filter = filter || {};
    return rows
      .filter(function (r) {
        for (var k in filter) if (filter[k] && r[k] !== filter[k]) return false;
        return true;
      })
      .map(function (r) {
        var o = {};
        for (var k in r) o[k] = r[k];
        o.gap = Math.round((agg - r.ref) * 100) / 100;
        o.chance = chance(agg, r.ref, band);
        return o;
      })
      .sort(function (a, b) { return b.ref - a.ref; });
  }

  /**
   * Eligibility floors (audit F-01): floors = [{part,min_pct,label,applies_to,source_url,programs?}],
   * parts = aggregate().parts. Bonus marks never count for eligibility (raw marks / total).
   * Returns the floors the marks fall below, each with the student's own percentage.
   */
  function eligibility(floors, parts) {
    var out = [];
    (floors || []).forEach(function (fl) {
      for (var i = 0; i < parts.length; i++) {
        var p = parts[i];
        if (p.key !== fl.part) continue;
        var mine = (p.raw == null ? p.obtained : p.raw) / p.total * 100;
        if (mine < num(fl.min_pct)) {
          var o = {};
          for (var k in fl) o[k] = fl[k];
          o.mine = mine;
          o.partLabel = p.label;
          out.push(o);
        }
      }
    });
    return out;
  }

  /** Rows left after failed floors: a floor without `programs` removes every row. */
  function eligibleRows(rows, fails) {
    return rows.filter(function (r) {
      return !fails.some(function (f) { return !f.programs || new RegExp(f.programs).test(r.program); });
    });
  }

  var AM = { aggregate: aggregate, chance: chance, predict: predict, eligibility: eligibility, eligibleRows: eligibleRows };
  root.AM = AM;
  if (typeof module !== 'undefined' && module.exports) module.exports = AM;
  if (typeof document === 'undefined') return;

  // ---------------------------------------------------------------- helpers
  var LABEL = { strong: 'Strong', borderline: 'Borderline', unlikely: 'Unlikely' };
  var store = {
    get: function (k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* private mode */ } }
  };
  function $(sel, el) { return (el || document).querySelector(sel); }
  function $$(sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); }
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }
  function pct(n) { return n.toFixed(2) + '%'; }
  function data(el, cls) {
    var s = $('script.' + cls, el);
    try { return s ? JSON.parse(s.textContent) : null; } catch (e) { return null; }
  }
  function waLink(text) { return 'https://wa.me/?text=' + encodeURIComponent(text); }

  function chanceList(list, empty) {
    if (!list.length) return '<p>' + esc(empty) + '</p>';
    var groups = { strong: [], borderline: [], unlikely: [] };
    list.forEach(function (r) { groups[r.chance].push(r); });
    var html = '';
    ['strong', 'borderline', 'unlikely'].forEach(function (g) {
      if (!groups[g].length) return;
      html += '<h3><span class="am-chip am-chip--' + g + '">' + LABEL[g] + '</span> ' + groups[g].length + ' program' + (groups[g].length > 1 ? 's' : '') + '</h3><ul class="am-plist">';
      groups[g].slice(0, g === 'unlikely' ? 10 : 50).forEach(function (r) {
        var name = (r.uni ? r.uni + ': ' : '') + r.program + (r.campus ? ' (' + r.campus + (r.shift ? ', ' + r.shift : '') + ')' : '');
        var link = r.url ? '<a href="' + esc(r.url) + '">' + esc(name) + '</a>' : esc(name);
        html += '<li>' + link + ' <span class="am-muted">closed at ' + pct(r.ref) + ' (' + r.year + '), you are ' + (r.gap >= 0 ? '+' : '') + r.gap.toFixed(2) + '</span></li>';
      });
      html += '</ul>';
    });
    return html;
  }

  // tail: 'none' (no merit rows to list), 'all' (list removed) or 'some' (matching programs removed).
  function eligNotice(fails, tail) {
    return '<div class="am-answer am-elig" role="status">' + fails.map(function (f) {
      return '<p><strong>Below the ' + esc(f.label) + ' of ' + num(f.min_pct) + '%</strong> (your ' + esc(f.partLabel) + ': ' + (Math.floor(f.mine * 100) / 100).toFixed(2) + '%, <a href="' + esc(f.source_url) + '" rel="nofollow noopener" target="_blank">source</a>). With these marks you are not eligible for ' + esc(f.applies_to) + ' this year' +
        (tail === 'none' ? '.' : tail === 'all' || !f.programs ? ', so no colleges or programs are listed.' : ', so those programs are left out below.') + '</p>';
    }).join('') + '</div>';
  }

  // Number fields hold '' for "1,050": badInput says so in most browsers; for the rest remember
  // that something was typed (am-typed) so the message can still name the comma (audit F-24).
  document.addEventListener('input', function (e) {
    var i = e.target;
    if (!i || i.type !== 'number') return;
    if (i.value !== '' || /^delete/.test(e.inputType || '')) i.removeAttribute('data-am-typed');
    else if (e.inputType && /^insert/.test(e.inputType)) i.setAttribute('data-am-typed', '');
  });
  function notNumber(i) { return (i.validity && i.validity.badInput) || (i.value === '' && i.hasAttribute('data-am-typed')); }

  function formValues(scope) {
    var v = {};
    $$('input', scope).forEach(function (i) { v[i.name] = i.type === 'checkbox' ? i.checked : i.value; });
    return v;
  }

  // ---------------------------------------------------------------- calculator
  $$('form[data-am-calc]').forEach(function (form) {
    var cfg = data(form, 'am-calc-data');
    if (!cfg) return;
    var key = 'am:calc:' + form.getAttribute('data-am-calc');
    var out = $('.am-result', form);
    var pick = $('[data-am-formula]', form);

    function current() {
      var id = pick ? pick.value : cfg.formulas[0].id;
      for (var i = 0; i < cfg.formulas.length; i++) if (cfg.formulas[i].id === id) return cfg.formulas[i];
      return cfg.formulas[0];
    }
    function fieldset(f) { return $('fieldset[data-am-fid="' + f.id + '"]', form); }
    function show() {
      $$('fieldset[data-am-fid]', form).forEach(function (fs) {
        var on = fs.getAttribute('data-am-fid') === current().id;
        fs.hidden = !on;
        $$('input', fs).forEach(function (i) { i.disabled = !on; });
      });
    }

    var saved = store.get(key);
    if (saved) {
      if (pick && saved.formula) pick.value = saved.formula;
      $$('input', form).forEach(function (i) {
        var fs = i.closest('fieldset');
        var v = saved.values && fs && fs.getAttribute('data-am-fid') === saved.formula ? saved.values[i.name] : undefined;
        if (v === undefined) return;
        if (i.type === 'checkbox') i.checked = !!v; else i.value = v;
      });
    }
    show();
    if (pick) pick.addEventListener('change', function () { show(); out.hidden = true; });

    function run(quiet) {
      var f = current();
      var values = formValues(fieldset(f));
      // A number field typed as "1,050" reads as empty: say why instead of "Enter your marks" (F-24).
      var typo = $$('input[type="number"]', fieldset(f)).filter(notNumber)[0];
      var r = typo ? { error: 'Enter numbers only, without commas.', el: typo } : aggregate(f, values);
      if (r.error) {
        if (quiet) return;
        out.hidden = false;
        out.innerHTML = '<p class="am-error" role="alert">' + esc(r.error) + '</p>';
        var bad = r.el || $('[name="o_' + r.key + '"]', fieldset(f));
        if (bad) bad.focus();
        return;
      }
      store.set(key, { formula: f.id, values: values, agg: r.aggregate });
      var agg = r.aggregate;
      var text = cfg.share.replace('{uni}', cfg.uni).replace('{aggregate}', agg.toFixed(2)).replace('{url}', cfg.url);
      var rows = '';
      r.parts.forEach(function (p) {
        rows += '<tr><td>' + esc(p.label) + '</td><td>' + p.obtained + ' / ' + p.total + '</td><td>' + p.weight + '%</td><td>' + p.share.toFixed(4) + '</td></tr>';
      });
      var html = '<p class="am-result-main">Your ' + esc(cfg.uni) + ' aggregate is <strong>' + pct(agg) + '</strong></p>' +
        '<div class="am-table-wrap"><table class="am-table"><caption>How your aggregate adds up</caption><thead><tr><th scope="col">Part</th><th scope="col">Marks</th><th scope="col">Weight</th><th scope="col">Points</th></tr></thead><tbody>' + rows + '</tbody></table></div>' +
        '<div class="am-share"><a class="am-btn am-btn--wa" target="_blank" rel="noopener" href="' + esc(waLink(text)) + '">Share on WhatsApp</a> <button type="button" class="am-btn" data-am-copy="' + esc(text) + '">Copy result</button></div>';
      var fails = eligibility(f.elig, r.parts);
      var rowsOk = cfg.rows && cfg.rows.length ? eligibleRows(cfg.rows, fails) : [];
      var blocked = fails.length && cfg.rows && cfg.rows.length && !rowsOk.length;
      if (fails.length) html += eligNotice(fails, !(cfg.rows && cfg.rows.length) ? 'none' : blocked ? 'all' : 'some');
      if (blocked) {
        // Not eligible anywhere: aggregate and breakdown stay, the "Where can you get in?" list goes.
      } else if (cfg.rows && cfg.rows.length) {
        html += '<h2>Where can you get in?</h2><p class="am-muted">Compared with the ' + esc(cfg.uni) + ' newest official closing merits (open merit, the main list of the merit table; the year is shown on each row). Borderline = within ±' + cfg.band + ' of the closing aggregate. <a href="' + esc(cfg.meritUrl || '#') + '">Full merit list</a>.</p>' +
          chanceList(predict(agg, rowsOk, cfg.band), 'No programs to compare yet.');
      } else if (cfg.noRows) {
        html += cfg.noRows;
      }
      if (cfg.predUrl && !blocked) {
        html += '<p><a href="' + esc(cfg.predUrl + '?agg=' + agg.toFixed(2) + (cfg.rows && cfg.rows.length ? '&uni=' + encodeURIComponent(cfg.uni) : '')) + '">Open this aggregate in the merit predictor</a></p>';
      }
      out.hidden = false;
      out.innerHTML = html;
      if (!quiet) out.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    form.addEventListener('submit', function (e) { e.preventDefault(); run(false); });
    form.addEventListener('reset', function () {
      out.hidden = true;
      store.set(key, null);
      setTimeout(show, 0);
    });
    if (saved) run(true);
  });

  // ---------------------------------------------------------------- predictor
  $$('form[data-am-predictor]').forEach(function (form) {
    var cfg = data(form, 'am-pred-data');
    if (!cfg) return;
    var out = $('.am-result', form);
    var key = 'am:predictor';
    var saved = store.get(key);
    if (saved) $$('input,select', form).forEach(function (i) { if (saved[i.name] != null) i.value = saved[i.name]; });
    // ?agg=81.5&uni=PU (from calculators and merit-list pages) wins over the saved values.
    var qs = {};
    location.search.replace(/[?&]([^=&]+)=([^&]*)/g, function (m, k, v) { qs[k] = decodeURIComponent(v.replace(/\+/g, ' ')); });
    if (qs.agg) {
      form.agg.value = qs.agg;
      if (form.uni) form.uni.value = qs.uni || '';
      saved = true;
    }
    // Aggregates saved by the calculators on this device → one-tap buttons.
    var box = $('.am-saved', form);
    var mine = (cfg.saved || []).map(function (s) {
      var v = store.get('am:calc:' + s.id);
      return v && typeof v.agg === 'number' ? { uni: s.uni, agg: v.agg } : null;
    }).filter(Boolean);
    var used = null; // the saved aggregate a chip put in the box, named at the top of the result (F-21)
    if (box && mine.length) {
      box.innerHTML = '<p class="am-muted">Or use a saved aggregate:</p>' + mine.map(function (m) {
        return '<button type="button" class="am-btn" data-uni="' + esc(m.uni) + '" data-agg="' + m.agg.toFixed(2) + '">' + esc(m.uni) + ' ' + pct(m.agg) + '</button>';
      }).join(' ');
      box.hidden = false;
      box.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-agg]');
        if (!b) return;
        form.agg.value = b.getAttribute('data-agg');
        if (form.uni) form.uni.value = b.getAttribute('data-uni');
        used = { uni: b.getAttribute('data-uni'), agg: form.agg.value };
        run(false);
      });
    }

    function run(quiet) {
      var agg = num(form.agg.value);
      var bad = notNumber(form.agg);
      if (bad || isNaN(agg) || agg <= 0 || agg > 100) {
        if (quiet) return;
        out.hidden = false;
        out.innerHTML = '<p class="am-error" role="alert">' + (bad ? 'Enter numbers only, without commas.' : 'Enter an aggregate between 0 and 100.') + '</p>';
        form.agg.focus();
        return;
      }
      var filter = { uni: form.uni ? form.uni.value : '', field: form.field ? form.field.value : '', prov: form.prov ? form.prov.value : '' };
      store.set(key, { agg: form.agg.value, uni: filter.uni, field: filter.field, prov: filter.prov });
      out.hidden = false;
      var list = predict(agg, cfg.rows, cfg.band, filter);
      var below = list.filter(function (r) { return r.min && agg < r.min; });
      var note = '';
      if (below.length) {
        list = list.filter(function (r) { return !(r.min && agg < r.min); });
        var ex = below[0];
        note = '<div class="am-answer am-elig" role="status"><p><strong>' + below.length + ' program' + (below.length > 1 ? 's' : '') + ' left out:</strong> an aggregate under ' + pct(ex.min) + ' cannot meet the ' + esc(ex.uni) + ' eligibility minimums (' + esc(ex.minNote) + ') for ' + esc(ex.program) + (below.length > 1 ? ' (and ' + (below.length - 1) + ' more with their own minimums)' : '') + '. Check your marks in that university\'s calculator.</p></div>';
      }
      var head = used && used.agg === form.agg.value ? '<p class="am-result-main">Using your saved ' + esc(used.uni) + ' aggregate ' + esc(used.agg) + '%</p>' : '';
      out.innerHTML = head + note + chanceList(list, below.length ? 'No other programs match these filters.' : 'No programs match these filters yet.');
    }
    form.addEventListener('submit', function (e) { e.preventDefault(); run(false); });
    if (saved) run(true);
  });

  // ---------------------------------------------------------------- scrollable tables
  // A table wider than the screen scrolls inside its wrapper: make that region keyboard-reachable.
  $$('.am-table-wrap').forEach(function (w) {
    if (w.scrollWidth <= w.clientWidth) return;
    var cap = $('caption', w);
    w.tabIndex = 0;
    w.setAttribute('role', 'region');
    w.setAttribute('aria-label', cap ? cap.textContent : 'Table');
  });

  // ---------------------------------------------------------------- merit table
  $$('[data-am-table]').forEach(function (box) {
    var table = $('table', box);
    var tbody = table.tBodies[0];
    var count = $('.am-count', box);
    var selects = $$('[data-am-filter]', box);

    function apply() {
      var shown = 0;
      $$('tr', tbody).forEach(function (tr) {
        var on = selects.every(function (s) { return !s.value || tr.getAttribute('data-' + s.getAttribute('data-am-filter')) === s.value; });
        tr.classList.toggle('am-off', !on);
        if (on) shown++;
      });
      if (count) count.textContent = 'Showing ' + shown + ' of ' + tbody.rows.length + ' rows.';
    }
    selects.forEach(function (s) { s.addEventListener('change', apply); });
    apply();

    $$('[data-am-sort]', table).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var col = +btn.getAttribute('data-am-sort');
        var isNum = btn.hasAttribute('data-num');
        var th = btn.parentNode;
        var dir = th.getAttribute('aria-sort') === 'descending' ? 1 : -1;
        if (!isNum && !th.getAttribute('aria-sort')) dir = 1;
        $$('th[aria-sort]', table).forEach(function (h) { h.removeAttribute('aria-sort'); });
        th.setAttribute('aria-sort', dir === 1 ? 'ascending' : 'descending');
        var rows = Array.prototype.slice.call(tbody.rows);
        rows.sort(function (a, b) {
          var x = a.cells[col], y = b.cells[col];
          if (isNum) {
            var nx = parseFloat(x.getAttribute('data-v')), ny = parseFloat(y.getAttribute('data-v'));
            if (isNaN(nx)) return 1;
            if (isNaN(ny)) return -1;
            return (nx - ny) * dir;
          }
          return x.textContent.localeCompare(y.textContent) * dir;
        });
        rows.forEach(function (r) { tbody.appendChild(r); });
      });
    });
  });

  // ---------------------------------------------------------------- embed builder
  $$('[data-am-embed]').forEach(function (box) {
    var cfg = data(box, 'am-embed-data');
    if (!cfg) return;
    var pick = $('select', box), code = $('textarea', box), copy = $('[data-am-copy]', box), prev = $('.am-embed-preview', box);
    var origin = location.origin;
    function snippet(slug) {
      var t = cfg[slug], id = 'am-embed-' + slug;
      return '<iframe id="' + id + '" src="' + t.src + '" title="' + t.label + '" style="width:100%;max-width:640px;height:720px;border:1px solid #dfe3e1;border-radius:8px" loading="lazy"></iframe>\n' +
        '<p style="font-size:14px"><a href="' + t.page + '">' + t.label + '</a> by Apna Merit</p>\n' +
        '<script>addEventListener("message",function(e){if(e.origin==="' + origin + '"&&e.data&&e.data.amEmbed==="' + slug + '"){document.getElementById("' + id + '").style.height=e.data.h+"px"}})</' + 'script>';
    }
    function update() {
      code.value = snippet(pick.value);
      copy.setAttribute('data-am-copy', code.value);
      prev.hidden = true;
      prev.innerHTML = '';
    }
    pick.addEventListener('change', update);
    $('[data-am-preview]', box).addEventListener('click', function () {
      var t = cfg[pick.value];
      prev.innerHTML = '<iframe src="' + esc(t.src) + '" title="' + esc(t.label) + '" style="width:100%;height:720px;border:1px solid #dfe3e1;border-radius:8px"></iframe>';
      prev.hidden = false;
    });
    addEventListener('message', function (e) {
      var f = $('iframe', prev);
      if (f && e.origin === origin && e.data && e.data.amEmbed === pick.value) f.style.height = e.data.h + 'px';
    });
  });

  // ---------------------------------------------------------------- embedded widget: report height to the parent page
  var embedId = document.body && document.body.getAttribute('data-am-embed-id');
  if (embedId && window.parent !== window) {
    var lastH = 0;
    var report = function () {
      var h = Math.ceil(document.documentElement.scrollHeight);
      if (Math.abs(h - lastH) > 2) { lastH = h; window.parent.postMessage({ amEmbed: embedId, h: h }, '*'); }
    };
    if (window.ResizeObserver) new ResizeObserver(report).observe(document.body);
    addEventListener('load', report);
    report();
  }

  // ---------------------------------------------------------------- submit a merit list (mailto, nothing stored)
  $$('form[data-am-submit]').forEach(function (form) {
    var out = $('.am-result', form);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var uni = form.uni.value.trim(), url = form.url.value.trim();
      if (!uni || !/^https?:\/\/\S+\.\S+/.test(url)) {
        out.hidden = false;
        out.innerHTML = '<p class="am-error" role="alert">Enter the university and the official link (starting with https://).</p>';
        (uni ? form.url : form.uni).focus();
        return;
      }
      var subject = 'Merit list: ' + uni + ' ' + form.year.value + ' (' + form.list.value + ')';
      var body = 'University: ' + uni + '\nList: ' + form.list.value + '\nYear: ' + form.year.value + '\nOfficial link: ' + url + '\n\n' + form.note.value;
      location.href = 'mailto:' + form.getAttribute('data-am-submit') + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
      out.hidden = false;
      out.innerHTML = '<p>Your email app should open now. If nothing happens, send the link to <a href="mailto:' + esc(form.getAttribute('data-am-submit')) + '">' + esc(form.getAttribute('data-am-submit')) + '</a>. Shukriya!</p>';
    });
  });

  // ---------------------------------------------------------------- percentage calculator
  $$('form[data-am-percent]').forEach(function (form) {
    var run = function () {
      $$('[data-am-pc]', form).forEach(function (o) {
        var k = o.getAttribute('data-am-pc');
        var ob = parseFloat(form['o_' + k].value), t = parseFloat(form['t_' + k].value);
        o.textContent = ob >= 0 && t > 0 && ob <= t ? (Math.round(ob / t * 10000) / 100).toFixed(2) + '%' : (ob > t ? 'Marks are more than the total' : '');
      });
    };
    form.addEventListener('input', run);
    form.addEventListener('submit', function (e) { e.preventDefault(); run(); });
  });

  // ---------------------------------------------------------------- IBCC equivalence
  $$('form[data-am-ibcc]').forEach(function (form) {
    var cfg = data(form, 'am-ibcc-data'), out = $('.am-result', form);
    if (!cfg) return;
    var level = function () { return form.level.value; };
    form.level.addEventListener('change', function () {
      $$('.am-ib-row', form).forEach(function (r) { r.hidden = level() === 'o' ? !r.hasAttribute('data-o') : !r.hasAttribute('data-a'); });
      out.hidden = true;
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var astar = Math.min(100, Math.max(90, parseFloat(form.astar.value) || 90)), sum = 0, n = 0, missing = 0;
      $$('.am-ib-row', form).forEach(function (r) {
        if (r.hidden) return;
        var g = $('select', r).value;
        if (!g) { missing++; return; }
        sum += (g === 'A*' || g === '9') ? astar : cfg.grades[g]; n++;
      });
      out.hidden = false;
      if (!n || missing) { out.innerHTML = '<p class="am-error" role="alert">Choose a grade for every subject.</p>'; return; }
      var p = sum / n;
      out.innerHTML = '<p class="am-result-main">Equivalent percentage: <strong>' + p.toFixed(2) + '%</strong></p><p>' + sum.toFixed(0) + ' out of ' + (n * 100) + ' marks over ' + n + ' subjects. Use this percentage in place of Matric or FSc in our <a href="/aggregate-calculator/">aggregate calculators</a>. Your IBCC certificate is the final word.</p>';
    });
  });

  // ---------------------------------------------------------------- share
  document.addEventListener('click', function (e) {
    var copy = e.target.closest('[data-am-copy]');
    if (copy) {
      var text = copy.getAttribute('data-am-copy');
      var done = function () { var t = copy.textContent; copy.textContent = 'Copied'; setTimeout(function () { copy.textContent = t; }, 1500); };
      if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, function () {});
      return;
    }
    var share = e.target.closest('[data-am-share]');
    if (share && navigator.share) {
      var box = share.closest('.am-share');
      navigator.share({ title: box.getAttribute('data-title'), url: box.getAttribute('data-url') }).catch(function () {});
    }
  });
  if (navigator.share) $$('[data-am-share]').forEach(function (b) { b.hidden = false; });
})(typeof window !== 'undefined' ? window : globalThis);
