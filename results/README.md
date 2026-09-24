# Results

One file per benchmark run, generated with `python3 tools/report.py <jobs-dir>/<job>` and named
`<date>-<agent>-<model>.md`. Only runs of the full dataset at a tagged benchmark version belong here;
state the version (commit or tag), the agent and its options, and the number of trials excluded for
infrastructure errors. Single runs are noisy (with 51 tasks, one task is ~2 percentage points), so
treat differences of a few points between runs as noise.
