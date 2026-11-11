'use strict';

const assert = require('assert');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const BRIDGE = path.join(ROOT, 'public/js/openplan3d-editor-bridge.js');
const LOCALE_SHIM = path.join(ROOT, 'public/js/openplan3d-hosted-locale.js');
const INDEX = path.join(ROOT, 'public/vendor/openplan3d-h1/editor/index.html');
const HTACCESS = path.join(ROOT, 'public/.htaccess');
const EMBED = path.join(ROOT, 'tests/js/fixtures/openplan3d-editor-embed.html');

function bridgeContentVersion() {
  const data = fs.readFileSync(BRIDGE, 'utf8').replace(/\r\n/g, '\n');
  return crypto.createHash('sha256').update(data).digest('hex').slice(0, 16);
}

function localeShimContentVersion() {
  const data = fs.readFileSync(LOCALE_SHIM, 'utf8').replace(/\r\n/g, '\n');
  return crypto.createHash('sha256').update(data).digest('hex').slice(0, 16);
}

function jsContentVersion(relPath) {
  const data = fs.readFileSync(path.join(ROOT, relPath), 'utf8').replace(/\r\n/g, '\n');
  return crypto.createHash('sha256').update(data).digest('hex').slice(0, 16);
}

function assertVersionedScriptSrc(html, basename, label) {
  const expected = jsContentVersion(`public/js/${basename}`);
  assert.doesNotMatch(
    html,
    new RegExp(`${basename.replace('.', '\\.')}(?!\\?v=)`),
    `${label} must not load ${basename} without ?v=`
  );
  assert.match(
    html,
    new RegExp(`${basename.replace('.', '\\.')}\\?v=${expected}`),
    `${label} ${basename} ?v= must match content hash (${expected})`
  );
}

