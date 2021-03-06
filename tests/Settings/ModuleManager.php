<?php

/**
 * ModuleManager test class
 * @package YetiForce.Test
 * @copyright YetiForce Sp. z o.o.
 * @license YetiForce Public License 2.0 (licenses/License.html or yetiforce.com)
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */
class ModuleManager extends \Tests\Base
{

	/**
	 * Zip file name
	 * @var string
	 */
	private static $zipFileName;

	/**
	 * Block id
	 * @var int
	 */
	private static $blockId;

	/**
	 * Array of fields id
	 * @var array()
	 */
	private static $fieldsId;

	/**
	 * Id for field extra
	 * @var array()
	 */
	private static $fieldsExtraId;

	/**
	 * Tables name for uitype: 16, 15
	 * @var array()
	 */
	private static $tablesName;

	/**
	 * Id for picklist
	 * @var array()
	 */
	private static $pickList;

	/**
	 * Testing language exports
	 * *****
	 */
	public function testLanguageExport()
	{
		$package = new \vtlib\LanguageExport();
		$package->exportLanguage('pl_pl', ROOT_DIRECTORY . '/PL.zip', 'PL.zip');
		$this->assertTrue(file_exists(ROOT_DIRECTORY . '/PL.zip') && filesize(ROOT_DIRECTORY . '/PL.zip') > 0);
		unlink(ROOT_DIRECTORY . '/PL.zip');
	}

	/**
	 * Testing the module creation
	 */
	public function testCreateModule()
	{
		$moduleManagerModel = new \Settings_ModuleManager_Module_Model();
		$moduleManagerModel->createModule([
			'module_name' => 'Test',
			'entityfieldname' => 'test',
			'module_label' => 'Test',
			'entitytype' => 1,
			'entityfieldlabel' => 'Test',
		]);
		$this->assertFileExists(ROOT_DIRECTORY . '/modules/Test/Test.php');
		$langFileToCheck = $this->getLangPathToFile('Test.php');
		foreach ($langFileToCheck as $pathToFile) {
			$this->assertFileExists($pathToFile);
		}
		$this->assertTrue((new \App\Db\Query())->from('vtiger_tab')->where(['name' => 'Test'])->exists());
	}

	/**
	 * Testing the creation of a new block for the module
	 */
	public function testCreateNewBlock()
	{
		$moduleModel = Settings_LayoutEditor_Module_Model::getInstanceByName('Test');
		$blockInstance = new Settings_LayoutEditor_Block_Model();
		$blockInstance->set('label', 'label block');
		$blockInstance->set('iscustom', 1);
		static::$blockId = $blockInstance->save($moduleModel);

		$row = (new \App\Db\Query())->from('vtiger_blocks')->where(['blockid' => static::$blockId])->one();
		$this->assertNotFalse($row, 'No record id: ' . static::$blockId);
		$this->assertEquals($row['blocklabel'], 'label block');
		$this->assertEquals($row['iscustom'], 1);
	}

