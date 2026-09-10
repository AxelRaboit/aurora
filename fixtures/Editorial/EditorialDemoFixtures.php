<?php

declare(strict_types=1);

namespace Aurora\Fixtures\Editorial;

use Aurora\Core\Locale\Enum\LocaleEnum;
use Aurora\Core\Sequence\SequencePrefixEnum;
use Aurora\Fixtures\Core\CoreDemoFixtures;
use Aurora\Fixtures\Ged\GedDemoFixtures;
use Aurora\Module\Configuration\Setting\Enum\ApplicationParameterEnum;
use Aurora\Module\Configuration\Setting\Service\SettingsService;
use Aurora\Module\Editorial\Comment\Entity\Comment;
use Aurora\Module\Editorial\Comment\Entity\CommentInterface;
use Aurora\Module\Editorial\Comment\Entity\CommentReaction;
use Aurora\Module\Editorial\Comment\Enum\CommentStatusEnum;
use Aurora\Module\Editorial\Comment\Enum\ReactionTypeEnum;
use Aurora\Module\Editorial\Form\Dto\FormFieldInput;
use Aurora\Module\Editorial\Form\Dto\FormInput;
use Aurora\Module\Editorial\Form\Entity\FormFieldInterface;
use Aurora\Module\Editorial\Form\Entity\FormInterface;
use Aurora\Module\Editorial\Form\Entity\FormTranslationInterface;
use Aurora\Module\Editorial\Form\Enum\FormFieldTypeEnum;
use Aurora\Module\Editorial\Form\Manager\FormManagerInterface;
use Aurora\Module\Editorial\Form\Repository\FormTranslationRepository;
use Aurora\Module\Editorial\Menu\Entity\MenuInterface;
use Aurora\Module\Editorial\Menu\Entity\MenuItem;
use Aurora\Module\Editorial\Menu\Enum\MenuItemTargetTypeEnum;
use Aurora\Module\Editorial\Menu\Repository\MenuItemRepository;
use Aurora\Module\Editorial\Menu\Repository\MenuRepository;
use Aurora\Module\Editorial\Post\Banner\BannerNormalizer;
use Aurora\Module\Editorial\Post\Entity\Post;
use Aurora\Module\Editorial\Post\Entity\PostInterface;
use Aurora\Module\Editorial\Post\Entity\PostTranslationInterface;
use Aurora\Module\Editorial\Post\Enum\PostStatusEnum;
use Aurora\Module\Editorial\Post\Enum\ThumbnailFitEnum;
use Aurora\Module\Editorial\Post\Gallery\GalleryNormalizer;
use Aurora\Module\Editorial\Post\Grid\GridNormalizer;
use Aurora\Module\Editorial\Post\Service\EditorBlocks;
use Aurora\Module\Editorial\Post\Service\PostTextExtractor;
use Aurora\Module\Editorial\PostType\Entity\PostTypeField;
use Aurora\Module\Editorial\PostType\Entity\PostTypeInterface;
use Aurora\Module\Editorial\PostType\Repository\PostTypeRepository;
use Aurora\Module\Editorial\Taxonomy\Entity\TaxonomyInterface;
use Aurora\Module\Editorial\Taxonomy\Entity\TaxonomyTerm;
use Aurora\Module\Editorial\Taxonomy\Entity\TaxonomyTermInterface;
use Aurora\Module\Editorial\Taxonomy\Repository\TaxonomyRepository;
use Aurora\Module\Ged\Document\Entity\Document;
use Aurora\Module\Platform\User\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use RuntimeException;

use function assert;

/**
 * Demo content: a handful of posts across the statuses, terms to file them
 * under, and a primary menu that points at them.
 *
 * Builds on what `aurora:install` already created rather than creating it
 * again - the post types and the taxonomies are the product's floor, not
 * demo data, and a fixture that made its own would give the site two
 * "Articles" types the day someone ran both. It looks them up and fails
 * loudly if they are absent, which means the install step was skipped.
 *
 * Dev/test only, `demo` group.
 */
class EditorialDemoFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function __construct(
        private readonly PostTypeRepository $postTypeRepository,
        private readonly TaxonomyRepository $taxonomyRepository,
        private readonly MenuRepository $menuRepository,
        private readonly MenuItemRepository $menuItemRepository,
        private readonly PostTextExtractor $textExtractor,
        private readonly GridNormalizer $gridNormalizer,
        private readonly GalleryNormalizer $galleryNormalizer,
        private readonly BannerNormalizer $bannerNormalizer,
        private readonly SettingsService $settingsManager,
        private readonly FormManagerInterface $forms,
        private readonly FormTranslationRepository $formTranslationRepository,
    ) {}

    public static function getGroups(): array
    {
        return ['demo'];
    }

    public function getDependencies(): array
    {
        // GED too, since the welcome page's grid points at a demo picture.
        return [CoreDemoFixtures::class, GedDemoFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        assert($manager instanceof EntityManagerInterface);

        $article = $this->postTypeRepository->findOneBySlug('article');
        $page = $this->postTypeRepository->findOneBySlug('page');
        if (!$article instanceof PostTypeInterface || !$page instanceof PostTypeInterface) {
            // Better than a fatal further down with no clue why: the demo
            // stands on the install step, and saying so is the whole message.
            throw new RuntimeException('Run `aurora:install` before loading the demo fixtures - the built-in post types are missing.');
        }

        $this->createCustomFields($manager, $article);

        $terms = $this->createTerms($manager);
        $posts = $this->createPosts($manager, $article, $page, $terms);

        $manager->flush();

        // After the flush on purpose: a grid points at a publication and a
        // document by *id*, and neither has one before it is written.
        $this->layOutWelcomePage($posts);
        $this->layOutFirstStepsArticle($posts);
        $this->addGalleries($posts);

        $this->fillPrimaryMenu($manager, $posts);

        $this->createQuoteForm();

        $this->createComments($manager, $posts);
        $manager->flush();

        $manager->flush();

        // The demo used to open on the list of its own two articles, which is
        // the fallback for a site that has not chosen a front page - so the
        // first screen anybody saw was the one screen nobody composes. The
        // welcome page is the composed one, banner included; naming it here is
        // what the parameter is for.
        $welcomeId = $posts['welcome']->getId();
        if (null !== $welcomeId) {
            $this->settingsManager->set(
                ApplicationParameterEnum::HomepagePostId->value,
                (string) $welcomeId,
            );
        }

        $manager->flush();
    }

    /**
     * Fields of its own on the Article type.
     *
     * The screen that lists a type's custom fields said "no custom field" on a
     * freshly loaded demo, which shows where the feature lives and nothing of
     * what it does. These four are the shapes that differ: a number, a link, a
     * closed list and a flag - and the first is marked translatable, since
     * whether a field follows the language is the choice that screen exists to
     * offer.
     */
    private function createCustomFields(EntityManagerInterface $em, PostTypeInterface $article): void
    {
        $defs = [
            ['reading_time', 'Temps de lecture (min)', 'number', false, false, []],
            ['source_url', 'Source', 'url', false, false, []],
            ['level', 'Niveau', 'select', false, true, ['Débutant', 'Intermédiaire', 'Avancé']],
            ['featured', 'Mettre en avant', 'checkbox', false, false, []],
        ];

        $repository = $em->getRepository(PostTypeField::class);

        foreach ($defs as $position => [$name, $label, $type, $required, $translatable, $options]) {
            // Reused by (type, machine name): the pair is what identifies a
            // field, and `make demo` runs twice.
            $field = $repository->findOneBy(['postType' => $article, 'name' => $name]) ?? new PostTypeField();

            $field->setPostType($article)
                ->setName($name)
                ->setLabel($label)
                ->setType($type)
                ->setRequired($required)
                ->setTranslatable($translatable)
                ->setOptions($options)
                ->setPosition($position);

            $em->persist($field);
        }
    }

    /**
     * A nested pair under `category` and two flat ones under `tag`, so both
     * taxonomy shapes have something in them to look at.
     *
     * @return array<string, TaxonomyTermInterface>
     */
    private function createTerms(EntityManagerInterface $em): array
    {
        $category = $this->taxonomyRepository->findOneBySlug('category');
        $tag = $this->taxonomyRepository->findOneBySlug('tag');

        $terms = [];

        if ($category instanceof TaxonomyInterface) {
            $terms['guides'] = $this->term($em, $category, [
                'fr' => ['Guides', 'guides'],
                'en' => ['Guides', 'guides'],
                'es' => ['Guías', 'guias'],
            ], SequencePrefixEnum::TaxonomyTerm->value.'-DEMO-1');

            $terms['starters'] = $this->term($em, $category, [
                'fr' => ['Premiers pas', 'premiers-pas'],
                'en' => ['Getting started', 'getting-started'],
                'es' => ['Primeros pasos', 'primeros-pasos'],
            ], SequencePrefixEnum::TaxonomyTerm->value.'-DEMO-2', $terms['guides']);
        }

        if ($tag instanceof TaxonomyInterface) {
            $terms['editorial'] = $this->term($em, $tag, [
                'fr' => ['Éditorial', 'editorial'],
                'en' => ['Editorial', 'editorial'],
                'es' => ['Editorial', 'editorial-es'],
            ], SequencePrefixEnum::TaxonomyTerm->value.'-DEMO-3');

            $terms['release'] = $this->term($em, $tag, [
                'fr' => ['Nouveautés', 'nouveautes'],
                'en' => ['Releases', 'releases'],
                'es' => ['Novedades', 'novedades'],
            ], SequencePrefixEnum::TaxonomyTerm->value.'-DEMO-4');
        }

        return $terms;
    }

    /**
     * @param array<string, array{0: string, 1: string}> $translations locale → [name, slug]
     */
    private function term(
        EntityManagerInterface $em,
        TaxonomyInterface $taxonomy,
        array $translations,
        string $reference,
        ?TaxonomyTermInterface $parent = null,
    ): TaxonomyTermInterface {
        // Reused when the reference is already taken, for the same reason as
        // the publications below: `make demo` should refresh a database that
        // already has demo data rather than dying halfway through it.
        $term = $em->getRepository(TaxonomyTerm::class)->findOneBy(['reference' => $reference])
            ?? new TaxonomyTerm();

        $term
            ->setTaxonomy($taxonomy)
            ->setParent($parent)
            ->setReference($reference)
            ->setPosition(0);

        foreach ($translations as $locale => [$name, $slug]) {
            $term->translate($locale)->setName($name)->setSlug($slug);
        }

        $em->persist($term);

        return $term;
    }

    /**
     * One post per status, so every filter on the list has something to
     * show and the dashboard bars are not a single full-width block.
     *
     * @param array<string, TaxonomyTermInterface> $terms
     *
     * @return array<string, PostInterface>
     */
    private function createPosts(
        EntityManagerInterface $em,
        PostTypeInterface $article,
        PostTypeInterface $page,
        array $terms,
    ): array {
        // Doctrine keys references by concrete class, so this asks for what
        // CoreDemoFixtures actually stored rather than for the interface.
        $author = $this->getReference(CoreDemoFixtures::userRef(0), User::class);
        $now = new DateTimeImmutable();

        $defs = [
            'welcome' => [
                'type' => $page,
                'media' => 0,
                'status' => PostStatusEnum::Published,
                'publishedAt' => $now->modify('-30 days'),
                'terms' => [],
                'fr' => ['Bienvenue', 'bienvenue', 'La page d\'accueil de ce site de démonstration.'],
                'en' => ['Welcome', 'welcome', 'The landing page of this demo site.'],
                'es' => ['Bienvenida', 'bienvenida', 'La página de inicio de este sitio de demostración.'],
            ],
            'first-steps' => [
                'type' => $article,
                'media' => 3,
                'status' => PostStatusEnum::Published,
                'publishedAt' => $now->modify('-12 days'),
                'terms' => ['starters', 'editorial'],
                'fr' => ['Écrire son premier article', 'ecrire-premier-article', 'Du brouillon à la mise en ligne, en cinq minutes.'],
                'en' => ['Writing your first post', 'writing-your-first-post', 'From draft to published, in five minutes.'],
                'es' => ['Escribir su primer artículo', 'escribir-primer-articulo', 'Del borrador a la publicación, en cinco minutos.'],
            ],
            'blocks' => [
                'type' => $article,
                'media' => 2,
                'status' => PostStatusEnum::Published,
                'publishedAt' => $now->modify('-3 days'),
                'terms' => ['guides'],
                'fr' => ['Composer avec les blocs', 'composer-avec-les-blocs', 'Titres, listes, encadrés : ce que l\'éditeur sait faire.'],
                'en' => ['Composing with blocks', 'composing-with-blocks', 'Headings, lists, callouts: what the editor can do.'],
                'es' => ['Componer con bloques', 'componer-con-bloques', 'Títulos, listas, destacados: lo que sabe hacer el editor.'],
            ],
            'roadmap' => [
                'type' => $article,
                'media' => 1,
                'status' => PostStatusEnum::Draft,
                'publishedAt' => null,
                'terms' => ['release'],
                'fr' => ['Ce qui arrive ensuite', 'ce-qui-arrive-ensuite', 'Un brouillon, visible seulement en administration.'],
                'en' => ['What comes next', 'what-comes-next', 'A draft, visible in the backend only.'],
                'es' => ['Lo que viene después', 'lo-que-viene-despues', 'Un borrador, visible solo en la administración.'],
            ],
            // Les deux statuts que la démo n'avait pas. La page de documentation
            // sur le cycle de vie en annonce cinq, et la liste n'en montrait
            // que trois : une capture qui contredit son propre texte.
            'review' => [
                'type' => $article,
                'media' => 1,
                'status' => PostStatusEnum::PendingReview,
                'publishedAt' => null,
                'terms' => ['guides'],
                'fr' => ['Relire avant de publier', 'relire-avant-de-publier', 'Envoyée en relecture : elle attend un avis.'],
                'en' => ['Review before publishing', 'review-before-publishing', 'Sent for review: it is waiting for an opinion.'],
                'es' => ['Revisar antes de publicar', 'revisar-antes-de-publicar', 'Enviada a revisión: espera una opinión.'],
            ],
            'retired' => [
                'type' => $article,
                'media' => 0,
                'status' => PostStatusEnum::Archived,
                'publishedAt' => $now->modify('-120 days'),
                'terms' => ['release'],
                'fr' => ["Les tarifs de l'an dernier", 'les-tarifs-de-l-an-dernier', 'Archivée : retirée du site, gardée en base.'],
                'en' => ["Last year's prices", 'last-years-prices', 'Archived: off the site, kept in the database.'],
                'es' => ['Las tarifas del año pasado', 'las-tarifas-del-ano-pasado', 'Archivada: fuera del sitio, guardada en base.'],
            ],
            'announcement' => [
                'type' => $article,
                'media' => 0,
                'status' => PostStatusEnum::Scheduled,
                'publishedAt' => null,
                'scheduledAt' => $now->modify('+7 days'),
                'terms' => ['release'],
                'fr' => ['Annonce à venir', 'annonce-a-venir', 'Programmée : elle se publiera toute seule.'],
                'en' => ['Upcoming announcement', 'upcoming-announcement', 'Scheduled: it will publish itself.'],
                'es' => ['Anuncio previsto', 'anuncio-previsto', 'Programado: se publicará solo.'],
            ],
        ];

        $posts = [];
        $index = 0;
        $repository = $em->getRepository(Post::class);

        foreach ($defs as $key => $def) {
            ++$index;

            $reference = sprintf('%s-DEMO-%d', SequencePrefixEnum::Post->value, $index);

            // Reused when it is already there, so a second `make demo`
            // refreshes the demo rather than dying on the unique reference.
            // Everything below is a setter, and `addTerm` already refuses a
            // term it holds - so one path serves both cases and there is no
            // second one to keep in step.
            $post = $repository->findOneBy(['reference' => $reference]) ?? new Post();

            $post
                ->setPostType($def['type'])
                ->setAuthor($author)
                // Every publication carries one: a demo where some cards have
                // a picture and some do not shows the empty layout as often as
                // the real one, and reads as unfinished rather than as a
                // choice.
                ->setThumbnail($this->getReference(GedDemoFixtures::mediaRef($def['media']), Document::class))
                // Stated rather than left to the default. It is the same value,
                // and a demo that relies on a default cannot show that the
                // control exists.
                ->setThumbnailFit(ThumbnailFitEnum::Cover)
                ->setStatus($def['status'])
                ->setPublishedAt($def['publishedAt'])
                ->setScheduledAt($def['scheduledAt'] ?? null)
                ->setReference($reference);

            foreach ($def['terms'] as $termKey) {
                if (isset($terms[$termKey])) {
                    $post->addTerm($terms[$termKey]);
                }
            }

            // A body is a grid of one full-width text zone. It used to be the
            // `blocks` column, which is the same thing said the old way - the
            // migration that moved every publication over does not run again on
            // a freshly loaded fixture set, so the fixtures have to speak the
            // new shape themselves or the demo pages come up empty.
            $post->setGridLayout($this->gridNormalizer->normalizeLayout([
                'enabled' => true,
                'snap' => 4,
                'zones' => [[
                    'id' => 'body',
                    'type' => GridNormalizer::ZONE_TEXT,
                    'span' => ['base' => 48, 'md' => null, 'lg' => 48],
                ]],
            ]));

            foreach (LocaleEnum::values() as $locale) {
                [$title, $slug, $description] = $def[$locale];

                $translation = $post->translate($locale)
                    ->setTitle($title)
                    ->setSlug($slug)
                    ->setDescription($description);

                $translation->setGrid($this->gridNormalizer->normalizeContent([
                    'zones' => ['body' => ['blocks' => $this->blocks($title, $description, $locale)]],
                ], $post->getGridLayout()));

                $this->indexForSearch($translation);
            }

            // persist() on an entity Doctrine already manages is a no-op, so
            // the reused branch needs no guard of its own.
            $em->persist($post);
            $posts[$key] = $post;
        }

        return $posts;
    }

    /**
     * The welcome page shows what a content grid can do: every zone type,
     * several widths, and a stack - the one arrangement the grid could not
     * make while zones only ever flowed along a row.
     *
     * Widths are all multiples of four, so every one of them is reachable at
     * the default snap - a demo an author cannot reproduce with the controls
     * in front of them teaches the wrong thing.
     *
     * The arrangement is written once and both languages share it; only what
     * fills the zones is translated. That is the feature, so the fixture has
     * to be built that way rather than duplicating a layout per locale.
     *
     * @param array<string, PostInterface> $posts
     */
    private function layOutWelcomePage(array $posts): void
    {
        $welcome = $posts['welcome'];
        $linked = $posts['first-steps'];
        $picture = $this->getReference(GedDemoFixtures::mediaRef(1), Document::class);
        // mediaRef(2), not (4): the references are numbered from one and the
        // fourth is the demo video, which a media zone would render as a
        // broken `<img>`. A portrait suits the lower half of a stack anyway.
        $stacked = $this->getReference(GedDemoFixtures::mediaRef(2), Document::class);

        $zones = [
            ['id' => 'intro', 'type' => GridNormalizer::ZONE_TEXT, 'span' => ['base' => 48, 'md' => null, 'lg' => 48]],
            // A tall picture beside a stack of two, which is the one shape the
            // grid could not make until stacks existed: a zone cannot occupy
            // two rows, so a third zone would have wrapped and landed *under*
            // this one rather than beside it.
            //
            // The portrait ratio is what gives the left zone a height that was
            // decided rather than inherited from its content - without it
            // "taller than its neighbours" is not something a picture can be
            // asked for.
            ['id' => 'picture', 'type' => GridNormalizer::ZONE_MEDIA, 'span' => ['base' => 48, 'md' => null, 'lg' => 24], 'ratio' => '3x4', 'mediaId' => $picture->getId()],
            [
                'id' => 'column',
                'type' => GridNormalizer::ZONE_STACK,
                'span' => ['base' => 48, 'md' => null, 'lg' => 24],
                // Halves that sum to 48, so the editor's fraction row reads
                // "1/2" on each and is telling the truth. The stack takes its
                // height from the row, which the picture beside it sets.
                'children' => [
                    ['id' => 'beside', 'type' => GridNormalizer::ZONE_TEXT, 'span' => ['base' => 48, 'md' => null, 'lg' => 24]],
                    // `fill`, not a ratio and not a half. The text above is
                    // three lines and its share was 403px, so an even split
                    // left 275px of nothing between the two - the gap this
                    // shape exists to close. The paragraph now takes what it
                    // says and the picture has the rest, which is what an
                    // author means by "a picture beside a short text".
                    ['id' => 'under', 'type' => GridNormalizer::ZONE_MEDIA, 'span' => ['base' => 48, 'md' => null, 'lg' => 24], 'ratio' => GridNormalizer::RATIO_FILL, 'mediaId' => $stacked->getId()],
                ],
            ],
            // Two thirds and one third: a player next to the article it is
            // about.
            ['id' => 'film', 'type' => GridNormalizer::ZONE_VIDEO, 'span' => ['base' => 48, 'md' => null, 'lg' => 32]],
            ['id' => 'linked', 'type' => GridNormalizer::ZONE_POST, 'span' => ['base' => 48, 'md' => null, 'lg' => 16], 'postId' => $linked->getId()],
            ['id' => 'outro', 'type' => GridNormalizer::ZONE_TEXT, 'span' => ['base' => 48, 'md' => null, 'lg' => 48]],
        ];

        $welcome->setGridLayout($this->gridNormalizer->normalizeLayout([
            'enabled' => true,
            'snap' => 4,
            'zones' => $zones,
        ]));

        // A header, because the demo home page had none and a site whose first
        // screen is a list of two cards shows the editor and hides everything
        // the banner can do. Full width, a gradient in the accent of the
        // shipped palette, and the foot dissolved into the page - the three
        // choices somebody would actually make, rather than a grey box proving
        // the field exists.
        $welcome->setBannerLayout($this->bannerNormalizer->normalizeLayout([
            'enabled' => true,
            'height' => 'lg',
            'width' => 'full_aligned',
            'verticalAlign' => 'center',
            'fadeOut' => true,
            'background' => [
                'type' => 'gradient',
                'gradientFrom' => '#059669',
                'gradientTo' => '#0b1120',
                'gradientAngle' => 60,
            ],
            'items' => [
                [
                    'id' => 'banner-text',
                    'type' => 'text',
                    'span' => ['base' => 48, 'md' => null, 'lg' => 30],
                    'titleColor' => '#ffffff',
                    'descriptionColor' => '#e5e7eb',
                    'align' => 'start',
                    'titleSize' => 'lg',
                ],
            ],
        ]));

        $content = [
            'fr' => [
                'intro' => [EditorBlocks::header('Une page composée par zones'),
                    EditorBlocks::paragraph('Chaque bloc ci-dessous est une zone posée sur une grille de 48 colonnes. Leur largeur se règle indépendamment, et ce qui les remplit se traduit - la disposition, elle, est écrite une seule fois.')],
                'beside' => [EditorBlocks::header('Une zone haute, deux zones à côté', 3),
                    EditorBlocks::paragraph('À gauche une image en portrait ; à droite une pile, qui prend la hauteur de la ligne et la partage entre ce texte et l\'image du dessous. Aucune hauteur n\'est réglée nulle part.')],
                'outro' => [EditorBlocks::paragraph("Une zone pleine largeur pour refermer. Modifiez tout ceci depuis l'administration, onglet Contenu.")],
            ],
            'en' => [
                'intro' => [EditorBlocks::header('A page laid out in zones'),
                    EditorBlocks::paragraph('Every block below is a zone on a 48-column grid. Widths are set independently, and what fills them is translated - the arrangement is written once.')],
                'beside' => [EditorBlocks::header('One tall zone, two beside it', 3),
                    EditorBlocks::paragraph('A portrait picture on the left; on the right a stack, which takes the height of the row and splits it between this text and the picture under it. No height is set anywhere.')],
                'outro' => [EditorBlocks::paragraph('A full-width zone to close. Change any of this from the backend, under Content.')],
            ],
            'es' => [
                'intro' => [EditorBlocks::header('Una página compuesta por zonas'),
                    EditorBlocks::paragraph('Cada bloque de abajo es una zona colocada sobre una cuadrícula de 48 columnas. Su anchura se ajusta por separado, y lo que las llena se traduce: la disposición, en cambio, se escribe una sola vez.')],
                'beside' => [EditorBlocks::header('Una zona alta, dos zonas al lado', 3),
                    EditorBlocks::paragraph('A la izquierda una imagen en vertical; a la derecha una pila, que toma la altura de la fila y la reparte entre este texto y la imagen de abajo. No hay ninguna altura fijada en ninguna parte.')],
                'outro' => [EditorBlocks::paragraph('Una zona a todo lo ancho para cerrar. Modifique todo esto desde la administración, pestaña Contenido.')],
            ],
        ];

        $captions = [
            'fr' => ['alt' => 'Un paysage de démonstration', 'caption' => 'Une image, avec sa légende - les deux se traduisent, l\'image non.'],
            'en' => ['alt' => 'A demo landscape', 'caption' => 'A picture and its caption - both translated, the picture itself is not.'],
            'es' => ['alt' => 'Un paisaje de demostración', 'caption' => 'Una imagen, con su pie de foto: los dos se traducen, la imagen no.'],
        ];

        $bannerTexts = [
            'fr' => [
                'title' => 'Bienvenue sur Aurora',
                'description' => "Un en-tête composé dans l'éditeur : hauteur, largeur, dégradé, fondu et boutons.",
            ],
            'en' => [
                'title' => 'Welcome to Aurora',
                'description' => 'A header composed in the editor: height, width, gradient, fade and buttons.',
            ],
            'es' => [
                'title' => 'Bienvenido a Aurora',
                'description' => 'Un encabezado compuesto en el editor: altura, anchura, degradado, difuminado y botones.',
            ],
        ];

        $stackedCaptions = [
            'fr' => ['alt' => 'Un bureau de démonstration', 'caption' => 'La seconde moitié de la pile.'],
            'en' => ['alt' => 'A demo desk', 'caption' => 'The second half of the stack.'],
            'es' => ['alt' => 'Un escritorio de demostración', 'caption' => 'La segunda mitad de la pila.'],
        ];

        $filmCaptions = [
            'fr' => 'Une vidéo, par langue.',
            'en' => 'A video, per language.',
            'es' => 'Un vídeo, por idioma.',
        ];

        foreach (LocaleEnum::values() as $locale) {
            $translation = $welcome->translate($locale);

            $translation->setBanner($this->bannerNormalizer->normalizeTexts([
                'items' => ['banner-text' => $bannerTexts[$locale]],
            ], $welcome->getBannerLayout()));

            $translation->setGrid($this->gridNormalizer->normalizeContent([
                'zones' => [
                    'intro' => ['blocks' => $content[$locale]['intro']],
                    'picture' => $captions[$locale],
                    'beside' => ['blocks' => $content[$locale]['beside']],
                    'under' => $stackedCaptions[$locale],
                    // Big Buck Bunny - Blender's open movie, which is here
                    // because a demo address that refuses to embed looks like
                    // a broken feature rather than a placeholder.
                    'film' => [
                        'url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
                        'caption' => $filmCaptions[$locale],
                    ],
                    'outro' => ['blocks' => $content[$locale]['outro']],
                ],
            ], $welcome->getGridLayout()));

            $this->indexForSearch($translation);
        }
    }

    /**
     * The article the welcome page links to gets a grid of its own, arranged
     * differently: a picture at a third beside its explanation at two thirds,
     * then two cards sharing a row - a "read next" strip, which is the shape
     * the linked-publication zone was added for.
     *
     * Different on purpose. Two demo pages laid out identically show one
     * arrangement twice and teach that the grid has a house style.
     *
     * @param array<string, PostInterface> $posts
     */
    /**
     * A gallery on two demo pages, one per layout.
     *
     * Both, on purpose: the two are different mechanisms rather than two sets of
     * classes - a grid crops every tile to one ratio and reads across a row,
     * masonry keeps each picture's proportions and reads down a column - and a
     * demo showing one of them teaches that the other is theoretical.
     *
     * On pages that already have a grid, because the point of the feature is
     * where it lands: under the content, without being asked. A gallery on an
     * otherwise empty page would show it working and not show it fitting.
     *
     * The library holds four images, so both galleries use the same four. The
     * normalizer refuses a picture twice **within** one gallery; across two posts
     * there is nothing to refuse.
     *
     * @param array<string, PostInterface> $posts
     */
    private function addGalleries(array $posts): void
    {
        $media = array_map(
            fn (int $index): Document => $this->getReference(GedDemoFixtures::mediaRef($index), Document::class),
            [0, 1, 2, 3],
        );

        $galleries = [
            // Portraits and a landscape at their own proportions: the case
            // masonry exists for, and the one where a fixed ratio would crop
            // the tall picture to nothing.
            'first-steps' => [
                'layout' => GalleryNormalizer::LAYOUT_MASONRY,
                'columns' => 3,
                'ratio' => GalleryNormalizer::RATIO_NATURAL,
                'words' => [
                    'fr' => [
                        ['Une bannière', 'Chaque image garde ses proportions.'],
                        ['Un paysage', 'Les colonnes se remplissent indépendamment.'],
                        ['Un portrait', 'Une image haute reste haute.'],
                        ['Un poste de travail', 'La lecture se fait colonne par colonne.'],
                    ],
                    'en' => [
                        ['A banner', 'Every picture keeps its proportions.'],
                        ['A landscape', 'The columns fill independently.'],
                        ['A portrait', 'A tall picture stays tall.'],
                        ['A workstation', 'This reads down a column, not across a row.'],
                    ],
                    'es' => [
                        ['Un banner', 'Cada imagen conserva sus proporciones.'],
                        ['Un paisaje', 'Las columnas se llenan de forma independiente.'],
                        ['Un retrato', 'Una imagen alta sigue siendo alta.'],
                        ['Un puesto de trabajo', 'La lectura va columna por columna, no de fila en fila.'],
                    ],
                ],
            ],
            // The same four, cropped square in four columns: uniform tiles, read
            // in the order they were written.
            'welcome' => [
                'layout' => GalleryNormalizer::LAYOUT_GRID,
                'columns' => 4,
                'ratio' => '1x1',
                'words' => [
                    'fr' => [
                        ['Une bannière', ''],
                        ['Un paysage', ''],
                        ['Un portrait', 'Recadrée au carré comme les autres.'],
                        ['Un poste de travail', ''],
                    ],
                    'en' => [
                        ['A banner', ''],
                        ['A landscape', ''],
                        ['A portrait', 'Cropped square like the rest.'],
                        ['A workstation', ''],
                    ],
                    'es' => [
                        ['Un banner', ''],
                        ['Un paisaje', ''],
                        ['Un retrato', 'Recortada en cuadrado como las demás.'],
                        ['Un puesto de trabajo', ''],
                    ],
                ],
            ],
        ];

        foreach ($galleries as $key => $definition) {
            $post = $posts[$key] ?? null;
            if (!$post instanceof PostInterface) {
                continue;
            }

            $items = [];
            foreach ($media as $index => $document) {
                $items[] = ['id' => sprintf('shot-%d', $index + 1), 'mediaId' => $document->getId()];
            }

            $layout = $this->galleryNormalizer->normalizeLayout([
                'enabled' => true,
                'layout' => $definition['layout'],
                'columns' => $definition['columns'],
                'ratio' => $definition['ratio'],
                'items' => $items,
            ]);
            $post->setGalleryLayout($layout);

            foreach (LocaleEnum::values() as $locale) {
                $words = [];
                foreach ($definition['words'][$locale] as $index => [$alt, $caption]) {
                    $words[sprintf('shot-%d', $index + 1)] = ['alt' => $alt, 'caption' => $caption];
                }

                $post->translate($locale)->setGallery(
                    $this->galleryNormalizer->normalizeContent(['items' => $words], $layout),
                );
            }
        }
    }

    private function layOutFirstStepsArticle(array $posts): void
    {
        $article = $posts['first-steps'];
        $shot = $this->getReference(GedDemoFixtures::mediaRef(3), Document::class);

        $article->setGridLayout($this->gridNormalizer->normalizeLayout([
            'enabled' => true,
            'snap' => 4,
            'zones' => [
                ['id' => 'lede', 'type' => GridNormalizer::ZONE_TEXT, 'span' => ['base' => 48, 'md' => null, 'lg' => 48]],
                ['id' => 'shot', 'type' => GridNormalizer::ZONE_MEDIA, 'span' => ['base' => 48, 'md' => null, 'lg' => 16], 'mediaId' => $shot->getId()],
                ['id' => 'explain', 'type' => GridNormalizer::ZONE_TEXT, 'span' => ['base' => 48, 'md' => null, 'lg' => 32]],
                ['id' => 'next-blocks', 'type' => GridNormalizer::ZONE_POST, 'span' => ['base' => 48, 'md' => null, 'lg' => 24], 'postId' => $posts['blocks']->getId()],
                ['id' => 'next-welcome', 'type' => GridNormalizer::ZONE_POST, 'span' => ['base' => 48, 'md' => null, 'lg' => 24], 'postId' => $posts['welcome']->getId()],
            ],
        ]));

        $content = [
            'fr' => [
                'lede' => [
                    EditorBlocks::header('Du brouillon à la mise en ligne'),
                    EditorBlocks::paragraph('Un article se compose de la même façon qu\'une page : des zones, une largeur chacune, et du contenu de nature différente dans chacune.'),
                ],
                'explain' => [
                    EditorBlocks::header('Une image au tiers, le texte aux deux tiers', 3),
                    EditorBlocks::paragraph('16 colonnes sur 48 pour la photo, 32 pour ce paragraphe. La page d\'accueil utilise l\'inverse - rien n\'impose une seule façon de découper une ligne.'),
                    EditorBlocks::list(['Ajoutez une zone', 'Réglez sa largeur au curseur', 'Remplissez-la']),
                ],
                'shot' => ['alt' => 'Un poste de travail', 'caption' => 'Une photo au tiers de la largeur.'],
            ],
            'en' => [
                'lede' => [
                    EditorBlocks::header('From draft to published'),
                    EditorBlocks::paragraph('An article is composed the same way a page is: zones, a width each, and content of a different kind in every one.'),
                ],
                'explain' => [
                    EditorBlocks::header('A picture at a third, the text at two thirds', 3),
                    EditorBlocks::paragraph('16 of 48 columns for the photo, 32 for this paragraph. The welcome page uses the reverse - nothing forces one way of splitting a row.'),
                    EditorBlocks::list(['Add a zone', 'Set its width with the slider', 'Fill it']),
                ],
                'shot' => ['alt' => 'A workstation', 'caption' => 'A photo at a third of the width.'],
            ],
            'es' => [
                'lede' => [
                    EditorBlocks::header('Del borrador a la publicación'),
                    EditorBlocks::paragraph('Un artículo se compone igual que una página: zonas, una anchura para cada una, y contenido de distinta naturaleza en cada una de ellas.'),
                ],
                'explain' => [
                    EditorBlocks::header('Una imagen a un tercio, el texto a dos tercios', 3),
                    EditorBlocks::paragraph('16 columnas de 48 para la foto, 32 para este párrafo. La página de inicio usa lo contrario: nada obliga a repartir una fila de una sola manera.'),
                    EditorBlocks::list(['Añada una zona', 'Ajuste su anchura con el cursor', 'Rellénela']),
                ],
                'shot' => ['alt' => 'Un puesto de trabajo', 'caption' => 'Una foto a un tercio de la anchura.'],
            ],
        ];

        foreach (LocaleEnum::values() as $locale) {
            $translation = $article->translate($locale);

            $translation->setGrid($this->gridNormalizer->normalizeContent([
                'zones' => [
                    'lede' => ['blocks' => $content[$locale]['lede']],
                    'shot' => $content[$locale]['shot'],
                    'explain' => ['blocks' => $content[$locale]['explain']],
                ],
            ], $article->getGridLayout()));

            $this->indexForSearch($translation);
        }
    }

    /**
     * A body in the shape the *editor* expects - which is stricter than what
     * the renderer accepts, and the reason these go through EditorBlocks
     * rather than being written out by hand.
     *
     * @return array<int, array<string, mixed>>
     */
    private function blocks(string $title, string $description, string $locale): array
    {
        // Written per language. It used to be French whatever the locale, so
        // an English demo page carried an English title over a French
        // paragraph - which is precisely the mistake the multilingual demo
        // exists to show you avoiding.
        $copy = [
            'fr' => ['Ce contenu est une démonstration. Remplacez-le par le vôtre depuis l\'administration.', ['Un premier point', 'Un deuxième point']],
            'en' => ['This content is a demonstration. Replace it with your own from the backend.', ['A first point', 'A second point']],
            'es' => ['Este contenido es una demostración. Sustitúyalo por el suyo desde la administración.', ['Un primer punto', 'Un segundo punto']],
        ];

        [$paragraph, $points] = $copy[$locale] ?? $copy['fr'];

        return [
            EditorBlocks::paragraph($description),
            EditorBlocks::header($title),
            EditorBlocks::paragraph($paragraph),
            EditorBlocks::list($points),
        ];
    }

    /**
     * The same flattening the Manager does on save. Without it a demo post
     * exists but no search finds it, which reads as a broken search rather
     * than as an unindexed fixture.
     */
    private function indexForSearch(PostTranslationInterface $translation): void
    {
        $translation->setSearchContent($this->textExtractor->extract($translation));
    }

    /**
     * Hangs the demo pages off the primary menu the install created, rather
     * than making a second menu: a location holds one menu, and a demo that
     * needed its own would be demonstrating something the product cannot do.
     *
     * @param array<string, PostInterface> $posts
     */
    private function fillPrimaryMenu(EntityManagerInterface $em, array $posts): void
    {
        $menu = $this->menuRepository->findOneByLocation('primary');
        if (!$menu instanceof MenuInterface) {
            return;
        }

        $entries = [
            ['post' => 'welcome', 'fr' => 'Bienvenue', 'en' => 'Welcome', 'es' => 'Bienvenida'],
            ['post' => 'first-steps', 'fr' => 'Premiers pas', 'en' => 'Getting started', 'es' => 'Primeros pasos'],
        ];

        // The seeded "Home" entry sits at position 0; these follow it.
        $position = 1;

        foreach ($entries as $entry) {
            $post = $posts[$entry['post']] ?? null;
            if (!$post instanceof PostInterface) {
                continue;
            }

            // Reused when this menu already points at that publication.
            // Without it every `make demo` appended another entry, and the
            // topbar grew a copy of each link per run - which is exactly what
            // it did here before this line existed.
            $item = $this->menuItemRepository->findOneBy([
                'menu' => $menu,
                'targetType' => MenuItemTargetTypeEnum::Post,
                'targetId' => $post->getId(),
            ]) ?? new MenuItem();

            $item
                ->setTargetType(MenuItemTargetTypeEnum::Post)
                ->setTargetId($post->getId())
                ->setPosition($position++);

            foreach (LocaleEnum::values() as $locale) {
                $item->translate($locale)->setLabel($entry[$locale] ?? $entry['fr']);
            }

            $menu->addItem($item);
            $em->persist($item);
        }
    }

    /**
     * A form with something in it.
     *
     * The builder, the field types, the conditional display and the list of
     * requests received all show the same screen on an empty demo: "no form".
     * This one is a quote request in two steps, and it carries one field of
     * every type the product offers, because the screen that lists the types
     * is only worth looking at when each of them is there to be seen.
     *
     * Idempotent on its French slug: `make demo` twice must not leave two.
     */
    private function createQuoteForm(): void
    {
        if ($this->formTranslationRepository->findOneByLocaleAndSlug('fr', 'demande-de-devis') instanceof FormTranslationInterface) {
            return;
        }

        $form = $this->forms->create(new FormInput(
            translations: [
                'fr' => ['title' => 'Demande de devis', 'slug' => 'demande-de-devis', 'description' => 'Décrivez votre projet, nous revenons vers vous sous 48 heures.'],
                'en' => ['title' => 'Quote request', 'slug' => 'quote-request', 'description' => 'Tell us about your project, we answer within 48 hours.'],
                'es' => ['title' => 'Solicitud de presupuesto', 'slug' => 'solicitud-de-presupuesto', 'description' => 'Cuéntenos su proyecto, respondemos en 48 horas.'],
            ],
            notifyEmail: 'contact@example.com',
            steps: [
                ['title' => 'Vos coordonnées'],
                ['title' => 'Votre projet'],
            ],
        ));

        $fields = [];
        foreach ($this->quoteFormFields() as $key => $definition) {
            // The conditional field points at another field by id, so it is
            // written after the one it depends on - hence the loop rather
            // than one createField call per line.
            $conditions = [];
            if (isset($definition['showsWhen'])) {
                [$dependency, $value] = $definition['showsWhen'];
                $conditions = [['fieldId' => (int) $fields[$dependency]->getId(), 'value' => $value]];
            }

            $fields[$key] = $this->forms->createField($form, new FormFieldInput(
                translations: $definition['translations'],
                type: $definition['type'],
                required: $definition['required'] ?? false,
                conditions: $conditions,
                step: $definition['step'],
            ));
        }

        $this->submitQuoteForm($form, $fields);
    }

    /**
     * @return array<string, array{type: FormFieldTypeEnum, step: int, required?: bool, showsWhen?: array{string, string}, translations: array<string, array{label: string, placeholder: ?string, options: list<string>}>}>
     */
    private function quoteFormFields(): array
    {
        return [
            'name' => [
                'type' => FormFieldTypeEnum::Text,
                'step' => 1,
                'required' => true,
                'translations' => [
                    'fr' => ['label' => 'Nom complet', 'placeholder' => 'Camille Durand', 'options' => []],
                    'en' => ['label' => 'Full name', 'placeholder' => 'Camille Durand', 'options' => []],
                    'es' => ['label' => 'Nombre completo', 'placeholder' => 'Camille Durand', 'options' => []],
                ],
            ],
            'email' => [
                'type' => FormFieldTypeEnum::Email,
                'step' => 1,
                'required' => true,
                'translations' => [
                    'fr' => ['label' => 'Adresse e-mail', 'placeholder' => 'camille@exemple.fr', 'options' => []],
                    'en' => ['label' => 'Email address', 'placeholder' => 'camille@example.com', 'options' => []],
                    'es' => ['label' => 'Correo electrónico', 'placeholder' => 'camille@ejemplo.es', 'options' => []],
                ],
            ],
            'phone' => [
                'type' => FormFieldTypeEnum::Tel,
                'step' => 1,
                'translations' => [
                    'fr' => ['label' => 'Téléphone', 'placeholder' => '06 12 34 56 78', 'options' => []],
                    'en' => ['label' => 'Phone', 'placeholder' => '+33 6 12 34 56 78', 'options' => []],
                    'es' => ['label' => 'Teléfono', 'placeholder' => '+34 612 34 56 78', 'options' => []],
                ],
            ],
            'source' => [
                'type' => FormFieldTypeEnum::Radio,
                'step' => 1,
                'translations' => [
                    'fr' => ['label' => 'Comment nous avez-vous connus ?', 'placeholder' => null, 'options' => ['Recherche web', 'Recommandation', 'Réseaux sociaux']],
                    'en' => ['label' => 'How did you hear about us?', 'placeholder' => null, 'options' => ['Web search', 'Word of mouth', 'Social media']],
                    'es' => ['label' => '¿Cómo nos ha conocido?', 'placeholder' => null, 'options' => ['Búsqueda web', 'Recomendación', 'Redes sociales']],
                ],
            ],
            'project' => [
                'type' => FormFieldTypeEnum::Select,
                'step' => 2,
                'required' => true,
                'translations' => [
                    'fr' => ['label' => 'Type de projet', 'placeholder' => null, 'options' => ['Site vitrine', 'Boutique en ligne', 'Application métier']],
                    'en' => ['label' => 'Kind of project', 'placeholder' => null, 'options' => ['Showcase site', 'Online shop', 'Business application']],
                    'es' => ['label' => 'Tipo de proyecto', 'placeholder' => null, 'options' => ['Sitio de presentación', 'Tienda en línea', 'Aplicación de gestión']],
                ],
            ],
            'references' => [
                'type' => FormFieldTypeEnum::Number,
                'step' => 2,
                // The one field nobody sees unless they need it: a shop is
                // the only answer that makes a catalogue size worth asking.
                'showsWhen' => ['project', 'Boutique en ligne'],
                'translations' => [
                    'fr' => ['label' => 'Nombre de références au catalogue', 'placeholder' => '250', 'options' => []],
                    'en' => ['label' => 'Number of catalogue items', 'placeholder' => '250', 'options' => []],
                    'es' => ['label' => 'Número de referencias del catálogo', 'placeholder' => '250', 'options' => []],
                ],
            ],
            'deadline' => [
                'type' => FormFieldTypeEnum::Date,
                'step' => 2,
                'translations' => [
                    'fr' => ['label' => 'Mise en ligne souhaitée', 'placeholder' => null, 'options' => []],
                    'en' => ['label' => 'Preferred launch date', 'placeholder' => null, 'options' => []],
                    'es' => ['label' => 'Fecha de lanzamiento deseada', 'placeholder' => null, 'options' => []],
                ],
            ],
            'message' => [
                'type' => FormFieldTypeEnum::Textarea,
                'step' => 2,
                'required' => true,
                'translations' => [
                    'fr' => ['label' => 'Votre projet en quelques lignes', 'placeholder' => 'Ce que vous vendez, à qui, et ce qui existe déjà.', 'options' => []],
                    'en' => ['label' => 'Your project in a few lines', 'placeholder' => 'What you sell, to whom, and what already exists.', 'options' => []],
                    'es' => ['label' => 'Su proyecto en unas líneas', 'placeholder' => 'Qué vende, a quién y qué existe ya.', 'options' => []],
                ],
            ],
            // "Cases à cocher" is a list of choices, not a single flag: the
            // value it stores is the set of options ticked. A checkbox field
            // with no option renders as a label above nothing.
            'consent' => [
                'type' => FormFieldTypeEnum::Checkbox,
                'step' => 2,
                'required' => true,
                'translations' => [
                    'fr' => ['label' => 'Accord', 'placeholder' => null, 'options' => ["J'accepte d'être recontacté au sujet de cette demande"]],
                    'en' => ['label' => 'Consent', 'placeholder' => null, 'options' => ['I agree to be contacted about this request']],
                    'es' => ['label' => 'Consentimiento', 'placeholder' => null, 'options' => ['Acepto que me contacten sobre esta solicitud']],
                ],
            ],
        ];
    }

    /**
     * Three requests already received.
     *
     * Going through the manager rather than writing rows: a submission gets
     * its reference from the sequence, and a list of requests whose reference
     * column is empty shows a screen the product never produces.
     *
     * @param array<string, FormFieldInterface> $fields
     */
    private function submitQuoteForm(FormInterface $form, array $fields): void
    {
        $answers = [
            [
                'name' => 'Camille Durand',
                'email' => 'camille.durand@example.com',
                'phone' => '06 12 34 56 78',
                'source' => 'Recommandation',
                'project' => 'Boutique en ligne',
                'references' => '250',
                'deadline' => '2026-03-02',
                'message' => 'Nous vendons du matériel de randonnée dans deux boutiques et nous voulons ouvrir en ligne avant la saison.',
                'consent' => ["J'accepte d'être recontacté au sujet de cette demande"],
            ],
            [
                'name' => 'Yann Lefebvre',
                'email' => 'y.lefebvre@example.org',
                'phone' => '07 88 45 12 03',
                'source' => 'Recherche web',
                'project' => 'Site vitrine',
                'deadline' => '2026-01-15',
                'message' => 'Un cabinet de trois architectes, un site qui montre les chantiers livrés et rien de plus.',
                'consent' => ["J'accepte d'être recontacté au sujet de cette demande"],
            ],
            [
                'name' => 'Sofia Marchetti',
                'email' => 'sofia.marchetti@example.net',
                'source' => 'Réseaux sociaux',
                'project' => 'Application métier',
                'message' => "Le suivi des interventions se fait aujourd'hui sur un tableur partagé, et il ne tient plus.",
                'consent' => ["J'accepte d'être recontacté au sujet de cette demande"],
            ],
        ];

        foreach ($answers as $answer) {
            $data = [];
            foreach ($answer as $key => $value) {
                $data[(string) $fields[$key]->getId()] = $value;
            }

            $this->forms->submit($form, $data, 'fr', '203.0.113.42');
        }
    }

    /**
     * Des commentaires, dans les trois états, avec un fil et des réactions.
     *
     * L'écran de modération et la page publique d'un article s'ouvraient tous
     * les deux sur « Aucun commentaire » : on y voyait où la fonction se
     * trouve, et rien de ce qu'elle fait. Les trois statuts sont là parce que
     * c'est entre eux que l'écran sert à choisir, et le fil parce qu'une
     * réponse ne se range pas comme un commentaire de premier niveau.
     *
     * Idempotent sur la référence : `make demo` deux fois ne doit pas en
     * laisser six.
     *
     * @param array<string, PostInterface> $posts
     */
    private function createComments(EntityManagerInterface $em, array $posts): void
    {
        $post = $posts['first-steps'] ?? null;

        if (!$post instanceof PostInterface) {
            return;
        }

        $repository = $em->getRepository(Comment::class);
        $now = new DateTimeImmutable();

        $entries = [
            'approved' => [
                'status' => CommentStatusEnum::Approved,
                'name' => 'Camille Durand',
                'email' => 'camille.durand@example.com',
                'content' => "Merci pour ce guide, la partie sur les zones m'a débloquée. Une question : peut-on réutiliser une disposition d'une page à l'autre ?",
                'at' => $now->modify('-6 days'),
                'reactions' => [ReactionTypeEnum::Like, ReactionTypeEnum::Like, ReactionTypeEnum::Love],
            ],
            'reply' => [
                'status' => CommentStatusEnum::Approved,
                'name' => 'Yann Lefebvre',
                'email' => 'y.lefebvre@example.org',
                'content' => "Oui : dupliquez la publication, videz les textes, et vous gardez la grille. C'est ce que je fais pour les fiches produit.",
                'at' => $now->modify('-5 days'),
                'parent' => 'approved',
                'reactions' => [ReactionTypeEnum::Like],
            ],
            'pending' => [
                'status' => CommentStatusEnum::Pending,
                'name' => 'Sofia Marchetti',
                'email' => 'sofia.marchetti@example.net',
                'content' => 'Est-ce que la mise en ligne programmée fonctionne aussi pour les pages, ou seulement pour les articles ?',
                'at' => $now->modify('-2 days'),
                'reactions' => [],
            ],
            'spam' => [
                'status' => CommentStatusEnum::Spam,
                'name' => 'Best SEO Offer',
                'email' => 'contact@example-spam.test',
                'content' => 'Boostez votre trafic maintenant, tarifs imbattables, contactez-nous vite !',
                'at' => $now->modify('-1 day'),
                'reactions' => [],
            ],
        ];

        $created = [];

        foreach ($entries as $key => $entry) {
            $reference = sprintf('%s-DEMO-%s', SequencePrefixEnum::Comment->value, mb_strtoupper($key));
            $comment = $repository->findOneBy(['reference' => $reference]) ?? new Comment();

            $comment
                ->setReference($reference)
                ->setPost($post)
                ->setAuthorName($entry['name'])
                ->setAuthorEmail($entry['email'])
                ->setContent($entry['content'])
                ->setStatus($entry['status']);

            if (isset($entry['parent'])) {
                $comment->setParent($created[$entry['parent']]);
            }

            $em->persist($comment);
            $created[$key] = $comment;

            // Une empreinte par réaction : c'est ce qui tient lieu d'identité
            // à un visiteur sans compte, et deux réactions du même visiteur
            // sur le même commentaire n'en font qu'une. La base le garantit
            // par un index unique, ce qui faisait échouer le deuxième
            // `make demo` - d'où la recherche avant l'insertion.
            $reactions = $em->getRepository(CommentReaction::class);

            foreach ($entry['reactions'] as $index => $type) {
                $fingerprint = sprintf('demo-%s-%d', $key, $index);

                if (null !== $reactions->findOneBy(['comment' => $comment, 'fingerprint' => $fingerprint])) {
                    continue;
                }

                $reaction = new CommentReaction();
                $reaction
                    ->setComment($comment)
                    ->setType($type)
                    ->setFingerprint($fingerprint);

                $em->persist($reaction);
            }
        }

        $em->flush();

        // Après le flush : la date de création est posée par le constructeur,
        // et une démo dont les quatre commentaires portent la même minute ne
        // montre pas un fil de discussion.
        foreach ($entries as $key => $entry) {
            $this->backdate($em, $created[$key], $entry['at']);
        }

        $em->flush();
    }

    /** Repositionne la date d'un commentaire, que l'entité ne laisse pas écrire. */
    private function backdate(EntityManagerInterface $em, CommentInterface $comment, DateTimeImmutable $at): void
    {
        $em->getConnection()->executeStatement(
            'UPDATE core_comments SET created_at = :at WHERE id = :id',
            ['at' => $at->format('Y-m-d H:i:s'), 'id' => $comment->getId()],
        );
    }
}
