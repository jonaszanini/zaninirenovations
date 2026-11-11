#!/usr/bin/env node
'use strict';

/**
 * Path → dorny filter → compute-ci-flags → selected browser/unit lists.
 * Uses tests/ci/workflow-filters.json (must match tests.yml).
 */
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const {
  loadFilters,
  aggregateFilterHits,
  envFromAggregate,
  computeBroadRun,
  matchFiltersForPath,
} = require('./match-workflow-filters.js');

const ROOT = path.resolve(__dirname, '../..');
const COMPUTE = path.join(__dirname, 'compute-ci-flags.sh');

function parseComputeOutput(text) {
  const out = {};
  for (const line of text.split('\n')) {
    const m = line.match(/^([a-z0-9_]+)=(true|false)$/);
    if (m) {
      out[m[1]] = m[2];
    }
  }
  return out;
}

function runCompute(changedPaths, extraEnv) {
  const tmp = fs.mkdtempSync(path.join(require('os').tmpdir(), 'ci-matrix-'));
  const changedFile = path.join(tmp, 'changed.txt');
  fs.writeFileSync(changedFile, changedPaths.join('\n') + (changedPaths.length ? '\n' : ''));
  const env = {
    GITHUB_REF: 'refs/pull/1/merge',
    GITHUB_EVENT_NAME: 'pull_request',
    DOCS_ONLY: 'false',
    HAS_CODE_CHANGES: 'true',
    CHANGED_FILES_FILE: changedFile,
    SLOW_INPUT: 'false',
    FILTER_GLOBAL: 'false',
    FILTER_BROWSER_ADMIN: 'false',
    FILTER_BROWSER_OPENPLAN3D: 'false',
    FILTER_BROWSER_QUOTATION: 'false',
    FILTER_BROWSER_CLIENT: 'false',
    ...extraEnv,
  };
  let analyzeOut;
  try {
    analyzeOut = execFileSync('node', [path.join(__dirname, 'match-workflow-filters.js'), '--analyze-changed', changedFile], {
      cwd: ROOT,
      encoding: 'utf8',
    });
  } catch (err) {
    fs.rmSync(tmp, { recursive: true, force: true });
    throw err;
  }
  for (const line of analyzeOut.split('\n')) {
    const t = line.trim();
    if (!t) {
      continue;
    }
    if (t.startsWith('FILTER_') && t.endsWith('=true')) {
      env[t.slice(0, t.indexOf('='))] = 'true';
    }
  }
  const stdout = execFileSync('bash', [COMPUTE], {
    cwd: ROOT,
    env: { ...process.env, ...env },
    encoding: 'utf8',
  });
  fs.rmSync(tmp, { recursive: true, force: true });
  return { flags: parseComputeOutput(stdout) };
}

function listBrowserSuites(flags) {
  if (flags.global_test_deps === 'true') {
    return readListFiles([
      'js-browser-admin.txt',
      'js-browser-openplan3d.txt',
      'js-browser-quotation.txt',
      'js-browser-client-projects.txt',
    ]);
  }
  const lists = [];
  if (flags.run_browser_admin === 'true') {
    lists.push('js-browser-admin.txt');
  }
  if (flags.run_browser_openplan3d === 'true') {
    lists.push('js-browser-openplan3d.txt');
  }
  if (flags.run_browser_quotation === 'true') {
    lists.push('js-browser-quotation.txt');
  }
  if (flags.run_browser_client === 'true') {
    lists.push('js-browser-client-projects.txt');
  }
  return readListFiles(lists);
}

function readListFiles(names) {
  const paths = new Set();
  for (const name of names) {
    const file = path.join(__dirname, name);
    const lines = fs.readFileSync(file, 'utf8').split('\n');
    for (const line of lines) {
      const t = line.trim();
      if (t && !t.startsWith('#')) {
        paths.add(t);
      }
    }
  }
  return [...paths].sort();
}

function assert(cond, msg) {
  if (!cond) {
    throw new Error(msg);
  }
}

