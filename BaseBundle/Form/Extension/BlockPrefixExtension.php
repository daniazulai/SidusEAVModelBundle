<?php
/*
 * This file is part of the Sidus/BaseBundle package.
 *
 * Copyright (c) 2015-2021 Vincent Chalnot
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sidus\EAVModelBundle\BaseBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function in_array;

/**
 * Adds a custom block_prefix option to adds a block prefix to the automatically computed ones
 *
 * @author Vincent Chalnot <vincent@sidus.fr>
 */
class BlockPrefixExtension extends AbstractTypeExtension
{
    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'block_prefix' => null,
            ]
        );
    }

    /**
     * @param FormView      $view
     * @param FormInterface $form
     * @param array         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if (null !== $options['block_prefix']
            && !in_array($options['block_prefix'], $view->vars['block_prefixes'], true)
        ) {
            array_splice(
                $view->vars['block_prefixes'],
                -1,
                0,
                [$options['block_prefix']]
            );
        }
    }

    /**
     * @return string
     */
    public function getExtendedType()
    {
        return FormType::class;
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
