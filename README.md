# json-bitrix-fields

установка через композер
```bash
composer require snappsisnappes/json-bitrix-fields
```


Это маленький класс-инструмент поможет в конвертации битриксных полей в человеческий вид и обратно.

Произизводительность O(1).

Принцип работы при инициализации класса -

```php
$CompanyConverter = new JsonBitrixFields($webhook,0,'company');
```

в той же папке что и класс скачается json файл со всеми полями вашего битрикса.

Формат файла (название вашено битрикса).birtix24.ru\_(сущность, может быть lead,company,deal)\_fields.json.

далее в коде вы можете использовать этот экземпляр класса для конвертации полей, пример:

```php
print_r($CompanyConverter->human_KEY('статус'));
// выдаст id поля, например у меня такое: UF_CRM_STATUS_ID
```

важное замечание - внутри параметров работает case insensative
нечувствительность к регистру, это означает,
что можно писать "Статус" или "стаТуС" результат будет такой же,
это работает во всех методах класса.

посмотрим все методы:

```php

print_r($CompanyConverter->human_KEY_VAL('статус', 'активный'));

/*
вывод
Array
(
    [KEY] => UF_CRM_STATUS_ID
    [VAL] => 345
)

*/


print_r($CompanyConverter->bitrix_KEY_VAL('UF_CRM_STATUS_ID', 345));
/*
вывод
Array
(
    [KEY] => Статус
    [VAL] => Активный
)
*/


$TypicalRestCompany = [
    'TITLE' => 'название какой то компании',
    'UF_CRM_STATUS_ID' => 345
];

print_r($CompanyConverter->convert_entity($TypicalRestCompany));
/* 
вывод
Array
(
    [Название компании] => название какой то компании
    [Статус] => Активный
)
*/
```
