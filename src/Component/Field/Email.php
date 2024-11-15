<?php

namespace CorepulseBundle\Component\Field;

class Email extends Input
{
    public function getFrontEndType()
    {
        return 'email';
    }
}
