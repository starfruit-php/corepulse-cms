<?php

namespace CorepulseBundle\Component\Field;

class AdvancedManyToManyRelation extends ManyToManyRelation
{
    public function format($value)
    {
        if (!empty($value)) {
            $result = [];
            /** @var DataObject\Data\ObjectMetadata $elementMetadata */
            foreach ($value as $elementMetadata) {
                $element = $elementMetadata->getElement();

                $result[] = [
                    'element' => $this->getElementType($element),
                    'fieldname' => $elementMetadata->getFieldname(),
                    'columns' => $elementMetadata->getColumns(),
                    'data' => $elementMetadata->getData(), ];
            }
            return $result;
        }

        return null;
    }
}
