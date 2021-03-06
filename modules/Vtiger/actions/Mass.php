<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * Contributor(s): YetiForce.com
 * *********************************************************************************** */

abstract class Vtiger_Mass_Action extends Vtiger_Action_Controller
{

	public static function getRecordsListFromRequest(\App\Request $request)
	{
		$cvId = $request->isEmpty('viewname') ? '' : $request->getByType('viewname', 2);
		$module = $request->getByType('module');
		if (!empty($cvId) && $cvId == 'undefined' && $request->getByType('source_module') !== 'Users') {
			$sourceModule = $request->getByType('sourceModule', 1);
			$cvId = CustomView_Record_Model::getAllFilterByModule($sourceModule)->getId();
		}
		$selectedIds = $request->get('selected_ids');
		$excludedIds = $request->get('excluded_ids');

		if (!empty($selectedIds) && !in_array($selectedIds, ['all', '"all"'])) {
			if (!empty($selectedIds) && count($selectedIds) > 0) {
				return $selectedIds;
			}
		}

		$customViewModel = CustomView_Record_Model::getInstanceById($cvId);
		if ($customViewModel) {
			$searchKey = $request->getByType('search_key');
			$searchValue = $request->get('search_value');
			$operator = $request->getByType('operator');
			if (!empty($operator)) {
				$customViewModel->set('operator', $operator);
				$customViewModel->set('search_key', $searchKey);
				$customViewModel->set('search_value', $searchValue);
			}

			$customViewModel->set('search_params', $request->get('search_params'));
			return $customViewModel->getRecordIds($excludedIds, $module);
		}
	}

	public function validateRequest(\App\Request $request)
	{
		$request->validateWriteAccess();
	}
}