const cases = [
  {
    name: 'admin hotkey only',
    paths: ['public/js/admin-hotkey-save.js'],
    expect: {
      run_browser: 'true',
      run_browser_admin: 'true',
      run_browser_openplan3d: 'false',
      run_browser_quotation: 'true',
      browserIncludes: ['tests/js/admin-hotkey-save-browser.test.js', 'tests/js/quotation-redraft-hotkey-lock-browser.test.js'],
      browserExcludes: ['tests/js/openplan3d-browser.test.js'],
    },
  },
  {
    name: 'openplan3d manifest only',
    paths: ['scripts/openplan3d-h1.json'],
    expect: {
      filter_room_area: 'true',
      run_browser_openplan3d: 'true',
      run_browser_admin: 'false',
      browserIncludes: ['tests/js/openplan3d-browser.test.js'],
      browserExcludes: ['tests/js/admin-hotkey-save-browser.test.js'],
    },
  },
  {
    name: 'measurement bridge PHP',
    paths: ['app/Services/OpenPlan3DMeasurementBridge.php'],
    expect: {
      filter_room_area: 'true',
      run_browser_openplan3d: 'true',
      run_browser_admin: 'false',
    },
  },
  {
    name: 'room area openplan3d blade',
    paths: ['resources/views/admin/utilities/_room_area_openplan3d.blade.php'],
    expect: {
      filter_room_area: 'true',
      run_browser_openplan3d: 'true',
    },
  },
  {
    name: 'admin layout shell',
    paths: ['resources/views/admin/layouts/app.blade.php'],
    expect: {
      run_browser_admin: 'true',
      run_browser_openplan3d: 'true',
      run_browser_quotation: 'true',
      run_browser_client: 'true',
    },
  },
  {
    name: 'info tip component',
    paths: ['resources/views/components/admin/info-tip.blade.php'],
    expect: {
      run_browser_admin: 'true',
      browserIncludes: ['tests/js/admin-info-tip-click.test.mjs'],
    },
  },
  {
    name: 'inch drag helper',
    paths: ['tests/js/openplan3d-inch-drag-helper.js'],
    expect: {
      run_browser_openplan3d: 'true',
      run_browser_admin: 'false',
    },
  },
  {
    name: 'openplan3d zanini tools',
    paths: ['public/js/openplan3d-zanini-tools.js'],
    expect: {
      run_browser_openplan3d: 'true',
      run_mysql_node: 'true',
    },
  },
  {
    name: 'quotation redraft fixture',
    paths: ['tests/js/fixtures/quotation-redraft-hotkey-lock.html'],
    expect: {
      run_browser_quotation: 'true',
      browserIncludes: ['tests/js/quotation-redraft-hotkey-lock-browser.test.js'],
    },
  },
  {
    name: 'client projects underscore views',
    paths: ['resources/views/admin/client_projects/show.blade.php'],
    expect: {
      run_browser_client: 'true',
      browserIncludes: [
        'tests/js/client-projects-table-screenshots.test.js',
        'tests/js/client-projects-overview-screenshots.test.js',
      ],
    },
  },
  {
    name: 'client projects js',
    paths: ['public/js/admin-client-projects.js'],
    expect: {
      run_browser_client: 'true',
    },
  },
  {
    name: 'quotation model',
    paths: ['app/Models/ClientProjectQuotation.php'],
    expect: {
      run_browser_quotation: 'true',
      run_browser_admin: 'false',
      filter_quotation: 'true',
      run_mysql_node: 'true',
    },
  },
  {
    name: 'quotation pdf support',
    paths: ['app/Support/Quotations/PdfFoldV2.php'],
    expect: {
      run_browser_quotation: 'true',
      filter_quotation: 'true',
    },
  },
  {
    name: 'paving quotation create help',
    paths: ['resources/views/admin/quotations/paving/create.blade.php'],
    expect: {
      run_browser_quotation: 'true',
      filter_quotation: 'true',
      run_mysql_node: 'true',
    },
  },
  {
    name: 'paving marble section view',
    paths: ['resources/views/admin/quotations/paving/_marble_section.blade.php'],
    expect: {
      run_browser_quotation: 'true',
      filter_quotation: 'true',
      run_mysql_node: 'true',
    },
  },
  {
    name: 'info tip js help wrapper',
    paths: ['public/js/admin-info-tip.js'],
    expect: {
      run_browser_quotation: 'true',
      filter_quotation: 'true',
      run_mysql_node: 'true',
    },
  },
  {
    name: 'info tip blade help wrapper',
    paths: ['resources/views/components/admin/info-tip.blade.php'],
    expect: {
      run_browser_quotation: 'true',
      filter_quotation: 'true',
      run_mysql_node: 'true',
    },
  },
  {
    name: 'admin css hanging-indent help wrapper',
    paths: ['public/css/admin.css'],
    expect: {
      run_browser_quotation: 'true',
      filter_quotation: 'true',
      run_mysql_node: 'true',
    },
  },
  {
    name: 'quotation editor footer partial',
    paths: ['resources/views/admin/partials/quotation_editor_footer.blade.php'],
    expect: {
      run_browser_quotation: 'true',
      filter_quotation: 'true',
      run_mysql_node: 'true',
    },
  },
  {
    name: 'quotation additional cost amount lines',
    paths: ['resources/views/admin/quotations/_amount_lines.blade.php'],
    expect: {
      run_browser_quotation: 'true',
      filter_quotation: 'true',
      run_mysql_node: 'true',
    },
  },
  {
    name: 'quotation editor header partial',
    paths: ['resources/views/admin/partials/quotation_editor_header.blade.php'],
    expect: {
      run_browser_quotation: 'true',
      filter_quotation: 'true',
      run_mysql_node: 'true',
    },
  },
  {
    name: 'basement quotation wizard js',
    paths: ['public/js/admin-basement-quotation.js'],
    expect: {
      run_browser_quotation: 'true',
      filter_quotation: 'true',
      run_mysql_node: 'true',
    },
  },
  {
    name: 'admin dialog shared with quotation fixtures',
    paths: ['public/js/admin-dialog.js'],
    expect: {
      run_browser_quotation: 'true',
      run_browser_admin: 'true',
      browserIncludes: ['tests/js/quotation-redraft-hotkey-lock-browser.test.js'],
    },
  },
  {
    name: 'admin dark css shared with client preview',
    paths: ['public/css/admin-dark.css'],
    expect: {
      run_browser_client: 'true',
      run_browser_admin: 'true',
    },
  },
  {
    name: 'room area finish math PHP glob',
    paths: ['app/Services/RoomAreaFinishMath.php'],
    expect: {
      filter_room_area: 'true',
    },
  },
  {
    name: 'admin dashboard blade fail-open browser',
    paths: ['resources/views/admin/dashboard/index.blade.php'],
    expect: {
      run_browser: 'true',
      run_browser_openplan3d: 'true',
      global_test_deps: 'true',
    },
  },
  {
    name: 'public site blade seo only no browser',
    paths: ['resources/views/site/partials/public-header.blade.php'],
    expect: {
      filter_seo: 'true',
      run_browser: 'false',
      global_test_deps: 'false',
    },
  },
  {
    name: 'rename admin-dialog to admin-modal keeps quotation browser',
    paths: ['public/js/admin-modal.js', 'public/js/admin-dialog.js'],
    extraEnv: {
      FILTER_BROWSER_ADMIN: 'true',
      FILTER_BROWSER_QUOTATION: 'false',
    },
    expect: {
      run_browser_quotation: 'true',
      run_browser_admin: 'true',
      global_test_deps: 'false',
      browserIncludes: ['tests/js/quotation-redraft-hotkey-lock-browser.test.js'],
    },
  },
  {
    name: 'negative unknown Dockerfile',
    paths: ['Dockerfile.ci'],
    expect: {
      global_test_deps: 'true',
      run_browser: 'true',
    },
  },
  {
    name: 'negative random app model without filter',
    paths: ['app/Models/Supplier.php'],
    expect: {
      global_test_deps: 'true',
    },
  },
];

