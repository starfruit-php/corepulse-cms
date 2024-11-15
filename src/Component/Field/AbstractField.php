<?php

namespace CorepulseBundle\Component\Field;

use CorepulseBundle\Component\Field\FieldInterface;
use Pimcore\Model\DataObject\Data\BlockElement;

abstract class AbstractField implements FieldInterface
{
    protected $object;
    protected $layout;
    protected $dataValue;
    protected $localized;

    public function __construct($object, $layout = null, $dataValue = null, $localized = null)
    {
        $this->setLayout($layout);
        $this->setObject($object);
        $this->setDataValue($dataValue);
        $this->setLocalized($localized);
    }

    // Getter and Setter methods
    public function getObject()
    {
        return $this->object;
    }

    public function setObject($object)
    {
        $this->object = $object;
    }

    public function getLayout()
    {
        return $this->layout;
    }

    public function setLayout($layout)
    {
        if (is_array($layout)) {
            $layout = (object)$layout;
        }

        $this->layout = $layout;
    }

    public function getDataValue()
    {
        return $this->dataValue;
    }

    public function setDataValue($dataValue)
    {
        $this->dataValue = $dataValue;
    }

    public function getLocalized()
    {
        return $this->localized;
    }

    public function setLocalized($localized)
    {
        $this->localized = $localized;
    }

    //default method
    public function getName()
    {
        return $this->getLayout()?->name ?? $this->getObject()?->getName();
    }

    public function getTitle()
    {
        return $this->getLayout()?->title ?? $this->getObject()?->getTitle();
    }

    public function getInvisible()
    {
        return $this->getLayout()?->invisible;
    }

    public function getDataSave()
    {
        return $this->formatDataSave($this->getDataValue());
    }

    public function getValue()
    {
        if (!$this->layout) {
            return $this->formatDocument($this->getObject()->getData());
        }
    
        if ($this->getObject() instanceof BlockElement) {
            return $this->formatBlock($this->getObject()->getData());
        }
    
        $method = 'get' . ucfirst($this->getName());
        $value = $this->getLocalized() ? $this->getObject()->$method($this->getLocalized()) : $this->getObject()->$method();
    
        return $this->format($value);
    }

    // override method
    abstract public function formatDataSave($value);

    abstract public function format($value);

    abstract public function formatDocument($value);

    abstract public function formatBlock($value);

    abstract public function getFrontEndType();
}