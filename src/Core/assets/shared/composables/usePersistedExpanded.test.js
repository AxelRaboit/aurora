/**
 * @vitest-environment happy-dom
 */
import { describe, it, expect, beforeEach } from "vitest";
import { usePersistedExpanded } from "./usePersistedExpanded.js";

const KEY = "test-expanded";

describe("usePersistedExpanded", () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it("holds nothing until something is said, and defaults to expanded", () => {
        const { isExpanded, getRaw } = usePersistedExpanded(KEY);

        expect(getRaw("a")).toBeUndefined();
        expect(isExpanded("a")).toBe(true);
    });

    it("writes the collapse down, and nothing else", () => {
        const { toggle } = usePersistedExpanded(KEY);

        toggle("a");

        expect(JSON.parse(localStorage.getItem(KEY))).toEqual({ a: false });
    });

    /**
     * The bug this method was added for. A caller whose own default is
     * "collapsed" - the documentation panel, which folds every rubric but the
     * one being read - cannot use `toggle`: the store believes an unset id is
     * expanded, so the flip writes `false`, the state already on screen, and
     * the click does nothing at all. The second one opens it.
     */
    it("sets the state asked for rather than flipping the store's belief", () => {
        const { isExpanded, set } = usePersistedExpanded(KEY);

        set("a", true);

        expect(isExpanded("a")).toBe(true);
        expect(JSON.parse(localStorage.getItem(KEY))).toEqual({ a: true });
    });

    it("reads back what a previous instance stored", () => {
        usePersistedExpanded(KEY).toggle("a");

        expect(usePersistedExpanded(KEY).isExpanded("a")).toBe(false);
    });
});