	/**
	 * Testing the creation of a new field for the module
	 * @param string $type
	 * @param array $param
	 * @dataProvider providerForField
	 */
	public function testCreateNewField($type, $param, $suffix = '')
	{
		$key = $type . $suffix;
		$param['fieldType'] = $type;
		$param['fieldLabel'] = $type . 'FieldLabel' . $suffix;
		$param['fieldName'] = strtolower($type . 'FieldLabel' . $suffix);
		$param['blockid'] = static::$blockId;
		$param['sourceModule'] = 'Test';

		$moduleModel = Settings_LayoutEditor_Module_Model::getInstanceByName($param['sourceModule']);
		$fieldModel = $moduleModel->addField($param['fieldType'], static::$blockId, $param);
		static::$fieldsId[$key] = $fieldModel->getId();
		$details = $moduleModel->getTypeDetailsForAddField($type, $param);

		$row = (new \App\Db\Query())->from('vtiger_field')->where(['fieldid' => static::$fieldsId[$key], 'tabid' => $moduleModel->getId()])->one();
		$this->assertNotFalse($row, 'No record id: ' . static::$fieldsId[$key]);
		$this->assertEquals($row['fieldname'], $param['fieldName']);
		$this->assertEquals($row['fieldlabel'], $param['fieldLabel']);
		$this->assertEquals($row['typeofdata'], $details['typeofdata']);
		$this->assertEquals($row['uitype'], $details['uitype']);

		$this->assertTrue((new \App\Db\Query())->from('vtiger_def_org_field')->where(['fieldid' => static::$fieldsId[$key], 'tabid' => $moduleModel->getId()])->exists(), 'No record in the table "vtiger_def_org_field" for type ' . $type);

		$profilesId = \vtlib\Profile::getAllIds();
		$this->assertCount((new \App\Db\Query())->from('vtiger_profile2field')->where(['fieldid' => static::$fieldsId[$key]])->count(), $profilesId, "The field \"$type\" did not add correctly to the profiles");

		switch ($row['uitype']) {
			case 11: //Phone
				$rowExtra = (new \App\Db\Query())->from('vtiger_field')->where(['fieldname' => $param['fieldName'] . '_extra'])->one();
				$this->assertNotFalse($rowExtra, 'No "extra" record for uitype: ' . $row['uitype']);
				$this->assertCount((new \App\Db\Query())->from('vtiger_profile2field')->where(['fieldid' => $rowExtra['fieldid']])->count(), $profilesId, "The \"extra\" field \"$type\" did not add correctly to the profiles");
				static::$fieldsExtraId[$key] = $rowExtra['fieldid'];
				break;
			case 10: //Related1M
				$this->assertCount((new \App\Db\Query())->from('vtiger_fieldmodulerel')->where(['fieldid' => static::$fieldsId[$key]])->count(), $param['referenceModule'], 'Problem with table "vtiger_fieldmodulerel" in database');
				break;
			case 16: //Picklist
				static::$tablesName[$key] = 'vtiger_' . $param['fieldName'];
				$this->assertNotNull(\App\Db::getInstance()->getTableSchema(static::$tablesName[$key]), 'Table "' . static::$tablesName[$key] . '" does not exist');
				$this->assertCount(0, array_diff($param['pickListValues'], (new \App\Db\Query())->select($param['fieldName'])->from(static::$tablesName[$key])->column()), 'Bad values in the table "' . static::$tablesName[$key] . '"');
				break;
			case 15: //Picklist
			case 33: //MultiSelectCombo
				static::$tablesName[$key] = 'vtiger_' . $param['fieldName'];
				$this->assertNotNull(\App\Db::getInstance()->getTableSchema(static::$tablesName[$key]), 'Table "' . static::$tablesName[$key] . '" does not exist');
				$this->assertCount(0, array_diff($param['pickListValues'], (new \App\Db\Query())->select($param['fieldName'])->from(static::$tablesName[$key])->column()), 'Bad values in the table "' . static::$tablesName[$key] . '"');

				$rowPicklist = (new App\Db\Query())->from('vtiger_picklist')->where(['name' => $param['fieldName']])->one();
				static::$pickList[$key] = $param['pickListValues'];
				$this->assertNotFalse($rowPicklist, 'The record from "vtiger_picklist" not exists NAME: ' . $param['fieldName']);

				$this->assertEquals((new \App\Db\Query)->from('vtiger_role')->count() * count($param['pickListValues']), (new \App\Db\Query)->from('vtiger_role2picklist')->where(['picklistid' => $rowPicklist['picklistid']])->count(), 'Wrong number of rows in the table "vtiger_role2picklist"');
				break;
		}
	}

	/**
	 * Data provider for testCreateNewField and testDeleteNewField
	 * @return array
	 * @codeCoverageIgnore
	 */
	public function providerForField()
	{
		return [
			['Text', ['fieldTypeList' => 0, 'fieldLength' => 12]],
			['Decimal', ['fieldTypeList' => 0, 'fieldLength' => 6, 'decimal' => 2]],
			['Integer', ['fieldTypeList' => 0, 'fieldLength' => 2]],
			['Percent', ['fieldTypeList' => 0]],
			['Currency', ['fieldTypeList' => 0, 'fieldLength' => 4, 'decimal' => 3]],
			['Date', ['fieldTypeList' => 0]],
			['Email', ['fieldTypeList' => 0]],
			['URL', ['fieldTypeList' => 0]],
			['Checkbox', ['fieldTypeList' => 0]],
			['TextArea', ['fieldTypeList' => 0]],
			['Skype', ['fieldTypeList' => 0]],
			['Time', ['fieldTypeList' => 0]],
			['Editor', ['fieldTypeList' => 0]],
			['Phone', ['fieldTypeList' => 0]],
			['Related1M', ['fieldTypeList' => 0, 'referenceModule' => ['Contacts', 'Accounts', 'Leads'],]],
			['Picklist', ['fieldTypeList' => 0, 'pickListValues' => ['a1', 'a2', 'a3'],]],
			['Picklist', ['fieldTypeList' => 0, 'pickListValues' => ['b1', 'b2', 'b3'], 'isRoleBasedPickList' => 1], '2'],
			['MultiSelectCombo', ['fieldTypeList' => 0, 'pickListValues' => ['c1', 'c2', 'c3']]],
		];
	}

