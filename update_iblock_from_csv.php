<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('iblock');


/* возвращает таблицу csv в виде массива
   принимает в качестве параметра путь к файлу */
function get_csv_table($filePath) {

    $handleFile = fopen($filePath, "r");

    $csvTable["properties"] = fgetcsv($handleFile, 0, ";");

    /* _________________КОСТЫЛЬ НЕ ТРОГАТЬ_________________ */
    $brokenKey = array_key_last($csvTable["properties"]);
    if ($csvTable["properties"][$brokenKey] == false) {
        unset($csvTable["properties"][$brokenKey]);
    }
    /* _________________КОСТЫЛЬ НЕ ТРОГАТЬ_________________ */

    $line = 1;
    while (($csvHandleFileLine = fgetcsv($handleFile, 0, ";")) !== false) {
        foreach ($csvTable["properties"] as $i => $prop) {
            $csvTable[$line][$prop] = $csvHandleFileLine[$i];
        }
        $line++;
    }

    return $csvTable;
}


/* возвращает таблицу инфоблока в виде массива
   принимает в качестве параметра ID информационного блока Bitrix */
function get_iblock_table($ID) {

    $ibTable["properties"] = [];
    $ibPropList = CIBlockProperty::GetList(array(), array("IBLOCK_ID" => $ID));

    while ($ibTable["properties"][] = $ibPropList->Fetch()['NAME']);

    /* _________________КОСТЫЛЬ НЕ ТРОГАТЬ_________________ */
    $brokenKey = array_key_last($ibTable["properties"]);
    if ($ibTable["properties"][$brokenKey] == false) {
        unset($ibTable["properties"][$brokenKey]);
    }
    /* _________________КОСТЫЛЬ НЕ ТРОГАТЬ_________________ */

    $arSelectFields = ["ID"];
    foreach ($ibTable["properties"] as $prop) {
        $arSelectFields[] = "PROPERTY_" . strtoupper($prop);
    }

    $ibElem = CIBlockElement::GetList(
      array("SORT"=>"ASC"),
      array("IBLOCK_ID" => $ID),
      false,
      false,
      $arSelectFields
    );

    for ($line = 1; $elem = $ibElem->Fetch(); $line++) {
        foreach ($ibTable["properties"] as $prop) {
            $ibTable[$line][$prop] = $elem["PROPERTY_" . strtoupper($prop) . "_VALUE"];
        }
    }

    return $ibTable;
}

/* возвращает false, если обновление не произошло
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
    while ($propID = $ibPropList->Fetch()['ID']) {
        $ibProp->Delete($propID);
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
        foreach ($csvTable[$i] as $prop => $field) {
            $arFields[$prop] = $field;
        }
        if ($elemID = $ibElemList->Fetch()['ID']) {
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
