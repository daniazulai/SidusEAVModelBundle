<?php
/*
 * This file is part of the Sidus/EAVModelBundle package.
 *
 * Copyright (c) 2015-2019 Vincent Chalnot
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sidus\EAVModelBundle\Model;

use Sidus\EAVModelBundle\Utilities\DebugInfoUtility;
use Sidus\EAVModelBundle\Utilities\SleepUtility;
use Sidus\EAVModelBundle\Entity\ContextualDataInterface;
use Sidus\EAVModelBundle\Exception\MissingAttributeException;
use Sidus\EAVModelBundle\Registry\AttributeRegistry;
use Sidus\EAVModelBundle\Registry\FamilyRegistry;
use Sidus\EAVModelBundle\Context\ContextManagerInterface;
use Sidus\EAVModelBundle\Entity\ContextualValueInterface;
use Sidus\EAVModelBundle\Entity\DataInterface;
use Sidus\EAVModelBundle\Entity\ValueInterface;
use Sidus\EAVModelBundle\Exception\MissingFamilyException;
use Sidus\EAVModelBundle\Translator\TranslatableTrait;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\VarDumper\Caster\Caster;
use UnexpectedValueException;

/**
 * Defines the model of a data, think of it as the data type
 *
 * @author Vincent Chalnot <vincent@sidus.fr>
 */
class Family implements FamilyInterface
{
    use TranslatableTrait;

    /** @var FamilyRegistry */
    protected $familyRegistry;

    /** @var string */
    protected $code;

    /** @var string */
    protected $type;

    /** @var string */
    protected $label;

    /** @var array|AttributeInterface[] */
    protected $attributeAsLabel = [];

    /** @var AttributeInterface */
    protected $attributeAsIdentifier;

    /** @var AttributeInterface[] */
    protected $attributes = [];

    /** @var Family */
    protected $parent;

    /** @var bool */
    protected $instantiable = true;

    /** @var bool */
    protected $singleton = false;

    /** @var array */
    protected $options = [];

    /** @var array */
    protected $formOptions = [];

    /** @var Family[] */
    protected $children;

    /** @var string */
    protected $dataClass;

    /** @var string */
    protected $valueClass;

    /** @var ContextManagerInterface */
    protected $contextManager;

    /*
     * Both following properties are used just before serializing a family in order to store dynamic data that would
     * otherwise be lost during serialization
     */
    /** @var array */
    protected $fallbackContext;

    /** @var string */
    protected $fallbackLabel;

    /**
     * @param string                  $code
     * @param AttributeRegistry       $attributeRegistry
     * @param FamilyRegistry          $familyRegistry
     * @param ContextManagerInterface $contextManager
     * @param array                   $config
     *
     * @throws \Sidus\EAVModelBundle\Exception\ContextException
     * @throws UnexpectedValueException
     * @throws MissingFamilyException
     * @throws AccessException
     * @throws InvalidArgumentException
     * @throws UnexpectedTypeException
     * @throws MissingAttributeException
     * @throws \TypeError
     */
    public function __construct(
        $code,
        /** @noinspection PhpInternalEntityUsedInspection */
        AttributeRegistry $attributeRegistry,
        FamilyRegistry $familyRegistry,
        ContextManagerInterface $contextManager,
        array $config = null
    ) {
        $this->code = $code;
        $this->familyRegistry = $familyRegistry;
        $this->contextManager = $contextManager;

        if (!empty($config['parent'])) {
            $this->parent = $familyRegistry->getFamily($config['parent']);
            $this->copyFromFamily($this->parent);
        }
        unset($config['parent']);

        $this->buildAttributes($attributeRegistry, $config);
        unset($config['attributes']);

        if (count($config['attributeAsLabel'])) {
            $labelCode = $config['attributeAsLabel'];

            foreach ($labelCode as $labelCodeValue) {
                if (!$this->hasAttribute($labelCodeValue)) {
                    $message = "Bad configuration for family {$code}: attribute as label '{$labelCodeValue}'";
                    $message .= " doesn't exists for this family";
                    throw new UnexpectedValueException($message);
                }
                $this->attributeAsLabel[] = $this->getAttribute($labelCodeValue);
            }
        }
        unset($config['attributeAsLabel']);

        if (!empty($config['attributeAsIdentifier'])) {
            $labelCode = $config['attributeAsIdentifier'];
            $commonMessage = "Bad configuration for family {$code}: attribute as identifier '{$labelCode}'";
            if (!$this->hasAttribute($labelCode)) {
                throw new UnexpectedValueException("{$commonMessage} doesn't exists for this family");
            }
            $this->attributeAsIdentifier = $this->getAttribute($labelCode);
            if (!$this->attributeAsIdentifier->isUnique()) {
                throw new UnexpectedValueException("{$commonMessage} should be unique");
            }
            if (!$this->attributeAsIdentifier->isRequired()) {
                throw new UnexpectedValueException("{$commonMessage} should be required");
            }
            if ($this->attributeAsIdentifier->isCollection()) {
                throw new UnexpectedValueException("{$commonMessage} should NOT be a collection");
            }
            if (0 !== \count($this->attributeAsIdentifier->getContextMask())) {
                throw new UnexpectedValueException("{$commonMessage} should NOT be contextualized");
            }
        }
        unset($config['attributeAsIdentifier']);

        $accessor = PropertyAccess::createPropertyAccessor();
        foreach ($config as $key => $value) {
            $accessor->setValue($this, $key, $value);
        }

        /** @var ContextualValueInterface $valueClass */
        $valueClass = $this->getValueClass();
        if (is_a($valueClass, ContextualValueInterface::class, true)) {
            $valueClass::checkContext($this->contextManager->getDefaultContext());
        }
    }