	/**
	 * Testing the deletion of a new field text for the module
	 * @link https://phpunit.de/manual/3.7/en/writing-tests-for-phpunit.html#writing-tests-for-phpunit.data-providers
	 * @dataProvider providerForField
	 * *****
	 */
	public function testDeleteNewField($type, $param, $suffix = '')
	{
		$key = $type . $suffix;
		$fieldInstance = Settings_LayoutEditor_Field_Model::getInstance(static::$fieldsId[$key]);
		$uitype = $fieldInstance->getUIType();
		$columnName = $fieldInstance->getColumnName();
		$this->assertTrue($fieldInstance->isCustomField(), 'Field is not customized');
		$fieldInstance->delete();

		$this->assertFalse((new App\Db\Query())->from('vtiger_field')->where(['fieldid' => static::$fieldsId[$key]])->exists(), 'The record was not removed from the database ID: ' . static::$fieldsId[$key]);

		switch ($uitype) {
			case 11: //Phone
				$this->assertFalse((new App\Db\Query())->from('vtiger_field')->where(['fieldid' => static::$fieldsExtraId[$key]])->exists(), 'The record "extra" was not removed from the database ID: ' . static::$fieldsExtraId[$key]);
				break;
			case 10: //Related1M
				$this->assertEquals((new \App\Db\Query())->from('vtiger_fieldmodulerel')->where(['fieldid' => static::$fieldsId[$key]])->count(), 0, 'Problem with table "vtiger_fieldmodulerel" in database');
				break;
			case 16: //Picklist
				$this->assertNull(\App\Db::getInstance()->getTableSchema(static::$tablesName[$key]), 'Table "' . static::$tablesName[$key] . '" exist');
				break;
			case 15: //Picklist
			case 33: //MultiSelectCombo
				$this->assertNull(\App\Db::getInstance()->getTableSchema(static::$tablesName[$key]), 'Table "' . static::$tablesName[$key] . '" exist');
				$this->assertFalse((new App\Db\Query())->from('vtiger_picklist')->where(['name' => $columnName])->exists(), 'The record from "vtiger_picklist" was not removed from the database ID: ' . static::$fieldsExtraId[$key]);

				$this->assertEquals(0, (new \App\Db\Query)->from('vtiger_role2picklist')->where(['picklistid' => static::$pickList[$key]])->count(), 'All rows in the table "vtiger_role2picklist" have not been deleted');
				break;
		}
	}

	/**
	 * Testing the deletion of a new block for the module
	 * *****
	 */
	public function testDeleteNewBlock()
	{
		$this->assertFalse(Vtiger_Block_Model::checkFieldsExists(static::$blockId), 'Fields exists');
		$blockInstance = Vtiger_Block_Model::getInstance(static::$blockId);
		$this->assertTrue($blockInstance->isCustomized(), 'Block is not customized');
		$blockInstance->delete(false);
	}

	/**
	 * Testing module export
	 * *****
	 */
	public function testExportModule()
	{
		$moduleModel = \vtlib\Module::getInstance('Test');
		$this->assertTrue($moduleModel->isExportable(), 'Module not exportable!');
		$packageExport = new vtlib\PackageExport();

		$packageExport->export($moduleModel, '', '', false);
		static::$zipFileName = $packageExport->getZipFileName();
		$this->assertFileExists(static::$zipFileName);

		$package = new vtlib\Package();
		$this->assertEquals('Test', $package->getModuleNameFromZip(static::$zipFileName));

		$zip = new \App\Zip(static::$zipFileName, ['checkFiles' => false]);
		$zipFiles = [];
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$fileName = $zip->getNameIndex($i);
			$zipFiles[] = $fileName;
		}
		$zip->close();
		$this->assertContains('manifest.xml', $zipFiles);
		$this->assertContains('modules/Test/Test.php', $zipFiles);

