---
title: "Le captcha"
description: "Turnstile ou reCAPTCHA, où poser les clés, et sur quoi il s'applique."
rubric: "Éditorial"
---
Une vérification anti-robots, désactivée par défaut, qui se branche avec votre propre compte chez le fournisseur.

## Les deux fournisseurs

Turnstile de Cloudflare, ou reCAPTCHA de Google. On en choisit un, on colle ses deux clés, on active.

Les clés sont les vôtres, pas celles d'Aurora : c'est votre compte qui voit le trafic et qui décide de la politique. Aucun service tiers n'est appelé tant que vous n'avez pas activé.

![L'onglet Anti-robots](../../images/02-editorial/captcha-reglages-01-l-onglet-anti-robots.png)

## Une donnée personnelle

Le service choisi **reçoit l'adresse IP du visiteur**. C'est une donnée personnelle : le compte doit être celui du site, et la politique de confidentialité doit le dire. L'écran le rappelle en jaune, au-dessus du choix du service.

## Sur quoi il s'applique

- Les formulaires du site.
- Les commentaires.

C'est une vérification **en plus** de ce qui existe déjà : le piège à robots, le filtre à liens, la limite d'envois et la modération. Elle n'est pas la première ligne, elle est la dernière.

Pas sur la connexion au back-office, qui est protégée autrement, par une limite de cinq tentatives par quart d'heure.

## Si les clés sont fausses

Le formulaire refuse les envois. Vérifiez que la clé publique et la clé secrète ne sont pas inversées : c'est l'erreur la plus fréquente, et le fournisseur ne le dit pas clairement.
