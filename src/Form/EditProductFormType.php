<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class EditProductFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title (form class)',
                'required' => true,
                'trim' => true,
            ])
            ->add('price', NumberType::class, [
                'label' => 'Price (form class)',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'step' => '0.01'
                ]
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantity (form class)',
            ])
            ->add('description')
            ->add('isPublished')
            ->add('isDeleted')
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
