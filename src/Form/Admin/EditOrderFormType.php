<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\StaticStorage\OrderStaticStorage;
use App\Entity\User;
use App\Form\DTO\EditOrderModel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class EditOrderFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', ChoiceType::class, [
                'label' => 'order.form.field.status',
                'required' => false,
                'choices' => array_flip(OrderStaticStorage::getOrderStatusChoices()),
                'choice_translation_domain' => 'messages',
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(message: 'order.validation.status.required'),
                ],
            ])
            ->add('owner', EntityType::class, [
                'label' => 'order.form.field.owner',
                'class' => User::class,
                'required' => false,
                'choice_label' => static function (User $user) {
                    return sprintf(
                        '#%s / %s / %s',
                        $user->getId(),
                        $user->getFullName(),
                        $user->getEmail(),
                    );
                },
                'choice_translation_domain' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(message: 'order.validation.owner.required'),
                ],
            ])
            ->add('isDeleted', CheckboxType::class, [
                'label' => 'order.form.field.is_deleted',
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
            'data_class' => EditOrderModel::class,
            'translation_domain' => 'admin',
        ]);
    }
}
