<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once 'data/CRMEntity.php';
require_once 'data/Tracker.php';

class Warehouse extends CRMEntity {
	public $db;
	public $log;

	public $table_name = 'vtiger_warehouse';
	public $table_index= 'warehouseid';
	public $column_fields = array();

	/** Indicator if this is a custom module or standard module */
	public $IsCustomModule = true;
	public $HasDirectImageField = false;
	public $moduleIcon = array('library' => 'standard', 'containerClass' => 'slds-icon_container slds-icon-standard-account', 'class' => 'slds-icon', 'icon'=>'account');

	/**
	 * Mandatory table for supporting custom fields.
	 */
	public $customFieldTable = array('vtiger_warehousecf', 'warehouseid');
	// Uncomment the line below to support custom field columns on related lists
	// public $related_tables = array('vtiger_warehousecf'=>array('warehouseid','vtiger_warehouse', 'warehouseid'));

	/**
	 * Mandatory for Saving, Include tables related to this module.
	 */
	public $tab_name = array('vtiger_crmentity', 'vtiger_warehouse', 'vtiger_warehousecf');

	/**
	 * Mandatory for Saving, Include tablename and tablekey columnname here.
	 */
	public $tab_name_index = array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_warehouse'   => 'warehouseid',
		'vtiger_warehousecf' => 'warehouseid');

	/**
	 * Mandatory for Listing (Related listview)
	 */
	public $list_fields = array(
		/* Format: Field Label => array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'Title'=> array('warehouse' => 'title'),
		'WarehouseNo' => array('warehouse' => 'warehno'),		
		'LBL_PHONE' => array('warehouse' => 'phone'),
		'Assigned To' => array('crmentity' => 'smownerid')
	);
	public $list_fields_name = array(
		/* Format: Field Label => fieldname */
		'Title' => 'title',
		'WarehouseNo' => 'warehno',
		'LBL_PHONE' => 'phone',
		'Assigned To' => 'assigned_user_id'
	);

	// Make the field link to detail view from list view (Fieldname)
	public $list_link_field = 'title';

	// For Popup listview and UI type support
	public $search_fields = array(
		/* Format: Field Label => array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'Title'=> array('warehouse' => 'title'),
		'WarehouseNo' => array('warehouse' => 'warehno'),
		'LBL_PHONE' => array('warehouse' => 'phone'),
	);
	public $search_fields_name = array(
		/* Format: Field Label => fieldname */
		'Title' => 'title',
		'WarehouseNo' => 'warehno',
		'LBL_PHONE' => 'phone',
	);

	// For Popup window record selection
	public $popup_fields = array('title');

	// Placeholder for sort fields - All the fields will be initialized for Sorting through initSortFields
	public $sortby_fields = array();

	// For Alphabetical search
	public $def_basicsearch_col = 'title';

	// Column value to use on detail view record text display
	public $def_detailview_recname = 'title';

	// Required Information for enabling Import feature
	public $required_fields = array();

	// Callback function list during Importing
	public $special_functions = array('set_import_assigned_user');

	public $default_order_by = 'title';
	public $default_sort_order='ASC';
	// Used when enabling/disabling the mandatory fields for the module.
	// Refers to vtiger_field.fieldname values.
	public $mandatory_fields = array('createdtime', 'modifiedtime', 'title');

	function getSortOrder() {
		global $currentModule;
		$sortorder = $this->default_sort_order;
		if($_REQUEST['sorder']) $sortorder = $this->db->sql_escape_string($_REQUEST['sorder']);
		else if($_SESSION[$currentModule.'_Sort_Order']) 
			$sortorder = $_SESSION[$currentModule.'_Sort_Order'];
		return $sortorder;
	}

	function getOrderBy() {
		global $currentModule;
		
		$use_default_order_by = '';		
		if(PerformancePrefs::getBoolean('LISTVIEW_DEFAULT_SORTING', true)) {
			$use_default_order_by = $this->default_order_by;
		}
		
		$orderby = $use_default_order_by;
		if($_REQUEST['order_by']) $orderby = $this->db->sql_escape_string($_REQUEST['order_by']);
		else if($_SESSION[$currentModule.'_Order_By'])
			$orderby = $_SESSION[$currentModule.'_Order_By'];
		return $orderby;
	}

