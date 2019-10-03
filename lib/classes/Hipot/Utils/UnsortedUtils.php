<?
namespace Hipot\Utils;

use \Bitrix\Main\Application,
	\Bitrix\Main\Grid\Declension,
	\Bitrix\Main\Loader;

/**
 * Различные не-структурированные утилиты
 *
 * @version 1.0
 * @author hipot studio
 */
class UnsortedUtils
{
	/**
	 * Возвращает слово с правильным суффиксом
	 *
	 * @param (int) $n - количество
	 * @param (array|string) $str - строка 'один|два|несколько' или 'слово|слова|слов'
	 *      или массив с такой же историей
	 * @return string
	 */
	public static function Suffix($n, $forms): string
	{
		if (is_string($forms)) {
			$forms = explode('|', $forms);
		}
		$declens = new Declension($forms[0], $forms[1], $forms[2]);
		return $declens->get($n);
	}

	/**
	 * Транслит в одну строку
	 * @param        $text
	 * @param string $lang
	 *
	 * @return string
	 */
	function TranslitText($text, $lang = 'ru'): string
	{
		return \CUtil::translit(trim($text), $lang, array(
			'max_len' => 100,
			'change_case' => "L",
			'replace_space' => '-',
			'replace_other' => '-',
			'delete_repeat_replace' => true
		));
	}

	/**
	 * Функция определения кодировки строки
	 * Удобно для автоматического определения кодировки csv-файла
	 *
	 * Почему не mb_detect_encoding()? Если кратко — он не работает.
	 *
	 * @param string $string строка в неизвестной кодировке
	 * @param int $pattern_size = 50
	 *        если строка больше этого размера, то определение кодировки будет
	 *        производиться по шаблону из $pattern_size символов, взятых из середины
	 *        переданной строки. Это сделано для увеличения производительности на больших текстах.
	 * @return string 'cp1251' 'utf-8' 'ascii' '855' 'KOI8R' 'ISO-IR-111' 'CP866' 'KOI8U'
	 *
	 * @see http://habrahabr.ru/post/107945/
	 * @see http://forum.dklab.ru/viewtopic.php?t=37833
	 * @see http://forum.dklab.ru/viewtopic.php?t=37830
	 */
	public static function detect_encoding($string, $pattern_size = 50): string
	{
		$list = array(
			'cp1251',
			'utf-8',
			'ascii',
			'855',
			'KOI8R',
			'ISO-IR-111',
			'CP866',
			'KOI8U'
		);
		$c = strlen($string);
		if ($c > $pattern_size) {
			$string = substr($string, floor(($c - $pattern_size) / 2), $pattern_size);
			$c = $pattern_size;
		}

		$reg1 = '/(\xE0|\xE5|\xE8|\xEE|\xF3|\xFB|\xFD|\xFE|\xFF)/i';
		$reg2 = '/(\xE1|\xE2|\xE3|\xE4|\xE6|\xE7|\xE9|\xEA|\xEB|\xEC|\xED|\xEF|\xF0|\xF1|\xF2|\xF4|\xF5|\xF6|\xF7|\xF8|\xF9|\xFA|\xFC)/i';

		$mk = 10000;
		$enc = 'ascii';
		foreach ($list as $item) {
			$sample1 = @iconv($item, 'cp1251', $string);
			$gl = @preg_match_all($reg1, $sample1, $arr);
			$sl = @preg_match_all($reg2, $sample1, $arr);
			if (!$gl || !$sl) {
				continue;
			}
			$k = abs(3 - ($sl / $gl));
			$k += $c - $gl - $sl;
			if ($k < $mk) {
				$enc = $item;
				$mk = $k;
			}
		}
		return $enc;
	}

