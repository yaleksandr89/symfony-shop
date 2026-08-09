<?php

namespace App\Tests\Unit\Form\Front;

use App\Form\Front\ChangePasswordFormType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\Test\FormIntegrationTestCase;
use Symfony\Component\Validator\Validation;

#[Group(name: 'unit')]
class ChangePasswordFormTypeTest extends FormIntegrationTestCase
{
    /**
     * @return FormExtensionInterface[]
     */
    protected function getExtensions(): array
    {
        return [new ValidatorExtension(Validation::createValidator())];
    }

    public function testBlankPasswordIsInvalid(): void
    {
        $form = $this->factory->create(ChangePasswordFormType::class);
        $form->submit([
            'plainPassword' => [
                'first' => '',
                'second' => '',
            ],
        ]);

        self::assertFalse($form->isValid());
    }

    public function testTooShortPasswordIsInvalid(): void
    {
        $form = $this->factory->create(ChangePasswordFormType::class);
        $form->submit([
            'plainPassword' => [
                'first' => 'short',
                'second' => 'short',
            ],
        ]);

        self::assertFalse($form->isValid());
    }

    public function testMatchingValidPasswordIsAccepted(): void
    {
        $form = $this->factory->create(ChangePasswordFormType::class);
        $form->submit([
            'plainPassword' => [
                'first' => 'valid-password',
                'second' => 'valid-password',
            ],
        ]);

        self::assertTrue($form->isValid());
    }

    #[TestDox('Несовпадающие пароли отклоняются')]
    public function testMismatchedPasswordsAreInvalid(): void
    {
        $form = $this->factory->create(ChangePasswordFormType::class);
        $form->submit([
            'plainPassword' => [
                'first' => 'valid-password',
                'second' => 'different-password',
            ],
        ]);

        self::assertFalse($form->isValid());
    }
}
