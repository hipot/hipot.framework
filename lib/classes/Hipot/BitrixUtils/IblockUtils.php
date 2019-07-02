<?
namespace Hipot\BitrixUtils;

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use \Hipot\Utils\IblockUpdateResult;
use \Hipot\Utils\UnsortedUtils;

try {
	Loader::includeModule('iblock');
} catch (LoaderException $e) {
	return false;
}

/**
 * Дополнительные утилиты для работы с инфоблоками
 *
 *
 * IMPORTANT:
 * Некоторые методы выборки избыточны (лучше использовать bitrix api).
 * В основном необходимы для построения быстрых решений: к примеру, отчетов.
 */
class IblockUtils
{
	/**
	 * Добавление секции в инфоблок, возвращает ошибку либо ID результата, см. return
	 *
	 * @param array $arAddFields массив к добавлению
	 * @return \Hipot\Utils\IblockUpdateResult
	 * @see \CIBlockSection::Add()
	 */
	public static function addSectionToDb($arAddFields = array())
	{
		if (! is_array($arAddFields)) {
			$arAddFields = array();
		}

		$el = new \CIBlockSection();
		$ID = $el->Add($arAddFields);

		if ($ID) {
			return new IblockUpdateResult(array('RESULT' => $ID,				'STATUS' => 'OK'));
		} else {
			return new IblockUpdateResult(array('RESULT' => $el->LAST_ERROR,	'STATUS' => 'ERROR'));
		}
	}

	/**
	 * Обновление секции в инфоблоке, возвращает ошибку либо ID результата, см. return
	 *
	 * @param array $arAddFields массив к добавлению
	 * @return \Hipot\BitrixUtils\IblockUpdateResult
	 * @see \CIBlockSection::Add()
	 */
	public static function updateSectionToDb($ID, $arAddFields = array())
	{
		if (! is_array($arAddFields)) {
			$arAddFields = array();
		}

		$ID = (int)$ID;

		$el		= new \CIBlockSection();
		$res	= $el->Update($ID, $arAddFields);

		if ($res) {
			return new IblockUpdateResult(array('RESULT' => $ID,				'STATUS' => 'OK'));
		} else {
			return new IblockUpdateResult(array('RESULT' => $el->LAST_ERROR,	'STATUS' => 'ERROR'));
		}
	}

	/**
	 * Добавление элемента в инфоблок, возвращает ошибку либо ID результата, см. return
	 *
	 * @param array $arAddFields массив к добавлению
	 * @return \Hipot\Utils\IblockUpdateResult
	 * @see \CIBlockElement::Add()
	 */
	public static function addElementToDb($arAddFields = array())
	{
		if (! is_array($arAddFields)) {
			$arAddFields = array();
		}

		$el = new \CIBlockElement();
		$ID = $el->Add($arAddFields);

		if ($ID) {
			return new IblockUpdateResult(array('RESULT' => $ID,				'STATUS' => 'OK'));
		} else {
			return new IblockUpdateResult(array('RESULT' => $el->LAST_ERROR,	'STATUS' => 'ERROR'));
		}
	}

	/**
	 * Обновление элемента в инфоблоке, возвращает ошибку либо ID результата, см. return
	 *
	 * @param int $ID массив к добавлению
	 * @param array $arAddFields массив к добавлению
	 * @return \Hipot\Utils\IblockUpdateResult
	 * @see \CIBlockElement::Update()
	 */
	public static function updateElementToDb($ID, $arAddFields)
	{
		if (! is_array($arAddFields)) {
			$arAddFields = array();
		}
		$ID = (int)$ID;

		if (isset($arAddFields["PROPERTY_VALUES"])) {
			$PROPS = $arAddFields["PROPERTY_VALUES"];
			unset($arAddFields["PROPERTY_VALUES"]);
		}

		$el 	= new \CIBlockElement();
		$bUpd 	= $el->Update($ID, $arAddFields);

		if (isset($PROPS) && $bUpd) {
			\CIBlockElement::SetPropertyValuesEx($ID, false, $PROPS);
		}

		if ($bUpd) {
			return new IblockUpdateResult(array('RESULT' => $ID,				'STATUS' => 'OK'));
		} else {
			return new IblockUpdateResult(array('RESULT' => $el->LAST_ERROR,	'STATUS' => 'ERROR'));
		}
	}

