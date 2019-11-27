<?php
// Copyright (C) 2010-2018 Combodo SARL
//
//   This file is part of iTop.
//
//   iTop is free software; you can redistribute it and/or modify	
//   it under the terms of the GNU Affero General Public License as published by
//   the Free Software Foundation, either version 3 of the License, or
//   (at your option) any later version.
//
//   iTop is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU Affero General Public License for more details.
//
//   You should have received a copy of the GNU Affero General Public License
//   along with iTop. If not, see <http://www.gnu.org/licenses/>


/**
 * DB Server abstraction
 *
 * @copyright   Copyright (C) 2010-2018 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

require_once('MyHelpers.class.inc.php');
require_once(APPROOT.'core/kpi.class.inc.php');

class MySQLException extends CoreException
{
	/**
	 * MySQLException constructor.
	 *
	 * @param string $sIssue
	 * @param array $aContext
	 * @param \Exception $oException
	 * @param \mysqli $oMysqli to use when working with a custom mysqli instance
	 */
	public function __construct($sIssue, $aContext, $oException = null, $oMysqli = null)
	{
		if ($oException != null)
		{
			$aContext['mysql_errno'] = $oException->getCode();
			$this->code = $oException->getCode();
			$aContext['mysql_error'] = $oException->getMessage();
		}
		else if ($oMysqli != null)
		{
			$aContext['mysql_errno'] = $oMysqli->errno;
			$this->code = $oMysqli->errno;
			$aContext['mysql_error'] = $oMysqli->error;
		}
		else
		{
			$aContext['mysql_errno'] = CMDBSource::GetErrNo();
			$this->code = CMDBSource::GetErrNo();
			$aContext['mysql_error'] = CMDBSource::GetError();
		}
		parent::__construct($sIssue, $aContext);
	}
}

/**
 * Class MySQLQueryHasNoResultException
 *
 * @since 2.5
 */
class MySQLQueryHasNoResultException extends MySQLException
{

}

/**
 * Class MySQLHasGoneAwayException
 *
 * @since 2.5
 * @see itop bug 1195
 * @see https://dev.mysql.com/doc/refman/5.7/en/gone-away.html
 */
class MySQLHasGoneAwayException extends MySQLException
{
	/**
	 * can not be a constant before PHP 5.6 (http://php.net/manual/fr/language.oop5.constants.php)
	 *
	 * @return int[]
	 */
	public static function getErrorCodes()
	{
		return array(
			2006,
			2013
		);
	}

	public function __construct($sIssue, $aContext)
	{
		parent::__construct($sIssue, $aContext, null);
	}
}

/**
 * @since 2.7.0 N°679
 */
class MySQLNoTransactionException extends MySQLException
{

}


/**
 * CMDBSource
 * database access wrapper 
 *
 * @package     iTopORM
 */
class CMDBSource
{
	const ENUM_DB_VENDOR_MYSQL = 'MySQL';
	const ENUM_DB_VENDOR_MARIADB = 'MariaDB';
	const ENUM_DB_VENDOR_PERCONA = 'Percona';
	
	protected static $m_sDBHost;
	protected static $m_sDBUser;
	protected static $m_sDBPwd;
	protected static $m_sDBName;
	/**
	 * @var boolean
	 * @since 2.5 N°1260 MySQL TLS first implementation
	 */
	protected static $m_bDBTlsEnabled;
	/**
	 * @var string
	 * @since 2.5 N°1260 MySQL TLS first implementation
	 */
	protected static $m_sDBTlsCA;

	/** @var mysqli $m_oMysqli */
	protected static $m_oMysqli;

	/**
	 * @var int number of level for nested transactions : 0 if no transaction was ever opened, +1 for each 'START TRANSACTION' sent
	 * @since 2.7.0 N°679
	 */
	protected static $m_iTransactionLevel = 0;

	/**
	 * SQL charset & collation declaration for text columns
	 *
	 * Using a function instead of a constant or attribute to avoid crash in the setup for older PHP versions (cannot
	 * use expression as value)
	 *
	 * @see https://dev.mysql.com/doc/refman/5.7/en/charset-column.html
	 * @since 2.5.1 N°1001 switch to utf8mb4
	 */
	public static function GetSqlStringColumnDefinition()
	{
		return ' CHARACTER SET '.DEFAULT_CHARACTER_SET.' COLLATE '.DEFAULT_COLLATION;
	}

	/**
	 * @param Config $oConfig
	 *
	 * @throws \MySQLException
	 * @uses \CMDBSource::Init()
	 * @uses \CMDBSource::SetCharacterSet()
	 */
	public static function InitFromConfig($oConfig)
	{
		$sServer = $oConfig->Get('db_host');
		$sUser = $oConfig->Get('db_user');
		$sPwd = $oConfig->Get('db_pwd');
		$sSource = $oConfig->Get('db_name');
		$bTlsEnabled = $oConfig->Get('db_tls.enabled');
		$sTlsCA = $oConfig->Get('db_tls.ca');

		self::Init($sServer, $sUser, $sPwd, $sSource, $bTlsEnabled, $sTlsCA);

		$sCharacterSet = DEFAULT_CHARACTER_SET;
		$sCollation = DEFAULT_COLLATION;
		self::SetCharacterSet($sCharacterSet, $sCollation);
	}

	/**
	 * @param string $sServer
	 * @param string $sUser
	 * @param string $sPwd
	 * @param string $sSource database to use
	 * @param bool $bTlsEnabled
	 * @param string $sTlsCA
	 *
	 * @throws \MySQLException
	 */
	public static function Init(
		$sServer, $sUser, $sPwd, $sSource = '', $bTlsEnabled = false, $sTlsCA = null
	)
	{
		self::$m_sDBHost = $sServer;
		self::$m_sDBUser = $sUser;
		self::$m_sDBPwd = $sPwd;
		self::$m_sDBName = $sSource;
		self::$m_bDBTlsEnabled = empty($bTlsEnabled) ? false : $bTlsEnabled;
		self::$m_sDBTlsCA = empty($sTlsCA) ? null : $sTlsCA;

		self::$m_oMysqli = self::GetMysqliInstance($sServer, $sUser, $sPwd, $sSource, $bTlsEnabled, $sTlsCA, true);
	}

