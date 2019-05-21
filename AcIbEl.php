<?php
/**
 * ACtive InfoBlock ELement
 * Класс, реализующий ActiveRecord для элементов инфоблоков
 */
class AcIbEl {
	// Массив полей и их значений (включая свойства, имеющие префикс PROPERTY_)
	protected $_fields = null;
	
	// ID инфоблока, которому принадлежит элемент
	protected $_infoblockId = null;
	
	// Новый элемент или существующий
	protected $_isNew = true;
	
	
	/**
	 * Конструктор
	 *
	 * @param int|string $infoBlockIdOrCode ID инфоблока или его символьный код
	 * @return AcIbEl 
	 */
	public function __construct($infoBlockIdOrCode) {
		// Если $infoBlockIdOrCode является целым положительным числом
		if (is_int(infoBlockIdOrCode) && infoBlockIdOrCode > 0) {
			$this->_infoblockId = $infoBlockIdOrCode;
		}
		
		// Если $infoBlockIdOrCode является непустой строкой
		if (is_string($infoBlockIdOrCode) && $infoBlockIdOrCode) {
			$ibId = self::getInfoblockIdByCode($infoBlockIdOrCode);
			
			if (is_int($ibId) && $ibId > 0) {
				$this->_infoblockId = $ibId;
			}
		}
		
		// Если $infoBlockIdOrCode не удалось определить
		if (!$this->_infoblockId) {
			 $this->_infoblockId = null;
		}
		
		return $this;
	}
	
	
	/**
	 * Сеттер для задания значения поля или свойства элемента инфоблока
	 *
	 * @param string $fieldName Название свойства или поля
	 * @param mixed $fieldValue Значение свойства или поля
	 */
	public function __set($fieldName, $fieldValue) {
		// Если поле не находится в перечне разрешенных
		$allowedNames = $this->allowedNames();
		
		if (!in_array($fieldName, $allowedNames))
			return null;
		
		$this->_fields($fieldName) = $fieldValue;
	}
	
	
	/**
	 * Геттер для получения значения поля или свойства элемента инфоблока
	 *
	 * @param string $fieldName Название свойства или поля
	 * @return mixed Значение свойства или поля
	 */	
	public function __get($fieldName) {
		// Если поле не находится в перечне разрешенных
		$allowedNames = $this->allowedNames();
		
		if (!in_array($fieldName, $allowedNames))
			return null;
		
		return $this->_fields($fieldName);
	}
	
	
	/**
	 * Возвращает ID инфоблока по заданному символьному коду
	 *
	 * @param string $code Символьный код
	 * @return int|null 
	 */
	public static function getInfoblockIdByCode($code) {
		if (!is_string($code))
			return null;
		
		if ($code == "")
			return null;
		
		// Поиск первого инфоблока, имеющего символьный код $code
		$infoBlocks = CIBlock::GetList(array(), array("CODE" => $code), "CHECK_PERMISSIONS" => "N", true);
		
		// Если такой инфоблок найден
		if ($infoBlock = $infoBlocks->Fetch()) {
			if ($infoBlock["ID"] && ctype_digit($infoBlock["ID"])) {
				$ibId = (int)($infoBlock["ID"]);
				return $ibId;
			}
		}
		
		return null;
	}
	
	
	/**
	 * Ищет элемент инфоблока
	 *
	 * @param array $params Массив параметров для поиска
	 * @return AcIbEl Экземпляр AcIbEl
	 */
	public function find($params) {
		// Если ID инфоблока не задан и не является числом
		if (!($this->_infoblockId && is_int($this->_infoblockId))) {
			return $this;
		}
		
		// $params должен быть массивом
		if (!is_array($params))
			$params = array();
		
		// Установка значений по-умолчанию
		// Если параметр ACTIVE не задан или пустой, то по-умолчанию устанавливается значение "Y"
		if (!isset($params['ACTIVE']) || !$params['ACTIVE'])
			$params['ACTIVE'] = 'Y';
		
		$params['IBLOCK_ID'] = $this->_infoblockId;
		
		// Поиск элемента инфоблока
		$elements = CIBlockElement::GetList(
			array(),
			$params,
			false,
			array("nPageSize" => 1),
			null
		);
		
		// Если элемент найден
		if ($element = $elements->GetNextElement()) {
			$result = array();
			
			// Перебор полей
			foreach($element->GetFields() as $fieldName => $fieldValue) {
				if (
					substr($fieldName, 0, 1) != "~" &&
					substr($fieldName, -6, 6) != "_VALUE" &&
					substr($fieldName, -9, 9) != "_VALUE_ID" &&
				) {
					$result[$fieldName] = $fieldValue;
				}
			}
			
			// Перебор свойств
			foreach($element->GetProperties() as $propertyName => $propertyValue) {
				$result["PROPERTY_".$propertyName] = $propertyValue["VALUE"];
			}
			
			$this->_fields = $result;
			$this->_isNew = false;
			
			$res = clone $this;
			
			return $res;
		}
	}
	
	
	/**
	 * Сохраняет элемент инфоблока в БД с текущими значениями полей и свойств
	 *
	 * @return bool Результат обновления/сохранения элемента
	 */
	public function save() {
		if (!$this->_infoblockId)
			return null;
		
		$arrayToSave = array();
		
		foreach($this->_fields as $fieldName => $fieldValue) {
			// Если поле
			if (substr($fieldname, 0, 9) != "PROPERTY_") {
				$arrayToSave[$fieldName] = $fieldValue;
			// Если свойство
			} else {
				if (!is_array($arrayToSave["PROPERTY_VALUES"]))
					$arrayToSave["PROPERTY_VALUES"] = array();
				
				$arrayToSave["PROPERTY_VALUES"][substr($fieldName, 9)] = $fieldValue
			}
			
		}
		
		$arrayToSave["IBLOCK_ID"] = $this->_infoblockId;
		
		$el = new CIBlockElement;
		
		if ($this->_isNew) {
			$elId = $el->Add($arrayToSave);
			
			if ($elId)
				$this->_isNew = false;
			
			return ($elId !== false);
		} else {
			return $el->Update($this->ID, $arrayToSave);
		}
	}
	
	
	/**
	 * Возвращает допустимые наименования полей элемента инфоблока
	 *
	 * @return array Массив допустимых наименований полей
	 */
	protected function allowedFieldNames() {
		return array(
			"ID", "CODE", "EXTERNAL_ID", "XML_ID", "IBLOCK_ID", "IBLOCK_SECTION_ID",
			"IBLOCK_CODE", "ACTIVE", "ACTIVE_FROM", "ACTIVE_TO", "SORT", 
			"PREVIEW_PICTURE", "PREVIEW_TEXT", "PREVIEW_TEXT_TYPE", "DETAIL_PICTURE", "DETAIL_TEXT", 
			"DETAIL_TEXT_TYPE", "SEARCHABLE_CONTENT", "DATE_CREATE", "CREATED_BY", "CREATED_USER_NAME", 
			"TIMESTAMP_X", "MODIFIED_BY", "USER_NAME", "LANG_DIR", "LIST_PAGE_URL", "DETAIL_PAGE_URL",
			"SHOW_COUNTER", "SHOW_COUNTER_START", "WF_COMMENTS", "WF_STATUS_ID", "LOCK_STATUS", "TAGS"
		);
	}
	
	
	/**
	 * Возвращает допустимые наименования свойств элемента инфоблока
	 *
	 * @return array Массив допустимых наименований свойств
	 */
	protected function allowedPropertiesNames() {
		if (!$this->_infoblockId)
			return array();
		
		$properties = CIBlockProperty::GetList(
			array("sort" => "asc", "name" => "asc"),
			array("ACTIVE" => "Y", "IBLOCK_ID" => $this->_infoblockId)
		);
		
		$result = array();
		
		while($prop_fields = $properties->GetNext()) {
			$result[] = "PROPERTY_".$prop_fields["CODE"];
		}
		
		return $result;
	}
	
	
	/**
	 * Возвращает допустимые наименования полей и свойств элемента инфоблока
	 * @return array Массив допустимых наименований полей и свойств
	 */
	protected function allowedNames() {
		return array_merge($this->allowedFieldNames(), $this->allowedPropertiesNames());
	}
}