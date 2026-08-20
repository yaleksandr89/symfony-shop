<?php

declare(strict_types=1);

namespace App\AdminBundle\Form\FilterType;

use App\AdminBundle\DTO\ProductFilterModel;
use App\AdminBundle\Filter\ExclusiveDateRangeFilter;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use DateTimeImmutable;
use Spiriit\Bundle\FormFilterBundle\Filter\FilterOperands;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type\BooleanFilterType;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type\DateRangeFilterType;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type\EntityFilterType;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type\NumberFilterType;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type\NumberRangeFilterType;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type\TextFilterType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ProductFilterFormType extends AbstractType
{
    public function __construct(private ExclusiveDateRangeFilter $exclusiveDateRangeFilter)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', NumberFilterType::class, [
                'label' => 'product.filter.field.id',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('category', EntityFilterType::class, [
                'label' => 'product.filter.field.category',
                'class' => Category::class,
                'query_builder' => function ($category) {
                    /** @var CategoryRepository $categoryRep */
                    $categoryRep = $category;

                    return $categoryRep->forFormQueryBuilderFindActiveCategory();
                },
                'choice_label' => function ($category) {
                    return sprintf('#%s %s', $category->getId(), $category->getTitle());
                },
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('title', TextFilterType::class, [
                'label' => 'product.filter.field.title',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('price', NumberRangeFilterType::class, [
                'label' => 'product.filter.field.price',
                'left_number_options' => [
                    'label' => 'filter.range.from',
                    'condition_operator' => FilterOperands::OPERATOR_GREATER_THAN_EQUAL,
                    'attr' => [
                        'class' => 'form-control',
                    ],
                ],
                'right_number_options' => [
                    'label' => 'filter.range.to',
                    'condition_operator' => FilterOperands::OPERATOR_LOWER_THAN_EQUAL,
                    'attr' => [
                        'class' => 'form-control',
                    ],
                ],
            ])
            ->add('quantity', NumberRangeFilterType::class, [
                'label' => 'product.filter.field.quantity',
                'left_number_options' => [
                    'label' => 'filter.range.from',
                    'condition_operator' => FilterOperands::OPERATOR_GREATER_THAN_EQUAL,
                    'attr' => [
                        'class' => 'form-control',
                    ],
                ],
                'right_number_options' => [
                    'label' => 'filter.range.to',
                    'condition_operator' => FilterOperands::OPERATOR_LOWER_THAN_EQUAL,
                    'attr' => [
                        'class' => 'form-control',
                    ],
                ],
            ])
            ->add('createdAt', DateRangeFilterType::class, [
                'label' => 'product.filter.field.created_at',
                'error_bubbling' => false,
                'left_date_options' => [
                    'label' => 'filter.range.from',
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'attr' => [
                        'class' => 'form-control',
                    ],
                ],
                'right_date_options' => [
                    'label' => 'filter.range.to',
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'attr' => [
                        'class' => 'form-control',
                    ],
                ],
                'constraints' => [
                    new Callback(
                        static function (mixed $range, ExecutionContextInterface $context): void {
                            if (!is_array($range)) {
                                return;
                            }

                            $leftDate = $range['left_date'] ?? null;
                            $rightDate = $range['right_date'] ?? null;

                            if ($leftDate instanceof DateTimeImmutable
                                && $rightDate instanceof DateTimeImmutable
                                && $leftDate > $rightDate
                            ) {
                                $context->buildViolation('admin.filter.date_range.invalid_order')
                                    ->setTranslationDomain('validators')
                                    ->addViolation();
                            }
                        },
                        groups: ['filtering'],
                    ),
                ],
                'apply_filter' => $this->exclusiveDateRangeFilter,
            ])
            ->add('isPublished', BooleanFilterType::class, [
                'label' => 'product.filter.field.is_published',
                'translation_domain' => 'admin',
                'choice_translation_domain' => 'SpiriitFormFilterBundle',
                'placeholder' => new TranslatableMessage('boolean.yes_or_no', [], 'SpiriitFormFilterBundle'),
                'attr' => [
                    'class' => 'form-control',
                ],
            ]);
    }

    public function getBlockPrefix(): string
    {
        return 'order_filter_form';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductFilterModel::class,
            'method' => 'GET',
            'translation_domain' => 'admin',
            'validation_groups' => ['filtering'],
        ]);
    }
}