	/**
	 * @param string $sDbHost
	 * @param string $sUser
	 * @param string $sPwd
	 * @param string $sSource database to use
	 * @param bool $bTlsEnabled
	 * @param string $sTlsCa
	 * @param bool $bCheckTlsAfterConnection If true then verify after connection if it is encrypted
	 *
	 * @return \mysqli
	 * @throws \MySQLException
	 */
	public static function GetMysqliInstance(
		$sDbHost, $sUser, $sPwd, $sSource = '', $bTlsEnabled = false, $sTlsCa = null, $bCheckTlsAfterConnection = false
	) {
		$oMysqli = null;

		$sServer = null;
		$iPort = null;
		self::InitServerAndPort($sDbHost, $sServer, $iPort);

		$iFlags = null;

		// *some* errors (like connection errors) will throw mysqli_sql_exception instead of generating warnings printed to the output
		// but some other errors will still cause the query() method to return false !!!
		mysqli_report(MYSQLI_REPORT_STRICT);

		try
		{
			$oMysqli = new mysqli();
			$oMysqli->init();

			if ($bTlsEnabled)
			{
				$iFlags = (empty($sTlsCa))
					? MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT
					: MYSQLI_CLIENT_SSL;
				$sTlsCert = null; // not implemented
				$sTlsCaPath = null; // not implemented
				$sTlsCipher = null; // not implemented
				$oMysqli->ssl_set($bTlsEnabled, $sTlsCert, $sTlsCa, $sTlsCaPath, $sTlsCipher);
			}
			$oMysqli->real_connect($sServer, $sUser, $sPwd, '', $iPort, ini_get("mysqli.default_socket"), $iFlags);
		}
		catch(mysqli_sql_exception $e)
		{
			throw new MySQLException('Could not connect to the DB server', array('host' => $sServer, 'user' => $sUser), $e);
		}

		if ($bTlsEnabled
			&& $bCheckTlsAfterConnection
			&& !self::IsOpenedDbConnectionUsingTls($oMysqli))
		{
			throw new MySQLException("Connection to the database is not encrypted whereas it was opened using TLS parameters",
				null, null, $oMysqli);
		}

		if (!empty($sSource))
		{
			try
			{
				mysqli_report(MYSQLI_REPORT_STRICT); // Errors, in the next query, will throw mysqli_sql_exception
				$oMysqli->query("USE `$sSource`");
			}
			catch(mysqli_sql_exception $e)
			{
				throw new MySQLException('Could not select DB',
					array('host' => $sServer, 'user' => $sUser, 'db_name' => $sSource), $e);
			}
		}

		return $oMysqli;
	}

	/**
	 * @param string $sDbHost initial value ("p:domain:port" syntax)
	 * @param string $sServer server variable to update
	 * @param int $iPort port variable to update
	 */
	public static function InitServerAndPort($sDbHost, &$sServer, &$iPort)
	{
		$aConnectInfo = explode(':', $sDbHost);

		$bUsePersistentConnection = false;
		if (strcasecmp($aConnectInfo[0], 'p') == 0)
		{
			// we might have "p:" prefix to use persistent connections (see http://php.net/manual/en/mysqli.persistconns.php)
			$bUsePersistentConnection = true;
			$sServer = $aConnectInfo[0].':'.$aConnectInfo[1];
		}
		else
		{
			$sServer = $aConnectInfo[0];
		}

		$iConnectInfoCount = count($aConnectInfo);
		if ($bUsePersistentConnection && ($iConnectInfoCount == 3))
		{
			$iPort = (int)($aConnectInfo[2]);
		}
		else if (!$bUsePersistentConnection && ($iConnectInfoCount == 2))
		{
			$iPort = (int)($aConnectInfo[1]);
		}
		else
		{
			$iPort = 3306;
		}
	}

	/**
	 * <p>A DB connection can be opened transparently (no errors thrown) without being encrypted, whereas the TLS
	 * parameters were used.<br>
	 * This method can be called to ensure that the DB connection really uses TLS.
	 *
	 * <p>We're using this object connection : {@link self::$m_oMysqli}
	 *
	 * @param \mysqli $oMysqli
	 *
	 * @return boolean true if the connection was really established using TLS
	 * @throws \MySQLException
	 *
	 * @uses IsMySqlVarNonEmpty
	 */
	private static function IsOpenedDbConnectionUsingTls($oMysqli)
	{
		if (self::$m_oMysqli == null)
		{
			self::$m_oMysqli = $oMysqli;
		}

		$bNonEmptySslVersionVar = self::IsMySqlVarNonEmpty('ssl_version');
		$bNonEmptySslCipherVar = self::IsMySqlVarNonEmpty('ssl_cipher');

		return ($bNonEmptySslVersionVar && $bNonEmptySslCipherVar);
	}

	/**
	 * @param string $sVarName
	 *
	 * @return bool
	 * @throws \MySQLException
	 *
	 * @uses SHOW STATUS queries
	 */
	private static function IsMySqlVarNonEmpty($sVarName)
	{
		try
		{
			$sResult = self::QueryToScalar("SHOW SESSION STATUS LIKE '$sVarName'", 1);
		}
		catch (MySQLQueryHasNoResultException $e)
		{
			$sResult = null;
		}

		return (!empty($sResult));
	}

