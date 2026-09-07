<?php

declare(strict_types=1);

namespace Aurora\Fixtures\Core;

use Aurora\Core\Locale\Enum\LocaleEnum;
use Aurora\Module\Configuration\Theme\Entity\Theme;
use Aurora\Module\Platform\User\Entity\User;
use Aurora\Module\Platform\User\Enum\UserRoleEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function assert;

/**
 * Demo scaffolding shared by every module's demo fixtures: the demo users.
 * Each user is exposed via a fixture reference ({@see userRef}) so module
 * fixtures - which ship in their own Composer package and cannot import
 * this concrete data - stay decoupled: they only depend on this class and
 * pull users by reference.
 *
 * Dev/test only - registered via `when@dev` in config/services.yaml.
 */
class CoreDemoFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    /** Number of demo users seeded (indices 0..USER_COUNT-1). */
    public const int USER_COUNT = 2;

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    /** Reference name for the demo user at the given index. */
    public static function userRef(int $index): string
    {
        return 'core_demo_user_'.$index;
    }

    public static function getGroups(): array
    {
        return ['demo'];
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        assert($manager instanceof EntityManagerInterface);

        $users = $this->createUsers($manager);

        foreach ($users as $i => $user) {
            $this->addReference(self::userRef($i), $user);
        }

        $this->createThemes($manager);

        $manager->flush();
    }

    /**
     * Palettes to switch between while showing the site.
     *
     * The install seeds one theme, which is enough to prove the screen exists
     * and not enough to show what it does: a list of one has nothing to
     * compare. These three are complete looks - ground, bands and accent
     * chosen together - so switching one radio button visibly changes the
     * public site, which is the whole point of the feature.
     *
     * Inactive on purpose. A demo reload must not take the site away from
     * whatever theme somebody is currently working on.
     */
    private function createThemes(EntityManagerInterface $em): void
    {
        $defs = [
            [
                'slug' => 'nuit-emeraude',
                'name' => 'Nuit émeraude',
                'description' => 'Fond encre, accent vert. Lisible longtemps, sobre en photo.',
                'config' => [
                    'primary_color' => '#10b981',
                    'background_color' => '#030712',
                    'header_color' => '#111827',
                    'footer_color' => '#111827',
                ],
            ],
            [
                'slug' => 'papier',
                'name' => 'Papier',
                'description' => 'Fond clair et accent ardoise, pour un site qui se lit comme un document.',
                'config' => [
                    'primary_color' => '#1f2937',
                    'background_color' => '#faf9f6',
                    'header_color' => '#ffffff',
                    'footer_color' => '#f3f4f6',
                ],
            ],
            [
                'slug' => 'corail',
                'name' => 'Corail',
                'description' => 'Chaud et affirmé : bandeaux profonds, accent orangé sur les liens.',
                'config' => [
                    'primary_color' => '#f97316',
                    'background_color' => '#1c1917',
                    'header_color' => '#292524',
                    'footer_color' => '#292524',
                ],
            ],
        ];

        $repository = $em->getRepository(Theme::class);

        foreach ($defs as $def) {
            // Reused by slug, like the users above: `make demo` runs twice.
            $theme = $repository->findOneBy(['slug' => $def['slug']]) ?? new Theme();

            $theme->setSlug($def['slug'])
                ->setName($def['name'])
                ->setDescription($def['description'])
                ->setConfig($def['config']);

            if (null === $theme->getId()) {
                $theme->setActive(false);
                $em->persist($theme);
            }
        }

        $em->flush();

        // The install seeds `default` with a name and no palette, so a demo
        // opened straight after `make demo` served the stylesheet's own
        // fallbacks: correct, and undecided. The first of these is switched on
        // in that case only - a site whose default theme carries colours has
        // been dressed by somebody, and a fixture reload must not undress it.
        $active = $repository->findOneBy(['active' => true]);

        if (null === $active || ('default' === $active->getSlug() && [] === $active->getConfig())) {
            $chosen = $repository->findOneBy(['slug' => 'nuit-emeraude']);

            if (null !== $chosen) {
                if (null !== $active) {
                    $active->setActive(false);
                }

                $chosen->setActive(true);
            }
        }
    }

    /** @return User[] */
    private function createUsers(EntityManagerInterface $em): array
    {
        $users = [];

        $defs = [
            [
                'email' => 'marie.dupont@aurora.app',
                'name' => 'Marie Dupont',
                'role' => UserRoleEnum::Admin,
                'privileges' => [],
                'mood' => 'Responsable des opérations 🚀',
            ],
            [
                'email' => 'jean.martin@aurora.app',
                'name' => 'Jean Martin',
                'role' => UserRoleEnum::User,
                'privileges' => [
                    'general.dashboard.view',
                    // GED - full document management
                    'ged.documents.view', 'ged.documents.create', 'ged.documents.edit', 'ged.documents.delete',
                    'ged.categories.view', 'ged.categories.create', 'ged.categories.edit', 'ged.categories.delete',
                    'ged.tags.manage', 'ged.folders.manage',
                ],
                'mood' => 'Gestionnaire documentaire',
            ],
        ];

        $repository = $em->getRepository(User::class);

        foreach ($defs as $def) {
            // Reused when it is already there, so `make demo` can be run twice.
            // It used to always insert, and the second run died on the unique
            // (email, type) - after purging var/uploads, which is the first
            // thing that target does. A reload that half-runs is worse than one
            // that refuses.
            $user = $repository->findOneBy(['email' => $def['email']]) ?? new User();
            $fresh = null === $user->getId();

            $user->setEmail($def['email'])
                 ->setName($def['name'])
                 ->setRoles([$def['role']->value])
                 ->setPrivileges($def['privileges'])
                 ->setMoodMessage($def['mood'])
                 ->setLocale(LocaleEnum::French);

            // Only on creation: a reload refreshes what the demo describes -
            // the name, the rights - without resetting a password somebody
            // changed in the meantime.
            if ($fresh) {
                $user->setPassword($this->hasher->hashPassword($user, 'password'));
                $em->persist($user);
            }

            $users[] = $user;
        }

        return $users;
    }
}