function assertVersionedBridgeSrc(html, label) {
  assert.doesNotMatch(
    html,
    /openplan3d-editor-bridge\.js["'\s>]/,
    `${label} must not load the bridge without a ?v= cache-bust query`
  );
  const expected = bridgeContentVersion();
  const pattern = new RegExp(`openplan3d-editor-bridge\\.js\\?v=${expected}`);
  assert.match(html, pattern, `${label} bridge ?v= must match current bridge content hash (${expected})`);
  return expected;
}

const htaccess = fs.readFileSync(HTACCESS, 'utf8');
assert.doesNotMatch(htaccess, /LocationMatch/i, '.htaccess must not use LocationMatch (500 in .htaccess on Apache 2.4)');
assert.match(htaccess, /SetEnvIf Request_URI "\^\(\?:\/public\)\?\/vendor\/openplan3d\/_app\/immutable\//, '.htaccess must mark immutable chunks with optional /public/ prefix');
assert.match(
  htaccess,
  /SetEnvIf Request_URI "\^\(\?:\/public\)\?\/vendor\/openplan3d-h1\/_app\/immutable\//,
  '.htaccess must mark openplan3d-h1 immutable chunks with optional /public/ prefix'
);
assert.match(htaccess, /\(\?:v\|ver\)=/, '.htaccess must detect ?v= / ?ver= via RewriteCond');
assert.match(htaccess, /RewriteRule \^ - \[E=asset_versioned:1\]/, '.htaccess must set asset_versioned env from rewrite');
assert.match(htaccess, /REDIRECT_asset_versioned/, '.htaccess must preserve asset_versioned across internal redirects');
assert.match(htaccess, /max-age=31536000, immutable.*asset_versioned/s, '.htaccess must long-cache versioned JS/CSS');
assert.match(htaccess, /must-revalidate.*!asset_versioned/s, '.htaccess must not immutable-cache unversioned JS/CSS');

const bridgeSrc = fs.readFileSync(BRIDGE, 'utf8');
assert.match(
  bridgeSrc,
  /contains\('zanini-hosted-openplan3d'\)/,
  'bridge must include #108 hosted-class guard (stale cached pre-#108 bridge caused mutation loop hang)'
);
const applyEditorThemeBlock = bridgeSrc.match(/function applyEditorTheme\(theme\) \{([\s\S]*?)\n  \}/);
assert.ok(applyEditorThemeBlock, 'applyEditorTheme must exist');
assert.match(
  applyEditorThemeBlock[1],
  /localStorage\.setItem\('o3d_theme'/,
  'applyEditorTheme (SET_THEME) must persist o3d_theme for admin theme toggles'
);
const syncBlock = bridgeSrc.match(/function syncHostedThemeFromParent\(\) \{([\s\S]*?)\n  \}/);
assert.ok(syncBlock, 'syncHostedThemeFromParent must exist');
assert.match(
  syncBlock[1],
  /existing !== 'dark' && existing !== 'light'/,
  'syncHostedThemeFromParent must only seed o3d_theme when unset'
);

assertVersionedBridgeSrc(fs.readFileSync(INDEX, 'utf8'), 'editor index.html');
assertVersionedBridgeSrc(fs.readFileSync(EMBED, 'utf8'), 'editor embed fixture');

const indexHtml = fs.readFileSync(INDEX, 'utf8');
assertVersionedScriptSrc(indexHtml, 'room-area-interior-geometry.js', 'editor index.html');
assertVersionedScriptSrc(indexHtml, 'openplan3d-measurement-bridge.js', 'editor index.html');
assertVersionedScriptSrc(indexHtml, 'openplan3d-zanini-extensions.js', 'editor index.html');
assertVersionedScriptSrc(indexHtml, 'openplan3d-zanini-tools.js', 'editor index.html');
const localeShimV = localeShimContentVersion();
assert.match(
  indexHtml,
  new RegExp(`openplan3d-hosted-locale\\.js\\?v=${localeShimV}`),
  `editor index locale shim ?v= must match (${localeShimV})`
);
const localeImportKey = indexHtml.match(
  /"(\/vendor\/openplan3d-h1\/_app\/immutable\/chunks\/[A-Za-z0-9_-]+\.js)": "\/js\/openplan3d-hosted-locale\.js\?v=/
)?.[1];
assert.ok(localeImportKey, 'import map must redirect an immutable locale chunk to hosted locale shim');
assert.doesNotMatch(
  indexHtml,
  new RegExp(`<link href="${localeImportKey.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}" rel="modulepreload">`),
  'redundant locale chunk modulepreload must be omitted (import map handles locale)'
);

assert.match(
  htaccess,
  /openplan3d-h1\/editor\/index\\.html/,
  '.htaccess must tag hosted editor index.html for no-cache'
);
assert.match(
  htaccess,
  /no-cache, must-revalidate.*openplan3d_editor_html/s,
  '.htaccess must send no-cache for editor index.html'
);

const manifest = JSON.parse(
  fs.readFileSync(path.join(ROOT, 'scripts/openplan3d-h1.json'), 'utf8')
);
const storeChunk = fs.readFileSync(
  path.join(ROOT, 'public/vendor/openplan3d-h1/_app/immutable/chunks', manifest.storeChunk),
  'utf8'
);
assert.doesNotMatch(
  storeChunk,
  /syncEditorRoomBoundariesForCanvasFill/,
  'store chunk must not mutate rooms inside un() on load'
);
assert.match(storeChunk, /canvasFillRoomEntries/, 'store chunk must use ephemeral canvas fill');
assert.match(storeChunk, /__zaniniRoomUse=\{/, 'store chunk va() must backfill persisted room.area');
assert.match(storeChunk, /return e\.polygon&&e\.polygon\.length>=3\?e\.polygon\.slice\(\):\[\]/, 'store chunk Bo() must fall back to room.polygon');

const legacyStorePath = path.join(
  ROOT,
  'public/vendor/openplan3d-h1/_app/immutable/chunks/1VjVcrjm-h3.js'
);
assert.ok(fs.existsSync(legacyStorePath), 'legacy 1VjVcrjm-h3.js must exist for stale immutable importers');
const legacyStore = fs.readFileSync(legacyStorePath, 'utf8');
assert.doesNotMatch(
  legacyStore,
  /canvasFillRoomEntries/,
  'legacy h3 store must not contain PR120 patched va() (main graph bytes)'
);
assert.notStrictEqual(legacyStore, storeChunk, 'legacy h3 and active h4 store must differ');

console.log('openplan3d-editor-asset-version: ok (bridge v=' + bridgeContentVersion() + ', locale shim v=' + localeShimV + ')');