	public static function SetCharacterSet($sCharset = DEFAULT_CHARACTER_SET, $sCollation = DEFAULT_COLLATION)
	{
		if (strlen($sCharset) > 0)
		{
			if (strlen($sCollation) > 0)
			{
				self::Query("SET NAMES '$sCharset' COLLATE '$sCollation'");
			}
			else
			{
				self::Query("SET NAMES '$sCharset'");
			}
		}
	}

	public static function SetTimezone($sTimezone = null)
	{
		// Note: requires the installation of MySQL special tables,
		//       otherwise, only 'SYSTEM' or "+10:00' may be specified which is NOT sufficient because of day light saving times
		if (!is_null($sTimezone))
		{
			$sQuotedTimezone = self::Quote($sTimezone);
			self::Query("SET time_zone = $sQuotedTimezone");
		}
	}

	public static function ListDB()
	{
		$aDBs = self::QueryToCol('SHOW DATABASES', 'Database');
		// Show Database does return the DB names in lower case
		return $aDBs;
	}

	public static function IsDB($sSource)
	{
		try
		{
			$aDBs = self::ListDB();
			foreach($aDBs as $sDBName)
			{
			// perform a case insensitive test because on Windows the table names become lowercase :-(
				if (strtolower($sDBName) == strtolower($sSource)) return true;
			}
			return false;
		}
		catch(Exception $e)
		{
			// In case we don't have rights to enumerate the databases
			// Let's try to connect directly
			return @((bool)self::$m_oMysqli->query("USE `$sSource`"));
		}

	}

	public static function GetDBVersion()
	{
		$aVersions = self::QueryToCol('SELECT Version() as version', 'version');
		return $aVersions[0];
	}

	/**
	 * @return string
	 */
	public static function GetServerInfo()
	{
		return mysqli_get_server_info ( self::$m_oMysqli );
	}

	/**
	 * Get the DB vendor between MySQL and its main forks
	 * @return string
	 */
	static public function GetDBVendor()
	{
		$sDBVendor = static::ENUM_DB_VENDOR_MYSQL;
		
		$sVersionComment = static::GetServerVariable('version') .  ' - ' . static::GetServerVariable('version_comment');
		if(preg_match('/mariadb/i', $sVersionComment) === 1)
		{
			$sDBVendor = static::ENUM_DB_VENDOR_MARIADB;
		}
		else if(preg_match('/percona/i', $sVersionComment) === 1)
		{
			$sDBVendor = static::ENUM_DB_VENDOR_PERCONA;
		}
		
		return $sDBVendor;
	}

	/**
	 * @param string $sSource
	 *
	 * @throws \MySQLException
	 */
	public static function SelectDB($sSource)
	{
		if (!((bool)self::$m_oMysqli->query("USE `$sSource`")))
		{
			throw new MySQLException('Could not select DB', array('db_name'=>$sSource));
		}
		self::$m_sDBName = $sSource;
	}

	/**
	 * @param string $sSource
	 *
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	public static function CreateDB($sSource)
	{
		self::Query("CREATE DATABASE `$sSource` CHARACTER SET ".DEFAULT_CHARACTER_SET." COLLATE ".DEFAULT_COLLATION);
		self::SelectDB($sSource);
	}

	public static function DropDB($sDBToDrop = '')
	{
		if (empty($sDBToDrop))
		{
			$sDBToDrop = self::$m_sDBName;
		}
		self::Query("DROP DATABASE `$sDBToDrop`");
		if ($sDBToDrop == self::$m_sDBName)
		{
			self::$m_sDBName = '';
		}
	}

	public static function CreateTable($sQuery)
	{
		$res = self::Query($sQuery);
		self::_TablesInfoCacheReset(); // reset the table info cache!
		return $res;
	}

	public static function DropTable($sTable)
	{
		$res = self::Query("DROP TABLE `$sTable`");
		self::_TablesInfoCacheReset(); // reset the table info cache!
		return $res;
	}

	public static function CacheReset($sTable)
	{
		self::_TablesInfoCacheReset($sTable);
	}

	/**
	 * @return \mysqli
	 */
	public static function GetMysqli()
	{
		return self::$m_oMysqli;
	}

	public static function GetErrNo()
	{
		if (self::$m_oMysqli->errno != 0)
		{
			return self::$m_oMysqli->errno;
		}
		else
		{
			return self::$m_oMysqli->connect_errno;
		}
	}

	public static function GetError()
	{
		if (self::$m_oMysqli->error != '')
		{
			return self::$m_oMysqli->error;
		}
		else
		{
			return self::$m_oMysqli->connect_error;
		}
	}

	public static function DBHost() {return self::$m_sDBHost;}
	public static function DBUser() {return self::$m_sDBUser;}
	public static function DBPwd() {return self::$m_sDBPwd;}
	public static function DBName() {return self::$m_sDBName;}

	/**
	 * Quote variable and protect against SQL injection attacks
	 * Code found in the PHP documentation: quote_smart($value)
	 *
	 * @param mixed $value
	 * @param bool $bAlways should be set to true when the purpose is to create a IN clause,
	 *                      otherwise and if there is a mix of strings and numbers, the clause would always be false
	 * @param string $cQuoteStyle
	 *
	 * @return array|string
	 */
	public static function Quote($value, $bAlways = false, $cQuoteStyle = "'")
	{
		if (is_null($value))
		{
			return 'NULL';
		}

		if (is_array($value))
		{
			$aRes = array();
			foreach ($value as $key => $itemvalue)
			{
				$aRes[$key] = self::Quote($itemvalue, $bAlways, $cQuoteStyle);
			}
			return $aRes;
		}

		// Stripslashes
		if (get_magic_quotes_gpc())
		{
			$value = stripslashes($value);
		}
		// Quote if not a number or a numeric string
		if ($bAlways || is_string($value))
		{
			$value = $cQuoteStyle . self::$m_oMysqli->real_escape_string($value) . $cQuoteStyle;
		}
		return $value;
	}

