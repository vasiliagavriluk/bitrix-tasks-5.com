<?php

namespace Only\Site\Handlers;

use CFile;
use CIBlock;
use CIBlockElement;
use CIBlockSection;
use CModule;


class IBlock
{

    /**
     * @param array $arFields
     * создаем обработчик события "OnAfterIBlockElementAdd" и "OnAfterIBlockElementUpdate"
     */
    public function addLog(array &$arFields)
    {
        // Здесь напиши свой обработчик
        $res = CIBlock::GetByID($arFields["IBLOCK_ID"]);
        $result = $res->GetNext();
        if($result['CODE'] != "LOG")
        {
            //выполняем проверку, если раздела не существует -> создаем его иначе обновляем раздел
            $arFilter = Array("IBLOCK_ID"=>1, "NAME"=>$arFields['NAME']);
            $get_info = CIBlockSection::GetCount($arFilter);

            if ($get_info == 0)
            {
                //создание раздела
                $bs = new CIBlockSection;
                $arFieldsSection = Array(
                    'IBLOCK_ID' => 1,
                    'NAME' => $arFields['NAME'],
                    'CODE' => $arFields['CODE'],
                    'ACTIVE' => 'Y',
                    "SORT" => 100
                );
                $IDSection = $bs->Add($arFieldsSection);
                AddMessage2Log("Раздел создан: ".$IDSection);
            }
            else
            {
                $db_list = CIBlockSection::GetList(Array(), $arFilter, false);
                while($ar_result = $db_list->GetNext())
                {
                    $IDSection = $ar_result['ID'];
                    AddMessage2Log("Раздел уже существует: ".$IDSection);
                }
            }
            //выполняем проверку, ИД раздела существует -> добавляем\изменяем элементы
            if(!empty($IDSection))
            {
                $sandbox =$result['NAME'].'/'.$arFields['NAME']."/".$arFields["ID"];
                $el = new CIBlockElement;
                $arFieldsElement = Array(
                    "ACTIVE_FROM"       => date("d.m.Y H:i:s"),
                    'CREATED_BY'        => $GLOBALS['USER']->GetID(),
                    'IBLOCK_ID'         => 1,
                    "IBLOCK_SECTION_ID" => $IDSection,
                    "NAME"              => $arFields["ID"],
                    "ACTIVE"            => "Y",
                    "PREVIEW_TEXT"      => $sandbox,
                    "DETAIL_TEXT"       => $arFields["DETAIL_TEXT"],
                    "SORT"              => 100
                );

                $arSelect = Array("ID", "NAME", "DATE_ACTIVE_FROM");
                $arFilter = Array("IBLOCK_ID"=>1, "NAME"=> $arFields['ID']);
                $res = CIBlockElement::GetList(Array(), $arFilter, false,$arSelect);

                if ($res->result->num_rows != 1)
                {
                    $arFieldsElement += Array("DATE_CREATE" => date("d.m.Y H:i:s")); //Добавляем дату создания элемента в массив
                    $IDElement = $el->Add($arFieldsElement);
                    AddMessage2Log("Элемент создан: ".$IDElement);
                }
                else
                {
                    while($ob = $res->GetNextElement())
                    {
                        $arFields_ = $ob->GetFields();
                        $res = CIBlockElement::GetByID($arFields_["ID"]);
                        if($el_res = $res->GetNext())
                        {
                            if ($arFields['ID'] == $el_res["NAME"])
                            {
                                $arFieldsElement += Array("TIMESTAMP_X" => date("d.m.Y H:i:s")); //Добавляем дату изменения в массив
                                $PRODUCT_ID = $el_res["ID"];  // ID изменяемого элемента
                                $el->Update($PRODUCT_ID, $arFieldsElement);
                                AddMessage2Log("Элемент обновлен: ".$PRODUCT_ID);
                            }
                        }
                    }
                }
            }
        }
        else
        {
            AddMessage2Log("Изменения в этом инфоблоке не логируются");
        }

    }

