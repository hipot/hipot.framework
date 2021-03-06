<?php
/**
 * hipot studio source file
 * User: <hipot AT ya DOT ru>
 * Date: 13.04.2021 17:03
 * @version pre 1.0
 */
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Highloadblock as HL;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Entity\DataManager;

/**
 * Уникальный компонент списка из Hl-блока
 */
class hiBlockListComponent extends CBitrixComponent
{
	public const CACHE_TTL = 3600 * 24;

	/**
	 * @var \Bitrix\Main\Entity\DataManager|null
	 */
	private ?DataManager $entity_class;

	/**
	 * <pre>
	 * HLBLOCK_ID int   or:
	 * HLBLOCK_CODE string
	 *
	 * ORDER DEF: ["ID" => "DESC"]
	 * SELECT DEF: [ID, *]
	 * FILTER
	 * PAGESIZE DEF:10
	 * NTOPCOUNT
	 * GROUP_BY
	 * NAV_SHOW_ALWAYS = Y/N DEF: N
	 * NAV_TITLE
	 * NAV_TEMPLATE
	 * SET_CACHE_KEYS = []
	 * SET_404 = Y/N DEF: N
	 * ALWAYS_INCLUDE_TEMPLATE = Y/N DEF: N
	 * </pre>
	 *
	 * @param $arParams
	 * @return array|void
	 */
	public function onPrepareComponentParams($arParams)
	{
		\CpageOption::SetOptionString("main", "nav_page_in_session", "N");

		$arParams['PAGEN_1']			    = (int)$_REQUEST['PAGEN_1'];
		$arParams['SHOWALL_1']			    = (int)$_REQUEST['SHOWALL_1'];
		$arParams['NAV_TEMPLATE']		    = (trim($arParams['NAV_TEMPLATE']) != '') ? $arParams['NAV_TEMPLATE'] : '';
		$arParams['NAV_SHOW_ALWAYS']	    = (trim($arParams['NAV_SHOW_ALWAYS']) == 'Y') ? 'Y' : 'N';

		return $arParams;
	}

	public function executeComponent()
	{
		global $USER_FIELD_MANAGER;

		// simplifier )
		$arParams     = &$this->arParams;
		$arResult     = &$this->arResult;
		$entity_class = &$this->entity_class;

		$requiredModules = ['highloadblock', 'iblock'];
		foreach ($requiredModules as $requiredModule) {
			if (! CModule::IncludeModule($requiredModule)) {
				ShowError($requiredModule . " not inslaled and required!");
				return 0;
			}
		}
		if ($this->startResultCache(false)) {
			// hlblock info
			$hlblock_id     = $arParams['HLBLOCK_ID'];
			$hlblock_code   = $arParams['HLBLOCK_CODE'];

			if (is_numeric($hlblock_id)) {
				$hlblock    = HighloadBlockTable::getByPrimary($hlblock_id, ['cache' => ["ttl" => self::CACHE_TTL]])->fetch();
			} else if (trim($hlblock_code) != '') {
				$hlblock	= HighloadBlockTable::getList([
					'filter'    => ['NAME' => $hlblock_code],
					'cache'     => ["ttl" => self::CACHE_TTL]
				])->fetch();
			} else {
				ShowError('cant init HL-block');
				$this->abortResultCache();
				return 0;
			}

			$entity_class = HighloadBlockTable::compileEntity( $hlblock );

			if (empty($entity_class)) {
				if ($arParams["SET_404"] == "Y") {
					include $_SERVER["DOCUMENT_ROOT"] . "/404_inc.php";
				}
				ShowError('404 HighloadBlock not found');
				return 0;
			}

			// region parameters
			// sort
			if ($arParams["ORDER"]) {
				$arOrder = $arParams["ORDER"];
			} else {
				$arOrder = ["ID" => "DESC"];
			}

			// limit
			$limit = [
				'nPageSize' => (int)$arParams["PAGESIZE"] > 0 ? (int)$arParams["PAGESIZE"] : 10,
				'iNumPage' => is_set($arParams['PAGEN_1']) ? $arParams['PAGEN_1'] : 1,
				'bShowAll' => $arParams['NAV_SHOW_ALL'] == 'Y',
				'nPageTop' => (int)$arParams["NTOPCOUNT"]
			];

			$arSelect = ["*"];
			if (!empty($arParams["SELECT"])) {
				$arSelect = $arParams["SELECT"];
				$arSelect[] = "ID";
			}

			$arFilter = [];
			if (!empty($arParams["FILTER"])) {
				$arFilter = $arParams["FILTER"];
			}

			$arGroupBy = [];
			if (!empty($arParams["GROUP_BY"])) {
				$arGroupBy = $arParams["GROUP_BY"];
			}
			// endregion

			$result = $entity_class::getList([
				"order"     => $arOrder,
				"select"    => $arSelect,
				"filter"    => $arFilter,
				"group"     => $arGroupBy,
				"limit"     => $limit["nPageTop"] > 0 ? $limit["nPageTop"] : 0,
			]);

			// region pager
			if ($limit["nPageTop"] <= 0) {
				$result = new \CDBResult($result);
				$result->NavStart($limit, false, true);

				$arResult["NAV_STRING"] = $result->GetPageNavStringEx(
					$navComponentObject,
					$arParams["NAV_TITLE"],
					$arParams["NAV_TEMPLATE"]
				);
				$arResult["NAV_CACHED_DATA"] = $navComponentObject->GetTemplateCachedData();
				$arResult["NAV_RESULT"] = $result;
			}
			// endregion

			// build results
			$arResult["ITEMS"] = [];

			// uf info
			$fields = $USER_FIELD_MANAGER->GetUserFields('HLBLOCK_' . $hlblock['ID'], 0, LANGUAGE_ID);

			while ($row = $result->Fetch()) {
				foreach ($row as $k => $v) {
					if ($k == "ID") {
						continue;
					}
					$arUserField = $fields[$k];

					/* @see https://dev.1c-bitrix.ru/api_help/iblock/classes/user_properties/GetAdminListViewHTML.php */
					$html = call_user_func(
						[$arUserField["USER_TYPE"]["CLASS_NAME"], "GetAdminListViewHTML"],
						$arUserField,
						[
							"NAME"      => "FIELDS[" . $row['ID'] . "][" . $arUserField["FIELD_NAME"] . "]",
							"VALUE"     => htmlspecialcharsbx($v)
						]
					);
					if ($html == '') {
						$html = '&nbsp;';
					}

					$row[$k] = $html;
					$row["~" . $k] = $v;
				}
				$arResult["ITEMS"][] = $row;
			}

			if (count($arResult["ITEMS"]) > 0) {
				// добавили сохранение ключей по параметру
				$arSetCacheKeys = [];
				if (is_array($arParams['SET_CACHE_KEYS'])) {
					$arSetCacheKeys = $arParams['SET_CACHE_KEYS'];
				}
				$this->setResultCacheKeys($arSetCacheKeys);
			} else {
				if ($arParams["SET_404"] == "Y") {
					include $_SERVER["DOCUMENT_ROOT"] . "/404_inc.php";
				}
				$this->abortResultCache();
			}

			if (count($arResult["ITEMS"]) > 0 || $arParams["ALWAYS_INCLUDE_TEMPLATE"] == "Y") {
				$this->includeComponentTemplate();
			}
		}

		// IF NEED SOME USE WITH "SET_CACHE_KEYS"-params
		return $arResult;
	}
}