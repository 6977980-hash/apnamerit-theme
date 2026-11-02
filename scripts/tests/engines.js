/* Node tests for the am.js engines (audit TS-16): node scripts/tests/engines.js <repo root>.
 * Cases worked out by hand from the official formulas; run by scripts/check.py. */
'use strict';
var path = require('path');
var root = path.resolve(process.argv[2] || path.join(__dirname, '..', '..'));
var AM = require(path.join(root, 'assets/js/am.js'));
var uni = function (s) { return require(path.join(root, 'data/universities', s + '.json')); };
var fails = 0, n = 0;
function eq(name, got, want, tol) {
  n++;
  if (typeof want === 'number' ? !(Math.abs(got - want) <= (tol || 0.005)) : got !== want) {
    fails++; process.stdout.write('FAIL ' + name + ': got ' + got + ', want ' + want + '\n');
  }
}
function vals(f, o, extra) {
  var v = {}; f.parts.forEach(function (p) { if (o[p.key] != null) v['o_' + p.key] = o[p.key]; });
  for (var k in extra || {}) v[k] = extra[k];
  return v;
}
var pu = uni('pu').formulas[0];
// PU basic: (1000/4 + 1090 + 20 Hafiz) / (1100/4 + 1100) = 1360 / 1375 (TL-01: bonus not capped).
eq('PU Hafiz', AM.aggregate(pu, vals(pu, { matric: 1000, fsc: 1090 }, { b_hafiz: true })).aggregate, 98.9091);
eq('PU plain', AM.aggregate(pu, vals(pu, { matric: 1000, fsc: 1090 })).aggregate, 97.4545);
eq('PU full + Hafiz', AM.aggregate(pu, vals(pu, { matric: 1100, fsc: 1100 }, { b_hafiz: true })).aggregate, 101.4545);
var uhs = uni('uhs').formulas[0];
var r = AM.aggregate(uhs, vals(uhs, (function () { var o = {}; uhs.parts.forEach(function (p) { o[p.key] = p.total * 0.9; }); return o; })()));
eq('UHS 90% everywhere', r.aggregate, 90);
// marksNeeded inverts aggregate.
var base = (function () { var o = {}; uhs.parts.forEach(function (p) { o[p.key] = p.total * 0.85; }); return o; })();
var last = uhs.parts[uhs.parts.length - 1].key;
var need = AM.marksNeeded(uhs, vals(uhs, base), uhs.parts[0].key, 92);
var back = {}; for (var k in base) back[k] = base[k]; back[uhs.parts[0].key] = need.need;
eq('marksNeeded round trip', AM.aggregate(uhs, vals(uhs, back)).aggregate, 92, 0.01);
eq('negative rejected', !!AM.aggregate(uhs, vals(uhs, (function () { var o = {}; for (var k in base) o[k] = base[k]; o[last] = -5; return o; })())).error, true);
// predict: list-valued province matches any (NUMS in Punjab, Sindh, Balochistan).
var rows = [{ ref: 90, prov: ['Punjab', 'Sindh'] }, { ref: 80, prov: 'Sindh' }, { ref: 70, prov: 'Punjab' }];
eq('predict prov list', AM.predict(85, rows, 1.5, { prov: 'Punjab' }).length, 2);
eq('predict prov string', AM.predict(85, rows, 1.5, { prov: 'Sindh' }).length, 2);
// listWhen (idea 2026-11-02 #1): first list at or below the aggregate; never reached sorts last.
var lw = AM.listWhen(93.7, [['A', '', [94.0, 93.8, 93.69]], ['B', '', [93.5, 93.4]], ['C', '', [95, 94.9, null]]]);
eq('listWhen order', lw.map(function (r) { return r.name + r.list; }).join(','), 'B1,A3,C0');
eq('listWhen closing', lw[1].closing, 93.69);
eq('listWhen short', lw[2].short, 1.2);
// whatIf (idea #4): UHS 150/180, 1000/1100, 1000/1100 = 87.1212; +10 MDCAT = 89.8990.
var uhsF = uni('uhs').formulas[0];
var wv = vals(uhsF, { mdcat: 150, fsc: 1000, matric: 1000 });
var wi = AM.whatIf(uhsF, wv, 'mdcat', 10, [{ ref: 89.5 }, { ref: 90.5 }, { ref: 87 }]);
eq('whatIf gain', wi.gain, 2.7778, 0.0002);
eq('whatIf more', wi.more.length, 1);
eq('whatIf cap', AM.whatIf(uhsF, vals(uhsF, { mdcat: 175, fsc: 1000, matric: 1000 }), 'mdcat', 10, []).to, 180);
// profileValues (idea #3): "fast|nat" falls back to NAT; a missing field is named.
var pv = AM.profileValues({ test: 'fast|nat', fsc: 'fsc', inter: 'zero' }, { o_fast: '', o_nat: '70', t_nat: '100', o_fsc: '900', t_fsc: '1100' });
eq('profile fallback', pv.values.o_test, '70');
eq('profile zero', pv.values.o_inter, 0);
eq('profile missing', AM.profileValues({ fsc: 'fsc1' }, { o_fsc1: '' }).missing, 'fsc1');
process.stdout.write('engines: ' + n + ' cases, ' + fails + ' failed\n');
process.exit(fails ? 1 : 0);
