'use strict';

const assert = require('assert');
const swipe = require('../../public/js/admin-project-calendar-swipe.js');

assert.strictEqual(
  swipe.qualifiesHorizontalSwipe(60, 5, 150),
  true,
  'fast horizontal swipe qualifies'
);
assert.strictEqual(
  swipe.qualifiesHorizontalSwipe(60, 80, 150),
  false,
  'vertical-dominant gesture is rejected'
);
assert.strictEqual(
  swipe.qualifiesHorizontalSwipe(40, 0, 100),
  false,
  'short horizontal distance is rejected'
);
assert.strictEqual(
  swipe.qualifiesHorizontalSwipe(80, 10, 800),
  false,
  'slow swipe is rejected'
);

assert.strictEqual(swipe.swipeDirection(-55), 'next', 'left swipe goes next');
assert.strictEqual(swipe.swipeDirection(55), 'prev', 'right swipe goes prev');
assert.strictEqual(swipe.swipeDirection(10), null, 'small delta has no direction');

console.log('admin-project-calendar-swipe.test.js: ok');
