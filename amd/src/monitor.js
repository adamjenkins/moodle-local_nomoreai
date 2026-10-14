// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Browser monitor: sends a page-view summary of counts and booleans only.
 *
 * Nothing typed, no individual key timings and no pointer paths leave the browser: only counts, and for typing
 * rhythm a median gap and how much the gaps vary. Clicks made from the keyboard
 * (Enter or Space on a control, which is how keyboard and screen-reader users click) have event.detail 0
 * and are not counted, so they cannot look like "clicks without pointer movement".
 *
 * @module     local_nomoreai/monitor
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/** @var {number} How often a changed summary is sent, in ms. */
const SEND_INTERVAL = 30000;

/** @var {number} How recent a key press or pointer move must be to count as "just before", in ms. */
const RECENT = 1000;

/** @var {number} Distance from a control's centre, as a share of its half-size, that counts as "dead centre". */
const CENTRE = 0.05;

/** @var {number} Pixels of slack around the centre, for browsers' whole-pixel click coordinates. */
const PIXEL_SLACK = 1.5;

/** @var {number} Gaps between key presses longer than this (ms) are pauses, not typing rhythm. */
const MAX_KEY_GAP = 2000;

/** @var {number} Most recent key gaps kept to compute the typing rhythm. */
const MAX_KEY_GAPS = 500;

/** @var {string} Controls whose centre a click is measured against. */
const CONTROLS = 'button, a, input, select, textarea, label, summary, [role="button"], [role="link"], [role="checkbox"], ' +
    '[role="radio"], [role="option"], [role="menuitem"], [role="tab"]';

/**
 * Median and coefficient of variation (standard deviation / mean, in thousandths) of a list of gaps.
 *
 * @param {number[]} gaps
 * @returns {{median: number, cv: number}}
 */
const rhythm = (gaps) => {
    if (!gaps.length) {
        return {median: 0, cv: 0};
    }
    const sorted = gaps.slice().sort((a, b) => a - b);
    const mid = Math.floor(sorted.length / 2);
    const median = sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
    const mean = gaps.reduce((a, b) => a + b, 0) / gaps.length;
    const variance = gaps.reduce((a, b) => a + (b - mean) * (b - mean), 0) / gaps.length;
    return {median: Math.round(median), cv: mean > 0 ? Math.round(1000 * Math.sqrt(variance) / mean) : 0};
};

/**
 * Make a random page-view id.
 *
 * @returns {string}
 */
const pageviewId = () => {
    const bytes = new Uint8Array(12);
    window.crypto.getRandomValues(bytes);
    return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
};

/**
 * Whether an element is a text field.
 *
 * @param {EventTarget} target
 * @returns {boolean}
 */
const isTextField = (target) => {
    if (!(target instanceof Element)) {
        return false;
    }
    if (target.isContentEditable || target.tagName === 'TEXTAREA') {
        return true;
    }
    return target.tagName === 'INPUT' &&
        ['text', 'search', 'email', 'url', 'number', 'tel', ''].includes((target.getAttribute('type') || '').toLowerCase());
};

/**
 * Start monitoring the page.
 *
 * @param {number} cmid course module id of the page
 * @param {Array<{id: string, selector: string}>} artifacts agent DOM signatures
 */
