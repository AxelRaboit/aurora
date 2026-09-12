---
name: La documentation fait partie de la fonctionnalité
description: Toute évolution visible met à jour /backend/documentation, les pages voisines devenues fausses, et les captures concernées
type: feedback
---

## Règle

**Une fonctionnalité n'est pas finie tant que `/backend/documentation` ne la
décrit pas.** Demandé explicitement par Axel le 12/09/2026.

Trois choses à chaque évolution visible depuis l'interface, pas une :

1. La page de la nouveauté, si elle en mérite une.
2. **Les pages voisines devenues fausses.** C'est celle qu'on saute.
3. Les captures des écrans touchés, y compris celles d'autres pages.

Le skill [[update-documentation]] (`.claude/skills/update-documentation/`)
opérationnalise la passe.

## Pourquoi

Le manuel est dans le produit, pas à côté. Une fonctionnalité livrée sans sa
page est une fonctionnalité que personne ne découvre. Une page qui décrit un
écran qui a changé est pire que pas de page : elle est lue, crue, et suivie.

Le point 2 est le vrai piège, et il s'est vérifié le jour où la règle a été
posée : l'onglet « Stockage des fichiers » ajouté aux réglages a rendu fausse
la page `08-configuration/010-les-groupes-de-reglages.md`, qui annonce « les
treize onglets » et les énumère. Rien n'échoue quand une page compte mal.
**Chercher les pages qui dénombrent** est le réflexe à avoir.

## Comment l'appliquer

Repérer ce que le changement a pu rendre faux, en lisant plutôt qu'en se fiant
aux noms de fichiers :

```bash
grep -rl "<ce que la modification touche>" src/Module/Documentation/content/
```

Les captures se régénèrent en local, sur les fixtures de démo, jamais sur la
prod (cf. [[aurora-screenshots-local-fixtures]]) :

```bash
make demo && make start-d
node tools/doc-screenshots/capture-doc.mjs <nom>
node tools/doc-screenshots/place.mjs
```

`place.mjs` lit le Markdown pour savoir où ranger chaque image : le chemin
écrit dans la page est la seule déclaration. `DocumentationImagesTest`, joué
par `make ft`, refuse une image citée et absente, et réclame la suppression
d'une image devenue inutile.

## Trois cibles distinctes, aucune ne remplace l'autre

| Quoi | Pour qui | Où | Règle |
|---|---|---|---|
| Le manuel | La personne qui se sert du logiciel | `src/Module/Documentation/content/` | cette mémoire |
| Les docs techniques | Le développeur qui étend Aurora | `docs/`, `.claude/memory/` | [[process_doc_audit_before_commit]] |
| Le CHANGELOG | Le développeur qui met à jour | `CHANGELOG.md` | [[process_release]] |

## Ne pas confondre avec le CHANGELOG

Le manuel dit **comment se servir** du logiciel, à la personne qui s'en sert.
Le CHANGELOG dit **ce qui a bougé**, au développeur qui met à jour. Les deux
sont obligatoires et ne se remplacent pas.
