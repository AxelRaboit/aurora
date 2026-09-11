# Écrire la documentation du produit

Le manuel que voit un utilisateur du back-office vit dans le code, pas dans
la base. Une fonctionnalité qui change et sa page qui change tiennent donc
dans le même commit, ce qui est toute la raison de ce dossier.

## Où c'est

```
src/Module/Documentation/
    content/02-editorial/100-cycle-de-vie.md    la page
    images/02-editorial/cycle-de-vie-01-....png les captures
```

Le dossier est la rubrique, le nombre est l'ordre de lecture, le reste du
nom est l'adresse : la page ci-dessus se lit à
`/backend/documentation/cycle-de-vie`. Il n'y a pas d'index à tenir à jour.

Chaque fichier commence par son en-tête :

```markdown
---
title: "Le cycle de vie d'une publication"
description: "Cinq statuts, une date de mise en ligne et une date de retrait."
rubric: "Éditorial"
---
```

Puis du Markdown ordinaire. Les titres `##` deviennent les étapes listées à
droite de la page ; les images s'écrivent en chemin relatif, et c'est ce
chemin qui dit dans quelle rubrique ranger le fichier.

## Ajouter une page

1. Créer le fichier dans la bonne rubrique, avec un numéro qui le place où
   il se lit.
2. Écrire la page. Une étape par `##`, une capture après l'étape qu'elle
   montre.
3. Prendre les captures, les ranger, vérifier.

## Les captures

Elles se prennent sur l'instance locale et sur le jeu de démonstration,
jamais sur une base réelle :

```bash
make fixtures                                   # une démo propre
node tools/doc-screenshots/capture-steps.mjs cycle-de-vie
node tools/doc-screenshots/place.mjs            # range ce que les pages citent
```

`capture-steps.mjs` pilote un vrai parcours et photographie chaque étape :
c'est la différence entre une page qui montre où cliquer et une page qui
montre un écran. `capture-doc.mjs` sert aux écrans qui n'ont pas de
parcours, une seule photo par adresse.

`place.mjs` lit les pages, relève les images qu'elles citent, et copie
depuis `var/doc-screenshots/out/` celles qui manquent ou qui ont changé. Il
signale ce qui est cité sans exister nulle part.

`audit-paths.mjs` vérifie que chaque adresse photographiée rend bien une
page. Quatre captures publiées venaient d'un point d'API : le navigateur
affichait le JSON sur fond sombre, et la page montrait un rectangle noir ou
un pavé de code. Toutes répondaient 200.

### Ce qu'il faut vérifier soi-même

**Regarder l'image.** Un parcours qui n'a pas planté n'est pas un parcours
qui a photographié la bonne chose : une capture d'un écran vide, d'un
rectangle noir ou de la page précédente passe tous les contrôles
automatiques. Ce dossier a publié les trois.

`DocumentationImagesTest` vérifie les deux sens - une image citée existe,
une image livrée est citée - et rien de plus : il ne sait pas ce qu'elle
montre.

## Ce que la documentation ne dit pas

Elle s'adresse à qui **tient** le site, pas à qui le construit. Une page qui
parle de ligne de commande, de déploiement, de base de données ou de tâche
de fond s'adresse au mauvais lecteur.

Quand la réponse à un problème est « il n'y a rien à faire ici, c'est
technique », c'est exactement cela qu'il faut écrire, sans nommer la
mécanique interne.
