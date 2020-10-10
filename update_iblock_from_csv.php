<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('iblock');

/* уникальное значение каждой записи(элемента) используемое для привязки записей(элементов)
   csv файла к записям(элементам) инфоблока */
define("TABLE_PRIMARY_KEY", "id");

/* возвращает таблицу csv в виде массива
   принимает в качестве параметра путь к файлу */
function get_csv_table($filePath) {

    $handleFile = fopen($filePath, "r");

    $csvTable["properties"] = fgetcsv($handleFile, 0, ";");

    /* если в конце пустое название свойства, то оно удаляется */
    $brokenKey = array_key_last($csvTable["properties"]);
    if ($csvTable["properties"][$brokenKey] == false) {
        unset($csvTable["properties"][$brokenKey]);
    }

    $idIndex = array_keys($csvTable["properties"], TABLE_PRIMARY_KEY)[0];
    while ($csvHandleFileLine = fgetcsv($handleFile, 0, ";")) {
        $primKey = $csvHandleFileLine[$idIndex];
        foreach ($csvTable["properties"] as $i => $prop) {
            $csvTable[$primKey][$prop] = $csvHandleFileLine[$i];
        }
    }

    return $csvTable;
}


/* возвращает таблицу инфоблока в виде массива
   принимает в качестве параметра ID информационного блока Bitrix */
function get_iblock_table($ID) {

    $ibTable["properties"] = [];
    $ibPropList = CIBlockProperty::GetList(array(), array("IBLOCK_ID" => $ID));

    while ($property = $ibPropList->Fetch()['NAME']) {
        array_push($ibTable["properties"], $property);
    }

    $arSelectFields = ["ID", "IBLOCK_ID"];
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

    while ($elem = $ibElem->Fetch()) {
        $idIndex = $elem["PROPERTY_" . strtoupper(TABLE_PRIMARY_KEY) . "_VALUE"];
        foreach ($ibTable["properties"] as $prop) {
            $ibTable[$idIndex][$prop] = $elem["PROPERTY_" . strtoupper($prop) . "_VALUE"];
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

    /* получаем новые свойства */
    $propToAdd = array_diff($csvTable["properties"], $ibTable["properties"]);
    /* сортируем эелементы по действиям с ними */
    $toAdd = array_keys(array_diff_assoc($csvTable, $ibTable));
    $toDelete = array_keys(array_diff_assoc($ibTable, $csvTable));

    $toUpdate = [];
    foreach ($csvTable as $primKey => $elem) {
        if ($primKey == "properties") {
            continue;
        }

        $elemDiff = array_diff_assoc($elem, $ibTable[$primKey]);
        if ( ! empty($elemDiff) ) {
            $toUpdate[$primKey] = $elemDiff;
        }
    }
    $toUpdate = array_keys($toUpdate);

    /* проверяем есть ли необходимость в изменениях */
    $updates = array_merge($propToAdd, $toAdd, $toDelete, $toUpdate);
    if (empty($updates)) {
        return false;
    }

    /* неактуальные свойства из инфоблока не удаляются, только добавляются новые */
    if (!empty($propToAdd)) {
        $ibProp = new CIBlockProperty;

        foreach ($propToAdd as $prop) {
            $ibProp->Add(array("IBLOCK_ID" => $ID, "NAME" => $prop, "CODE" => $prop));
        }
    }

  /** обновление и удаление с привязкой к свойству,
    * которое определенно в константе TABLE_PRIMARY_KEY
    */
    $ibElem = new CIBlockElement;
    // проверка необходимости удалять и обновлять элементы
    if (!empty(array_merge($toUpdate, $toDelete))) {
        $ibElemList = CIBlockElement::GetList(
            array("SORT"=>"ASC"),
            array("IBLOCK_ID" => $ID),
            false,
            false,
            array(
                "ID",
                "IBLOCK_ID",
                "PROPERTY_" . strtoupper(TABLE_PRIMARY_KEY)
            )
        );

        while ($elem = $ibElemList->Fetch()) {
            $primKey = $elem["PROPERTY_" . strtoupper(TABLE_PRIMARY_KEY) . "_VALUE"];

            foreach ($csvTable[$primKey] as $prop => $field) {
                $arFields[$prop] = $field;
            }

            if (in_array($primKey, $toUpdate)) {
                $ibElem->Update($elem['ID'], array("PROPERTY_VALUES" => $arFields));
            } else if (in_array($primKey, $toDelete)) {
                $ibElem->Delete($elem['ID']);
            }
        }
    }

    /* добавление элементов */
    foreach ($toAdd as $id) {
        foreach ($csvTable[$id] as $prop => $field) {
            $arFields[$prop] = $field;
        }
        $ibElem->Add(array("IBLOCK_ID" => $ID, "NAME" => "element", "PROPERTY_VALUES" => $arFields));
    }

    return true;
}


$ID = 9;
$filePath = "test.csv";
echo update_iblock_from_csv($ID, $filePath);
