<?php
/*
 * This file is part of the Sidus/BaseBundle package.
 *
 * Copyright (c) 2015-2021 Vincent Chalnot
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sidus\EAVModelBundle\Base\Form\Extension;

use InvalidArgumentException;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

/**
 * Allow multiple choices to handle iterators
 *
 * @author Vincent Chalnot <vincent@sidus.fr>
 */
class ChoiceTypeExtension extends AbstractTypeExtension
{
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     *
     * @throws InvalidArgumentException
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(
            new CallbackTransformer(
                static function ($toView) {
                    if ($toView instanceof Traversable) {
                        return iterator_to_array($toView);
                    }

                    return $toView;
                },
                static function ($toModel) {
                    return $toModel;
                }
            )
        );
    }

    /**
     * Returns the name of the type being extended.
     *
     * @return string The name of the type being extended
     */
    public function getExtendedType()
    {
        return ChoiceType::class;
    }

    public static function getExtendedTypes(): iterable
    {
        return [ChoiceType::class];
    }

}