    /**
     * @return array|AttributeInterface[]
     */
    public function getAttributeAsLabel(): array
    {
        return $this->attributeAsLabel;
    }

    /**
     * @param array|AttributeInterface[] $attributes
     */
    public function setAttributeAsLabel(array $attributes)
    {
        $this->attributeAsLabel = $attributes;
    }

    /**
     * @return AttributeInterface|null
     */
    public function getAttributeAsIdentifier()
    {
        return $this->attributeAsIdentifier;
    }

    /**
     * @param AttributeInterface $attributeAsIdentifier
     */
    public function setAttributeAsIdentifier(AttributeInterface $attributeAsIdentifier)
    {
        $this->attributeAsIdentifier = $attributeAsIdentifier;
    }

    /**
     * @return AttributeInterface[]
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param AttributeInterface $attribute
     */
    public function addAttribute(AttributeInterface $attribute)
    {
        $attribute->setFamily($this);
        $this->attributes[$attribute->getCode()] = $attribute;
    }

    /**
     * @return Family
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param Family $parent
     */
    public function setParent(Family $parent = null)
    {
        $this->parent = $parent;
    }

    /**
     * @return Family[]
     */
    public function getChildren()
    {
        if (null === $this->children) {
            $this->children = $this->familyRegistry->getByParent($this);
        }

        return $this->children;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param string $code
     *
     * @throws MissingAttributeException
     *
     * @return AttributeInterface
     */
    public function getAttribute($code)
    {
        if (!$this->hasAttribute($code)) {
            throw new MissingAttributeException("Unknown attribute {$code} in family {$this->code}");
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
        return !empty($this->attributes[$code]);
    }

    /**
     * @return bool
     */
    public function isInstantiable()
    {
        return $this->instantiable;
    }

    /**
     * @param boolean $instantiable
     */
    public function setInstantiable($instantiable)
    {
        $this->instantiable = $instantiable;
    }

    /**
     * @return boolean
     */
    public function isSingleton()
    {
        return $this->singleton;
    }

    /**
     * @param boolean $singleton
     */
    public function setSingleton($singleton)
    {
        $this->singleton = $singleton;
    }

    /**
     * Will check the translator for the key "eav.family.{$code}.label"
     * and humanize the code if no translation is found
     *
     * @return string
     */
    public function getLabel()
    {
        if ($this->label) {
            return $this->label;
        }
        if (null === $this->translator) {
            return $this->fallbackLabel;
        }

        return $this->tryTranslate("eav.family.{$this->getCode()}.label", [], $this->getCode());
    }

    /**
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getLabel();
    }

    /**
     * Return current family code and all it's sub-families codes
     *
     * @return array
     */
    public function getMatchingCodes()
    {
        $codes = [$this->getCode()];
        foreach ($this->getChildren() as $child) {
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $codes = array_merge($codes, $child->getMatchingCodes());
        }

        return $codes;
    }

    /**
     * @return string
     */
    public function getValueClass()
    {
        return $this->valueClass;
    }

    /**
     * @param string $valueClass
     */
    public function setValueClass($valueClass)
    {
        $this->valueClass = $valueClass;
    }

    /**
     * @return string
     */
    public function getDataClass()
    {
        return $this->dataClass;
    }

    /**
     * @param string $dataClass
     */
    public function setDataClass($dataClass)
    {
        $this->dataClass = $dataClass;
    }

    /**
     * @param DataInterface      $data
     * @param AttributeInterface $attribute
     * @param array              $context
     *
     * @throws UnexpectedValueException
     *
     * @return ValueInterface
     */
    public function createValue(DataInterface $data, AttributeInterface $attribute, array $context = null)
    {
        $valueClass = $this->getValueClass();
        /** @var ValueInterface $value */
        $value = new $valueClass($data, $attribute);
        $data->addValue($value);

        if ($value instanceof ContextualValueInterface
            && $data instanceof ContextualDataInterface
            && \count($attribute->getContextMask())
        ) {
            if ($context) {
                $context = array_merge($data->getCurrentContext(), $context);
            } else {
                $context = $data->getCurrentContext();
            }
            foreach ($attribute->getContextMask() as $key) {
                $value->setContextValue($key, $context[$key]);
            }
        }

        return $value;
    }

    /**
     * @throws \LogicException
     *
     * @return DataInterface
     */
    public function createData()
    {
        if (!$this->isInstantiable()) {
            throw new \LogicException("Family {$this->getCode()} is not instantiable");
        }
        if ($this->isSingleton()) {
            throw new \LogicException(
                "Family {$this->getCode()} is a singleton, use the repository to retrieve the instance"
            );
        }
        $dataClass = $this->getDataClass();

        return new $dataClass($this);
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return array
     */
    public function getContext()
    {
        if (null === $this->contextManager) {
            return $this->fallbackContext;
        }

        return $this->contextManager->getContext();
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param string $code
     * @param mixed  $fallback
     *
     * @return mixed
     */
    public function getOption($code, $fallback = null)
    {
        if (!array_key_exists($code, $this->options)) {
            return $fallback;
        }

        return $this->options[$code];
    }

    /**
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * @return array
     */
    public function getFormOptions()
    {
        return $this->formOptions;
    }

    /**
     * @param array $formOptions
     */
    public function setFormOptions(array $formOptions)
    {
        $this->formOptions = $formOptions;
    }

    /**
     * Remove service references before serializing
     *
     * @throws \ReflectionException
     *
     * @return array
     */
    public function __sleep()
    {
        if ($this->contextManager) {
            $this->fallbackContext = $this->contextManager->getContext();
        }
        if ($this->translator) {
            $this->fallbackLabel = $this->getLabel();
        }
        $this->getChildren(); // Trigger the storage of children families before discarding the familyRegistry

        return SleepUtility::sleep(__CLASS__, ['translator', 'familyRegistry', 'contextManager']);
    }

    /**
     * Custom debug info
     *
     * @return array
     */
    public function __debugInfo()
    {
        return DebugInfoUtility::debugInfo(
            $this,
            [
                Caster::PREFIX_PROTECTED.'translator',
                Caster::PREFIX_PROTECTED.'familyRegistry',
                Caster::PREFIX_PROTECTED.'contextManager',
                Caster::PREFIX_PROTECTED.'fallbackContext',
                Caster::PREFIX_PROTECTED.'fallbackLabel',
            ]
        );
    }

    /**
     * @param FamilyInterface $parent
     *
     * @throws \UnexpectedValueException
     */
    protected function copyFromFamily(FamilyInterface $parent)
    {
        foreach ($parent->getAttributes() as $attribute) {
            $this->addAttribute(clone $attribute);
        }
        if ($parent->getAttributeAsLabel()) {
            $this->attributeAsLabel = $this->getAttribute($parent->getAttributeAsLabel()->getCode());
        }
        if ($parent->getAttributeAsIdentifier()) {
            $this->attributeAsIdentifier = $this->getAttribute($parent->getAttributeAsIdentifier()->getCode());
        }
        $this->valueClass = $parent->getValueClass();
        $this->dataClass = $parent->getDataClass();
    }

    /**
     * @param AttributeRegistry $attributeRegistry
     * @param array             $config
     *
     * @throws \UnexpectedValueException
     */
    protected function buildAttributes(AttributeRegistry $attributeRegistry, array $config)
    {
        foreach ((array) $config['attributes'] as $attributeCode => $attributeConfig) {
            if ($attributeRegistry->hasAttribute($attributeCode)) {
                // If attribute already exists, merge family config into clone
                $attribute = clone $attributeRegistry->getAttribute($attributeCode);
                if (null !== $attributeConfig) {
                    $attribute->mergeConfiguration($attributeConfig);
                }
            } else {
                // If attribute doesn't exist, create it locally
                $attribute = $attributeRegistry->createAttribute($attributeCode, $attributeConfig);
            }
            $this->addAttribute($attribute);
        }
    }
}