	/**
	 * MariaDB returns "'value'" for enum, while MySQL returns "value" (without the surrounding single quotes)
	 *
	 * @param string $sValue
	 *
	 * @return string without the surrounding quotes
	 * @since 2.7.0 N°2490
	 */
	private static function RemoveSurroundingQuotes($sValue)
	{
		if (utils::StartsWith($sValue, '\'') && utils::EndsWith($sValue, '\''))
		{
			$sValue = substr($sValue, 1, -1);
		}

		return $sValue;
	}

	/**
	 * @param string $sSQLQuery
	 *
	 * @return \mysqli_result|null
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \CoreException
	 *
	 * @since 2.7.0 N°679 handles nested transactions
	 */
	public static function Query($sSQLQuery)
	{
		if (preg_match('/^START TRANSACTION;?$/i', $sSQLQuery))
		{
			self::StartTransaction();

			return null;
		}
		if (preg_match('/^COMMIT;?$/i', $sSQLQuery))
		{
			self::Commit();

			return null;
		}
		if (preg_match('/^ROLLBACK;?$/i', $sSQLQuery))
		{
			self::Rollback();

			return null;
		}


		return self::DBQuery($sSQLQuery);
	}

	/**
	 * Send the query directly to the DB. **Be extra cautious with this !**
	 *
	 * Use {@link Query} if you're not sure.
	 *
	 * @internal
	 *
	 * @param string $sSql
	 *
	 * @return bool|\mysqli_result
	 * @throws \MySQLHasGoneAwayException
	 * @throws \MySQLException
	 *
	 * @since 2.7.0 N°679
	 */
	private static function DBQuery($sSql)
	{
		$oKPI = new ExecutionKPI();
		try
		{
			$oResult = self::$m_oMysqli->query($sSql);
		}
		catch (mysqli_sql_exception $e)
		{
			throw new MySQLException('Failed to issue SQL query', array('query' => $sSql, $e));
		}
		$oKPI->ComputeStats('Query exec (mySQL)', $sSql);
		if ($oResult === false)
		{
			$aContext = array('query' => $sSql);

			$iMySqlErrorNo = self::$m_oMysqli->errno;
			$aMySqlHasGoneAwayErrorCodes = MySQLHasGoneAwayException::getErrorCodes();
			if (in_array($iMySqlErrorNo, $aMySqlHasGoneAwayErrorCodes))
			{
				throw new MySQLHasGoneAwayException(self::GetError(), $aContext);
			}

			throw new MySQLException('Failed to issue SQL query', $aContext);
		}

		return $oResult;
	}

	/**
	 * If nested transaction, we are not starting a new one : only one global transaction will exist.
	 *
	 * Indeed [the official documentation](https://dev.mysql.com/doc/refman/5.6/en/commit.html) states :
	 *
	 * > Beginning a transaction causes any pending transaction to be committed
	 *
	 * @internal
	 * @see m_iTransactionLevel
	 * @since 2.7.0 N°679
	 */
	private static function StartTransaction()
	{
		$bHasExistingTransactions = self::IsInsideTransaction();
		if (!$bHasExistingTransactions)
		{
			self::DBQuery('START TRANSACTION');
		}

		self::AddTransactionLevel();
	}

	/**
	 * Sends the COMMIT to the db only if we are at the root transaction level
	 *
	 * @internal
	 * @see m_iTransactionLevel
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \MySQLNoTransactionException if called with no opened transaction
	 * @since 2.7.0 N°679
	 */
	private static function Commit()
	{
		if (!self::IsInsideTransaction())
		{
			// should not happen !
			throw new MySQLNoTransactionException('Trying to commit transaction whereas none have been started !', null);
		}

		self::RemoveLastTransactionLevel();

		if (self::IsInsideTransaction())
		{
			return;
		}
		self::DBQuery('COMMIT');
	}

	/**
	 * Sends the ROLLBACK to the db only if we are at the root transaction level
	 *
	 * The parameter allows to send a ROLLBACK whatever the current transaction level is
	 *
	 * @internal
	 * @see m_iTransactionLevel
	 *
	 * @throws \MySQLNoTransactionException if called with no opened transaction
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @since 2.7.0 N°679
	 */
	private static function Rollback()
	{
		if (!self::IsInsideTransaction())
		{
			// should not happen !
			throw new MySQLNoTransactionException('Trying to commit transaction whereas none have been started !', null);
		}
		self::RemoveLastTransactionLevel();
		if (self::IsInsideTransaction())
		{
			return;
		}

		self::DBQuery('ROLLBACK');
	}

	/**
	 * @api
	 * @see m_iTransactionLevel
	 * @return bool true if there is one transaction opened, false otherwise (not a single 'START TRANSACTION' sent)
	 * @since 2.7.0 N°679
	 */
	public static function IsInsideTransaction()
	{
		return (self::$m_iTransactionLevel > 0);
	}

	/**
	 * @internal
	 * @see m_iTransactionLevel
	 * @since 2.7.0 N°679
	 */
	private static function AddTransactionLevel()
	{
		++self::$m_iTransactionLevel;
	}

	/**
	 * @internal
	 * @see m_iTransactionLevel
	 * @since 2.7.0 N°679
	 */
	private static function RemoveLastTransactionLevel()
	{
		if (self::$m_iTransactionLevel === 0)
		{
			return;
		}

		--self::$m_iTransactionLevel;
	}

	/**
	 * @internal
	 * @see m_iTransactionLevel
	 * @since 2.7.0 N°679
	 */
	private static function RemoveAllTransactionLevels()
	{
		self::$m_iTransactionLevel = 0;
	}