public function save_module($module) {
		if ($this->HasDirectImageField) {
			$this->insertIntoAttachment($this->id, $module);
		}
	}

	function trash($module, $id) {
		global $adb;
		$warehno=$adb->getone("select warehno from vtiger_warehouse where warehouseid=$id");
		if ($warehno=='Purchase' or $warehno=='Sale') {
			die("<br><br><center>".getTranslatedString('CannotDelete','Warehouse')." <a href='javascript:window.history.back()'>".getTranslatedString('LBL_GO_BACK').".</a></center>");
		}
		parent::trash($module, $id);
	}


	/**
	 * Invoked when special actions are performed on the module.
	 * @param String Module name
	 * @param String Event Type (module.postinstall, module.disabled, module.enabled, module.preuninstall)
	 */
	public function vtlib_handler($modulename, $event_type) {
		if ($event_type == 'module.postinstall') {
			// TODO Handle post installation actions
			$this->setModuleSeqNumber('configure', $modulename, 'WH-', '0000001');
			global $current_user,$adb;
			// Product stock Readonly
			$adb->query("UPDATE vtiger_field SET displaytype='2' WHERE vtiger_field.columnname='qtyinstock' and tabid=14");
			$module=Vtiger_Module::getInstance($modulename);
			$module->addLink('HEADERSCRIPT', 'AddressCaptureFunctions', 'modules/Warehouse/Warehouse.js');
			Warehouse::addWarehouseFields();
		} elseif ($event_type == 'module.disabled') {
			// TODO Handle actions when this module is disabled.
		} elseif ($event_type == 'module.enabled') {
			// TODO Handle actions when this module is enabled.
		} elseif ($event_type == 'module.preuninstall') {
			// TODO Handle actions when this module is about to be deleted.
			// Product stock ReadWrite
			$adb->query("UPDATE vtiger_field SET displaytype='1' WHERE vtiger_field.columnname='qtyinstock' and tabid=14");
		} elseif ($event_type == 'module.preupdate') {
			// TODO Handle actions before this module is updated.
		} elseif ($event_type == 'module.postupdate') {
			// TODO Handle actions after this module is updated.
		}
	}

	static function addWarehouseFields() {
		// Turn on debugging level
		$Vtiger_Utils_Log = true;
		global $adb;
		include_once('vtlib/Vtiger/Module.php');

		$modSO = Vtiger_Module::getInstance('SalesOrder');
		if($modSO) {
			$block1= VTiger_Block::getInstance('LBL_SO_INFORMATION',$modSO);
			if($block1) {
				$field1 = new Vtiger_Field();
				$field1->name = 'whid';
				$field1->label= 'Warehouse';
				$field1->table = $modSO->basetable;
				$field1->column = 'whid';
				$field1->columntype = 'INT(11)';
				$field1->uitype = 10;
				$field1->displaytype = 1;
				$field1->typeofdata = 'I~M';
				$field1->presence = 0;
				$block1->addField($field1);
				$field1->setRelatedModules(Array('Warehouse'));
				echo "<br><b>Added Field ".$field1->name." to ".$modSO->name." module.</b><br>";
			} else {
				echo "<b>Failed to find ".$modSO->name." block</b><br>";
			}
		} else {
			echo "<b>Failed to find ".$modSO->name." module.</b><br>";
		}

		$modPO = Vtiger_Module::getInstance('PurchaseOrder');
		if($modPO) {
			$block1= VTiger_Block::getInstance('LBL_PO_INFORMATION',$modPO);
			if($block1) {
				$field1 = new Vtiger_Field();
				$field1->name = 'whid';
				$field1->label= 'Warehouse';
				$field1->table = $modPO->basetable;
				$field1->column = 'whid';
				$field1->columntype = 'INT(11)';
				$field1->uitype = 10;
				$field1->displaytype = 1;
				$field1->typeofdata = 'I~M';
				$field1->presence = 0;
				$block1->addField($field1);
				$field1->setRelatedModules(Array('Warehouse'));
				echo "<br><b>Added Field ".$field1->name." to ".$modPO->name." module.</b><br>";
			} else {
				echo "<b>Failed to find ".$modPO->name." block</b><br>";
			}
		} else {
			echo "<b>Failed to find ".$modPO->name." module.</b><br>";
		}

		$modInvoice= Vtiger_Module::getInstance('Invoice');
		if($modInvoice) {
			$block1= VTiger_Block::getInstance('LBL_INVOICE_INFORMATION',$modInvoice);
			if($block1) {
				$field1 = new Vtiger_Field();
				$field1->name = 'whid';
				$field1->label= 'Warehouse';
				$field1->table = $modInvoice->basetable;
				$field1->column = 'whid';
				$field1->columntype = 'INT(11)';
				$field1->uitype = 10;
				$field1->displaytype = 1;
				$field1->typeofdata = 'I~M';
				$field1->presence = 0;
				$block1->addField($field1);
				$field1->setRelatedModules(Array('Warehouse'));
				echo "<br><b>Added Field ".$field1->name." to ".$modInvoice->name." module.</b><br>";
			} else {
				echo "<b>Failed to find ".$modInvoice->name." block</b><br>";
			}
		} else {
			echo "<b>Failed to find ".$modInvoice->name." module.</b><br>";
		}

		$modQuote= Vtiger_Module::getInstance('Quotes');
		if($modQuote) {
			$block1= VTiger_Block::getInstance('LBL_QUOTE_INFORMATION',$modQuote);
			if($block1) {
				$field1 = new Vtiger_Field();
				$field1->name = 'whid';
				$field1->label= 'Warehouse';
				$field1->table = $modQuote->basetable;
				$field1->column = 'whid';
				$field1->columntype = 'INT(11)';
				$field1->uitype = 10;
				$field1->displaytype = 1;
				$field1->typeofdata = 'I~M';
				$field1->presence = 0;
				$block1->addField($field1);
				$field1->setRelatedModules(Array('Warehouse'));
				echo "<br><b>Added Field ".$field1->name." to ".$modQuote->name." module.</b><br>";
			} else {
				echo "<b>Failed to find ".$modQuote->name." block</b><br>";
			}
		} else {
			echo "<b>Failed to find ".$modQuote->name." module.</b><br>";
		}
	}

	/** 
	 * Handle saving related module information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	// public function save_related_module($module, $crmid, $with_module, $with_crmid) { }

	/**
	 * Handle deleting related module information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function delete_related_module($module, $crmid, $with_module, $with_crmid) { }

	/**
	 * Handle getting related list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function get_related_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }

	/**
	 * Handle getting dependents list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function get_dependents_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }
}
?>
