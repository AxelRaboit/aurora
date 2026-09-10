/**
 * The named date formats the components ask `d()` for.
 *
 * vue-i18n resolves a named format against this table and returns an *empty
 * string* when the name is absent - no exception, no fallback to the browser's
 * default. The table did not exist, so every `d(value, "short")` in the
 * product rendered nothing: the list of form submissions read
 * "SUB-000001 · · fr", and a comment awaiting moderation had no date at all.
 *
 * Two names, because there are two questions. "short" answers "when exactly",
 * clock included; "long" answers "which day", and is what an all-day entry
 * gets - a whole-day event with 00:00 next to it says something false.
 *
 * Same shapes in the three locales: Intl already writes each of them the way
 * that language does, and repeating the options per locale would only create
 * a way for them to drift.
 */
const short = {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
};

const long = {
    year: "numeric",
    month: "long",
    day: "numeric",
};

export const datetimeFormats = {
    fr: { short, long },
    en: { short, long },
    es: { short, long },
};
