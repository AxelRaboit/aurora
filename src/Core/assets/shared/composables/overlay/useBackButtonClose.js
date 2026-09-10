import { onBeforeUnmount, onMounted, ref, unref, watch } from "vue";

/**
 * Wires the browser Back button to close an overlay/modal.
 *
 * **One history entry for "an overlay is open", not one per overlay.**
 *
 * The first version pushed an entry per instance and called `history.back()`
 * per instance on close. `history.back()` is asynchronous: the entry is not
 * gone when the call returns, it is gone when `popstate` fires. So the very
 * common chain - a row-actions menu closes while the confirmation it opened
 * appears, both of them modals - interleaved a push and a pending pop, the
 * stack unwound one step too far, and the browser left the page. Opening a
 * row menu, clicking an action and pressing *Cancel* threw the user out of
 * the list and onto the module dashboard, in every list of the product.
 *
 * With a single shared entry there is nothing to interleave: the entry exists
 * while at least one overlay is open, and it is popped when the last one
 * closes. Back closes the topmost overlay and re-pushes the entry if others
 * remain, so pressing Back repeatedly unstacks them one by one before it
 * leaves the page.
 *
 * @param {object} options
 * @param {import('vue').Ref<boolean> | (() => boolean)} options.isOpen
 * @param {() => void} options.onClose
 */

/** Open overlays, outermost first. The last one is what Back closes. */
const open = [];

/** Whether the shared history entry currently exists. */
let entryPushed = false;

/** A `history.back()` we asked for, whose popstate is not a user gesture. */
let selfPop = false;

/**
 * A drop waiting to see whether another overlay opens in the same turn.
 *
 * A row-actions menu closing while it opens a confirmation passes through
 * "no overlay is open" for the length of one Vue flush. Acting on that
 * instant would drop the entry and push it straight back - the interleaving
 * this file exists to avoid, since the pop is asynchronous and lands after
 * the new push. Waiting one microtask makes the swap a no-op.
 */
let dropQueued = false;

function pushEntry() {
    // A drop that has not run yet: the entry is still there, so keeping it is
    // the whole point.
    dropQueued = false;

    if (entryPushed) return;

    history.pushState({ __overlayBack: true }, "");
    entryPushed = true;
}

function dropEntry() {
    if (!entryPushed) return;

    entryPushed = false;
    selfPop = true;
    history.back();
}

function dropEntrySoon() {
    if (!entryPushed || dropQueued) return;

    dropQueued = true;
    queueMicrotask(() => {
        if (!dropQueued) return;

        dropQueued = false;

        if (0 === open.length) dropEntry();
    });
}

function onPopState() {
    // Our own `history.back()` coming home. The entry is already accounted
    // for; consuming the event here is what keeps it from reading as a user
    // pressing Back.
    if (selfPop) {
        selfPop = false;

        return;
    }

    if (0 === open.length) return;

    // The user pressed Back: the entry is gone, and the topmost overlay is
    // what it closes.
    entryPushed = false;
    const topmost = open[open.length - 1];

    // Re-pushed *before* closing, so an overlay left underneath still has an
    // entry to consume on the next Back.
    if (open.length > 1) pushEntry();

    topmost.close();
}

let listening = false;

export function useBackButtonClose({ isOpen, onClose }) {
    const registered = ref(false);
    const read = () =>
        typeof isOpen === "function" ? isOpen() : unref(isOpen);
    const entry = { close: () => onClose() };

    function enter() {
        if (registered.value) return;

        registered.value = true;
        open.push(entry);
        pushEntry();
    }

    function leave() {
        if (!registered.value) return;

        registered.value = false;
        const at = open.indexOf(entry);
        if (at !== -1) open.splice(at, 1);

        if (0 === open.length) dropEntrySoon();
    }

    watch(read, (isNowOpen) => {
        if (isNowOpen) {
            enter();
        } else {
            leave();
        }
    });

    onMounted(() => {
        if (listening) return;

        listening = true;
        window.addEventListener("popstate", onPopState);
    });

    // The listener is module-wide and outlives any single overlay, so it is
    // not removed here: a modal unmounting must not stop Back from closing
    // the one still on screen.
    onBeforeUnmount(leave);

    function requestClose() {
        // Let the parent flip `show` to false; the watch above does the
        // history bookkeeping. One code path for every close trigger - X,
        // Escape, backdrop, parent state change.
        onClose();
    }

    return { requestClose };
}

/** Test seam: the module-level stack has to start empty in each test. */
export function __resetBackButtonClose() {
    open.length = 0;
    entryPushed = false;
    selfPop = false;
    dropQueued = false;
    listening = false;
}
