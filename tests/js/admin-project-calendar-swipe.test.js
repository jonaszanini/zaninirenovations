'use strict';

const assert = require('assert');
const swipe = require('../../public/js/admin-project-calendar-swipe.js');

assert.strictEqual(swipe.HORIZONTAL_DOMINANCE, 2, 'requires 2:1 horizontal-to-vertical ratio');

assert.strictEqual(
  swipe.qualifiesHorizontalSwipe(60, 5, 150),
  true,
  'fast horizontal swipe qualifies (50px + velocity)'
);
assert.strictEqual(
  swipe.qualifiesHorizontalSwipe(80, 10, 800),
  true,
  'long deliberate swipe qualifies on distance alone'
);
assert.strictEqual(
  swipe.qualifiesHorizontalSwipe(60, 80, 150),
  false,
  'vertical-dominant gesture is rejected'
);
assert.strictEqual(
  swipe.qualifiesHorizontalSwipe(60, 35, 150),
  false,
  'fails 2:1 ratio when vertical is too large'
);
assert.strictEqual(
  swipe.qualifiesHorizontalSwipe(40, 0, 100),
  false,
  'short slow swipe is rejected'
);
assert.strictEqual(
  swipe.qualifiesHorizontalSwipe(55, 5, 800),
  false,
  'between 50–80px without enough velocity is rejected'
);

assert.strictEqual(swipe.swipeDirection(-55), 'next', 'left swipe goes next');
assert.strictEqual(swipe.swipeDirection(55), 'prev', 'right swipe goes prev');
assert.strictEqual(swipe.swipeDirection(10), null, 'small delta has no direction');

var active = { id: 1, tracking: true };
assert.strictEqual(
  swipe.shouldCancelSwipeTrackingOnPointerDown(active, { pointerId: 2, isPrimary: true }),
  true,
  'second pointer cancels tracking'
);
assert.strictEqual(
  swipe.shouldCancelSwipeTrackingOnPointerDown(active, { pointerId: 1, isPrimary: false }),
  true,
  'non-primary on same pointer cancels tracking'
);
assert.strictEqual(
  swipe.shouldCancelSwipeTrackingOnPointerDown(active, { pointerId: 1, isPrimary: true }),
  false,
  'primary continuation keeps tracking'
);

console.log('admin-project-calendar-swipe.test.js: ok');
