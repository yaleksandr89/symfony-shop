<?php

declare(strict_types=1);

namespace App\AdminBundle\Form\FilterType;

use App\AdminBundle\DTO\OrderFilterModel;
use App\AdminBundle\Filter\ExclusiveDateRangeFilter;
use App\Entity\StaticStorage\OrderStaticStorage;
use App\Entity\User;
use DateTimeImmutable;
use Spiriit\Bundle\FormFilterBundle\Filter\FilterOperands;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type\ChoiceFilterType;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type\DateRangeFilterType;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type\EntityFilterType;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type\NumberFilterType;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type\NumberRangeFilterType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class OrderFilterFormType extends AbstractType
{
    public function __construct(private ExclusiveDateRangeFilter $exclusiveDateRangeFilter)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', NumberFilterType::class, [
                'label' => 'order.filter.field.id',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('owner', EntityFilterType::class, [
                'label' => 'order.filter.field.owner',
                'class' => User::class,
                'choice_label' => function ($user) {
                    return sprintf('#%s %s', $user->getId(), $user->getEmail());
                },
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('status', ChoiceFilterType::class, [
                'label' => 'order.filter.field.status',
                'choices' => array_flip(OrderStaticStorage::getOrderStatusChoices()),
                'choice_translation_domain' => 'messages',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('totalPrice', NumberRangeFilterType::class, [
                'label' => 'order.filter.field.total_price',
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
                'label' => 'order.filter.field.created_at',
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
            ]);
    }

    public function getBlockPrefix(): string
    {
        return 'order_filter_form';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OrderFilterModel::class,
            'method' => 'GET',
            'translation_domain' => 'admin',
            'validation_groups' => ['filtering'],
        ]);
    }
}
