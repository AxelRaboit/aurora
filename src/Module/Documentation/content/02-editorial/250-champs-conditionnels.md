---
title: "Les champs conditionnels"
description: "Ne poser une question que si la réponse précédente l'appelle."
rubric: "Éditorial"
---
Une question qui ne concerne qu'une partie des visiteurs n'a pas besoin d'être posée à tout le monde. Un champ conditionné n'apparaît que si les réponses attendues ont été données.

## 1. Un champ conditionné se signale

Dans la liste des champs, la mention **Conditions d'affichage** suit le type et l'étape. C'est la seule marque visible : le détail de la condition se lit dans la fenêtre du champ.

![La mention « Conditions d'affichage » sous un champ](../../images/02-editorial/champs-conditionnels-01-le-champ-porte-une-mention.png)

## 2. Ouvrir le champ

Le menu de la ligne donne **Modifier** et **Supprimer**. Supprimer un champ retire aussi les conditions qui le citaient : une condition qui pointe vers un champ disparu ne pourrait plus jamais être remplie, et le champ qui en dépend serait perdu sans que rien ne l'explique.

![Le menu d'un champ](../../images/02-editorial/champs-conditionnels-02-le-menu-de-la-ligne.png)

## 3. La condition

Une condition est une paire : un **champ**, et la **valeur** qu'il doit avoir. On en met plusieurs, et la logique dit s'il les faut **toutes** ou **au moins une**.

La valeur attendue s'écrit exactement comme l'option : c'est le texte de l'option, pas son rang.

![La condition d'affichage du champ](../../images/02-editorial/champs-conditionnels-03-la-condition-du-champ.png)

## 4. Sans la réponse, pas de champ

Sur le site, tant que la réponse attendue n'est pas donnée, le champ n'est pas affiché du tout. Il n'est pas grisé : il n'est pas là.

Un champ caché n'est jamais obligatoire. Le visiteur n'a pas à répondre à une question qu'on ne lui montre pas.

![Le formulaire sans la réponse qui déclenche le champ](../../images/02-editorial/champs-conditionnels-04-sans-la-reponse-le-champ-est-absent.png)

## 5. La réponse fait apparaître le champ

Dès que la réponse correspond, le champ s'insère à sa place, dans l'ordre où il a été rangé. Changer d'avis le fait disparaître, et la réponse qu'il contenait n'est pas envoyée.

![Le champ apparu, une fois la réponse donnée](../../images/02-editorial/champs-conditionnels-05-la-reponse-fait-apparaitre-le-champ.png)
