<?php
/*
 * This file is part of the Sidus/EAVModelBundle package.
 *
 * Copyright (c) 2015-2019 Vincent Chalnot
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sidus\EAVModelBundle\Form\Type;

use Sidus\EAVModelBundle\Doctrine\RepositoryFinder;
use Sidus\EAVModelBundle\Entity\DataRepository;
use Sidus\EAVModelBundle\Form\AttributeFormBuilderInterface;
use Sidus\EAVModelBundle\Model\AttributeInterface;
use Sidus\EAVModelBundle\Registry\FamilyRegistry;
use Sidus\EAVModelBundle\Entity\DataInterface;
use Sidus\EAVModelBundle\Exception\MissingFamilyException;
use Sidus\EAVModelBundle\Model\FamilyInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\Exception\AccessException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Base form used for data edition
 *
 * @author Vincent Chalnot <vincent@sidus.fr>
 */
class DataType extends AbstractType
{
    /** @var AttributeFormBuilderInterface */
    protected $attributeFormBuilder;

    /** @var FamilyRegistry */
    protected $familyRegistry;

    /** @var RepositoryFinder */
    protected $repositoryFinder;

    /**
     * @param AttributeFormBuilderInterface $attributeFormBuilder
     * @param FamilyRegistry                $familyRegistry
     * @param RepositoryFinder              $repositoryFinder
     */
    public function __construct(
        AttributeFormBuilderInterface $attributeFormBuilder,
        FamilyRegistry $familyRegistry,
        RepositoryFinder $repositoryFinder
    ) {
        $this->attributeFormBuilder = $attributeFormBuilder;
        $this->familyRegistry = $familyRegistry;
        $this->repositoryFinder = $repositoryFinder;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     *
     * @throws \Exception
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->buildValuesForm($builder, $options);
        $this->buildDataForm($builder, $options);

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                $data = $event->getData();
                if ($data instanceof DataInterface) {
                    $data->setUpdatedAt(new \DateTime());
                }
            }
        );
    }

    /**
     * For additional fields in data form that are not linked to EAV model
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildDataForm(
        FormBuilderInterface $builder,
        array $options = []
    ) {
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     *
     * @throws \Exception
     */
    public function buildValuesForm(FormBuilderInterface $builder, array $options = [])
    {
        /** @var FamilyInterface $family */
        $family = $options['family'];

        $attributes = $family->getAttributes();
        if ($options['attributes_config']) {
            if (!$options['merge_attributes_config']) {
                $attributes = [];
            }
            foreach (array_keys($options['attributes_config']) as $attributeCode) {
                $attributes[$attributeCode] = $family->getAttribute($attributeCode);
            }
        }

        foreach ($attributes as $attribute) {
            $this->attributeFormBuilder->addAttribute(
                $builder,
                $attribute,
                $this->resolveAttributeConfig($attribute, $options)
            );
        }
    }

    /**
     * @param OptionsResolver $resolver
     *
     * @throws AccessException
     * @throws UndefinedOptionsException
     * @throws MissingFamilyException
     * @throws \UnexpectedValueException
     * @throws \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'empty_data' => null,
                'data_class' => null,
                'attributes_config' => null,
                'merge_attributes_config' => false,
                'attribute' => null,
                'family' => null,
                'fieldset_options' => [],
            ]
        );
        $resolver->setAllowedTypes('attributes_config', ['null', 'array']);
        $resolver->setAllowedTypes('merge_attributes_config', ['bool']);
        $resolver->setAllowedTypes('attribute', ['null', AttributeInterface::class]);
        $resolver->setAllowedTypes('family', ['null', 'string', FamilyInterface::class]);
        $resolver->setAllowedTypes('fieldset_options', ['array']);

        $resolver->setNormalizer(
            'family',
            function (Options $options, $value) {
                // If family option is not set, try to fetch the family from the attribute option
                if (null === $value) {
                    /** @var AttributeInterface $attribute */
                    $attribute = $options['attribute'];
                    if (!$attribute) {
                        throw new MissingOptionsException(
                            "An option is missing: you must set either the 'family' option or the 'attribute' option"
                        );
                    }
                    $allowedFamilies = $attribute->getOption('allowed_families', []);
                    if (1 !== \count($allowedFamilies)) {
                        $m = "Can't automatically compute the 'family' option with an attribute with no family allowed";
                        $m .= " or multiple allowed families, please set the 'family' option manually";
                        throw new MissingOptionsException($m);
                    }

                    $value = reset($allowedFamilies);
                }

                if ($value instanceof FamilyInterface) {
                    return $value;
                }

                return $this->familyRegistry->getFamily($value);
            }
        );
        $resolver->setNormalizer(
            'empty_data',
            function (Options $options, $value) {
                if (null !== $value) {
                    return $value;
                }
                /** @var FamilyInterface $family */
                $family = $options['family'];

                if ($family->isSingleton()) {
                    /** @var DataRepository $repository */
                    $repository = $this->repositoryFinder->getRepository($family->getDataClass());

                    return $repository->getInstance($family);
                }

                return $family->createData();
            }
        );
        $resolver->setNormalizer(
            'data_class',
            function (Options $options, $value) {
                if (null !== $value) {
                    $m = "DataType form does not supports the 'data_class' option, it will be automatically resolved";
                    $m .= ' with the family';
                    throw new \UnexpectedValueException($m);
                }
                /** @var FamilyInterface $family */
                $family = $options['family'];

                return $family->getDataClass();
            }
        );
    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'sidus_data';
    }

    /**
     * @param AttributeInterface $attribute
     * @param array              $options
     *
     * @return array
     */
    protected function resolveAttributeConfig(AttributeInterface $attribute, array $options)
    {
        $attributeConfig = [];
        if (array_key_exists('fieldset_options', $options)) {
            $attributeConfig['fieldset_options'] = $options['fieldset_options']; // Copy all fieldset options
        }
        if (isset($options['attributes_config'][$attribute->getCode()])) {
            return array_merge($attributeConfig, $options['attributes_config'][$attribute->getCode()]);
        }

        return array_merge($attributeConfig, $attribute->getOption('attribute_config', []));
    }
}
