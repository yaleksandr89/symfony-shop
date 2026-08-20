<?php

declare(strict_types=1);

namespace App\OAuthBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\NotBlank;

final class OAuthUnlinkFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'attr' => [
                    'autocomplete' => 'current-password',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(),
                    new UserPassword(),
                ],
                'label' => 'personal_account.social_group.oauth_unlink.current_password',
                'mapped' => false,
            ])
            ->add('submit', SubmitType::class, [
                'attr' => ['class' => 'btn btn-danger'],
                'label' => 'personal_account.social_group.oauth_unlink.submit',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }

    public function getBlockPrefix(): string
    {
        return 'oauth_unlink_form';
    }
}
