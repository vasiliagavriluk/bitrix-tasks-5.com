<?php
define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/log.txt");
//подключим класс
require($_SERVER["DOCUMENT_ROOT"]."/local/modules/dev.site/lib/Handlers/Iblock.php");
require($_SERVER["DOCUMENT_ROOT"]."/local/modules/dev.site/lib/Agents/Iblock.php");

use Only\Site\Handlers\IBlock;

//создаем этот класс
    $IBlock = new IBlock();

//Событие после попытки добавления нового элемента (OnAfterIBlockElementAdd)
    AddEventHandler(
        "iblock",
        "OnAfterIBlockElementAdd",
        [ $IBlock, "addLog"]
    );

//Событие после попытки изменения элемента (OnAfterIBlockElementUpdate)
    AddEventHandler(
            "iblock",
            "OnAfterIBlockElementUpdate",
            [ $IBlock, "addLog"]
    );





