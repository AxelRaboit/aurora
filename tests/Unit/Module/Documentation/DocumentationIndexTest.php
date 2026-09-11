<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Module\Documentation;

use Aurora\Module\Documentation\Service\DocumentationIndex;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;

/**
 * The manual is read from files, so the rules that matter are file rules:
 * what orders the pages, what names them, and what the search answers.
 *
 * Against a folder written by the test rather than the shipped one: the
 * real content changes with every feature, and a test that asserted on it
 * would fail for the wrong reason every time somebody wrote a page.
 */
final class DocumentationIndexTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/aurora-doc-'.bin2hex(random_bytes(4));

        $this->write('02-editorial/020-seconde.md', 'La seconde', 'Éditorial', "Du texte sur les **zones**.\n\n## Une étape\n\nAvec sa suite.");
        $this->write('02-editorial/010-premiere.md', 'La première', 'Éditorial', 'Une histoire de girafes.');
        $this->write('01-general/010-accueil.md', "L'accueil", 'Général', 'Ce qui ouvre le back-office.');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*/*.md') ?: [] as $file) {
            unlink($file);
        }

        foreach (glob($this->dir.'/*', GLOB_ONLYDIR) ?: [] as $folder) {
            rmdir($folder);
        }

        rmdir($this->dir);
    }

    /** The folder is the rubric and the number is the order, nothing else. */
    public function testTheTreeFollowsTheFoldersAndTheNumbers(): void
    {
        $tree = $this->index()->tree();

        self::assertSame(['Général', 'Éditorial'], array_column($tree, 'label'));
        self::assertSame(
            ['premiere', 'seconde'],
            array_column($tree[1]['pages'], 'slug'),
        );
    }

    public function testAPageKeepsItsFrontMatterAndItsText(): void
    {
        $page = $this->index()->page('accueil');

        self::assertSame("L'accueil", $page['title']);
        self::assertSame('Général', $page['rubric']);
        self::assertStringContainsString('back-office', $page['markdown']);
    }

    public function testAnUnknownAddressIsNull(): void
    {
        self::assertNull($this->index()->page('nexiste-pas'));
    }

    /** Reading a manual is walking it, and the walk crosses rubrics. */
    public function testNeighboursCrossRubrics(): void
    {
        $neighbours = $this->index()->neighbours('premiere');

        self::assertSame('accueil', $neighbours['previous']['slug']);
        self::assertSame('seconde', $neighbours['next']['slug']);
    }

    public function testTheFirstPageHasNoPrevious(): void
    {
        self::assertNull($this->index()->neighbours('accueil')['previous']);
    }

    public function testTheSearchLooksInsideTheText(): void
    {
        $found = $this->index()->search('girafes');

        self::assertCount(1, $found);
        self::assertSame('premiere', $found[0]['slug']);
    }

    /**
     * A result says why it matched, and it says it in prose: the reader
     * never typed the asterisks that make a word bold in the source.
     */
    public function testTheExcerptIsProseAndNotMarkdown(): void
    {
        $found = $this->index()->search('zones');

        self::assertStringContainsString('zones', $found[0]['excerpt']);
        self::assertStringNotContainsString('**', $found[0]['excerpt']);
        self::assertStringNotContainsString('##', $found[0]['excerpt']);
    }

    public function testAnEmptyQuestionGetsAnEmptyAnswer(): void
    {
        self::assertSame([], $this->index()->search('   '));
    }

    private function index(): DocumentationIndex
    {
        return new DocumentationIndex($this->dir);
    }

    private function write(string $path, string $title, string $rubric, string $body): void
    {
        $full = $this->dir.'/'.$path;
        @mkdir(dirname($full), 0o775, true);

        file_put_contents($full, <<<MD
            ---
            title: "{$title}"
            description: "Un résumé."
            rubric: "{$rubric}"
            ---
            {$body}

            MD);
    }
}