	/**
	 * Получить результат выборки из инфоблока по параметрам
	 *
	 * @param array $arOrder
	 * @param array $arFilter
	 * @param bool $arGroupBy
	 * @param bool $arNavParams
	 * @param array $arSelect
	 * @return \CIBlockResult
	 */
	public static function selectElementsByFilter(
										$arOrder, $arFilter, $arGroupBy = false,
										$arNavParams = false, $arSelect = array())
	{
		$rsItems = \CIBlockElement::GetList($arOrder, $arFilter, $arGroupBy, $arNavParams, $arSelect);
		return $rsItems;
	}

	/**
	 * Получить массив элементов инфоблока по параметрам (со свойствами)
	 *
	 * @param array $arOrder
	 * @param array $arFilter
	 * @param bool $arGroupBy
	 * @param bool|array $arNavParams
	 * @param array $arSelect
	 * @param bool $SelectAllProps = false
	 * @param bool $OnlyPropsValue = true
	 * @return Ambigous <unknown, mixed, multitype:unknown NULL string , string, boolean, multitype:, NULL>
	 */
	public static function selectElementsByFilterArray(
							$arOrder, $arFilter, $arGroupBy = false, $arNavParams = false,
							$arSelect = array(), $SelectAllProps = false, $OnlyPropsValue = true)
	{
		if (! in_array('IBLOCK_ID', $arSelect)) {
			$arSelect[] = 'IBLOCK_ID';
		}
		if (! in_array('ID', $arSelect)) {
			$arSelect[] = 'ID';
		}

		$rsItems = self::selectElementsByFilter($arOrder, $arFilter, $arGroupBy, $arNavParams, $arSelect);
		
		if (! is_object($rsItems)) {
			return $rsItems;
		}

		while ($arItem = $rsItems->GetNext()) {

			if ($SelectAllProps === true) {
				$arItem['PROPERTIES'] = self::selectElementProperties(
					$arItem['ID'],
					$arItem['IBLOCK_ID'],
					$OnlyPropsValue
				);
			}

			$arResult[] = $arItem;
		}

		return $arResult;
	}

	/**
	 * Получить свойства элемента инфоблока
	 *
	 * @param int  $ID элемент инфоблока
	 * @param int  $IBLOCK_ID = 0 код инфоблока, если не указан, то будет выбран (желательно указывать для быстродействия!)
	 * @param bool $onlyValue = false возвращать только значение свойства
	 *
	 * @return array
	 */
	public static function selectElementProperties($ID, $IBLOCK_ID = 0, $onlyValue = false)
	{
		global $DB;

		$IBLOCK_ID	= (int)$IBLOCK_ID;
		$ID			= (int)$ID;

		if ($IBLOCK_ID <= 0) {
			$rs = $DB->Query("select IBLOCK_ID from b_iblock_element where ID=" . $ID);
			if ($ar = $rs->Fetch()) {
				$IBLOCK_ID = $ar["IBLOCK_ID"];
			} else {
				return false;
			}
		}

		$PROPERTIES = array();

		// QUERY 2
		$db_props = \CIBlockElement::GetProperty(
			$IBLOCK_ID,
			$ID,
			array("sort" => "asc"),
			array("EMPTY" => "N")
		);
		while ($ar_props = $db_props->Fetch()) {

			if (trim($ar_props['CODE']) == '') {
				$ar_props['CODE'] = $ar_props['ID'];
			}

			if ($ar_props['MULTIPLE'] == "Y") {
				$PROPERTIES[ $ar_props['CODE'] ][]	= ($onlyValue) ? $ar_props['VALUE'] : $ar_props;
			} else {
				$PROPERTIES[ $ar_props['CODE'] ]	= ($onlyValue) ? $ar_props['VALUE'] : $ar_props;
			}
		}

		return $PROPERTIES;
	}

