<?php

declare(strict_types=1);

namespace App\Form\Admin\FilterType;

use App\Entity\StaticStorage\OrderStaticStorage;
use App\Entity\User;
use App\Form\DTO\OrderFilterModel;
use App\Form\Filter\ExclusiveDateRangeFilter;
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
                'label' => 'Id',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('owner', EntityFilterType::class, [
                'label' => 'Owner',
                'class' => User::class,
                'choice_label' => function ($user) {
                    return sprintf('#%s %s', $user->getId(), $user->getEmail());
                },
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('status', ChoiceFilterType::class, [
                'label' => 'Status',
                'choices' => array_flip(OrderStaticStorage::getOrderStatusChoices()),
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('totalPrice', NumberRangeFilterType::class, [
                'label' => 'Total price',
                'left_number_options' => [
                    'label' => 'From',
                    'condition_operator' => FilterOperands::OPERATOR_GREATER_THAN_EQUAL,
                    'attr' => [
                        'class' => 'form-control',
                    ],
                ],
                'right_number_options' => [
                    'label' => 'To',
                    'condition_operator' => FilterOperands::OPERATOR_LOWER_THAN_EQUAL,
                    'attr' => [
                        'class' => 'form-control',
                    ],
                ],
            ])
            ->add('createdAt', DateRangeFilterType::class, [
                'label' => 'Created at',
                'error_bubbling' => false,
                'left_date_options' => [
                    'label' => 'From',
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'attr' => [
                        'class' => 'form-control',
                    ],
                ],
                'right_date_options' => [
                    'label' => 'To',
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
                                $context->buildViolation('Дата «От» не может быть позднее даты «До».')
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
            'validation_groups' => ['filtering'],
        ]);
    }
}