	/**
	 *
	 * @deprecated 2.7.0 N°1627 use ItopCounter instead
	 *
	 * @param string $sTable
	 *
	 * @return int
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	public static function GetNextInsertId($sTable)
	{
		$sSQL = "SHOW TABLE STATUS LIKE '$sTable'";
		$oResult = self::Query($sSQL);
		$aRow = $oResult->fetch_assoc();

		return $aRow['Auto_increment'];
	}

	public static function GetInsertId()
	{
		$iRes = self::$m_oMysqli->insert_id;
		if (is_null($iRes))
		{
			return 0;
		}
		return $iRes;
	}

	public static function InsertInto($sSQLQuery)
	{
		if (self::Query($sSQLQuery))
		{
			return self::GetInsertId();
		}
		return false;
	}

	/**
	 * @param $sSQLQuery
	 *
	 * @throws \CoreException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	public static function DeleteFrom($sSQLQuery)
	{
		self::Query($sSQLQuery);
	}

	/**
	 * @param string $sSql
	 * @param int $iCol beginning at 0
	 *
	 * @return string corresponding cell content on the first line
	 * @throws \MySQLException
	 * @throws \MySQLQueryHasNoResultException
	 */
	public static function QueryToScalar($sSql, $iCol = 0)
	{
		$oKPI = new ExecutionKPI();
		try
		{
			$oResult = self::$m_oMysqli->query($sSql);
		}
		catch(mysqli_sql_exception $e)
		{
			$oKPI->ComputeStats('Query exec (mySQL)', $sSql);
			throw new MySQLException('Failed to issue SQL query', array('query' => $sSql, $e));
		}
		$oKPI->ComputeStats('Query exec (mySQL)', $sSql);
		if ($oResult === false)
		{
			throw new MySQLException('Failed to issue SQL query', array('query' => $sSql));
		}

		if ($aRow = $oResult->fetch_array(MYSQLI_BOTH))
		{
			$res = $aRow[$iCol];
		}
		else
		{
			$oResult->free();
			throw new MySQLQueryHasNoResultException('Found no result for query', array('query' => $sSql));
		}
		$oResult->free();

		return $res;
	}


	/**
	 * @param string $sSql
	 *
	 * @return array
	 * @throws \MySQLException if query cannot be processed
	 */
	public static function QueryToArray($sSql)
	{
		$aData = array();
		$oKPI = new ExecutionKPI();
		try
		{
			$oResult = self::$m_oMysqli->query($sSql);
		}
		catch(mysqli_sql_exception $e)
		{
			$oKPI->ComputeStats('Query exec (mySQL)', $sSql);
			throw new MySQLException('Failed to issue SQL query', array('query' => $sSql, $e));
		}
		$oKPI->ComputeStats('Query exec (mySQL)', $sSql);
		if ($oResult === false)
		{
			throw new MySQLException('Failed to issue SQL query', array('query' => $sSql));
		}
				
		while ($aRow = $oResult->fetch_array(MYSQLI_BOTH))
		{
			$aData[] = $aRow;
		}
		$oResult->free();
		return $aData;
	}

	/**
	 * @param string $sSql
	 * @param int $col
	 *
	 * @return array
	 * @throws \MySQLException
	 */
	public static function QueryToCol($sSql, $col)
	{
		$aColumn = array();
		$aData = self::QueryToArray($sSql);
		foreach($aData as $aRow)
		{
			@$aColumn[] = $aRow[$col];
		}
		return $aColumn;
	}

	/**
	 * @param string $sSql
	 *
	 * @return array
	 * @throws \MySQLException if query cannot be processed
	 */
	public static function ExplainQuery($sSql)
	{
		$aData = array();
		try
		{
			$oResult = self::$m_oMysqli->query($sSql);
		}
		catch(mysqli_sql_exception $e)
		{
			throw new MySQLException('Failed to issue SQL query', array('query' => $sSql, $e));
		}
		if ($oResult === false)
		{
			throw new MySQLException('Failed to issue SQL query', array('query' => $sSql));
		}

		$aNames = self::GetColumns($oResult, $sSql);

		$aData[] = $aNames;
		while ($aRow = $oResult->fetch_array(MYSQLI_ASSOC))
		{
			$aData[] = $aRow;
		}
		$oResult->free();
		return $aData;
	}

	/**
	 * @param string $sSql
	 *
	 * @return string
	 * @throws \MySQLException if query cannot be processed
	 */
	public static function TestQuery($sSql)
	{
		try
		{
			$oResult = self::$m_oMysqli->query($sSql);
		}
		catch(mysqli_sql_exception $e)
		{
			throw new MySQLException('Failed to issue SQL query', array('query' => $sSql, $e));
		}
		if ($oResult === false)
		{
			throw new MySQLException('Failed to issue SQL query', array('query' => $sSql));
		}

		if (is_object($oResult))
		{
			$oResult->free();
		}
		return '';
	}

	public static function NbRows($oResult)
	{
		return $oResult->num_rows;
	}

	public static function AffectedRows()
	{
		return self::$m_oMysqli->affected_rows;
	}

	public static function FetchArray($oResult)
	{
		return $oResult->fetch_array(MYSQLI_ASSOC);
	}

	/**
	 * @param mysqli_result $oResult
	 * @param string $sSql
	 *
	 * @return string[]
	 * @throws \MySQLException
	 */
	public static function GetColumns($oResult, $sSql)
	{
		$aNames = array();
		for ($i = 0; $i < (($___mysqli_tmp = $oResult->field_count) ? $___mysqli_tmp : 0) ; $i++)
		{
			$meta = $oResult->fetch_field_direct($i);
			if (!$meta)
			{
				throw new MySQLException('mysql_fetch_field: No information available', array('query'=>$sSql, 'i'=>$i));
			}
			else
			{
				$aNames[] = $meta->name;
			}
		}
		return $aNames;
	}

	public static function Seek($oResult, $iRow)
	{
		return $oResult->data_seek($iRow);
	}