	/**
	 * Получить список секций по фильтру.
	 * FUTURE
	 *
	 * @see CIBlockSection::GetList()
	 *
	 * @param unknown                            $arOrder
	 * @param unknown                            $arFilter
	 * @param bool|string                        $bIncCnt
	 * @param array|\Hipot\BitrixUtils\unknown   $arSelect
	 * @param bool|string                        $arNavStartParams
	 *
	 * @return \CIBlockResult
	 */
	public static function selectSectionsByFilter(
										$arOrder, $arFilter, $bIncCnt = false,
										$arSelect = array(), $arNavStartParams=false)
	{
		if (! in_array('ID', $arSelect)) {
			$arSelect[] = 'ID';
		}
		if (! in_array('IBLOCK_ID', $arSelect)) {
			$arSelect[] = 'IBLOCK_ID';
		}

		$rsSect = \CIBlockSection::GetList($arOrder, $arFilter, $bIncCnt, $arSelect, $arNavStartParams);
		return $rsSect;
	}

	/**
	 * Получить список секций по фильтру в виде массива
	 *
	 * @param array       $arOrder
	 * @param array       $arFilter
	 * @param bool|string $bIncCnt
	 * @param array       $arSelect
	 * @param bool|string $arNavStartParams
	 *
	 * @return array|boolean
	 */
	public static function selectSectionsByFilterArray(
										$arOrder, $arFilter, $bIncCnt = false,
										$arSelect = array(), $arNavStartParams = false)
	{
		$arResult = array();

		$rsSect = self::selectSectionsByFilter($arOrder, $arFilter, $bIncCnt, $arSelect, $arNavStartParams);
		while ($arSect = $rsSect->GetNext()) {
			$arResult[] = $arSect;
		}

		return $arResult;
	}


	/**
	 * Получить варианты значения выпадающего списка
	 *
	 * @param string|int $propCode
	 * @param array $arFilterEx = array() фильтр по выборке вариантов
	 * @param array $aSort = array("DEF"=>"DESC", "SORT"=>"ASC")
	 *
	 * @return void|boolean
	 */
	public static function selectPropertyEnumArray(
										$propCode, $arFilterEx = array(),
										$aSort = array("DEF"=>"DESC", "SORT"=>"ASC"))
	{
		if (trim($propCode) == '') {
			return false;
		}

		$arFilter = array();

		if (is_numeric($propCode)) {
			$arFilter['ID']		= (int)$propCode;
		} else {
			$arFilter['CODE']	= $propCode;
		}

		foreach ($arFilterEx as $f => $filter) {
			$arFilter[ $f ] = $filter;
		}
		unset($f, $filter);

		$arRes = array();

		$property_enums = \CIBlockPropertyEnum::GetList($aSort, $arFilter);
		while ($enum_fields = $property_enums->GetNext()) {
			$arRes[] = $enum_fields;
		}
		return $arRes;
	}