		$langFileToCheck = $this->getLangPathToFile('Test.php');
		foreach ($langFileToCheck as $pathToFile) {
			$pathToFile = str_replace('./', '', $pathToFile);
			$this->assertContains($pathToFile, $zipFiles);
		}
	}

	/**
	 * Testing module removal
	 * *****
	 */
	public function testDeleteModule()
	{
		$db = \App\Db::getInstance()->getSchema()->refresh();
		$moduleInstance = \vtlib\Module::getInstance('Test');
		$moduleInstance->delete();
		$this->assertFileNotExists(ROOT_DIRECTORY . '/modules/Test/Test.php');

		$langFileToCheck = $this->getLangPathToFile('Test.php');
		foreach ($langFileToCheck as $pathToFile) {
			$this->assertFileNotExists($pathToFile);
		}

		$this->assertFalse((new \App\Db\Query())->from('vtiger_tab')->where(['name' => 'Test'])->exists(), 'The test module exists in the database');
	}

	/**
	 * Testing module import
	 * *****
	 */
	public function testImportModule()
	{
		$db = \App\Db::getInstance()->getSchema()->refresh();
		$package = new vtlib\Package();

		$this->assertEquals('Test', $package->getModuleNameFromZip(static::$zipFileName));
		$this->assertFalse($package->isLanguageType(static::$zipFileName), 'The module is a language type');
		$this->assertFalse($package->isUpdateType(static::$zipFileName), 'The module is a update type');
		$this->assertFalse($package->isModuleBundle(static::$zipFileName), 'The module is a bundle type');

		$package->import(static::$zipFileName);

		$this->assertEquals('LBL_INVENTORY_MODULE', $package->getTypeName());
		$this->assertFileExists(ROOT_DIRECTORY . '/modules/Test/Test.php');
		$this->assertTrue((new \App\Db\Query())->from('vtiger_tab')->where(['name' => 'Test'])->exists(), 'The test module does not exist in the database');

		unlink(static::$zipFileName);
		$this->assertFileNotExists(static::$zipFileName);
	}

	/**
	 * Testing imported module removal
	 * *****
	 */
	public function testDeleteImportedModule()
	{
		$this->testDeleteModule();
	}

	/**
	 * Testing download librares
	 * *****
	 */
	public function testDownloadLibraryModule()
	{
		$libraries = Settings_ModuleManager_Library_Model::getAll();
		foreach ($libraries as $key => $library) {
			//Check if remote file exists
			$header = get_headers($library['url'], 1);
			$this->assertNotRegExp('/404/', $header['Status']);

			Settings_ModuleManager_Library_Model::download($key);
			$this->assertFileExists($library['dir'] . 'version.php');
		}
	}

	/**
	 * Testing module off
	 * *****
	 */
	public function testOffAllModule()
	{
		$allModules = Settings_ModuleManager_Module_Model::getAll();
		$moduleManagerModel = new Settings_ModuleManager_Module_Model();
		foreach ($allModules as $module) {
			//Turn off the module if it is on
			if ((int) $module->get('presence') !== 1) {
				$moduleManagerModel->disableModule($module->get('name'));
				$this->assertEquals(1, (new \App\Db\Query())->select('presence')->from('vtiger_tab')->where(['tabid' => $module->getId()])->scalar());
			}
		}
	}

	/**
	 * Testing module on
	 * *****
	 */
	public function testOnAllModule()
	{
		$allModules = Settings_ModuleManager_Module_Model::getAll();
		$moduleManagerModel = new Settings_ModuleManager_Module_Model();
		foreach ($allModules as $module) {
			//Turn on the module if it is off
			if ((int) $module->get('presence') !== 0) {
				$moduleManagerModel->enableModule($module->get('name'));
				$this->assertEquals(0, (new \App\Db\Query())->select('presence')->from('vtiger_tab')->where(['tabid' => $module->getId()])->scalar());
			}
		}
	}

	/**
	 *
	 * @param string $fileName
	 * @return array
	 * @throws Exception
	 */
	private function getLangPathToFile($fileName)
	{
		$langFileToCheck = [];
		$allLang = \App\Language::getAll();
		foreach ($allLang as $key => $lang) {
			$langFileToCheck[] = './languages/' . $key . '/' . $fileName;
		}
		return $langFileToCheck;
	}
}
