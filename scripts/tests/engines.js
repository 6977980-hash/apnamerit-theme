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
process.stdout.write('engines: ' + n + ' cases, ' + fails + ' failed\n');
process.exit(fails ? 1 : 0);