	/**
	 * Проверить наличие элемента/секции с именем или симв. кодом $field в инфоблоке $iblockId.
	 *
	 * @param string $field
	 * @param int $iblockId
	 * @param string $fieldType
	 * @param string $table = b_iblock_element | b_iblock_section
	 * @return boolean|number
	 */
	public static function checkExistsByNameOrCode($field, $iblockId, $fieldType = 'name', $table = 'b_iblock_element')
	{
		global $DB;

		$fieldType = ToLower($fieldType);

		$iblockFieldsBase = ['code', 'xml_id', 'name', 'preview_text'];
		$fields = UnsortedUtils::getTableFieldsFromDB($table);
		foreach ($fields as &$f) {
			$f = ToLower($f);
			$iblockFields[] = $f;
		}
		unset($f);

		if (trim($field) == '' || (int)$iblockId == 0
			|| !in_array(trim($fieldType, '?=%!<> '), $iblockFields)
		) {
			return false;
		}

		$fw = '';
		if ($fieldType == 'code') {
			$fw = 'CODE = "' . $DB->ForSql($field) . '"';
		} else if ($fieldType == 'xml_id') {
			$fw = 'XML_ID = "' . $DB->ForSql($field) . '"';
		} else if (! in_array($fieldType, $iblockFieldsBase)) {
			$fw = $fieldType . ' = "' . $DB->ForSql($field) . '"';
		} else {
			$fw = 'NAME = "' . $DB->ForSql($field) . '"';
		}

		$sqlCheck = 'SELECT ID FROM ' . $table . ' WHERE ' . $fw . ' AND IBLOCK_ID = ' . (int)$iblockId;
		$el = $DB->Query($sqlCheck)->Fetch();

		if ((int)$el['ID'] > 0) {
			return (int)$el['ID'];
		} else {
			return false;
		}
	}

	/**
	 * Проверить наличие элемента с именем или симв. кодом $field в инфоблоке $iblockId.
	 * Удобно для импортеров.
	 *
	 * !!! Крайне желательно иметь двойной индекс $field / $iblockId в таблице b_iblock_element
	 *
	 * @param string $fieldVal по какому значению ищем
	 * @param int $iblockId
	 * @param string $fieldType  = 'name' тип поля: name|code|xml_id
	 * @return boolean|integer
	 */
	public static function checkElementExistsByNameOrCode($fieldVal, $iblockId, $fieldType = 'name')
	{
		return self::checkExistsByNameOrCode($fieldVal, $iblockId, $fieldType, 'b_iblock_element');
	}

	/**
	 * Проверить наличие секции с именем или симв. кодом $field в инфоблоке $iblockId.
	 * Удобно для импортеров.
	 *
	 * !!! Крайне желательно иметь двойной индекс $field / $iblockId в таблице b_iblock_section
	 *
	 * @param string $fieldVal по какому значению ищем
	 * @param int $iblockId
	 * @param string $fieldType  = 'name' тип поля: name|code|xml_id
	 * @return boolean|integer
	 */
	public static function checkSectionExistsByNameOrCode($fieldVal, $iblockId, $fieldType = 'name')
	{
		return self::checkExistsByNameOrCode($fieldVal, $iblockId, $fieldType, 'b_iblock_section');
	}

