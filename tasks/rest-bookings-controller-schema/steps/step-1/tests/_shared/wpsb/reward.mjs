// Aggregate check results into Harbor's /logs/verifier/reward.json.
// Usage: node reward.mjs <checks-dir> <out.json> [expected-required-check...]
//
// reward.json keys (all numeric):
//   reward            1 iff every required check passed (and every expected check ran), else 0
//   required_passed   number of required checks that passed
//   required_total    number of required checks
//   <check-name>      pass fraction of that check (0..1), e.g. phpunit, e2e, build, wpcs
import fs from 'node:fs';
import path from 'node:path';

const [dir, out, ...expected] = process.argv.slice(2);
const checks = fs.existsSync(dir)
	? fs.readdirSync(dir).filter((f) => f.endsWith('.json')).map((f) => {
		try { return JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8')); }
		catch { return { name: f.replace(/\.json$/, ''), required: true, passed: 0, total: 1, ok: false, failures: ['unreadable check file'] }; }
	})
	: [];

const byName = new Map(checks.map((c) => [c.name, c]));
for (const name of expected) {
	if (!byName.has(name)) {
		const c = { name, required: true, passed: 0, total: 1, ok: false, failures: ['expected check did not run'] };
		checks.push(c);
		byName.set(name, c);
	}
}

const required = checks.filter((c) => c.required);
const reward = {
	reward: required.length > 0 && required.every((c) => c.ok) ? 1 : 0,
	required_passed: required.filter((c) => c.ok).length,
	required_total: required.length,
};
for (const c of checks) {
	reward[c.name] = c.total > 0 ? Math.round((c.passed / c.total) * 1000) / 1000 : 0;
}
fs.writeFileSync(out, JSON.stringify(reward, null, 2) + '\n');

// Human-readable summary next to it.
const lines = checks.map((c) => `${c.ok ? 'PASS' : 'FAIL'} ${c.required ? '[required]' : '[soft]    '} ${c.name} ${c.passed}/${c.total}` +
	(c.ok ? '' : '\n' + (c.failures || []).slice(0, 30).map((f) => '    - ' + String(f).split('\n').join('\n      ')).join('\n')));
fs.writeFileSync(path.join(path.dirname(out), 'summary.txt'), lines.join('\n') + `\n\nreward = ${reward.reward}\n`);
process.stderr.write(lines.join('\n') + `\nreward = ${reward.reward}\n`);