	/**
	 * Является ли текущая страница в данный момент страницей постраничной навигации
	 * @return bool
	 * @throws \Bitrix\Main\SystemException
	 */
	public static function isPageNavigation(): bool
	{
		$request = Application::getInstance()->getContext()->getRequest();
		foreach ([1, 2, 3] as $pnCheck) {
			$req_name = 'PAGEN_' . $pnCheck;
			if ((int)$request->getPost($req_name) > 1 || (int)$request->getQuery($req_name) > 1) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Выполняет команду в OS в фоне и без получения ответа
	 *
	 * @param string $cmd команда на выполнение
	 * @see exec()
	 * @return NULL
	 */
	public static function execInBackground($cmd): void
	{
		if (strpos(php_uname(), "Windows") === 0) {
			pclose(popen("start /B " . $cmd, "r"));
		} else {
			exec($cmd . " > /dev/null &");
		}
	}

	public static function getPhpPath(): string
	{
		$phpPath = 'php';
		if (! defined('BX_UTF')) {
			$phpPath .= ' -d mbstring.func_overload=0 -d mbstring.internal_encoding=CP1251 ';
		} else {
			$phpPath .= ' -d mbstring.func_overload=2 -d mbstring.internal_encoding=UTF-8 ';
		}
		return $phpPath;
	}

	/**
	 * Пересекаются ли времена заданные unix-таймштампами.
	 *
	 * Решение сводится к проверке границ одного отрезка на принадлежность другому отрезку
	 * и наоборот. Достаточно попадания одной точки.
	 *
	 * @param int $left1_ts
	 * @param int $right1_ts
	 * @param int $left2_ts
	 * @param int $right2_ts
	 * @return boolean
	 */
	public static function IsIntervalsTsIncl($left1_ts, $right1_ts, $left2_ts, $right2_ts): bool
	{
		// echo $left1_ts . ' ' . $right1_ts . ' ' . $left2_ts . ' ' . $right2_ts . '<br />';
		if ($left1_ts <= $left2_ts) {
			return $right1_ts >= $left2_ts;
		} else {
			return $left1_ts <= $right2_ts;
		}
	}
	
	/**
	 * Получить список колонок SQL-запросом, либо если уже был получен, то просто вернуть
	 * @param string $tableName имя таблицы
	 * @return array
	 */
	public static function getTableFieldsFromDB($tableName): array
	{
		$a = [];
		if (trim($tableName) != '') {
			$query	= "SHOW COLUMNS FROM " . $tableName;
			$res	= $GLOBALS['DB']->Query($query);

			while ($row = $res->Fetch()) {
				$a[] = $row['Field'];
			}
		}
		return $a;
	}

	/**
	 * Получить содержимое по урлу
	 * @param $url
	 * @return bool|false|string
	 */
	public static function getPageContentByUrl($url)
	{
		$el = new \Bitrix\Main\Web\HttpClient();
		$cont = $el->get( $url );
		return $cont;
	}

	/**
	 * Возвращает размер удаленного файла
	 *
	 * @param $path Путь к удаленному файлу
	 * @return int | bool
	 */
	public static function remote_filesize($path)
	{
		preg_match('#(ht|f)tp(s)?://(?P<host>[a-zA-Z-_]+.[a-zA-Z]{2,4})(?P<name>/[\S]+)#', $path, $m);
		$x = 0;
		$stop = false;
		$fp = fsockopen($m['host'], 80, &$errno, &$errstr, 30);
		fwrite($fp, "HEAD $m[name] HTTP/1.0\nHOST: $m[host]\n\n");
		while (!feof($fp) && !$stop) {
			$y = fgets($fp, 2048);
			if ($y == "\r\n") {
				$stop = true;
			}
			$x .= $y;
		}
		fclose($fp);

		if (preg_match("#Content-Length: ([0-9]+)#", $x, $size)) {
			return $size[1];
		} else {
			return false;
		}
	}

	/**
	 * Добавить в модуль веб-формы в форму данные
	 *
	 * @param int   $WEB_FORM_ID id формы, для которой пришел ответ
	 * @param array $arrVALUES = <pre>array (
	 * [WEB_FORM_ID] => 3
	 * [web_form_submit] => Отправить
	 *
	 * [form_text_18] => aafafsfasdf
	 * [form_text_19] => q1241431342
	 * [form_text_21] => afsafasdfdsaf
	 * [form_textarea_20] =>
	 * [form_text_22] => fasfdfasdf
	 * [form_text_23] => 31243123412впывапвыапывпыв аывпывпыв
	 *
	 * 18, 19, 21 - ID ответов у вопросов https://yadi.sk/i/_9fwfZMvO2kblA
	 * )</pre>
	 *
	 * @return bool | UpdateResult
	 * @throws \Bitrix\Main\LoaderException
	 */
	public static function formResultAddSimple($WEB_FORM_ID, $arrVALUES)
	{
		if (! Loader::includeModule('form')) {
			return false;
		}

		// add result bitrix:form.result.new
		$arrVALUES['WEB_FORM_ID'] = (int)$WEB_FORM_ID;
		if ($arrVALUES['WEB_FORM_ID'] <= 0) {
			return false;
		}
		$arrVALUES["web_form_submit"] = "Отправить";

		/*$arrVALUES = */

		if ($RESULT_ID = \CFormResult::Add($WEB_FORM_ID, $arrVALUES)) {
			// send email notifications
			\CFormCRM::onResultAdded($WEB_FORM_ID, $RESULT_ID);
			\CFormResult::SetEvent($RESULT_ID);
			\CFormResult::Mail($RESULT_ID);

			if ($RESULT_ID) {
				return new UpdateResult(['RESULT' => $RESULT_ID,			     'STATUS' => 'OK']);
			} else {
				return new UpdateResult(['RESULT' => 'Не опознанная ошибка',  'STATUS' => 'ERROR']);
			}
		}
	}


} // end class


