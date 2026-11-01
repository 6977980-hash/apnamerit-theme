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
      if (isNaN(obtained)) return { error: 'Enter your ' + marksOf(p.label) + '.', key: p.key };
      if (obtained < 0) return { error: marksOf(p.label) + ' cannot be negative.', key: p.key };
      if (isNaN(total) || total <= 0) return { error: 'Enter the total marks for ' + p.label + '.', key: p.key };
      if (obtained > total) return { error: marksOf(p.label) + ' cannot be more than ' + total + '.', key: p.key };
      // Bonus marks (Hafiz-e-Quran) are added to marks earned and may take a part above its total:
      // PU, UET Taxila and KU divide (marks + additional) by the plain total (audit TL-01).
      var bonus = extra[p.key] || 0;
      var got = obtained + bonus;
      var row = { key: p.key, label: p.label, obtained: got, raw: obtained, bonus: bonus, total: total, weight: num(p.weight), share: (got / total) * num(p.weight) };
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

  /**
   * Reverse calculator (idea 2): marks needed in one part to reach a target aggregate, with the
   * other parts as entered. The aggregate is linear in each part, so two evaluations fix the line.
   * Returns {need, total, pct, max} (need may be < 0 or > total: the caller words that) or {error}.
   */
  function marksNeeded(formula, values, partKey, target) {
    var part = null;
    for (var i = 0; i < formula.parts.length; i++) if (formula.parts[i].key === partKey) part = formula.parts[i];
    if (!part) return { error: 'Unknown part.' };
    var total = num(values['t_' + partKey] != null && values['t_' + partKey] !== '' ? values['t_' + partKey] : part.total);
    if (isNaN(total) || total <= 0) return { error: 'Enter the total marks for ' + part.label + '.', key: partKey };
    var v0 = {}, v1 = {};
    for (var k in values) { v0[k] = values[k]; v1[k] = values[k]; }
    v0['o_' + partKey] = 0; v1['o_' + partKey] = total;
    v0['t_' + partKey] = total; v1['t_' + partKey] = total;
    var a0 = aggregate(formula, v0), a1 = aggregate(formula, v1);
    if (a0.error) return a0;
    if (a1.error) return a1;
    var slope = (a1.aggregate - a0.aggregate) / total;
    if (slope <= 0) return { error: part.label + ' has no weight in this formula.' };
    var need = (target - a0.aggregate) / slope;
    return { need: need, total: total, pct: need / total * 100, max: a1.aggregate, min: a0.aggregate };
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
        // A row value may be a list (a university in several provinces): any match counts.
        for (var k in filter) if (filter[k] && (Array.isArray(r[k]) ? r[k].indexOf(filter[k]) < 0 : r[k] !== filter[k])) return false;
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

  var AM = { aggregate: aggregate, chance: chance, predict: predict, eligibility: eligibility, eligibleRows: eligibleRows, marksNeeded: marksNeeded };
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
  // Query string as an object; setQuery() writes a result's inputs into the address bar so the
  // link can be shared (the page reads them back on load). Canonical stays the clean URL.
  function getQuery() {
    var qs = {};
    location.search.replace(/[?&]([^=&]+)=([^&]*)/g, function (m, k, v) { qs[decodeURIComponent(k)] = decodeURIComponent(v.replace(/\+/g, ' ')); });
    return qs;
  }
  function withQuery(base, obj) {
    var parts = [];
    for (var k in obj) if (obj[k] !== '' && obj[k] != null && obj[k] !== false) parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(obj[k] === true ? '1' : obj[k]));
    return base.replace(/[?#].*$/, '') + (parts.length ? '?' + parts.join('&') : '');
  }
  function setQuery(obj) {
    try { history.replaceState(null, '', withQuery(location.pathname, obj)); } catch (e) { /* file: or sandbox */ }
  }
  // "5 programs Strong, 2 Borderline" for share texts (chance list already computed).
  function chanceSummary(list) {
    var n = { strong: 0, borderline: 0 };
    list.forEach(function (r) { if (n[r.chance] != null) n[r.chance]++; });
    if (!n.strong && !n.borderline) return '';
    return (n.strong ? n.strong + ' program' + (n.strong > 1 ? 's' : '') + ' Strong' : '') + (n.strong && n.borderline ? ', ' : '') + (n.borderline ? n.borderline + ' Borderline' : '');
  }

  function chanceList(list, empty) {
    if (!list.length) return '<p>' + esc(empty) + '</p>';
    var groups = { strong: [], borderline: [], unlikely: [] };
    list.forEach(function (r) { groups[r.chance].push(r); });
    // Programs in order of their best closing merit (MBBS public before MBBS private before BDS), so a
    // Strong list of private colleges does not hide the public ones further down (audit FE).
    var rank = {};
    list.slice().sort(function (a, b) { return b.ref - a.ref; }).forEach(function (r) {
      var k = (r.uni || '') + '|' + r.program;
      if (!(k in rank)) rank[k] = Object.keys(rank).length;
    });
    var college = list.every(function (r) { return r.campus; }) && Object.keys(rank).length <= 4;
    var html = '';
    ['strong', 'borderline', 'unlikely'].forEach(function (g) {
      var rows = groups[g];
      if (!rows.length) return;
      if (college) rows = rows.slice().sort(function (a, b) { return rank[(a.uni || '') + '|' + a.program] - rank[(b.uni || '') + '|' + b.program] || b.ref - a.ref; });
      var max = g === 'unlikely' ? 10 : 50;
      html += '<h3><span class="am-chip am-chip--' + g + '">' + LABEL[g] + '</span> ' + rows.length + ' program' + (rows.length > 1 ? 's' : '') + '</h3>' +
        (rows.length > max ? '<p class="am-muted">Showing the first ' + max + ' of ' + rows.length + ', highest closing merit first. Pick a university, field or province to narrow the list.</p>' : '') + '<ul class="am-plist">';
      rows.slice(0, max).forEach(function (r) {
        var name = (r.uni ? r.uni + ': ' : '') + r.program + (r.campus ? ' (' + r.campus + (r.shift ? ', ' + r.shift : '') + ')' : '');
        var link = r.url ? '<a href="' + esc(r.url) + '">' + esc(name) + '</a>' : esc(name);
        html += '<li>' + link + ' <span class="am-muted">closed at ' + pct(r.ref) + ' (' + r.year + '), you are ' + (r.gap >= 0 ? '+' : '') + r.gap.toFixed(2) + '</span></li>';
      });
      html += '</ul>';
    });
    return html;
  }

  // Suggested preference order (idea 3): one university's programs, highest closing merit first,
  // grouped Reach (Unlikely within 2 bands), Match (Borderline) and Safe (Strong); copy as a numbered list.
  var PREF = { unlikely: 'Reach', borderline: 'Match', strong: 'Safe' };
  function prefList(list, band, guideUrl) {
    var unis = {};
    list.forEach(function (r) { unis[r.uni || ''] = true; });
    if (Object.keys(unis).length !== 1 || list.length < 2) return '';
    var rows = list.filter(function (r) { return r.chance !== 'unlikely' || r.gap >= -band * 2; })
      .sort(function (a, b) { return b.ref - a.ref; });
    if (rows.length < 2) return '';
    // College-wise lists (every row has a campus, few programs): one preference list per program,
    // because MBBS public, MBBS private and BDS are separate forms. Otherwise one list of programs.
    var progs = {};
    rows.forEach(function (r) { progs[r.program] = true; });
    var byProg = rows.every(function (r) { return r.campus; }) && Object.keys(progs).length <= 4;
    var groups = byProg ? Object.keys(progs).map(function (pg) { return { label: pg, rows: rows.filter(function (r) { return r.program === pg; }) }; }) : [{ label: '', rows: rows }];
    var name = function (r) { return byProg ? r.campus + (r.shift ? ' (' + r.shift + ')' : '') : r.program + (r.campus ? ' (' + r.campus + (r.shift ? ', ' + r.shift : '') + ')' : ''); };
    var html = '';
    groups.forEach(function (g) {
      if (g.rows.length < 2) return;
      var li = g.rows.map(function (r) {
        return '<li>' + esc(name(r)) + ' <span class="am-chip am-chip--' + r.chance + '">' + PREF[r.chance] + '</span> <span class="am-muted">' + pct(r.ref) + '</span></li>';
      }).join('');
      var text = (g.label ? g.label + '\n' : '') + g.rows.map(function (r, i) { return (i + 1) + '. ' + name(r) + ' (' + PREF[r.chance] + ', closed at ' + pct(r.ref) + ')'; }).join('\n');
      html += (g.label ? '<h3>' + esc(g.label) + '</h3>' : '') + '<ol class="am-pref">' + li + '</ol><p><button type="button" class="am-btn" data-am-copy="' + esc(text) + '">Copy ' + (g.label ? esc(g.label) + ' ' : '') + 'preference list</button></p>';
    });
    if (!html) return '';
    return '<h2>Suggested preference order</h2><p class="am-muted">Highest closing merit first: Reach (within ' + band * 2 + ' points above you), then Match, then Safe. Allotment is merit-cum-preference, so a high first choice never costs you a lower one; rearrange within each group by the college you would rather attend, and never leave out a college you would still accept.' +
      (guideUrl ? ' <a href="' + esc(guideUrl) + '">How to fill the preference list</a>.' : '') + '</p>' + html;
  }

  // tail: 'none' (no merit rows to list), 'all' (list removed) or 'some' (matching programs removed).
  function eligNotice(fails, tail) {
    return '<div class="am-answer am-elig" role="status">' + fails.map(function (f) {
      return '<p><strong>Below the ' + esc(f.label) + ' of ' + num(f.min_pct) + '%</strong> (your ' + esc(f.partLabel) + ': ' + (Math.floor(f.mine * 100) / 100).toFixed(2) + '%, <a href="' + esc(f.source_url) + '" rel="noopener" target="_blank">source</a>). With these marks you are not eligible for ' + esc(f.applies_to) + ' this year' +
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
    // A shared link (?f=pmdc&o_mdcat=160&t_mdcat=180...) wins over this device's saved marks.
    var q = getQuery(), fromLink = null;
    for (var qk in q) if (/^(o|t|b)_/.test(qk)) { fromLink = { formula: q.f || '', values: {} }; break; }
    if (fromLink) {
      for (qk in q) if (/^(o|t|b)_/.test(qk)) fromLink.values[qk] = qk.charAt(0) === 'b' ? q[qk] === '1' : q[qk];
      if (!fromLink.formula || !cfg.formulas.some(function (f) { return f.id === fromLink.formula; })) fromLink.formula = cfg.formulas[0].id;
      saved = fromLink;
    }
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
      // Shareable result: marks go into the address bar and the WhatsApp text carries the chances (idea 1).
      var link = { f: f.id };
      for (var vk in values) if (values[vk] !== '' && values[vk] !== false) link[vk] = values[vk];
      setQuery(link);
      var resultUrl = withQuery(cfg.url, link);
      var fails = eligibility(f.elig, r.parts);
      var rowsOk = cfg.rows && cfg.rows.length ? eligibleRows(cfg.rows, fails) : [];
      var blocked = fails.length && cfg.rows && cfg.rows.length && !rowsOk.length;
      var list = blocked || !rowsOk.length ? [] : predict(agg, rowsOk, cfg.band);
      var summary = chanceSummary(list);
      var text = cfg.share.replace('{uni}', cfg.uni).replace('{aggregate}', agg.toFixed(2)).replace('{chances}', summary ? ' (' + summary + ')' : '').replace('{url}', resultUrl);
      var rows = '';
      r.parts.forEach(function (p) {
        rows += '<tr><td>' + esc(p.label) + '</td><td>' + (p.bonus ? p.raw + ' + ' + p.bonus : p.obtained) + ' / ' + p.total + '</td><td>' + p.weight + '%</td><td>' + p.share.toFixed(4) + '</td></tr>';
      });
      var html = '<p class="am-result-main">Your ' + esc(cfg.uni) + ' aggregate is <strong>' + pct(agg) + '</strong></p>' +
        '<div class="am-table-wrap am-fit"><table class="am-table"><caption>How your aggregate adds up</caption><thead><tr><th scope="col">Part</th><th scope="col">Marks</th><th scope="col">Weight</th><th scope="col">Points</th></tr></thead><tbody>' + rows + '</tbody></table></div>' +
        '<div class="am-share"><a class="am-btn am-btn--wa" target="_blank" rel="noopener" href="' + esc(waLink(text)) + '">Share on WhatsApp</a> <button type="button" class="am-btn" data-am-copy="' + esc(text) + '">Copy message</button> <button type="button" class="am-btn" data-am-copy="' + esc(resultUrl) + '">Copy link</button></div>';
      if (fails.length) html += eligNotice(fails, !(cfg.rows && cfg.rows.length) ? 'none' : blocked ? 'all' : 'some');
      if (blocked) {
        // Not eligible anywhere: aggregate and breakdown stay, the "Where can you get in?" list goes.
      } else if (cfg.rows && cfg.rows.length) {
        html += '<h2>Where can you get in?</h2><p class="am-muted">Compared with the ' + esc(cfg.uni) + ' newest official closing merits (open merit, the main list of the merit table; the year is shown on each row). Borderline = within ±' + cfg.band + ' of the closing aggregate. <a href="' + esc(cfg.meritUrl || '#') + '">Full merit list</a>.</p>' +
          chanceList(list, 'No programs to compare yet.');
      } else if (cfg.noRows) {
        html += cfg.noRows;
      }
      if (cfg.predUrl && !blocked) {
        html += '<p><a href="' + esc(cfg.predUrl + '?agg=' + agg.toFixed(2) + (cfg.rows && cfg.rows.length ? '&uni=' + encodeURIComponent(cfg.uni) : '')) + '">Open this aggregate in the merit predictor</a></p>';
      }
      // Reverse calculator (idea 2): marks needed in one part (default: the test) for a target program.
      if (cfg.rows && cfg.rows.length) {
        var solve = f.parts.filter(function (p) { return /test|mdcat|ecat|net|nat|sat/i.test(p.key + ' ' + p.label); })[0] || f.parts[0];
        var targets = cfg.rows.slice().sort(function (a, b) { return b.ref - a.ref; }).map(function (r, i) {
          var nm = r.program + (r.campus ? ' (' + r.campus + (r.shift ? ', ' + r.shift : '') + ')' : '');
          return '<option value="' + i + '">' + esc(nm) + ' — ' + pct(r.ref) + ' (' + r.year + ')</option>';
        }).join('');
        var parts = f.parts.filter(function (p) { return num(p.weight) > 0; }).map(function (p) {
          return '<option value="' + esc(p.key) + '"' + (p.key === solve.key ? ' selected' : '') + '>' + esc(p.label) + '</option>';
        }).join('');
        html += '<h2>Marks needed for a target</h2><p class="am-muted">Pick a program: with your other marks as entered, this is the least you need in one part to reach its newest closing merit.</p>' +
          '<div class="am-target"><div class="am-field"><label for="am-tg-' + esc(form.getAttribute('data-am-calc')) + '">Target program</label><select id="am-tg-' + esc(form.getAttribute('data-am-calc')) + '" data-am-target>' + targets + '</select></div>' +
          '<div class="am-field"><label for="am-tp-' + esc(form.getAttribute('data-am-calc')) + '">Solve for</label><select id="am-tp-' + esc(form.getAttribute('data-am-calc')) + '" data-am-part>' + parts + '</select></div>' +
          '<p class="am-result-main" data-am-need></p></div>';
      }
      out.hidden = false;
      out.innerHTML = html;
      var tgt = $('[data-am-target]', out);
      if (tgt) {
        var needOut = $('[data-am-need]', out), partSel = $('[data-am-part]', out);
        var sorted = cfg.rows.slice().sort(function (a, b) { return b.ref - a.ref; });
        var showNeed = function () {
          var r = sorted[+tgt.value], key = partSel.value, m = marksNeeded(f, values, key, r.ref), lbl = '';
          f.parts.forEach(function (p) { if (p.key === key) lbl = p.label; });
          if (m.error) { needOut.textContent = m.error; return; }
          var whole = m.total >= 50 && m.total === Math.floor(m.total);
          var fmt = function (n) { return whole ? String(Math.max(0, Math.ceil(n - 1e-9))) : Math.max(0, n).toFixed(2); };
          var floor = null;
          (f.elig || []).forEach(function (fl) { if (fl.part === key && (!fl.programs || new RegExp(fl.programs).test(r.program))) floor = floor == null ? num(fl.min_pct) : Math.max(floor, num(fl.min_pct)); });
          var txt;
          if (m.need > m.total) txt = 'Not reachable this way: even full marks in ' + lbl + ' give ' + pct(m.max) + ', below ' + pct(r.ref) + '.';
          else if (m.need <= 0) txt = 'Your other marks alone already reach ' + pct(r.ref) + '.';
          else txt = 'You need at least <strong>' + fmt(m.need) + ' / ' + m.total + '</strong> in ' + esc(lbl) + ' (' + m.pct.toFixed(1) + '%) to reach ' + pct(r.ref) + ', the newest closing merit (' + r.year + ').' +
            (m.need + cfg.band / ((m.max - m.min) / m.total) <= m.total ? ' For a Strong chance (+' + cfg.band + ') about ' + fmt(m.need + cfg.band / ((m.max - m.min) / m.total)) + '.' : ' A Strong margin (+' + cfg.band + ') would need more than full marks.');
          if (floor != null && m.need / m.total * 100 < floor && m.need <= m.total) txt += ' The minimum to apply is ' + floor + '% (' + fmt(floor * m.total / 100) + ' / ' + m.total + ').';
          needOut.innerHTML = txt;
        };
        tgt.addEventListener('change', showNeed);
        partSel.addEventListener('change', showNeed);
        showNeed();
      }
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
    // ?agg=81.5&uni=PU&field=&prov= (from calculators, merit-list pages and shared links) wins over the saved values.
    var qs = getQuery();
    if (qs.agg) {
      form.agg.value = qs.agg;
      ['uni', 'field', 'prov'].forEach(function (k) { if (form[k]) form[k].value = qs[k] || ''; });
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
      // Shareable result: the inputs go into the address bar and into the WhatsApp text (idea 1).
      var link = { agg: agg.toFixed(2), uni: filter.uni, field: filter.field, prov: filter.prov };
      setQuery(link);
      var resultUrl = withQuery(location.origin + location.pathname, link);
      var summary = chanceSummary(list);
      var text = (cfg.share || 'Mera aggregate {aggregate}% hai{chances}. Apna check karo: {url}').replace('{uni}', filter.uni || '').replace('{aggregate}', agg.toFixed(2))
        .replace('{chances}', summary ? ': ' + summary + (filter.uni ? ' (' + filter.uni + ')' : '') : '').replace('{url}', resultUrl);
      var share = list.length ? '<div class="am-share"><a class="am-btn am-btn--wa" target="_blank" rel="noopener" href="' + esc(waLink(text)) + '">Share on WhatsApp</a> <button type="button" class="am-btn" data-am-copy="' + esc(text) + '">Copy message</button> <button type="button" class="am-btn" data-am-copy="' + esc(resultUrl) + '">Copy link</button></div>' : '';
      // "All universities": aggregates from different formulas are not comparable (audit FE).
      var unis = {};
      list.forEach(function (r) { unis[r.uni] = true; });
      var mix = !filter.uni && Object.keys(unis).length > 1 ? '<p class="am-muted"><strong>Programs from ' + Object.keys(unis).length + ' universities.</strong> Each university has its own formula, so your aggregate only compares fairly with the university whose calculator gave it. Pick that university above.</p>' : '';
      out.innerHTML = head + note + '<h2>Your chances' + (filter.uni ? ' at ' + esc(filter.uni) : '') + '</h2>' + mix + chanceList(list, below.length ? 'No other programs match these filters.' : 'No programs match these filters yet.') + prefList(list, cfg.band, cfg.prefUrl) + share;
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
    // Big lists keep the full table in <template class="am-more"> until it is needed (audit CI-04).
    var more = $('template.am-more', box);
    function expand() {
      if (!more) return;
      tbody.innerHTML = '';
      tbody.appendChild(document.importNode(more.content, true));
      more.parentNode.removeChild(more);
      more = null;
    }

    var qbox = $('[data-am-q]', box), q = '';
    function apply() {
      var shown = 0;
      q = qbox ? qbox.value.trim().toLowerCase() : '';
      $$('tr', tbody).forEach(function (tr) {
        var on = selects.every(function (s) { return !s.value || tr.getAttribute('data-' + s.getAttribute('data-am-filter')) === s.value; }) &&
          (!q || tr.textContent.toLowerCase().indexOf(q) >= 0);
        tr.classList.toggle('am-off', !on);
        if (on) shown++;
      });
      if (count) count.textContent = 'Showing ' + shown + ' of ' + (more ? +more.getAttribute('data-rows') : tbody.rows.length) + ' rows.';
    }
    selects.forEach(function (s) { s.addEventListener('change', function () { expand(); apply(); }); });
    // Text search (audit TL-05): searches the rows the other filters leave, so set List or Year to All for every row.
    if (qbox) qbox.addEventListener('input', function () { expand(); apply(); });
    apply();

    $$('[data-am-sort]', table).forEach(function (btn) {
      btn.addEventListener('click', function () {
        expand();
        apply();
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

  // ---------------------------------------------------------------- eligibility check (G-01)
  $$('form[data-am-elig]').forEach(function (form) {
    var cfg = data(form, 'am-elig-data'), out = $('.am-result', form);
    if (!cfg) return;
    // Start from the marks typed into this university's calculator, if any.
    var saved = store.get('am:calc:' + cfg.calc);
    if (saved && saved.values) $$('input', form).forEach(function (i) { if (saved.values[i.name]) i.value = saved.values[i.name]; });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var parts = [], bad = null;
      $$('input[name^="o_"]', form).forEach(function (i) {
        var k = i.name.slice(2), t = num(form['t_' + k].value), o = num(i.value);
        var label = i.closest('.am-field').querySelector('label').textContent;
        if (bad) return;
        if (notNumber(i)) bad = [i, 'Enter numbers only, without commas.'];
        else if (isNaN(o) || i.value === '') bad = [i, 'Enter your ' + label + '.'];
        else if (o < 0) bad = [i, label + ' cannot be negative.']; // audit TL-03
        else if (!(t > 0) || o > t) bad = [i, label + ' cannot be more than the total.'];
        else parts.push({ key: k, label: label.replace(/ marks$/, ''), obtained: o, total: t });
      });
      out.hidden = false;
      if (bad) { out.innerHTML = '<p class="am-error" role="alert">' + esc(bad[1]) + '</p>'; bad[0].focus(); return; }
      var fails = eligibility(cfg.floors, parts), ok = 0;
      var html = cfg.programs.map(function (prog) {
        var mine = fails.filter(function (f) { return !f.programs || new RegExp(f.programs).test(prog); });
        if (!mine.length) { ok++; return '<p><strong>' + esc(prog) + ': eligible.</strong> Your marks meet every ' + esc(cfg.uni) + ' minimum for ' + esc(prog) + '.</p>'; }
        return '<p><strong>' + esc(prog) + ': not eligible.</strong> ' + mine.map(function (f) {
          return 'Your ' + esc(f.partLabel) + ' is ' + (Math.floor(f.mine * 100) / 100).toFixed(2) + '%, below the ' + esc(f.label) + ' of ' + num(f.min_pct) + '% (<a href="' + esc(f.source_url) + '" rel="noopener" target="_blank">source</a>).';
        }).join(' ') + '</p>';
      }).join('');
      out.innerHTML = '<div class="am-answer' + (ok ? '' : ' am-elig') + '" role="status">' + html + '</div>' +
        (ok && cfg.calcUrl ? '<p>Next: <a href="' + esc(cfg.calcUrl) + '">work out your aggregate</a> and see which colleges it reached last year.</p>' : '');
    });
    form.addEventListener('reset', function () { out.hidden = true; });
  });

  // ---------------------------------------------------------------- season schedule: next step
  $$('[data-am-season]').forEach(function (box) {
    var today = new Date(Date.now() + 5 * 3600 * 1000).toISOString().slice(0, 10); // Asia/Karachi
    var next = null;
    $$('td[data-am-date]', box).forEach(function (td) {
      var d = td.getAttribute('data-am-date');
      if (d < today) td.className = 'am-past';
      else if (!next || d < next.getAttribute('data-am-date')) next = td;
    });
    var p = box.nextElementSibling;
    if (next && p && p.hasAttribute('data-am-next')) {
      // Countdown to the step's start (a running window counts as "now"); the step's guide = what to do now.
      var start = next.getAttribute('data-am-start') || next.getAttribute('data-am-date');
      var days = Math.round((Date.parse(start) - Date.parse(today)) / 86400000);
      var when = days <= 0 ? 'now, until ' + esc(next.textContent) : days === 1 ? 'tomorrow' : 'in ' + days + ' days';
      var guide = next.getAttribute('data-am-guide');
      p.innerHTML = '<strong>Next official step:</strong> ' + esc(next.getAttribute('data-am-step')) + ', ' + esc(next.textContent) + ' (' + when + ').' +
        (guide ? ' What to do now: <a href="' + esc(guide) + '">' + esc(next.getAttribute('data-am-guide-label')) + '</a>.' : '') + ' Past dates are greyed out.';
      p.hidden = false;
    }
  });

  // ---------------------------------------------------------------- home: next official step (audit FE)
  $$('[data-am-steps]').forEach(function (p) {
    var steps = [], span = $('span', p);
    try { steps = JSON.parse(p.getAttribute('data-am-steps')) || []; } catch (e) { /* keep the server line */ }
    if (!steps.length) return;
    var today = new Date(Date.now() + 5 * 3600 * 1000).toISOString().slice(0, 10); // Asia/Karachi
    var next = null;
    for (var i = 0; i < steps.length; i++) if (steps[i].e >= today) { next = steps[i]; break; }
    if (!next || !span) { p.hidden = true; return; }
    var days = Math.round((Date.parse(next.s) - Date.parse(today)) / 86400000);
    var when = days <= 0 ? 'now' : days === 1 ? 'tomorrow' : 'in ' + days + ' days';
    span.innerHTML = esc(next.t) + ', ' + esc(next.w) + ' (' + when + ').' + (next.g ? ' <a href="' + esc(next.g) + '">What to do now</a>' : '');
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
