'use strict';

const assert = require('assert');
const lib = require('../../public/js/shared-prices-effective.js');

const baseboardBlank = {
  sell: '',
  rate: 10,
  owner: 253,
  blankSell: 4.75,
  kind: 'flat',
  price: 0,
  matUnit: 'lf',
};

const computed = lib.effectiveUnitPrice(baseboardBlank);
assert.strictEqual(computed, 4.75, 'blank built-in Sell keeps 4.75 when Cost is 10');
assert.notStrictEqual(computed, 25.3, 'must not use owner ÷ Cost (253/10 = 25.30)');
assert.strictEqual(
  lib.formatEffectiveHint(computed, 'lf'),
  'CA$4.75\u200B/lf',
  'hint text is CA$4.75/lf'
);
assert.notStrictEqual(
  lib.formatEffectiveHint(253 / 10, 'lf'),
  lib.formatEffectiveHint(computed, 'lf')
);

assert.strictEqual(
  lib.effectiveUnitPrice(Object.assign({}, baseboardBlank, { sell: 6.35 })),
  6.35,
  'typed Sell wins'
);

assert.strictEqual(
  lib.effectiveUnitPrice({
    sell: '',
    rate: 10,
    owner: 85,
    blankSell: 0,
    kind: 'per_hour',
  }),
  8.5,
  'custom rows without a built-in default still use Cost'
);

console.log('shared-prices-effective.test.js ok');