	/**
	 * Выбрать следующие/предыдущие $cntSelect штук относительно $elId
	 *
	 * @param int        $ELEMENT_ID относительно какого элемента выбрать след/предыдущие $cntSelect штук
	 * @param array      $rowSort = array('sort' => 'asc') сортировка ряда след./пред.
	 * @param array      $prevNextSelect = array('ID', 'NAME', 'DETAIL_PAGE_URL', 'DATE_ACTIVE_FROM') -
	 *                               какие поля выбрать у след./пред. элементов. Дополнительно еще выбирается
	 *                               TYPE - PREV|NEXT указывающий тип элемента (до или после элемента $elId)
	 * @param array      $dopFilter = array('ACTIVE' => 'Y') дополнительный фильтр при выборке.
	 *                               Помимо указанных значений всегда передается IBLOCK_ID указанного элемента $elId
	 *
	 * @param int|number $cntSelect = 1 по скольку элементов справа и слева выбрать
	 *
	 * @return array
	 */
	public static function getNextPrevElemetsByElementId(
													$ELEMENT_ID,
													$rowSort = array(),
													$prevNextSelect = array(),
													$dopFilter = array('ACTIVE' => 'Y'),
													$cntSelect = 1
												)
	{
		$ELEMENT_ID = (int)$ELEMENT_ID;
		if ($ELEMENT_ID <= 0) {
			return false;
		}

		global $DB;
		if ($ar = $DB->Query("select IBLOCK_ID from b_iblock_element where ID=" . $ELEMENT_ID)->Fetch()) {
			$IBLOCK_ID = $ar["IBLOCK_ID"];
		} else {
			return false;
		}

		$arFilter = array(
			'IBLOCK_ID' => (int)$IBLOCK_ID,
		);
		$arSort   = array('SORT' => 'ASC');
		if (!empty( $rowSort )) {
			$arSort = $rowSort;
		}
		$arFields = array('ID', 'NAME', 'DETAIL_PAGE_URL', 'DATE_ACTIVE_FROM');
		if ($prevNextSelect) {
			$arFields = array_merge($arFields, $prevNextSelect);
		}
		if ($dopFilter) {
			$arFilter = array_merge($arFilter, $dopFilter);
		}
		$arNavStartParams = array(
			'nElementID' => $ELEMENT_ID
		);
		if ($cntSelect) {
			$arNavStartParams['nPageSize'] = (int)$cntSelect;
		}

		$rsList = CIBlockElement::GetList(
			$arSort,
			$arFilter,
			false,
			$arNavStartParams,
			$arFields
		);

		$arResult = array();

		while ($arItem = $rsList->GetNext()) {
			if ($arItem['ID'] != $ELEMENT_ID) {
				$arResult['PREV_NEXT'][$arItem['RANK']] = $arItem;
			} else {
				$arResult['CURRENT'] = $arItem;
			}
		}

		foreach ($arResult['PREV_NEXT'] as $key => $val) {
			if ($key < $arResult['CURRENT']['RANK']) {
				$arResult['PREV'][] = $val;
			}
			if ($key > $arResult['CURRENT']['RANK']) {
				$arResult['NEXT'][] = $val;
			}
		}

		return $arResult;
	}


	/**
	 * Выбор секции с детьми. В итоговый массив попадает и $sectionId
	 *
	 * @param int $sectionId
	 * @return array
	 */
	public static function selectSubsectionByParentSection($sectionId)
	{
		if ((int)$sectionId <= 0) {
			return false;
		}

		$SectBorders = \CIBlockSection::GetList(array("SORT" => "ASC"), array(
			"ACTIVE"    => "Y",
			"ID"      	=> $sectionId,
		), false, array('ID', 'IBLOCK_ID', 'LEFT_MARGIN', 'RIGHT_MARGIN', 'IBLOCK_ID'))->GetNext();

		$rsSections = \CIBlockSection::GetList(
			array( "SORT"=>"ASC" ),
			array(
				"ACTIVE"        => "Y",
				"IBLOCK_ID"     => $SectBorders['IBLOCK_ID'],
				">LEFT_MARGIN"  => $SectBorders["LEFT_MARGIN"],
				"<RIGHT_MARGIN" => $SectBorders["RIGHT_MARGIN"],
			)
		);
		$SectionIDS = array();
		while ($Section = $rsSections->GetNext()) {
			$SectionIDS[] = $Section["ID"];
		}

		return $SectionIDS;
	}

	 /**
	 * Добавляет фотки из ссылок, во множественное свойство типа файл
	 *
	 * @param $ID ID
	 * @param $IBLOCK_ID ID
	 * @param $arFiles Массив array('link.png'=>'description')
	 * @param $prop_code Код
	 *
	 * @return bool|string
	 */
	public function AddMultipleFileValue($ID, $IBLOCK_ID, $arFiles, $prop_code)
	{
		$result = true;

		$rsPr = CIBlockElement::GetProperty($IBLOCK_ID, $ID, ['sort', 'asc'], [
			'CODE' => $prop_code,
			'EMPTY' => 'N'
		]);
		while ($arPr = $rsPr->GetNext()) {
			$far = CFile::GetFileArray($arPr['VALUE']);
			$far = CFile::MakeFileArray($far['SRC']);
			$ar[] = array(
				'VALUE' => $far,
				'DESCRIPTION' => $arPr['DESCRIPTION']
			);
		}

		foreach ($arFiles as $l => $d) {
			$art = false;
			$gn = 0;
			while (!$art || ($art['type'] == 'unknown' && $gn < 10)) {
				$gn ++;
				$art = CFile::MakeFileArray($l);
			}
			if ($art['tmp_name']) {
				$ar[] = array(
					'VALUE' => $art,
					'DESCRIPTION' => $d
				);
			} else {
				$result = 'partial';
			}
		}
		\CIBlockElement::SetPropertyValuesEx($ID, $IBLOCK_ID, array(
			$prop_code => $ar
		));

		return $result;
	}

