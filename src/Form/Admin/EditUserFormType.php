<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\StaticStorage\UserStaticStorage;
use App\Form\DTO\EditUserModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

class EditUserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Email(),
                    new Length(max: 180),
                ],
            ])
            ->add('plainPassword', TextType::class, [
                'label' => 'New password',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Roles',
                'required' => false,
                'multiple' => true,
                'choices' => array_flip(UserStaticStorage::getUserRolesChoices()),
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('fullName', TextType::class, [
                'label' => 'Full name',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Length(max: 255),
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Phone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Length(max: 30),
                    new Regex(pattern: '/^((8|\+7)[\- ]?)?(\(?\d{3}\)?[\- ]?)?[\d\- ]{7,10}$/'),
                ],
            ])
            ->add('address', TextType::class, [
                'label' => 'Address',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Length(max: 180),
                ],
            ])
            ->add('zipCode', TextType::class, [
                'label' => 'Zip code',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('isDeleted', CheckboxType::class, [
                'label' => 'Is Deleted',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'label_attr' => [
                    'class' => 'form-check-label',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Save changes',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EditUserModel::class,
            'user_repository' => null,
        ]);
    }
}
