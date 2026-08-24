<?php

declare(strict_types=1);

namespace App\AdminBundle\Form;

use App\Account\User\UserStaticStorage;
use App\AdminBundle\DTO\EditUserModel;
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
                'label' => 'user.form.field.email',
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
                'label' => 'user.form.field.plain_password',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'user.form.field.roles',
                'required' => false,
                'multiple' => true,
                'choices' => array_flip(UserStaticStorage::getUserRolesChoices()),
                'choice_translation_domain' => 'admin',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('fullName', TextType::class, [
                'label' => 'user.form.field.full_name',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Length(max: 255),
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => 'user.form.field.phone',
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
                'label' => 'user.form.field.address',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Length(max: 180),
                ],
            ])
            ->add('zipCode', TextType::class, [
                'label' => 'user.form.field.zip_code',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('isDeleted', CheckboxType::class, [
                'label' => 'user.form.field.is_deleted',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'label_attr' => [
                    'class' => 'form-check-label',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'action.save_changes',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EditUserModel::class,
            'user_repository' => null,
            'translation_domain' => 'admin',
        ]);
    }
}
