<?php
/*
 * This file is part of the Sidus/EAVModelBundle package.
 *
 * Copyright (c) 2015-2019 Vincent Chalnot
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sidus\EAVModelBundle\Registry;

use Sidus\EAVModelBundle\Utilities\DebugInfoUtility;
use Sidus\EAVModelBundle\Exception\AttributeConfigurationException;
use Sidus\EAVModelBundle\Model\AttributeInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\VarDumper\Caster\Caster;
use UnexpectedValueException;
use function in_array;

/**
 * Container for all global attributes.
 *
 * @author   Vincent Chalnot <vincent@sidus.fr>
 *
 * @internal Don't use this service to fetch attributes, use the families instead
 */
class AttributeRegistry
{
    /** @var string */
    protected $attributeClass;

    /** @var array */
    protected $globalContextMask;

    /** @var AttributeTypeRegistry */
    protected $attributeTypeRegistry;

    /** @var TranslatorInterface */
    protected $translator;

    /** @var AttributeInterface[] */
    protected $attributes = [];

    /** @var array */
    protected static $reservedCodes = [
        'id',
        'identifier',
        'values',
        'value',
        'valueData',
        'valuesData',
        'refererValues',
        'createdAt',
        'updatedAt',
        'family',
        'familyCode',
        'currentContext',
        'empty',
    ];

    /**
     * @param string                $attributeClass
     * @param array                 $globalContextMask
     * @param AttributeTypeRegistry $attributeTypeRegistry
     * @param TranslatorInterface   $translator
     */
    public function __construct(
        $attributeClass,
        array $globalContextMask,
        AttributeTypeRegistry $attributeTypeRegistry,
        TranslatorInterface $translator
    ) {
        $this->attributeClass = $attributeClass;
        $this->globalContextMask = $globalContextMask;
        $this->attributeTypeRegistry = $attributeTypeRegistry;
        $this->translator = $translator;
    }

    /**
     * @param array $globalConfig
     *
     * @throws \UnexpectedValueException
     */
    public function parseGlobalConfig(array $globalConfig)
    {
        foreach ($globalConfig as $code => $configuration) {
            $attribute = $this->createAttribute($code, $configuration);
            $this->addAttribute($attribute);
        }
    }

    /**
     * @param string $code
     * @param array  $attributeConfiguration
     *
     * @throws \UnexpectedValueException
     *
     * @return AttributeInterface
     */
    public function createAttribute($code, array $attributeConfiguration = [])
    {
        $attributeClass = $this->attributeClass;
        /** @var AttributeInterface $attribute */
        $attribute = new $attributeClass(
            $code,
            $this->attributeTypeRegistry,
            $attributeConfiguration,
            $this->globalContextMask
        );
        if (method_exists($attribute, 'setTranslator')) {
            $attribute->setTranslator($this->translator);
        }

        return $attribute;
    }

    /**
     * @return AttributeInterface[]
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param string $code
     *
     * @throws UnexpectedValueException
     *
     * @return AttributeInterface
     */
    public function getAttribute($code)
    {
        if (!$this->hasAttribute($code)) {
            throw new UnexpectedValueException("No attribute with code : {$code}");
        }

        return $this->attributes[$code];
    }

    /**
     * @param string $code
     *
     * @return bool
     */
    public function hasAttribute($code)
    {
        return array_key_exists($code, $this->attributes);
    }

    /**
     * Custom debugInfo to prevent profiler from crashing
     *
     * @return array
     */
    public function __debugInfo()
    {
        return DebugInfoUtility::debugInfo(
            $this,
            [
                Caster::PREFIX_PROTECTED.'attributeTypeRegistry',
                Caster::PREFIX_PROTECTED.'translator',
            ]
        );
    }

    /**
     * @param AttributeInterface $attribute
     *
     * @throws \UnexpectedValueException
     */
    protected function addAttribute(AttributeInterface $attribute)
    {
        if (in_array($attribute->getCode(), static::$reservedCodes, true)) {
            throw new AttributeConfigurationException("Attribute code '{$attribute->getCode()}' is a reserved code");
        }
        $this->attributes[$attribute->getCode()] = $attribute;
    }
}
