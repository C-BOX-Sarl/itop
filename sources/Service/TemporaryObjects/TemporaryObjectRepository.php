<?php
/*
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Service\TemporaryObjects;

use DBObjectSet;
use DBSearch;
use Exception;
use ExceptionLog;
use MetaModel;
use TemporaryObjectDescriptor;

/**
 * TemporaryObjectRepository.
 *
 * Repository class to perform ORM tasks.
 *
 * @since 3.1
 */
class TemporaryObjectRepository
{
	/** @var TemporaryObjectRepository|null Singleton */
	static private ?TemporaryObjectRepository $oSingletonInstance = null;

	/**
	 * GetInstance.
	 *
	 * @return TemporaryObjectRepository
	 */
	public static function GetInstance(): TemporaryObjectRepository
	{
		if (is_null(self::$oSingletonInstance)) {
			self::$oSingletonInstance = new TemporaryObjectRepository();
		}

		return self::$oSingletonInstance;
	}

	/**
	 * Constructor.
	 *
	 */
	private function __construct()
	{
	}

	/**
	 * Create.
	 *
	 * @param string $sTempId Temporary id
	 * @param string $sObjectClass Object class
	 * @param string $sObjectKey Object key
	 *
	 * @return TemporaryObjectDescriptor|null
	 */
	public function Create(string $sTempId, string $sObjectClass, string $sObjectKey): ?TemporaryObjectDescriptor
	{
		try {

			// Create a temporary object descriptor
			/** @var \TemporaryObjectDescriptor $oTemporaryObjectDescriptor */
			$oTemporaryObjectDescriptor = MetaModel::NewObject(TemporaryObjectDescriptor::class, [
				'operation'       => 'create',
				'temp_id'         => $sTempId,
				'expiration_date' => time() + MetaModel::GetConfig()->Get(TemporaryObjectHelper::CONFIG_TEMP_LIFETIME),
				'item_class'      => $sObjectClass,
				'item_id'         => $sObjectKey,
			]);
			$oTemporaryObjectDescriptor->DBInsert();

			return $oTemporaryObjectDescriptor;
		}
		catch (Exception $e) {

			ExceptionLog::LogException($e);

			return null;
		}
	}


	public function Delete()
	{

	}

	/**
	 * SearchByTempId.
	 *
	 * @param string $sTempId temporary id
	 * @param bool $bReverseOrder reverse order of result
	 *
	 * @return \DBObjectSet
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	public function SearchByTempId(string $sTempId, bool $bReverseOrder = false): DBObjectSet
	{
		// Prepare OQL
		$sOQL = sprintf('SELECT `%s` WHERE temp_id=:temp_id', TemporaryObjectDescriptor::class);

		// Create db search
		$oDbObjectSearch = DBSearch::FromOQL($sOQL);

		// Create db set from db search
		$oDbObjectSet = new DBObjectSet($oDbObjectSearch, [], [
			'temp_id' => $sTempId,
		]);

		// Reverse order
		if ($bReverseOrder) {
			$oDbObjectSet->SetOrderBy([
				'id' => false,
			]);
		}

		return $oDbObjectSet;
	}

	/**
	 * CountTemporaryObjectsByTempId.
	 *
	 * @param string $sTempId
	 *
	 * @return int
	 */
	public function CountTemporaryObjectsByTempId(string $sTempId): int
	{
		try {

			$oDbObjectSet = $this->SearchByTempId($sTempId);

			// return operation success
			return $oDbObjectSet->count();
		}
		catch (Exception $e) {

			ExceptionLog::LogException($e);

			return -1;
		}
	}

}
