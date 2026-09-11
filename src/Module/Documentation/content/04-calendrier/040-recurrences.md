---
title: "Les récurrences"
description: "Décrire la règle une fois, et la portée d'une modification."
rubric: "Calendrier"
---
Un événement qui se répète n'est pas cinquante événements : c'est une règle, et des exceptions.

![Les récurrences](../../images/04-calendrier/planning-week.png)

## La règle

Elle se décrit une fois - tous les lundis, le premier de chaque mois - avec une date de fin facultative. Les occurrences sont calculées à l'affichage, donc changer la règle change tout ce qui vient.

## Les quatre portées

Déplacer ou modifier une occurrence pose la question au moment d'enregistrer :

- Celle-ci seulement : l'occurrence devient une exception, le reste ne bouge pas.
- Celle-ci et les suivantes : la règle est coupée en deux.
- Toutes.
- L'unique, quand l'événement ne se répète pas.

## Supprimer

La même question se pose. Supprimer une seule occurrence la retire de la série sans toucher aux autres : c'est une exception, pas une suppression.
