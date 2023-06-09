<?php

namespace Combodo\iTop\Test\UnitTest\Setup;

use Combodo\iTop\Test\UnitTest\ItopTestCase;
use DOMDocument;
use iTopDesignFormat;


/**
 * @covers iTopDesignFormat
 *
 * @since 2.7.0 N°2586
 * @package Combodo\iTop\Test\UnitTest\Setup
 */
class iTopDesignFormatTest extends ItopTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->RequireOnceItopFile('setup/modelfactory.class.inc.php');
		$this->RequireOnceItopFile('setup/itopdesignformat.class.inc.php');
	}

	/**
	 * @covers       iTopDesignFormat::Convert
	 * @dataProvider MigrationMethodProvider
	 *
	 * @param string $sTargetVersion
	 * @param string $sInputXmlFileName example "1.7_to_1.6.input"
	 * @param string $sExpectedXmlFileName example "1.7_to_1.6.expected"
	 *
	 * @throws \Exception
	 */
	public function testMigrationMethod($sTargetVersion, $sInputXmlFileName, $sExpectedXmlFileName)
	{
		$sInputXml = $this->GetFileContent($sInputXmlFileName);
		$sExpectedXml = $this->GetFileContent($sExpectedXmlFileName);

		$oInputDocument = new DOMDocument();
		libxml_clear_errors();
		$oInputDocument->preserveWhiteSpace = false;
		$oInputDocument->loadXML($sInputXml);
		$oInputDocument->formatOutput = true;
		$oDesignFormat = new iTopDesignFormat($oInputDocument);
		$oDesignFormat->Convert($sTargetVersion);

		$sConvertedXml = $oInputDocument->saveXML();

		$this->assertEquals($sExpectedXml, $sConvertedXml);
	}

	private function GetFileContent($sFileName)
	{
		$sCurrentPath = __DIR__;

		return file_get_contents($sCurrentPath.DIRECTORY_SEPARATOR.$sFileName.'.xml');
	}

	public function MigrationMethodProvider()
	{
		return array(
			'1.7 to 1.6' => array('1.6', '1.7_to_1.6.input', '1.7_to_1.6.expected'),
		);
	}
}