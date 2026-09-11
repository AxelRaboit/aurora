<?php

declare(strict_types=1);

namespace Aurora\Module\Documentation\Service;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;

use function array_keys;
use function array_search;
use function array_slice;
use function array_values;
use function basename;
use function dirname;
use function file_get_contents;
use function is_string;
use function max;
use function mb_substr;
use function mb_trim;
use function preg_match;
use function sort;
use function str_contains;
use function Symfony\Component\String\u;

/**
 * The product's own documentation, read from the files that ship with it.
 *
 * It used to be a hundred and twenty publications in each client's database,
 * written through the editor like any other content. That was the wrong
 * place twice over: a client could edit it, and a feature shipped without
 * its documentation because the two lived in different repositories. Here
 * the page sits beside the code it describes, and a change to one is a
 * change to the other in the same commit.
 *
 * Layout, and nothing else to configure:
 *
 *     content/02-editorial/100-cycle-de-vie.md
 *
 * The folder is the rubric, the number is the reading order, the rest is the
 * address. The front matter carries the title and the summary. No index file
 * to keep in step: adding a page is adding a file.
 */
final class DocumentationIndex
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $pages = null;

    public function __construct(private readonly string $contentDir) {}

    /**
     * The tree, in reading order: rubrics, then their pages.
     *
     * @return list<array{slug: string, label: string, pages: list<array{slug: string, title: string, description: string}>}>
     */
    public function tree(): array
    {
        $rubrics = [];

        foreach ($this->all() as $page) {
            $rubrics[$page['rubricSlug']] ??= [
                'slug' => $page['rubricSlug'],
                'label' => $page['rubric'],
                'pages' => [],
            ];

            $rubrics[$page['rubricSlug']]['pages'][] = [
                'slug' => $page['slug'],
                'title' => $page['title'],
                'description' => $page['description'],
            ];
        }

        return array_values($rubrics);
    }

    /** @return array<string, mixed>|null */
    public function page(string $slug): ?array
    {
        return $this->all()[$slug] ?? null;
    }

    /**
     * The page before and the page after, across rubrics.
     *
     * Reading a documentation is walking it, and the walk does not stop at a
     * rubric boundary.
     *
     * @return array{previous: ?array{slug: string, title: string}, next: ?array{slug: string, title: string}}
     */
    public function neighbours(string $slug): array
    {
        $slugs = array_keys($this->all());
        $at = array_search($slug, $slugs, true);

        if (false === $at) {
            return ['previous' => null, 'next' => null];
        }

        return [
            'previous' => $this->brief($slugs[$at - 1] ?? null),
            'next' => $this->brief($slugs[$at + 1] ?? null),
        ];
    }

    /**
     * Pages whose title, summary or text contain the words asked for.
     *
     * Over the files rather than over an index: a hundred pages is four
     * hundred kilobytes, and an index would be a second thing to keep in
     * step with the first for no gain anybody could measure.
     *
     * @return list<array{slug: string, title: string, description: string, rubric: string, excerpt: string}>
     */
    public function search(string $query, int $limit = 20): array
    {
        $needle = $this->fold($query);

        if ('' === $needle) {
            return [];
        }

        $found = [];

        foreach ($this->all() as $page) {
            $haystack = $this->fold($page['title'].' '.$page['description'].' '.$page['markdown']);

            if (!str_contains($haystack, $needle)) {
                continue;
            }

            $found[] = [
                'slug' => $page['slug'],
                'title' => $page['title'],
                'description' => $page['description'],
                'rubric' => $page['rubric'],
                'excerpt' => $this->excerpt($page['markdown'], $needle),
            ];
        }

        return array_slice($found, 0, $limit);
    }

    /**
     * Lowercased and stripped of its accents, on both sides of the question.
     *
     * Somebody looking for "depublier" means "depublier" with an accent: a
     * manual that answers nothing because the reader skipped one is a
     * manual they conclude has no page on the subject.
     */
    private function fold(string $text): string
    {
        return u(mb_trim($text))->ascii()->lower()->toString();
    }

    /**
     * The words around the match, so a result says why it matched.
     *
     * A title alone answers "this page exists"; the sentence that carries the
     * word answers "and this is what it says about it".
     */
    private function excerpt(string $markdown, string $needle): string
    {
        // What is left is prose: an excerpt cut inside an image path reads
        // as gibberish, a heading is a label rather than a sentence, and the
        // emphasis markers are punctuation nobody typed.
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/u', '', $markdown) ?? $markdown;
        $text = preg_replace('/^#+ .*$/mu', '', $text) ?? $text;
        $text = preg_replace('/\*\*|`|^[-*] /mu', '', $text) ?? $text;
        $text = mb_trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        // Sur le texte replie, parce que c'est lui qui a permis la
        // correspondance : chercher un mot sans accent dans un texte qui en
        // porte ne trouverait rien, et l'extrait tomberait au debut de la page.
        $at = mb_strpos($this->fold($text), $needle);

        if (false === $at) {
            return mb_substr($text, 0, 160);
        }

        $from = max(0, $at - 70);
        $cut = mb_substr($text, $from, 200);

        return (0 === $from ? '' : '…').$cut.'…';
    }

    /** @return array{slug: string, title: string}|null */
    private function brief(?string $slug): ?array
    {
        if (null === $slug) {
            return null;
        }

        $page = $this->all()[$slug] ?? null;

        return null === $page ? null : ['slug' => $page['slug'], 'title' => $page['title']];
    }

    /**
     * Every page, keyed by address, in reading order.
     *
     * Read once per request and kept: the tree, a page and its neighbours are
     * three questions about the same hundred files, and asking the disk three
     * times to answer them would be three times the work for one answer.
     *
     * @return array<string, array<string, mixed>>
     */
    private function all(): array
    {
        if (null !== $this->pages) {
            return $this->pages;
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->contentDir));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && 'md' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }

        // The numeric prefixes order the whole thing, rubric folder first and
        // page second, which a plain sort of the full path gets right.
        sort($files);

        $pages = [];

        foreach ($files as $path) {
            $page = $this->read($path);

            if (null !== $page) {
                $pages[$page['slug']] = $page;
            }
        }

        return $this->pages = $pages;
    }

    /** @return array<string, mixed>|null */
    private function read(string $path): ?array
    {
        $raw = file_get_contents($path);

        if (false === $raw) {
            return null;
        }

        $matches = [];

        if (1 !== preg_match('/\A---\R(.*?)\R---\R(.*)\z/su', $raw, $matches)) {
            return null;
        }

        /** @var array<string, mixed> $meta */
        $meta = Yaml::parse($matches[1]) ?? [];
        $rubricDir = basename(dirname($path));

        return [
            'slug' => preg_replace('/^\d+-/', '', basename($path, '.md')) ?? '',
            'title' => is_string($meta['title'] ?? null) ? $meta['title'] : '',
            'description' => is_string($meta['description'] ?? null) ? $meta['description'] : '',
            'rubric' => is_string($meta['rubric'] ?? null) ? $meta['rubric'] : $rubricDir,
            'rubricSlug' => $rubricDir,
            'markdown' => mb_trim($matches[2]),
        ];
    }
}
