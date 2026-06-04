<?php

namespace MyVars\FormFlow\InlineEdit;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Generic form type for inline editing of a single field.
 *
 * Usage:
 *   $this->createForm(InlineFieldType::class, new InlineFieldForm($value), [
 *       'field_type' => TextType::class,
 *       'constraints' => [new NotBlank(), new Length(max: 255)],
 *       'field_attr' => ['placeholder' => 'Enter name...'],
 *   ]);
 *
 * @extends AbstractType<InlineFieldForm>
 */
final class InlineFieldType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('value', $options['field_type'], [
            'label' => false,
            'required' => false, // Disable HTML5 validation; server-side constraints handle it
            'constraints' => $options['value_constraints'],
            'empty_data' => '',
            'attr' => array_merge([
                'placeholder' => $options['placeholder'],
                'autocomplete' => 'off',
            ], $options['field_attr']),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InlineFieldForm::class,
            'field_type' => TextType::class,
            'value_constraints' => [],
            'field_attr' => [],
            'placeholder' => '',
        ]);

        $resolver->setAllowedTypes('value_constraints', 'array');
        $resolver->setAllowedTypes('field_attr', 'array');
        $resolver->setAllowedTypes('placeholder', 'string');
    }
}