	/**
	 * По имени либо получает значение, либо если его нет - добавляет его в справочник.
	 *
	 * @param string $fieldVal = Значение, не может быть пустым (Dr.Pepper)
	 * @param string $iblockId = инентификатор справочника
	 *
	 * @retrun возвращается значение ID значения в справочнике
	 * @return bool|int
	 */
	static public function addToHelperAndReturnElementId($fieldVal, $iblockId)
	{
		$iblockId = (int)$iblockId;
		if (($fieldVal = trim($fieldVal)) == '' || $iblockId <= 0) {
			return false;
		}

		static $cacheProcess;
		$cacheKey = $fieldVal . '|' . $iblockId;

		if (isset($cacheProcess[$cacheKey])) {
			return $cacheProcess[$cacheKey];
		}

		$checkBase = self::checkElementExistsByNameOrCode($fieldVal, $iblockId, 'name');
		if ($checkBase) {
			$cacheProcess[$cacheKey] = $checkBase;
		} else {
			$r = self::addElementToDb([
				'ACTIVE'        => 'Y',
				'NAME'          => $fieldVal,
				'IBLOCK_ID'     => $iblockId,
				'CODE'          => \Hipot\Utils\UnsortedUtils::TranslitText($fieldVal) . '-' . randString(3)
			]);
			if ($r->STATUS != 'OK') {
				return false;
			}
			$cacheProcess[$cacheKey] = (int)$r->RESULT;
		}

		return $cacheProcess[$cacheKey];
	}

	/**
	 * Получить новое сео у элемента
	 *
	 * @param int $IBLOCK_ID
	 * @param int $ID
	 *
	 * @return array
	 */
	static function returnSeoValues($IBLOCK_ID, $ID)
	{
		$ipropValues = new \Bitrix\Iblock\InheritedProperty\ElementValues((int)$IBLOCK_ID, (int)$ID);
		return $ipropValues->getValues();
	}

	/**
	 * Установить новое сео у элементов
	 * @param int   $IBLOCK_ID
	 * @param int   $ID
	 * @param array $arTemplates (обычно в ключах ELEMENT_META_DESCRIPTION, ELEMENT_META_TITLE)
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 */
	static function setSeoValues($IBLOCK_ID, $ID, $arTemplates = [])
	{
		$ipropTemplates = new\Bitrix\Iblock\InheritedProperty\ElementTemplates((int)$IBLOCK_ID, (int)$ID);
		$ipropTemplates->set($arTemplates);
	}

	/**
	 * Установить секцию у элемента
	 *
	 * @param int $elId
	 * @param int $sectionId
	 *
	 * @return bool
	 */
	static function setGroupToElement($elId, $sectionId)
	{
		$elId = (int)$elId;
		$sectionId = (int)$sectionId;
		if ($elId <= 0 || $sectionId <= 0) {
			return false;
		}

		$db_old_groups = \CIBlockElement::GetElementGroups($elId, true);
		$ar_new_groups = [$sectionId];
		while ($ar_group = $db_old_groups->Fetch()) {
			$ar_new_groups[] = $ar_group["ID"];
		}
		\CIBlockElement::SetElementSection($elId, $ar_new_groups);
		return true;
	}

} // end class


