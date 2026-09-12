---
name: R2 - ne jamais lire les métadonnées d'un objet par un HEAD
description: Cloudflare gzippe les types compressibles, donc un HEAD rend une taille absente et un ETag faible ; passer par un listing
type: feedback
---

## Règle

Sur Cloudflare R2, **la taille et l'empreinte d'un objet se lisent dans un
listing, jamais dans une réponse `HeadObject`**.

## Pourquoi

Cloudflare compresse à la volée les types qu'il juge compressibles. Une réponse
gzippée n'a pas d'en-tête `Content-Length` et porte un ETag faible (`W/"…"`).
Mesuré le 11/09/2026 sur un vrai bucket, avec 54 octets écrits :

| `Content-Type` | `content-length` du HEAD | ETag | encodage |
|---|---|---|---|
| `text/plain` | **absent** | faible | gzip |
| `image/png` | 54 | fort | aucun |
| aucun | 54 | fort | aucun |

Le piège est qu'il ne casse rien tout de suite et qu'il dépend du type : les
images, majoritaires dans la GED, répondent juste. Un `text/plain` rend zéro.
Une taille fausse écrite en base ne se voit qu'au moment où quelqu'un la
compare, c'est-à-dire à la migration, très loin de la cause.

## Comment l'appliquer

`R2StorageAdapter::stat()` interroge `listObjectsV2` avec `Prefix` égal à la
clé et `MaxKeys: 1`, puis compare la clé **exactement** (un préfixe attrape
aussi `note.txt.bak`). Le listing rapporte ce que l'objet pèse dans le bucket,
pas ce que la réponse pèse sur le fil, donc il est juste dans les deux cas.

Coût assumé : une opération de classe A là où un HEAD est en classe B, environ
douze fois plus cher. Acceptable sur un chemin que rien n'appelle en boucle.
Du code qui a besoin des tailles de plusieurs objets doit les **lister**, ce
qui est exactement ce à quoi sert `list()` (cf. [[pattern_storage_adapter]]).

`exists()` peut rester sur un HEAD : l'existence n'est pas affectée.

## Voisin

L'ETag d'un envoi multipart se termine par `-<n>` et n'est l'empreinte de rien.
Le traiter comme un jeton de comparaison opaque, jamais comme un MD5.
