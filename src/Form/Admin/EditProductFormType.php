<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Category;
use App\Form\DTO\EditProductModel;
use App\Form\Validator\GreaterThanOrEqualPrice;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class EditProductFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'product.form.field.title',
                'required' => true,
                'trim' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(message: 'product.validation.title.required'),
                ],
            ])
            ->add('price', NumberType::class, [
                'label' => 'product.form.field.price',
                'required' => true,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                ],
                'constraints' => [
                    new NotBlank(message: 'product.validation.price.required'),
                    new GreaterThanOrEqualPrice(),
                ],
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'product.form.field.quantity',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                ],
                'constraints' => [
                    new NotBlank(message: 'product.validation.quantity.required'),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'product.form.field.description',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'style' => 'overflow: hidden',
                ],
            ])
            ->add('newImage', FileType::class, [
                'label' => 'product.form.field.new_image',
                'required' => false,
                'attr' => [
                    'class' => 'form-control-file',
                ],
                'constraints' => [
                    new File(
                        maxSize: '10M',
                        mimeTypes: ['image/jpeg', 'image/png'],
                        mimeTypesMessage: 'product.validation.image.invalid_type'
                    ),
                ],
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'product.form.field.is_published',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'label_attr' => [
                    'class' => 'form-check-label',
                ],
            ])
            ->add('isNew', CheckboxType::class, [
                'label' => 'product.form.field.is_new',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'label_attr' => [
                    'class' => 'form-check-label',
                ],
            ])
            ->add('isOnSale', CheckboxType::class, [
                'label' => 'product.form.field.is_on_sale',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'label_attr' => [
                    'class' => 'form-check-label',
                ],
            ])
            ->add('category', EntityType::class, [
                'label' => 'product.form.field.category',
                'required' => true,
                'class' => Category::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er
                        ->createQueryBuilder('c')
                        ->where('c.isDeleted != true');
                },
                'choice_label' => 'title',
                'placeholder' => 'product.form.category_placeholder',
                'choice_translation_domain' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(message: 'product.validation.category.required'),
                ],
            ])
            ->add('isDeleted', CheckboxType::class, [
                'label' => 'product.form.field.is_deleted',
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
            'data_class' => EditProductModel::class,
            'translation_domain' => 'admin',
        ]);
    }
}
