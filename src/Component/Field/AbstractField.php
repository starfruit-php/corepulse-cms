<?php

namespace CorepulseBundle\Component\Field;

use CorepulseBundle\Component\Field\FieldInterface;
use Pimcore\Model\DataObject\Data\BlockElement;

abstract class AbstractField implements FieldInterface
{
    protected $objectOrDocument;
    protected $layout;
    protected $dataValue;
    protected $localized;

    protected $isObject;

    protected $isDocumentBlock;

    public function __construct($objectOrDocument, $layout = null, $dataValue = null, $localized = null, $isObject = true, $isDocumentBlock = false)
    {
        $this->setLayout($layout);
        $this->setObjectOrDocument($objectOrDocument);
        $this->setDataValue($dataValue);
        $this->setLocalized($localized);
        $this->setIsObject($isObject);
        $this->setIsDocumentBlock($isDocumentBlock);
    }

    // Getter and Setter methods
    public function getObjectOrDocument()
    {
        return $this->objectOrDocument;
    }

    public function setObjectOrDocument($objectOrDocument)
    {
        $this->objectOrDocument = $objectOrDocument;
    }

    public function getIsObject()
    {
        return $this->isObject;
    }

    public function setIsObject($isObject)
    {
        $this->isObject = $isObject;
    }

    public function getIsDocumentBlock()
    {
        return $this->isDocumentBlock;
    }

    public function setIsDocumentBlock($isDocumentBlock)
    {
        $this->isDocumentBlock = $isDocumentBlock;
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
        return $this->getLayout()?->name ?? $this->getObjectOrDocument()?->getName();
    }

    public function getTitle()
    {
        return $this->getLayout()?->title ?? $this->getObjectOrDocument()?->getTitle();
    }

    public function getInvisible()
    {
        return $this->getLayout()?->invisible;
    }

    public function getDataSave()
    {
        if (!$this->getIsObject()) {
            return $this->formatDocumentSave($this->getDataValue());
        }

        return $this->formatDataSave($this->getDataValue());
    }

    public function getValue()
    {
        if (!$this->getIsObject()) {
            return $this->formatDocument($this->getLayout());
        }
    
        if ($this->getObjectOrDocument() instanceof BlockElement) {
            return $this->formatBlock($this->getObjectOrDocument()->getData());
        }
    
        $method = 'get' . ucfirst($this->getName());
        $value = $this->getLocalized() ? $this->getObjectOrDocument()->$method($this->getLocalized()) : $this->getObjectOrDocument()->$method();
    
        return $this->format($value);
    }

    // override method
    abstract public function formatDocumentSave($value);

    abstract public function formatDataSave($value);

    abstract public function format($value);

    abstract public function formatDocument($value);

    abstract public function formatBlock($value);

    abstract public function getFrontEndType();
}