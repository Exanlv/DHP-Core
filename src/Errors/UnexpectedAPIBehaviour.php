<?php

namespace DHP\Errors;

class UnexpectedAPIBehaviour extends \Error
{
    public function __construct()
    {
        parent::__construct('Unexpected Discord API behaviour');
    }
}