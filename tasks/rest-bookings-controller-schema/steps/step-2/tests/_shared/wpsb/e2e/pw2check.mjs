// Convert a Playwright JSON report into a wp-swe-bench check JSON.
// Usage: node pw2check.mjs <name> <required 0|1> <report.json> <stdout.log>
import fs from 'node:fs';

const [name, required, report, log] = process.argv.slice(2);
const result = { name, required: required === '1', passed: 0, total: 0, ok: false, failures: [] };

function walk(suite, titles = []) {
	for (const spec of suite.specs || []) {
		for (const test of spec.tests || []) {
			result.total++;
			const last = (test.results || []).at(-1);
			const status = last?.status;
			const id = [...titles, spec.title].filter(Boolean).join(' › ');
			if (test.status === 'expected' && status === 'passed') {
				result.passed++;
			} else {
				const err = (last?.errors || []).map((e) => e.message || e.value || '').join('\n') || status || 'unknown';
				result.failures.push(`${id} => ${String(err).replace(/\u001b\[[0-9;]*m/g, '').slice(0, 1500)}`);
			}
		}
	}
	for (const child of suite.suites || []) walk(child, [...titles, child.title]);
}

try {
	const data = JSON.parse(fs.readFileSync(report, 'utf8'));
	for (const s of data.suites || []) walk(s, []);
	for (const e of data.errors || []) {
		result.total++;
		result.failures.push(`global error => ${String(e.message || '').slice(0, 1500)}`);
	}
} catch (e) {
	const tail = fs.existsSync(log) ? fs.readFileSync(log, 'utf8').slice(-3000) : '';
	result.failures.push(`Playwright produced no JSON report: ${e.message}\n${tail}`);
}
if (result.total === 0) {
	result.total = 1;
	if (!result.failures.length) result.failures.push('No E2E tests were executed.');
}
result.ok = result.passed === result.total;
process.stdout.write(JSON.stringify(result, null, 2) + '\n');
