<?php

require_once __DIR__ . '/../vendor/autoload.php';

function d($obj, $die = false)
{
    echo print_r($obj, true);
    if ($die) die;
}