    function OnBeforeIBlockElementAddHandler(&$arFields)
    {
        $iQuality = 95;
        $iWidth = 1000;
        $iHeight = 1000;
        /*
         * Получаем пользовательские свойства
         */
        $dbIblockProps = \Bitrix\Iblock\PropertyTable::getList(array(
            'select' => array('*'),
            'filter' => array('IBLOCK_ID' => $arFields['IBLOCK_ID'])
        ));
        /*
         * Выбираем только свойства типа ФАЙЛ (F)
         */
        $arUserFields = [];
        while ($arIblockProps = $dbIblockProps->Fetch()) {
            if ($arIblockProps['PROPERTY_TYPE'] == 'F') {
                $arUserFields[] = $arIblockProps['ID'];
            }
        }
        /*
         * Перебираем и масштабируем изображения
         */
        foreach ($arUserFields as $iFieldId) {
            foreach ($arFields['PROPERTY_VALUES'][$iFieldId] as &$file) {
                if (!empty($file['VALUE']['tmp_name'])) {
                    $sTempName = $file['VALUE']['tmp_name'] . '_temp';
                    $res = \CAllFile::ResizeImageFile(
                        $file['VALUE']['tmp_name'],
                        $sTempName,
                        array("width" => $iWidth, "height" => $iHeight),
                        BX_RESIZE_IMAGE_PROPORTIONAL_ALT,
                        false,
                        $iQuality);
                    if ($res) {
                        rename($sTempName, $file['VALUE']['tmp_name']);
                    }
                }
            }
        }

        if ($arFields['CODE'] == 'brochures') {
            $RU_IBLOCK_ID = \Only\Site\Helpers\IBlock::getIblockID('DOCUMENTS', 'CONTENT_RU');
            $EN_IBLOCK_ID = \Only\Site\Helpers\IBlock::getIblockID('DOCUMENTS', 'CONTENT_EN');
            if ($arFields['IBLOCK_ID'] == $RU_IBLOCK_ID || $arFields['IBLOCK_ID'] == $EN_IBLOCK_ID) {
                \CModule::IncludeModule('iblock');
                $arFiles = [];
                foreach ($arFields['PROPERTY_VALUES'] as $id => &$arValues) {
                    $arProp = \CIBlockProperty::GetByID($id, $arFields['IBLOCK_ID'])->Fetch();
                    if ($arProp['PROPERTY_TYPE'] == 'F' && $arProp['CODE'] == 'FILE') {
                        $key_index = 0;
                        while (isset($arValues['n' . $key_index])) {
                            $arFiles[] = $arValues['n' . $key_index++];
                        }
                    } elseif ($arProp['PROPERTY_TYPE'] == 'L' && $arProp['CODE'] == 'OTHER_LANG' && $arValues[0]['VALUE']) {
                        $arValues[0]['VALUE'] = null;
                        if (!empty($arFiles)) {
                            $OTHER_IBLOCK_ID = $RU_IBLOCK_ID == $arFields['IBLOCK_ID'] ? $EN_IBLOCK_ID : $RU_IBLOCK_ID;
                            $arOtherElement = \CIBlockElement::GetList([],
                                [
                                    'IBLOCK_ID' => $OTHER_IBLOCK_ID,
                                    'CODE' => $arFields['CODE']
                                ], false, false, ['ID'])
                                ->Fetch();
                            if ($arOtherElement) {
                                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                                \CIBlockElement::SetPropertyValues($arOtherElement['ID'], $OTHER_IBLOCK_ID, $arFiles, 'FILE');
                            }
                        }
                    } elseif ($arProp['PROPERTY_TYPE'] == 'E') {
                        $elementIds = [];
                        foreach ($arValues as &$arValue) {
                            if ($arValue['VALUE']) {
                                $elementIds[] = $arValue['VALUE'];
                                $arValue['VALUE'] = null;
                            }
                        }
                        if (!empty($arFiles && !empty($elementIds))) {
                            $rsElement = \CIBlockElement::GetList([],
                                [
                                    'IBLOCK_ID' => \Only\Site\Helpers\IBlock::getIblockID('PRODUCTS', 'CATALOG_' . $RU_IBLOCK_ID == $arFields['IBLOCK_ID'] ? '_RU' : '_EN'),
                                    'ID' => $elementIds
                                ], false, false, ['ID', 'IBLOCK_ID', 'NAME']);
                            while ($arElement = $rsElement->Fetch()) {
                                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                                \CIBlockElement::SetPropertyValues($arElement['ID'], $arElement['IBLOCK_ID'], $arFiles, 'FILE');
                            }
                        }
                    }
                }
            }
        }
    }

}
