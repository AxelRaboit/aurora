<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Module\Editorial\Comment;

use Aurora\Module\Editorial\Comment\Entity\Comment;
use Aurora\Module\Editorial\Comment\Serializer\CommentSerializer;
use Aurora\Module\Editorial\Post\Entity\Post;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

/**
 * The moderation screen names the publication each comment sits under.
 *
 * It took `getTranslations()->first()` - whichever translation Doctrine had
 * hydrated first - so a French back office listed comments "Sur Escribir su
 * primer artículo". Nothing failed; the screen simply named the page in a
 * language nobody had asked for.
 */
final class CommentSerializerTest extends TestCase
{
    public function testTheTitleFollowsTheLanguageOfTheScreen(): void
    {
        $comment = $this->commentOnATrilingualPost();

        self::assertSame(
            'Écrire son premier article',
            $this->serialize($comment, 'fr')['postTitle'],
        );

        self::assertSame(
            'Writing your first article',
            $this->serialize($comment, 'en')['postTitle'],
        );
    }

    /**
     * A publication with no version in the reader's language still has to be
     * named: a title in another language beats an empty cell.
     */
    public function testAnyTitleRatherThanNoneWhenTheLanguageIsMissing(): void
    {
        $comment = $this->commentOnATrilingualPost();

        self::assertNotSame('', $this->serialize($comment, 'de')['postTitle']);
    }

    /** @return array<string, mixed> */
    private function serialize(Comment $comment, string $locale): array
    {
        $translator = new Translator($locale);

        return (new CommentSerializer($translator))->serialize($comment);
    }

    private function commentOnATrilingualPost(): Comment
    {
        $post = new Post();
        $post->translate('fr')->setTitle('Écrire son premier article')->setSlug('ecrire-premier-article');
        $post->translate('en')->setTitle('Writing your first article')->setSlug('writing-your-first-article');
        $post->translate('es')->setTitle('Escribir su primer artículo')->setSlug('escribir-su-primer-articulo');

        $comment = new Comment();
        $comment
            ->setPost($post)
            ->setAuthorName('Camille Durand')
            ->setAuthorEmail('camille.durand@example.com')
            ->setContent('Merci pour ce guide.');

        return $comment;
    }
}
