/**
 * Pure layout helpers for document zoom viewport pan.
 * Oversized + margin:auto clamps scroll on some engines; zero margin when overflowing.
 */

export function docZoomOverflows(imageWidth, imageHeight, viewportWidth, viewportHeight) {
    return imageWidth > viewportWidth + 1 || imageHeight > viewportHeight + 1;
}

export function docZoomImageMargin(overflows) {
    return overflows ? '0' : '0 auto';
}

export function docZoomCenterScroll(scrollSize, clientSize) {
    return Math.max(0, (scrollSize - clientSize) / 2);
}

/**
 * Keep one axis of an image point under the pointer after a size change.
 * rel = fraction along the content before zoom; result is clamped to the scroll range.
 */
export function docZoomAnchorScroll(rel, contentSize, cursor, clientSize) {
    const maxScroll = Math.max(0, contentSize - clientSize);
    return Math.max(0, Math.min(maxScroll, rel * contentSize - cursor));
}

export function docZoomCanPan(scale, scrollWidth, clientWidth, scrollHeight, clientHeight) {
    return scale > 1
        || scrollWidth > clientWidth + 1
        || scrollHeight > clientHeight + 1;
}
