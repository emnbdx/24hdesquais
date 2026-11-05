<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();

date_default_timezone_set('Europe/Paris');