let failed = 0;
for (const tc of cases) {
  try {
    const { flags } = runCompute(tc.paths, tc.extraEnv || {});
    for (const [key, val] of Object.entries(tc.expect)) {
      if (key === 'browserIncludes' || key === 'browserExcludes') {
        continue;
      }
      assert(flags[key] === val, `${tc.name}: expected ${key}=${val}, got ${flags[key]}`);
    }
    if (tc.expect.browserIncludes || tc.expect.browserExcludes) {
      const browser = listBrowserSuites(flags);
      for (const inc of tc.expect.browserIncludes || []) {
        assert(browser.includes(inc), `${tc.name}: browser suite missing ${inc}`);
      }
      for (const exc of tc.expect.browserExcludes || []) {
        assert(!browser.includes(exc), `${tc.name}: browser suite should not include ${exc}`);
      }
    }
    for (const p of tc.paths) {
      if (tc.expect.global_test_deps === 'true') {
        continue;
      }
      const hits = matchFiltersForPath(p, loadFilters());
      const any = Object.entries(hits).some(([k, v]) => v && !k.startsWith('_'));
      assert(any, `${tc.name}: path ${p} must match at least one workflow filter (simulated dorny)`);
    }
    console.log('ok', tc.name);
  } catch (err) {
    failed += 1;
    console.error('FAIL', tc.name, err.message);
  }
}

if (failed) {
  process.exit(1);
}
console.log(`matrix-sim: ${cases.length} cases ok`);
