import { ref } from "vue";

/**
 * Manages a map of expanded/collapsed states persisted in localStorage.
 * Default state is expanded (true) - only explicit collapses are stored.
 *
 * **Use `set` whenever you know the state you want**, and `toggle` only for a
 * control that flips whatever the store currently says. The two differ for any
 * caller that draws a different default from the store's own: the documentation
 * panel shows every rubric folded but the one being read, so `toggle` on a
 * folded rubric wrote `false` - the state already on screen - and the first
 * click did nothing at all. The second one opened it.
 */
export function usePersistedExpanded(storageKey) {
    const expanded = ref(load());

    function load() {
        try {
            const raw = localStorage.getItem(storageKey);
            return raw ? JSON.parse(raw) : {};
        } catch {
            return {};
        }
    }

    function isExpanded(id) {
        return expanded.value[id] !== false;
    }

    function set(id, value) {
        expanded.value = { ...expanded.value, [id]: value };
        localStorage.setItem(storageKey, JSON.stringify(expanded.value));
    }

    function toggle(id) {
        set(id, !isExpanded(id));
    }

    function getRaw(id) {
        return expanded.value[id];
    }

    return { isExpanded, set, toggle, getRaw };
}
