<?php
require_once __DIR__ .'/vendor/autoload.php';
use SnappsiSnappes\JsonBitrixFields;

$webhook = '';

$CompanyConverter = new JsonBitrixFields($webhook,0,'company');

$TypicalRestCompany = [
    'TITLE' => 'название какой то компании',
    'UF_CRM_STATUS_ID' => 345
];

print_r($CompanyConverter->human_KEY('статус'));
print_r($CompanyConverter->bitrix_KEY_VAL('UF_CRM_STATUS_ID', 345));
print_r($CompanyConverter->human_KEY_VAL('статус', 'активный'));
print_r($CompanyConverter->convert_entity($TypicalRestCompany));


