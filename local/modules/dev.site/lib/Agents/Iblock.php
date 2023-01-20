<?php
namespace Only\Site\Agents;

use CIBlockElement;
use CModule;

class Iblock
{
    public static function clearOldLogs(): string
    {
        // Здесь напиши свой агент

        // подключаем нужные модули
        CModule::IncludeModule("iblock");
        $items = [];

        //Вытаскиваем элементы
        $result = CIBlockElement::GetList(array('TIMESTAMP_X' => 'DESC'),
            array("IBLOCK_ID" => 1), // Необходимо установить ID нужного инфоблока
            false, array('nTopCount' => 10000, 'nOffset'=>10),
            array ("ID"));
        while($item = $result->fetch()){
            $items[] = $item['ID'];
        }

        //Удаляем элементы
        foreach ($items as $section)
        {
            CIBlockElement::Delete($section);
        }

        return "Only\Site\Agents\Iblock::clearOldLogs();";
    }
}
