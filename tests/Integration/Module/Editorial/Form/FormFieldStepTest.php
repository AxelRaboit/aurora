<?php

declare(strict_types=1);

namespace Aurora\Tests\Integration\Module\Editorial\Form;

use Aurora\Core\Validation\Exception\FieldException;
use Aurora\Module\Editorial\Form\Dto\FormFieldInput;
use Aurora\Module\Editorial\Form\Dto\FormInput;
use Aurora\Module\Editorial\Form\Entity\FormInterface;
use Aurora\Module\Editorial\Form\Enum\FormFieldTypeEnum;
use Aurora\Module\Editorial\Form\Manager\FormManagerInterface;
use Aurora\Tests\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A field can only sit on a step the form has.
 *
 * The builder took any number in that box. The renderer reads steps from 1
 * and draws the fields of the current one, so a field on step 0 - or on step
 * 3 of a two-step form - was never shown to anybody: still listed in the
 * back office, still editable, and absent from the site. Nothing said so,
 * and the only way to notice was to open the public page and count.
 */
final class FormFieldStepTest extends IntegrationTestCase
{
    private FormManagerInterface $forms;

    private EntityManagerInterface $entityManager;

    private FormInterface $form;

    protected function setUp(): void
    {
        parent::setUp();

        $this->forms = static::getContainer()->get(FormManagerInterface::class);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->form = $this->forms->create(new FormInput(
            translations: ['fr' => ['title' => 'Devis '.bin2hex(random_bytes(4)), 'slug' => null, 'description' => null]],
            steps: [['title' => 'Vous'], ['title' => 'Votre projet']],
        ));
    }

    protected function tearDown(): void
    {
        $this->entityManager->remove($this->form);
        $this->entityManager->flush();

        parent::tearDown();
    }

    public function testAStepOfZeroIsRefused(): void
    {
        $this->expectException(FieldException::class);

        $this->forms->createField($this->form, $this->field(step: 0));
    }

    public function testAStepTheFormDoesNotHaveIsRefused(): void
    {
        $this->expectException(FieldException::class);

        $this->forms->createField($this->form, $this->field(step: 3));
    }

    public function testTheLastStepIsAccepted(): void
    {
        $field = $this->forms->createField($this->form, $this->field(step: 2));

        self::assertSame(2, $field->getStep());
    }

    /** A single-page form keeps its fields stepless, and that stays legal. */
    public function testNoStepIsAccepted(): void
    {
        $field = $this->forms->createField($this->form, $this->field(step: null));

        self::assertNull($field->getStep());
    }

    /** The same guard on the way in applies to an edit. */
    public function testMovingAFieldOffTheFormIsRefused(): void
    {
        $field = $this->forms->createField($this->form, $this->field(step: 1));

        $this->expectException(FieldException::class);

        $this->forms->updateField($field, $this->field(step: 9));
    }

    private function field(?int $step): FormFieldInput
    {
        return new FormFieldInput(
            translations: ['fr' => ['label' => 'Nom', 'placeholder' => null, 'options' => []]],
            type: FormFieldTypeEnum::Text,
            step: $step,
        );
    }
}
