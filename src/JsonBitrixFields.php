<?php

namespace SnappsiSnappes;

use Exception;
use function in_array;

class JsonBitrixFields
{
    private $FileName;
    private $JsonData;
    private $JsonFields;
    public $Webhook;
    private $Dir = __DIR__;

    private $HumanLabelToBitrixKey = [];
    private $BitrixKeyToHumanLabel = [];
    private $EnumValueToId = [];
    private $EnumIdToValue = [];

    public function __construct($webhook, $AlwaysDownloadFields = false, $Entity)
    {
        $this->EntityValidation($Entity);

        $EntityLower = strtolower($Entity);
        $this->FileName = parse_url($webhook, PHP_URL_HOST) . "_{$EntityLower}_fields.json";
        $this->Webhook = $webhook;
        $FilePath = $this->Dir . '/' . $this->FileName;

        if ($AlwaysDownloadFields && file_exists($FilePath)) {
            unlink($FilePath);
        }

        if (!file_exists($FilePath)) {
            $this->createFields($EntityLower, $this->FileName);
        }

        $this->JsonData = file_get_contents($FilePath);
        $this->JsonFields = json_decode($this->JsonData, true);

        $this->buildIndexes();
    }

    private function EntityValidation($GivenEntity)
    {
        $AllowedValues = ['company', 'deal', 'lead'];
        if (!in_array(strtolower($GivenEntity), $AllowedValues)) {
            throw new Exception('Invalid entity. Allowed: ' . implode(', ', $AllowedValues) . ' given value ' . $GivenEntity, 500);
        }
    }

    private function buildIndexes()
    {
        foreach ($this->JsonFields as $bitrixKey => $field) {
            $humanLabel = null;

            if (isset($field['listLabel'])) {
                $humanLabel = $field['listLabel'];
            } elseif (isset($field['title']) && !in_array($bitrixKey, ['ID', 'TITLE'], true)) {
                $humanLabel = $field['title'];
            } else {
                $humanLabel = $field['title'] ?? $bitrixKey;
            }

            if ($humanLabel !== null) {
                $normLabel = mb_strtolower(trim($humanLabel));
                $this->HumanLabelToBitrixKey[$normLabel] = $bitrixKey;
                $this->BitrixKeyToHumanLabel[$bitrixKey] = $humanLabel;
            }

            if (isset($field['items']) && is_array($field['items'])) {
                foreach ($field['items'] as $item) {
                    if (isset($item['ID']) && isset($item['VALUE'])) {
                        $id = (string) $item['ID'];
                        $value = (string) $item['VALUE'];
                        $normValue = mb_strtolower(trim($value));

                        $this->EnumValueToId[$bitrixKey][$normValue] = $id;
                        $this->EnumIdToValue[$bitrixKey][$id] = $value; 
                    }
                }
            }
        }
    }

    private function bxRequest($hook, $method, $post_fields = null)
    {
        $hook = $hook ?: $this->Webhook;

        if (!empty($post_fields)) {
            $post_fields = http_build_query($post_fields);
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POSTREDIR => 10,
            CURLOPT_URL => $hook . $method,
        ]);

        if ($post_fields !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
        }

        $result = json_decode(curl_exec($curl), true);
        curl_close($curl);

        return $result;
    }

    private function createFields($entity, $filename)
    {
        $methodMap = [
            'company' => 'crm.company.fields',
            'deal' => 'crm.deal.fields',
            'lead' => 'crm.lead.fields',
        ];

        $method = $methodMap[$entity] ?? 'crm.company.fields';

        $result = $this->bxRequest($this->Webhook, $method, null);
        $result = $result['result'] ?? [];
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->Dir . '/' . $filename, $json);
    }

    /**
     * 
     * @param array $oneItem
     * @return array
     */
    public function convert_entity(array $oneItem): array
    {
        $converted_one_item = [];
        foreach ($oneItem as $key => $val) {
            $converted_to_human_field = $this->bitrix_KEY_VAL($key, $val);
            $converted_one_item[$converted_to_human_field['KEY']] = $converted_to_human_field['VAL'];
        }
        return $converted_one_item;
    }

    public function human_VAL($KEY, $VAL): string
    {
        if (empty($KEY) || empty($VAL)) {
            return $VAL;
        }

        if (mb_strtolower(trim($KEY)) === 'название компании' || mb_strtolower(trim($KEY)) === 'title') {
            return $VAL;
        }

        $normKey = mb_strtolower(trim($KEY));
        $bitrixKey = $this->HumanLabelToBitrixKey[$normKey] ?? '';

        if ($bitrixKey === '') {
            return $VAL;
        }

        $normVal = mb_strtolower(trim($VAL));
        if (isset($this->EnumValueToId[$bitrixKey][$normVal])) {
            return $this->EnumValueToId[$bitrixKey][$normVal];
        }

        return $VAL;
    }



    public function human_KEY($search_key)
    {
        $normKey = mb_strtolower(trim($search_key));
        return $this->HumanLabelToBitrixKey[$normKey] ?? '';
    }


    /**
     * @param string $KEY
     * @param string $VAL
     * @return array{KEY: string, VAL: string}
     */
    public function human_KEY_VAL($KEY, $VAL): array
    {
        $normKey = mb_strtolower(trim($KEY));
        $bitrixKey = $this->HumanLabelToBitrixKey[$normKey] ?? '';

        $normVal = mb_strtolower(trim($VAL));
        $bitrixVal = '';
        if (isset($this->EnumValueToId[$bitrixKey][$normVal])) {
            $bitrixVal = $this->EnumValueToId[$bitrixKey][$normVal] ?? '';
        }

        return ['KEY' => $bitrixKey, 'VAL' => $bitrixVal];
    }

    public function bitrix_VAL($KEY, $VAL): string
    {
        if (empty($VAL)) {
            return '';
        }
        if ($KEY === 'TITLE') {
            return $VAL;
        }

        if (isset($this->EnumIdToValue[$KEY][$VAL])) {
            return $this->EnumIdToValue[$KEY][$VAL];
        }

        return $VAL;
    }


    public function bitrix_KEY($KEY): string
    {
        if (empty($KEY)) {
            return '';
        }
        return $this->BitrixKeyToHumanLabel[$KEY] ?? '';
    }


    /**
     *
     * @param string $KEY
     * @param string $VAL
     * @return array{KEY: string, VAL: string}
     */
    public function bitrix_KEY_VAL($KEY, $VAL): array
    {
        $humanKey = $this->bitrix_KEY($KEY);
        $humanVal = $this->bitrix_VAL($KEY, $VAL);
        return [
            'KEY' => $humanKey,
            'VAL' => $humanVal
        ];
    }

    public function human_KEY_ufCrm($search_key)
    {
        $normKey = mb_strtolower(trim($search_key));
        foreach ($this->HumanLabelToBitrixKey as $label => $bitrixKey) {
            if ($label === $normKey && str_starts_with($bitrixKey, 'UF_CRM')) {
                return $bitrixKey;
            }
        }
        return '';
    }

}