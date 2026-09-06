import hljs from "highlight.js/lib/core";

// Common languages only - keeps the chunk under ~50kb gzip. Adding a
// language costs roughly 1-4kb gzip. The `xml` package covers HTML.
import javascript from "highlight.js/lib/languages/javascript";
import typescript from "highlight.js/lib/languages/typescript";
import php from "highlight.js/lib/languages/php";
import css from "highlight.js/lib/languages/css";
import xml from "highlight.js/lib/languages/xml";
import json from "highlight.js/lib/languages/json";
import bash from "highlight.js/lib/languages/bash";
import sql from "highlight.js/lib/languages/sql";
import python from "highlight.js/lib/languages/python";
import markdown from "highlight.js/lib/languages/markdown";
import yaml from "highlight.js/lib/languages/yaml";

/**
 * The one configured highlighter, shared by the Markdown preview in the back
 * office and by code zones on the public site.
 *
 * It lived inside the Notes module while it had one consumer. A second one
 * arrived with the grid's code zone, and two copies of this list would have
 * meant a language added for a reader and missing for a writer - so the list
 * moved here and both import it.
 *
 * Aliases are registered rather than resolved at the call site: an author
 * writes `js` or `sh` as readily as `javascript` or `bash`, and a language
 * highlight.js does not know falls back to plain escaped text.
 */
hljs.registerLanguage("javascript", javascript);
hljs.registerLanguage("js", javascript);
hljs.registerLanguage("typescript", typescript);
hljs.registerLanguage("ts", typescript);
hljs.registerLanguage("php", php);
hljs.registerLanguage("css", css);
hljs.registerLanguage("html", xml);
hljs.registerLanguage("xml", xml);
hljs.registerLanguage("json", json);
hljs.registerLanguage("bash", bash);
hljs.registerLanguage("sh", bash);
hljs.registerLanguage("sql", sql);
hljs.registerLanguage("python", python);
hljs.registerLanguage("py", python);
hljs.registerLanguage("markdown", markdown);
hljs.registerLanguage("md", markdown);
hljs.registerLanguage("yaml", yaml);
hljs.registerLanguage("yml", yaml);

/** The languages an author may name, for the editor's dropdown. */
export const HIGHLIGHT_LANGUAGES = [
    "bash",
    "css",
    "html",
    "javascript",
    "json",
    "markdown",
    "php",
    "python",
    "sql",
    "typescript",
    "yaml",
];

export default hljs;
