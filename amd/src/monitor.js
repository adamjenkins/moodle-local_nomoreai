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
 * Nothing typed, no key timings and no pointer paths leave the browser. Clicks made from the keyboard
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
    };
    const matched = new Set();
    let lastKey = 0;
    let lastMove = 0;
    let dirty = true;

    const keyed = () => {
        lastKey = Date.now();
    };
    document.addEventListener('keydown', keyed, true);
    document.addEventListener('compositionstart', keyed, true);
    document.addEventListener('compositionupdate', keyed, true);

    const moved = () => {
        lastMove = Date.now();
    };
    document.addEventListener('pointermove', moved, {capture: true, passive: true});
    document.addEventListener('pointerdown', moved, {capture: true, passive: true});

    document.addEventListener('input', (e) => {
        if (isTextField(e.target) && e.inputType !== 'insertFromPaste' && e.inputType !== 'insertFromDrop' &&
                Date.now() - lastKey > RECENT) {
            summary.textwithoutkeys++;
            dirty = true;
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
