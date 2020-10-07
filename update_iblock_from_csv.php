<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('iblock');




/* возвращает таблицу csv в виде массива
   принимает в качестве параметра путь к файлу */
function get_csv_table($filePath) {
    $csvTable = [];

    $handleFile = fopen($filePath, "r");

    $csvTable["properties"] = fgetcsv($handleFile, 0, ";");

    $line = 1;
    while (($csvHandleFileLine = fgetcsv($handleFile, 0, ";")) != false) {
        foreach ($csvTable["properties"] as $i => $prop) {
            $csvTable[$line][$prop] = $csvHandleFileLine[$i];
            settype($csvTable[$line][$prop], "string");
        }
        $line++;
    }

    return $csvTable;
}

/* возвращает таблицу инфоблока в виде массива
   принимает в качестве параметра ID информационного блока Bitrix */
function get_iblock_table($ID) {
    $ibTable = [];
    $ibProperty = [];
    $ibPropList = CIBlockProperty::GetList(array(), array("IBLOCK_ID" => $ID));

    while ($ibProperty[] = $ibPropList->Fetch()['NAME']);

    $arSelectFields = ["ID"];
    foreach ($ibProperty as $prop) {
        $arSelectFields[] = "PROPERTY_" . strtoupper($prop);
    }

    $ibElem = CIBlockElement::GetList(
      array("SORT"=>"ASC"),
      array("IBLOCK_ID" => $ID),
      false,
      false,
      $arSelectFields
    );

    $ibTable["properties"] = $ibProperty;
    for ($i = 1; $elem = $ibElem->Fetch(); $i++) {
        $ibTable[$i] = [];
        foreach ($ibProperty as $prop) {
            $ibTable[$i][$prop] = $elem["PROPERTY_" . strtoupper($prop) . "_VALUE"];
        }
    }
    return $ibTable;
}

/* возвращает false, если обновление не требуется
   возвращает true, если потребовалось обновление таблицы
   в качестве параметра $ID принимает ID инфоблока
   в качестве параметра $filePath принимает адрес csv файда в виде строки */
function update_iblock_from_csv($ID, $filePath) {

    /* получение таблицы из csv файла */
    $csvTable = get_csv_table($filePath);

    /* получение таблицы из инфоблока */
    $ibTable = get_iblock_table($ID);

    /* сравнение таблиц */
    if ($csvTable == $ibTable) {
        return false;
    }

    /* обновление значений свойств */
    $ibProp = new CIBlockProperty;
    $ibPropList = CIBlockProperty::GetList(array(), array("IBLOCK_ID" => $ID));

    foreach ($csvTable["properties"] as $prop) {
        if ($propID = $ibPropList->Fetch()['ID']) {
            $ibProp->Update($propID, array("NAME" => $prop, "CODE" => $prop));
        } else {
            $ibProp->Add(array("IBLOCK_ID" => $ID, "NAME" => $prop, "CODE" => $prop));
        }
    }
    while ($propID) {
        $ibProp->Delete($propID);
        $propID = $ibPropList->Fetch()['ID'];
    }

    /* обновление значений элементов */
    $ibElem = new CIBlockElement;
    $ibElemList = CIBlockElement::GetList(
      array("SORT"=>"ASC"),
      array("IBLOCK_ID" => $ID),
      false,
      false,
      array("ID")
    );

    $arFields = [];
    for ($i = 1; $csvTable[$i] !== null; $i++) {

        $elemID = $ibElemList->Fetch()['ID'];

        foreach ($csvTable[$i] as $prop => $field) {
            $arFields[$prop] = $field;
        }
        if ($elemID) {
            $ibElem->Update($elemID, array("PROPERTY_VALUES" => $arFields));
        } else {
            $ibElem->Add(array("IBLOCK_ID" => $ID, "NAME" => "element", "PROPERTY_VALUES" => $arFields));
        }
    }
    while ($elemID = $ibElemList->Fetch()['ID']) {
        $ibElem->Delete($elemID);
    }

    return true;
}
