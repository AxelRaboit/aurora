---
name: convention_collection_on_concrete
description: Les Collection ManyToMany propriétaires vivent sur la classe concrète. Les OneToMany inverses vivent sur l'Abstract, avec leur constructeur - c'est ce que fait le code depuis Editorial.
metadata:
  type: feedback
---

## Règle

**ManyToMany propriétaire (et ManyToMany inverse)** : sur la classe concrète.
Précédents actuels : `Post::$terms`, `Post::$relatedPosts`.

**OneToMany inverse** : sur `Abstract<Name>` (MappedSuperclass), avec le
constructeur qui l'initialise. C'est ce que font les quatre entités à
collections du code actuel : `AbstractPost`, `AbstractForm`,
`AbstractTaxonomy`, `AbstractPlanning`. Le coût est réel et assumé : un client
qui substitue la concrète doit appeler `parent::__construct()`.

> **Corrigé le 08/09/2026.** Cette mémoire affirmait « toute Collection sur la
> concrète », y compris les OneToMany. Le code dit l'inverse depuis la
> reconstruction d'Editorial, et les précédents qu'elle citait
> (`Listing`, `ListingCategory`, `ListingTag`) ont quitté le core avec leur
> module. Vérifié entité par entité avant de réécrire la règle.

```php
// ✅ ManyToMany propriétaire sur la Concrete
class ListingCategory extends AbstractListingCategory implements ListingCategoryInterface
{
    /** @var Collection<int, ListingInterface> */
    #[ORM\ManyToMany(targetEntity: ListingInterface::class, mappedBy: 'categories')]
    private Collection $listings;

    /** @var Collection<int, ListingCategoryInterface> */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: ListingCategoryInterface::class)]
    private Collection $children;

    public function __construct()
    {
        $this->listings = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->translations = new ArrayCollection();
    }
}
```

Pour un OneToMany inverse, c'est l'Abstract :

```php
// ✅ OneToMany inverse sur l'Abstract, constructeur compris
abstract class AbstractContractTemplate implements ContractTemplateInterface
{
    /** @var Collection<int, ContractTemplateVersionInterface> */
    #[ORM\OneToMany(targetEntity: ContractTemplateVersionInterface::class, mappedBy: 'template')]
    protected Collection $versions;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
    }
}
```

## Pourquoi

Si le constructeur (et donc l'initialisation des collections) vit dans
`Abstract<Name>`, **tout client qui étend doit appeler `parent::__construct()`**,
une seule oubli et les collections sont `null`, l'app crash au premier `add()`.

En gardant constructeur + propriétés sur la concrete :

- Aurora-core garantit lui-même l'init des collections de la concrete par défaut.
- Le client qui substitue (`AppListing extends Listing`) peut overrider le
  constructeur **proprement** (`parent::__construct()` reste optionnel selon
  qu'il garde ou non les collections de base).
- Pas de couplage caché entre MappedSuperclass et concrete.

Cohérent avec [[convention_extensibility]] et [[pitfall_readonly_class]] :
laisser le maximum de flexibilité dans la concrete.

## Comment l'appliquer

1. ManyToMany propriétaire ou inverse → la propriété va dans `<Name>.php`
   (concrete). OneToMany inverse → dans `Abstract<Name>.php`, avec son
   `new ArrayCollection()` dans le constructeur de l'Abstract.
2. Le getter/setter peut vivre dans `Abstract<Name>` (logique partagée) si la
   propriété est protected. Mais l'init `new ArrayCollection()` reste dans le
   constructor de la concrete.
3. Toujours type-hint les collections avec l'**Interface** de l'entité cible
   (cf [[convention_interface_over_concrete]]).