	public static function FreeResult($oResult)
	{
		$oResult->free(); /* returns void */
		return true;
	}

	public static function IsTable($sTable)
	{
		$aTableInfo = self::GetTableInfo($sTable);
		return (!empty($aTableInfo));
	}

	public static function IsKey($sTable, $iKey)
	{
		$aTableInfo = self::GetTableInfo($sTable);
		if (empty($aTableInfo)) return false;
		if (!array_key_exists($iKey, $aTableInfo["Fields"])) return false;
		$aFieldData = $aTableInfo["Fields"][$iKey];
		if (!array_key_exists("Key", $aFieldData)) return false;
		return ($aFieldData["Key"] == "PRI"); 
	}

	public static function IsAutoIncrement($sTable, $sField)
	{
		$aTableInfo = self::GetTableInfo($sTable);
		if (empty($aTableInfo)) return false;
		if (!array_key_exists($sField, $aTableInfo["Fields"])) return false;
		$aFieldData = $aTableInfo["Fields"][$sField];
		if (!array_key_exists("Extra", $aFieldData)) return false;
		//MyHelpers::debug_breakpoint($aFieldData);
		return (strstr($aFieldData["Extra"], "auto_increment")); 
	}

	public static function IsField($sTable, $sField)
	{
		$aTableInfo = self::GetTableInfo($sTable);
		if (empty($aTableInfo)) return false;
		if (!array_key_exists($sField, $aTableInfo["Fields"])) return false;
		return true;
	}

	public static function IsNullAllowed($sTable, $sField)
	{
		$aTableInfo = self::GetTableInfo($sTable);
		if (empty($aTableInfo)) return false;
		if (!array_key_exists($sField, $aTableInfo["Fields"])) return false;
		$aFieldData = $aTableInfo["Fields"][$sField];
		return (strtolower($aFieldData["Null"]) == "yes");
	}

	public static function GetFieldType($sTable, $sField)
	{
		$aTableInfo = self::GetTableInfo($sTable);
		if (empty($aTableInfo)) return false;
		if (!array_key_exists($sField, $aTableInfo["Fields"])) return false;
		$aFieldData = $aTableInfo["Fields"][$sField];
		return ($aFieldData["Type"]);
	}

