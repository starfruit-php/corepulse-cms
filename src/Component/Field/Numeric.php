<?php

namespace CorepulseBundle\Component\Field;

class Numeric extends Input
{
    public function getFrontEndType()
    {
        return 'number';
    }
}
