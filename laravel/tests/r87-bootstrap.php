<?php

$autoload = getenv('PI_TEST_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
require $autoload;
require_once dirname(__DIR__).'/app/Services/Pi/Taxonomy/AffairFirstNomenclature.php';
