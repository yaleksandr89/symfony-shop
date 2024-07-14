<?php

declare(strict_types=1);

namespace App\Form\Front;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileEditFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'personal_account.edit.labels.full_name',
                'trim' => true,
            ])
            ->add('phone', TextType::class, [
                'label' => 'personal_account.edit.labels.phone',
                'trim' => true,
                'required' => false,
            ])
            ->add('address', TextType::class, [
                'label' => 'personal_account.edit.labels.address',
                'trim' => true,
            ])
            ->add('zipCode', IntegerType::class, [
                'label' => 'personal_account.edit.labels.zipcode',
                'required' => false,
                'trim' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
