---
title: "L'onglet Contenu et sa grille"
description: "Poser une zone, tirer ses bords, les 48 colonnes, la largeur par écran."
rubric: "Éditorial"
---
La grille est la manière dont Aurora compose une page. Pas un éditeur de texte avec des blocs à la queue leu leu : des zones posées sur une grille, dont vous décidez la largeur.

## 1. La disposition

Un interrupteur active la grille. En dessous, la disposition : chaque zone montre son type et sa largeur, exprimée en quarante-huitièmes. Une zone à 48/48 tient toute la largeur, deux zones à 24/48 se partagent une ligne.

Pourquoi 48 : c'est divisible par 2, 3, 4, 6, 8, 12 et 16, donc à peu près toutes les répartitions tombent juste, sans reste.

![La grille de contenu et sa disposition](../../images/02-editorial/onglet-contenu-01-la-grille-de-contenu.png)

## 2. Choisir une zone

Un clic la sélectionne. Les poignées sur ses bords permettent de la redimensionner à la souris ; le pas de redimensionnement est réglable, ce qui évite de viser au pixel.

Le signe entre deux lignes ajoute une ligne à cet endroit précis, plutôt qu'à la fin.

![Une zone sélectionnée dans la grille](../../images/02-editorial/onglet-contenu-02-une-zone-choisie.png)

## 3. La palette

En bas, les onze types de zone posables : texte, image, publication, vidéo, bouton, séparateur, liste, liste automatique, formulaire, code, sommaire, et la pile qui contient les autres.

![La palette des types de zone](../../images/02-editorial/onglet-contenu-03-la-palette-de-zones.png)

## La largeur par taille d'écran

Chaque zone dit sa largeur pour le **téléphone**, la **tablette** et le **grand écran**. Seule la première est obligatoire, les autres héritent de la précédente quand on les laisse vides.

C'est le point à vérifier avant de publier : deux colonnes qui vont bien sur un écran large donnent, sur un téléphone, deux colonnes de trois mots. La règle simple est de repasser à 48/48 sur téléphone pour tout ce qui porte du texte.

## Traverser l'écran

Une zone peut sortir de la colonne de lecture et occuper toute la largeur de la fenêtre. Réservé aux images et aux bandeaux : du texte à pleine largeur ne se lit pas.
