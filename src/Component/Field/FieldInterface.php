<?php

namespace CorepulseBundle\Component\Field;

interface FieldInterface
{
    public function getName();

    public function getTitle();

    public function getValue();

    public function getDataSave();

    public function getFrontEndType();
}
