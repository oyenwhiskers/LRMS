import assert from 'node:assert/strict';
import {
    docZoomAnchorScroll,
    docZoomCanPan,
    docZoomCenterScroll,
    docZoomImageMargin,
    docZoomOverflows,
} from './doc-zoom-layout.js';

assert.equal(docZoomOverflows(800, 600, 400, 300), true);
assert.equal(docZoomOverflows(200, 100, 400, 300), false);
assert.equal(docZoomImageMargin(true), '0');
assert.equal(docZoomImageMargin(false), '0 auto');
assert.equal(docZoomCenterScroll(800, 400), 200);
assert.equal(docZoomCenterScroll(300, 400), 0);
assert.equal(docZoomAnchorScroll(0.5, 800, 200, 400), 200);
assert.equal(docZoomAnchorScroll(0, 800, 100, 400), 0);
assert.equal(docZoomAnchorScroll(1, 800, 100, 400), 400);
assert.equal(docZoomCanPan(1, 400, 400, 300, 300), false);
assert.equal(docZoomCanPan(2, 400, 400, 300, 300), true);
assert.equal(docZoomCanPan(1, 800, 400, 300, 300), true);

console.log('doc-zoom-layout.check: ok');