export const init = (cmid, artifacts) => {
    const pageview = pageviewId();
    const summary = {
        webdriver: navigator.webdriver === true,
        textwithoutkeys: 0,
        clicks: 0,
        clickswithoutmove: 0,
        pastes: 0,
        pastedchars: 0,
        centreclicks: 0,
        measuredclicks: 0,
        keyintervals: 0,
        keymedian: 0,
        keycv: 0,
        firstinputms: -1,
        pointermoves: 0,
        scrolls: 0,
    };
    const started = performance.now();
    const keygaps = [];
    let lastTypedKey = 0;
    const matched = new Set();
    let lastKey = 0;
    let lastMove = 0;
    let dirty = true;

    const keyed = () => {
        lastKey = Date.now();
    };
    const firstInput = () => {
        if (summary.firstinputms < 0) {
            summary.firstinputms = Math.round(performance.now() - started);
            dirty = true;
        }
    };
    document.addEventListener('keydown', (e) => {
        keyed();
        if (e.key && e.key.length === 1 && !e.repeat && !e.ctrlKey && !e.metaKey && !e.altKey) {
            const now = performance.now();
            if (lastTypedKey && now - lastTypedKey <= MAX_KEY_GAP) {
                keygaps.push(now - lastTypedKey);
                if (keygaps.length > MAX_KEY_GAPS) {
                    keygaps.shift();
                }
                summary.keyintervals++;
                dirty = true;
            }
            lastTypedKey = now;
        }
    }, true);
    document.addEventListener('compositionstart', keyed, true);
    document.addEventListener('compositionupdate', keyed, true);

    const moved = () => {
        lastMove = Date.now();
    };
    document.addEventListener('pointermove', () => {
        moved();
        summary.pointermoves++;
    }, {capture: true, passive: true});
    document.addEventListener('pointerdown', moved, {capture: true, passive: true});
    const scrolled = () => {
        summary.scrolls++;
    };
    document.addEventListener('scroll', scrolled, {capture: true, passive: true});
    document.addEventListener('wheel', scrolled, {capture: true, passive: true});

    document.addEventListener('input', (e) => {
        if (isTextField(e.target) && e.inputType !== 'insertFromPaste' && e.inputType !== 'insertFromDrop' &&
                Date.now() - lastKey > RECENT) {
            summary.textwithoutkeys++;
            dirty = true;
        }
        if (isTextField(e.target)) {
            firstInput();
        }
    }, true);

    document.addEventListener('click', (e) => {
        if (e.detail === 0) {
            // Keyboard-activated click.
            return;
        }
        summary.clicks++;
        if (Date.now() - lastMove > RECENT) {
            summary.clickswithoutmove++;
        }
        firstInput();
        // How close to the centre of the control the click landed. Tiny controls are skipped: any click on them
        // is near the centre.
        const control = e.target instanceof Element ? (e.target.closest(CONTROLS) || e.target) : null;
        if (control) {
            const box = control.getBoundingClientRect();
            if (box.width >= 16 && box.height >= 12) {
                summary.measuredclicks++;
                // Browsers report whole-pixel coordinates, so allow the rounding: within the larger of CENTRE of
                // the half-size and PIXEL_SLACK pixels, on each axis.
                const rx = Math.max(PIXEL_SLACK, CENTRE * box.width / 2);
                const ry = Math.max(PIXEL_SLACK, CENTRE * box.height / 2);
                const dx = (e.clientX - (box.left + box.width / 2)) / rx;
                const dy = (e.clientY - (box.top + box.height / 2)) / ry;
                if (dx * dx + dy * dy <= 1) {
                    summary.centreclicks++;
                }
            }
        }
        dirty = true;
    }, true);

    document.addEventListener('paste', (e) => {
        summary.pastes++;
        summary.pastedchars += ((e.clipboardData && e.clipboardData.getData('text')) || '').length;
        dirty = true;
    }, true);

    const checkArtifacts = () => {
        artifacts.forEach(({id, selector}) => {
            if (matched.has(id)) {
                return;
            }
            try {
                if (document.querySelector(selector)) {
                    matched.add(id);
                    dirty = true;
                }
            } catch (err) {
                // An invalid selector in the settings is ignored.
            }
        });
    };

    const send = () => {
        checkArtifacts();
        if (!dirty) {
            return;
        }
        dirty = false;
        const typing = rhythm(keygaps);
        summary.keymedian = typing.median;
        summary.keycv = typing.cv;
        Ajax.call([{
            methodname: 'local_nomoreai_record_pageview',
            args: Object.assign({cmid: cmid, pageview: pageview, artifacts: Array.from(matched)}, summary),
        }])[0].catch(() => {
            // Monitoring must never disturb the page.
            dirty = true;
        });
    };

    setTimeout(send, 5000);
    setInterval(send, SEND_INTERVAL);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            send();
        }
    });
};
