'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const save = require('../../public/js/admin-utilities-lumber-board.js');

const savedName = 'Scott deck — all lumber';

assert.deepStrictEqual(
  save.lumberBoardResolveSaveName(false, '', ''),
  { blocked: true, name: '' },
  'a brand new calculation still requires a name'
);
assert.deepStrictEqual(
  save.lumberBoardResolveSaveName(false, '   ', savedName),
  { blocked: true, name: '' },
  'whitespace is not a name for a new calculation'
);
assert.deepStrictEqual(
  save.lumberBoardResolveSaveName(false, '  Patio boards  ', ''),
  { blocked: false, name: 'Patio boards' }
);

assert.deepStrictEqual(
  save.lumberBoardResolveSaveName(true, '', savedName),
  { blocked: false, name: savedName },
  'update with an empty field keeps the saved name'
);
assert.deepStrictEqual(
  save.lumberBoardResolveSaveName(true, '   ', savedName),
  { blocked: false, name: savedName },
  'update with a blank field keeps the saved name'
);
assert.deepStrictEqual(
  save.lumberBoardResolveSaveName(true, 'Renamed deck', savedName),
  { blocked: false, name: 'Renamed deck' },
  'a typed name still replaces the saved name'
);
assert.deepStrictEqual(
  save.lumberBoardResolveSaveName(true, '', ''),
  { blocked: false, name: '' },
  'an update is not blocked when the field is empty; the server keeps the stored name'
);

const nameInput = { value: '' };
const projectSelect = { value: '' };
const applied = save.lumberBoardApplyLoadedIdentity(
  { nameInput: nameInput, projectSelect: projectSelect },
  { name: savedName, client_project_id: 42 }
);
assert.strictEqual(applied.name, savedName);
assert.strictEqual(applied.clientProjectId, '42');
assert.strictEqual(nameInput.value, savedName, 'loading a calculation prefills the name input');
assert.strictEqual(projectSelect.value, '42', 'loading a calculation prefills the linked project');

const cleared = save.lumberBoardApplyLoadedIdentity(
  { nameInput: nameInput, projectSelect: projectSelect },
  { name: null, client_project_id: null }
);
assert.strictEqual(cleared.name, '');
assert.strictEqual(cleared.clientProjectId, '');
assert.strictEqual(nameInput.value, '');
assert.strictEqual(projectSelect.value, '');

const src = fs.readFileSync(path.join(__dirname, '../../public/js/admin-utilities-lumber-board.js'), 'utf8');
const saveFn = src.slice(src.indexOf('function saveCalculation()'), src.indexOf('function showSaveFeedback('));
assert.ok(saveFn.includes('lumberBoardResolveSaveName('), 'save uses the name resolver');
assert.ok(!saveFn.includes("els.saveName.value = ''"), 'saving must not clear the name while the card switches to Update');
assert.ok(src.includes('lumberBoardApplyLoadedIdentity('), 'load applies the saved name and project');