	private static function IsNumericType($aFieldData)
	{
		$aNumericTypes = array('tinyint(', 'decimal(', 'int(' );
		$sType = strtolower($aFieldData["Type"]);
		foreach ($aNumericTypes as $sNumericType)
		{
			if (strpos($sType, $sNumericType) === 0)
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * There may have some differences between DB : for example in MySQL 5.7 we have "INT", while in MariaDB >= 10.2 you get "int DEFAULT 'NULL'"
	 *
	 * We still do a case sensitive comparison for enum values !
	 *
	 * A better solution would be to generate SQL field definitions ({@link GetFieldSpec} method) based on the DB used... But for
	 * now (N°2490 / SF #1756 / PR #91) we did implement this simpler solution
	 *
	 * @param string $sItopGeneratedFieldType
	 * @param string $sDbFieldType
	 *
	 * @return bool true if same type and options (case sensitive comparison only for type options), false otherwise
	 * @since 2.7.0 N°2490
	 */
	public static function IsSameFieldTypes($sItopGeneratedFieldType, $sDbFieldType)
	{
		list($sItopFieldDataType, $sItopFieldTypeOptions, $sItopFieldOtherOptions) = static::GetFieldDataTypeAndOptions($sItopGeneratedFieldType);
		list($sDbFieldDataType, $sDbFieldTypeOptions, $sDbFieldOtherOptions) = static::GetFieldDataTypeAndOptions($sDbFieldType);

		if (strcasecmp($sItopFieldDataType, $sDbFieldDataType) !== 0)
		{
			return false;
		}

		if (strcmp($sItopFieldTypeOptions, $sDbFieldTypeOptions) !== 0)
		{
			// case sensitive comp as we need to check case for enum possible values for example
			return false;
		}

		// remove the default value NULL added by MariadDB
		$sMariaDbDefaultNull = ' DEFAULT \'NULL\'';
		if (utils::EndsWith($sDbFieldOtherOptions, $sMariaDbDefaultNull))
		{
			$sDbFieldOtherOptions = substr($sDbFieldOtherOptions, 0, -strlen($sMariaDbDefaultNull));
		}
		// remove quotes around default values (always present in MariaDB)
		$sDbFieldOtherOptions = preg_replace_callback(
			'/( DEFAULT )\'([^\']+)\'/',
			function ($aMatches) use ($sItopFieldDataType) {
				// ENUM default values should keep quotes, but all other numeric values don't have quotes
				if (is_numeric($aMatches[2]) && ($sItopFieldDataType !== 'ENUM'))
				{
					return $aMatches[1].$aMatches[2];
				}

				return $aMatches[0];
			},
			$sDbFieldOtherOptions);

		if (strcasecmp($sItopFieldOtherOptions, $sDbFieldOtherOptions) !== 0)
		{
			return false;
		}

		return true;
	}

	/**
	 * @param string $sCompleteFieldType sql field type, for example 'VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 0'
	 *
	 * @return string[] consisting of 3 items :
	 *      1. data type : for example 'VARCHAR'
	 *      2. type value : for example '255'
	 *      3. other options : for example ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 0'
	 */
	private static function GetFieldDataTypeAndOptions($sCompleteFieldType)
	{
		preg_match('/^([a-zA-Z]+)(\(([^\)]+)\))?( .+)?$/', $sCompleteFieldType, $aMatches);

		$sDataType = $aMatches[1];
		$sTypeOptions = isset($aMatches[2]) ? $aMatches[3] : '';
		$sOtherOptions = isset($aMatches[4]) ? $aMatches[4] : '';

		return array($sDataType, $sTypeOptions, $sOtherOptions);
	}

	/**
	 * @param string $sTable
	 * @param string $sField
	 *
	 * @return bool|string
	 * @see \AttributeDefinition::GetSQLColumns()
	 */
	public static function GetFieldSpec($sTable, $sField)
	{
		$aTableInfo = self::GetTableInfo($sTable);
		if (empty($aTableInfo)) return false;
		if (!array_key_exists($sField, $aTableInfo["Fields"])) return false;
		$aFieldData = $aTableInfo["Fields"][$sField];

		$sRet = $aFieldData["Type"];

		$sColumnCharset = $aFieldData["Charset"];
		$sColumnCollation = $aFieldData["Collation"];
		if (!empty($sColumnCharset))
		{
			$sRet .= ' CHARACTER SET '.$sColumnCharset;
			$sRet .= ' COLLATE '.$sColumnCollation;
		}

		if ($aFieldData["Null"] == 'NO')
		{
			$sRet .= ' NOT NULL';
		}

		if (is_numeric($aFieldData["Default"]))
		{
			if (strtolower(substr($aFieldData["Type"], 0, 5)) == 'enum(')
			{
				// Force quotes to match the column declaration statement
				$sRet .= ' DEFAULT '.self::Quote($aFieldData["Default"], true);
			}
			else
			{
				if (self::IsNumericType($aFieldData))
				{
					$sRet .= ' DEFAULT '.$aFieldData["Default"];
				}
				else
				{
					$default = $aFieldData["Default"] + 0; // Coerce to a numeric variable
					$sRet .= ' DEFAULT '.self::Quote($default);
				}
			}
		}
		elseif (is_string($aFieldData["Default"]) == 'string')
		{
			$sDefaultValue = static::RemoveSurroundingQuotes($aFieldData["Default"]);
			$sRet .= ' DEFAULT '.self::Quote($sDefaultValue);
		}

		return $sRet;
	}

	public static function HasIndex($sTable, $sIndexId, $aFields = null, $aLength = null)
	{
		$aTableInfo = self::GetTableInfo($sTable);
		if (empty($aTableInfo)) return false;
		if (!array_key_exists($sIndexId, $aTableInfo['Indexes'])) return false;

		if ($aFields == null)
		{
			// Just searching for the name
			return true;
		}

		// Compare the columns
		$sSearchedIndex = implode(',', $aFields);
		$aColumnNames = array();
		$aSubParts = array();
		foreach($aTableInfo['Indexes'][$sIndexId] as $aIndexDef)
		{
			$aColumnNames[] = $aIndexDef['Column_name'];
			$aSubParts[] = $aIndexDef['Sub_part'];
		}
		$sExistingIndex = implode(',', $aColumnNames);

		if (is_null($aLength))
		{
			return ($sSearchedIndex == $sExistingIndex);
		}

		$sSearchedLength = implode(',', $aLength);
		$sExistingLength = implode(',', $aSubParts);

		return ($sSearchedIndex == $sExistingIndex) && ($sSearchedLength == $sExistingLength);
	}

	// Returns an array of (fieldname => array of field info)
	public static function GetTableFieldsList($sTable)
	{
		assert(!empty($sTable));
		
		$aTableInfo = self::GetTableInfo($sTable);
		if (empty($aTableInfo)) return array(); // #@# or an error ?

		return array_keys($aTableInfo["Fields"]);
	}
	
	// Cache the information about existing tables, and their fields
	private static $m_aTablesInfo = array();
	private static function _TablesInfoCacheReset($sTableName = null)
	{
		if (is_null($sTableName))
		{
			self::$m_aTablesInfo = array();
		}
		else
		{
			self::$m_aTablesInfo[strtolower($sTableName)] = null;
		}
	}

	/**
	 * @param $sTableName
	 *
	 * @throws \MySQLException
	 */
	private static function _TableInfoCacheInit($sTableName)
	{
		if (isset(self::$m_aTablesInfo[strtolower($sTableName)])
			&& (self::$m_aTablesInfo[strtolower($sTableName)] != null))
		{
			return;
		}

		// Create array entry, if table does not exist / has no columns
		self::$m_aTablesInfo[strtolower($sTableName)] = null;

		// Get table informations
		//   We were using SHOW COLUMNS FROM... but this don't return charset and collation info !
		//   so since 2.5 and #1001 (switch to utf8mb4) we're using INFORMATION_SCHEMA !
		$aMapping = array(
			"Name" => "COLUMN_NAME",
			"Type" => "COLUMN_TYPE",
			"Null" => "IS_NULLABLE",
			"Key" => "COLUMN_KEY",
			"Default" => "COLUMN_DEFAULT",
			"Extra" => "EXTRA",
			"Charset" => "CHARACTER_SET_NAME",
			"Collation" => "COLLATION_NAME",
			"CharMaxLength" => "CHARACTER_MAXIMUM_LENGTH",
		);
		$sColumns = implode(', ', $aMapping);
		$sDBName = self::$m_sDBName;
		$aFields = self::QueryToArray("SELECT $sColumns FROM information_schema.`COLUMNS` WHERE table_schema = '$sDBName' AND table_name = '$sTableName';");
		foreach ($aFields as $aFieldData)
		{
			$aFields = array();
			foreach($aMapping as $sKey => $sColumn)
			{
				$aFields[$sKey] = $aFieldData[$sColumn];
			}
			$sFieldName = $aFieldData["COLUMN_NAME"];
			self::$m_aTablesInfo[strtolower($sTableName)]["Fields"][$sFieldName] = $aFields;
		}

		if (!is_null(self::$m_aTablesInfo[strtolower($sTableName)]))
		{
			$aIndexes = self::QueryToArray("SHOW INDEXES FROM `$sTableName`");
			$aMyIndexes = array();
			foreach ($aIndexes as $aIndexColumn)
			{
				$aMyIndexes[$aIndexColumn['Key_name']][$aIndexColumn['Seq_in_index']-1] = $aIndexColumn;
			}
			self::$m_aTablesInfo[strtolower($sTableName)]["Indexes"] = $aMyIndexes;
		}
	}

	public static function GetTableInfo($sTable)
	{
		self::_TableInfoCacheInit($sTable);

		// perform a case insensitive match because on Windows the table names become lowercase :-(
		return self::$m_aTablesInfo[strtolower($sTable)];
	}

	/**
	 * @param string $sTableName
	 *
	 * @return string query to upgrade table charset and collation if needed, null if not
	 * @throws \MySQLException
	 *
	 * @since 2.5 N°1001 switch to utf8mb4
	 * @see https://dev.mysql.com/doc/refman/5.7/en/charset-table.html
	 */
	public static function DBCheckTableCharsetAndCollation($sTableName)
	{
		$sDBName = self::DBName();
		$sTableInfoQuery = "SELECT C.CHARACTER_SET_NAME, T.TABLE_COLLATION
			FROM information_schema.`TABLES` T inner join information_schema.`COLLATION_CHARACTER_SET_APPLICABILITY` C
				ON T.table_collation = C.collation_name
			WHERE T.table_schema = '$sDBName'
  			AND T.table_name = '$sTableName';";
		$aTableInfo = self::QueryToArray($sTableInfoQuery);
		$sTableCharset = $aTableInfo[0]['CHARACTER_SET_NAME'];
		$sTableCollation = $aTableInfo[0]['TABLE_COLLATION'];

		if ((DEFAULT_CHARACTER_SET == $sTableCharset) && (DEFAULT_COLLATION == $sTableCollation))
		{
			return null;
		}


		return 'ALTER TABLE `'.$sTableName.'` '.self::GetSqlStringColumnDefinition().';';

	}

	/**
	 * @param string $sTable
	 *
	 * @return array
	 * @throws \MySQLException if query cannot be processed
	 */
	public static function DumpTable($sTable)
	{
		$sSql = "SELECT * FROM `$sTable`";
		try
		{
			$oResult = self::$m_oMysqli->query($sSql);
		}
		catch(mysqli_sql_exception $e)
		{
			throw new MySQLException('Failed to issue SQL query', array('query' => $sSql), $e);
		}
		if ($oResult === false)
		{
			throw new MySQLException('Failed to issue SQL query', array('query' => $sSql));
		}
		
		$aRows = array();
		while ($aRow = $oResult->fetch_array(MYSQLI_ASSOC))
		{
			$aRows[] = $aRow;
		}
		$oResult->free();
		return $aRows;
	}
	
	/**
	 * Returns the value of the specified server variable
	 * @param string $sVarName Name of the server variable
	 * @return mixed Current value of the variable
	 */	   	
	public static function GetServerVariable($sVarName)
	{
		$result = '';
		$sSql = "SELECT @@$sVarName as theVar";
		$aRows = self::QueryToArray($sSql);
		if (count($aRows) > 0)
		{
			$result = $aRows[0]['theVar'];
		}
		return $result;
	}


	/**
	 * Returns the privileges of the current user
	 * @return string privileges in a raw format
	 */	   	
	public static function GetRawPrivileges()
	{
		try
		{
			$oResult = self::Query('SHOW GRANTS'); // [ FOR CURRENT_USER()]
		}
		catch(MySQLException $e)
		{
			$iCode = self::GetErrNo();
			return "Current user not allowed to see his own privileges (could not access to the database 'mysql' - $iCode)";
		}

		$aRes = array();
		while ($aRow = $oResult->fetch_array(MYSQLI_NUM))
		{
			// so far, only one column...
			$aRes[] = implode('/', $aRow);
		}
		$oResult->free();
		// so far, only one line...
		return implode(', ', $aRes);
	}

	/**
	 * Determine the slave status of the server
	 * @return bool true if the server is slave 
	 */	   	
	public static function IsSlaveServer()
	{
		try
		{
			$oResult = self::Query('SHOW SLAVE STATUS');
		}
		catch(MySQLException $e)
		{
			throw new CoreException("Current user not allowed to check the status", array('mysql_error' => $e->getMessage()));
		}

		if ($oResult->num_rows == 0)
		{
			return false;
		}

		// Returns one single row anytime
		$aRow = $oResult->fetch_array(MYSQLI_ASSOC);
		$oResult->free();

		if (!isset($aRow['Slave_IO_Running']))
		{
			return false;
		}
		if (!isset($aRow['Slave_SQL_Running']))
		{
			return false;
		}

		// If at least one slave thread is running, then we consider that the slave is enabled
		if ($aRow['Slave_IO_Running'] == 'Yes')
		{
			return true;
		}
		if ($aRow['Slave_SQL_Running'] == 'Yes')
		{
			return true;
		}
		return false;
	}

	/**
	 * @return string query to upgrade database charset and collation if needed, null if not
	 * @throws \MySQLException
	 *
	 * @since 2.5 N°1001 switch to utf8mb4
	 * @see https://dev.mysql.com/doc/refman/5.7/en/charset-database.html
	 */
	public static function DBCheckCharsetAndCollation()
	{
		$sDBName = CMDBSource::DBName();
		$sDBInfoQuery = "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
			FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$sDBName';";
		$aDBInfo = CMDBSource::QueryToArray($sDBInfoQuery);
		$sDBCharset = $aDBInfo[0]['DEFAULT_CHARACTER_SET_NAME'];
		$sDBCollation = $aDBInfo[0]['DEFAULT_COLLATION_NAME'];

		if ((DEFAULT_CHARACTER_SET == $sDBCharset) && (DEFAULT_COLLATION == $sDBCollation))
		{
			return null;
		}

		return 'ALTER DATABASE'.CMDBSource::GetSqlStringColumnDefinition().';';
	}
}
