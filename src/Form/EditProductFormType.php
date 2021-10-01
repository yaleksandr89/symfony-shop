<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class EditProductFormType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'required' => true,
                'trim' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank([], 'Should be filled')
                ],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Price',
                'required' => true,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'step' => '0.01'
                ],
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantity',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'style' => 'overflow: hidden',
                ],
            ])
            ->add('newImage', FileType::class, [
                'label' => 'Choose new image',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control-file'
                ],
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'Is Published',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'label_attr' => [
                    'class' => 'form-check-label',
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

    /**
     * @param OptionsResolver $resolver
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
