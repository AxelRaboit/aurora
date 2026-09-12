---
name: update-documentation
description: Bring /backend/documentation back in step with the code after a user-visible change. Use after shipping any feature, option, screen or renamed control, and whenever the user asks to "mettre à jour la doc", "documenter", or asks whether the documentation or its screenshots are still accurate. Covers the pages, the pages that became stale next door, and the screenshots.
scope: core-only
---

# update-documentation

The manual at `/backend/documentation` is part of the product, not a note
alongside it. A feature that ships without its page is a feature the person
using the software has no way to discover, and a page that describes a screen
that has changed is worse than no page: it is read, believed, and acted on.

This skill is the pass to run **before** a feature is called done.

## Where things live

| What | Where |
|---|---|
| Pages | `src/Module/Documentation/content/<NN-rubric>/<NNN>-<slug>.md` |
| Images | `src/Module/Documentation/images/<NN-rubric>/<name>.png` |
| Capture steps | `tools/doc-screenshots/capture-steps.mjs`, `capture-doc.mjs` |
| Filing | `tools/doc-screenshots/place.mjs` |

Numbers order the rubrics and the pages inside them. Leave gaps when
inserting, the way the existing files do, so a later page does not force a
renumbering of its neighbours.

## The three questions, in order

### 1. Does this change need a page of its own?

Yes when somebody using the software would have to be told. A new screen, a
new option in the settings, a new action in a menu, a new column that changes
how a list is read.

No for anything invisible from the interface: a refactor, a performance fix,
an internal rename. The manual is not a changelog, and there is one already.

### 2. Which existing pages did this change make wrong?

**This is the question that gets skipped, and it is the one that matters.**
A new feature rarely lands alone: it appears in a list somewhere, it adds a
tab to a screen a page enumerates, it changes what a control is called.

Grep the content folder for what the change touches, and read what comes back
rather than trusting the filename:

```bash
grep -rl "<the thing you touched>" src/Module/Documentation/content/
```

Look especially for pages that **count** things. A page saying "les treize
onglets" is wrong the moment a fourteenth exists, and nothing will fail to
tell you.

### 3. Do the screenshots still show what the page describes?

A screenshot of a screen that has gained a field, a column or a tab is a
picture of software the reader does not have. Re-capture every shot on a
screen the change touched, not only the one whose page you edited.

## Regenerating a screenshot

The captures run against a **local** server on **demo fixtures**. Never
against production: these pictures go on a page anyone can open.

```bash
make demo          # seeds the demo data the shots expect
make start-d       # the server the capture drives
node tools/doc-screenshots/capture-doc.mjs <name>   # or capture-steps.mjs
node tools/doc-screenshots/place.mjs                # files them where the pages ask
```

`place.mjs` reads the Markdown to know where each picture belongs, so the
image path written in the page is the only declaration. A capture nobody
cites is ignored, and a citation with no capture is reported.

Removing a picture that is no longer used is manual, and
`DocumentationImagesTest` will ask for it.

## Writing a page

Read two or three neighbouring pages first and match them. The house voice is
specific, addressed to the person using the software, and explains **why** a
thing is the way it is rather than only naming it. It does not describe the
interface widget by widget: a reader can see the screen.

```markdown
---
title: "Court, et le sujet réel de la page"
description: "Une phrase qui dit à qui elle sert et ce qu'elle règle."
rubric: "Configuration"
---
```

Rules worth restating because they are easy to miss:

- **French**, and no em dash anywhere.
- Say what a setting **costs** or **risks**, not only what it does. A page
  that only lists controls tells the reader nothing they could not see.
- Credentials, endpoints and anything naming real infrastructure stay out:
  these pages are public, and `aurora-core` is a public repository.

## Before calling it done

- `make ft` green, which runs `DocumentationImagesTest` among the rest.
- The new page is reachable from its rubric, and any page that enumerates
  siblings has been updated to include it.
- Every screenshot on a screen the change touched has been re-captured.
- A CHANGELOG entry exists, separately: the manual says how to use the
  software, the changelog says what moved.
