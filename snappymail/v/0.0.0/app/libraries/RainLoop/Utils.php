<?php

namespace RainLoop;

class Utils
{
	/**
	 * @var string
	 */
	static $CookieDefaultPath = '';

	/**
	 * @var bool|null
	 */
	static $CookieDefaultSecure = null;

	const
		/**
		 * Used by: ServiceProxyExternal, compileLogParams, GetCsrfToken
		 * To preven CSRF attacks on all requests.
		 */
		CONNECTION_TOKEN = 'rltoken',

		/**
		 * Used by: GetAuthToken, GetAuthTokenQ, GetAccountFromCustomToken, EncryptStringQ, DecryptStringQ
		 */
		SHORT_TOKEN = 'rlsession';

	public static function EncryptString(string $sString, string $sKey) : string
	{
		return \MailSo\Base\Crypt::Encrypt($sString, $sKey);
	}

	public static function DecryptString(string $sEncryptedString, string $sKey) : string
	{
		return \MailSo\Base\Crypt::Decrypt($sEncryptedString, $sKey);
	}

	public static function EncryptStringQ(string $sString, string $sKey) : string
	{
		return \MailSo\Base\Crypt::Encrypt($sString, $sKey.'Q'.static::GetShortToken());
	}

	public static function DecryptStringQ(string $sEncryptedString, string $sKey) : string
	{
		return \MailSo\Base\Crypt::Decrypt($sEncryptedString, $sKey.'Q'.static::GetShortToken());
	}

	public static function EncodeKeyValues(array $aValues, string $sCustomKey = '') : string
	{
		return \MailSo\Base\Utils::UrlSafeBase64Encode(
			static::EncryptString(\json_encode($aValues), \md5(APP_SALT.$sCustomKey)));
	}

	public static function DecodeKeyValues(string $sEncodedValues, string $sCustomKey = '') : array
	{
		return static::unserialize(
			static::DecryptString(\MailSo\Base\Utils::UrlSafeBase64Decode($sEncodedValues), \md5(APP_SALT.$sCustomKey))
		);
	}

	public static function EncodeKeyValuesQ(array $aValues, string $sCustomKey = '') : string
	{
		return \MailSo\Base\Utils::UrlSafeBase64Encode(
			static::EncryptStringQ(
				\json_encode($aValues), \md5(APP_SALT.$sCustomKey)));
	}

	public static function DecodeKeyValuesQ(string $sEncodedValues, string $sCustomKey = '') : array
	{
		return static::unserialize(
			static::DecryptStringQ(\MailSo\Base\Utils::UrlSafeBase64Decode($sEncodedValues), \md5(APP_SALT.$sCustomKey))
		);
	}

	public static function unserialize(string $sDecodedValues) : array
	{
		try {
			return \json_decode($sDecodedValues, true, 512, JSON_THROW_ON_ERROR) ?: array();
		} catch (\Throwable $e) {
			return \unserialize($aResult) ?: array();
		}
	}

	public static function GetConnectionToken() : string
	{
		$sToken = static::GetCookie(self::CONNECTION_TOKEN, null);
		if (null === $sToken)
		{
			$sToken = \MailSo\Base\Utils::Sha1Rand(APP_SALT);
			static::SetCookie(self::CONNECTION_TOKEN, $sToken, \time() + 60 * 60 * 24 * 30);
		}

		return \md5('Connection'.APP_SALT.$sToken.'Token'.APP_SALT);
	}

	public static function Fingerprint() : string
	{
		return \md5($_SERVER['HTTP_USER_AGENT'] ?: 'RainLoopFingerprint');
	}

	public static function GetShortToken() : string
	{
		$sToken = static::GetCookie(self::SHORT_TOKEN, null);
		if (!$sToken)
		{
			$sToken = \MailSo\Base\Utils::Sha1Rand(APP_SALT);
			static::SetCookie(self::SHORT_TOKEN, $sToken, 0);
		}

		return \md5('Session'.APP_SALT.$sToken.'Token'.APP_SALT);
	}

	public static function UpdateConnectionToken() : void
	{
		$sToken = static::GetCookie(self::CONNECTION_TOKEN, '');
		if (!empty($sToken))
		{
			static::SetCookie(self::CONNECTION_TOKEN, $sToken, \time() + 60 * 60 * 24 * 30);
		}
	}

	public static function GetCsrfToken() : string
	{
		return \md5('Csrf'.APP_SALT.self::GetConnectionToken().'Token'.APP_SALT);
	}

	public static function PathMD5(string $sPath) : string
	{
		$sResult = '';
		if (\is_dir($sPath))
		{
			$oDirIterator = new \RecursiveDirectoryIterator($sPath);
			$oIterator = new \RecursiveIteratorIterator($oDirIterator, \RecursiveIteratorIterator::SELF_FIRST);

			foreach ($oIterator as $oFile)
			{
				$sResult = \md5($sResult.($oFile->isFile() ? \md5_file($oFile) : $oFile));
			}
		}

		return $sResult;
	}

	public static function ClearHtmlOutput(string $sHtml) : string
	{
//		return $sHtml;
		return \preg_replace(
			['@"\\s*/>@', '/\\s*&nbsp;/i', '/&nbsp;\\s*/i', '/[\\r\\n\\t]+/', '/>\\s+</'],
			['">', '&nbsp;', '&nbsp;', ' ', '><'],
			\trim($sHtml)
		);
	}

	/**
	 * @param mixed $mDefault = null
	 * @return mixed
	 */
	public static function GetCookie(string $sName, $mDefault = null)
	{
		return isset($_COOKIE[$sName]) ? $_COOKIE[$sName] : $mDefault;
	}

	public static function SetCookie(string $sName, string $sValue = '', int $iExpire = 0, bool $bHttpOnly = true)
	{
		$sPath = static::$CookieDefaultPath;
		$_COOKIE[$sName] = $sValue;
		\setcookie($sName, $sValue, array(
			'expires' => $iExpire,
			'path' => $sPath && 0 < \strlen($sPath) ? $sPath : '/',
//			'domain' => $sDomain,
			'secure' => isset($_SERVER['HTTPS']) || static::$CookieDefaultSecure,
			'httponly' => $bHttpOnly,
			'samesite' => 'Strict'
		));
	}

	public static function ClearCookie(string $sName)
	{
		if (isset($_COOKIE[$sName])) {
			$sPath = static::$CookieDefaultPath;
			unset($_COOKIE[$sName]);
			\setcookie($sName, '', array(
				'expires' => \time() - 3600 * 24 * 30,
				'path' => $sPath && 0 < \strlen($sPath) ? $sPath : '/',
//				'domain' => null,
				'secure' => isset($_SERVER['HTTPS']) || static::$CookieDefaultSecure,
				'httponly' => true,
				'samesite' => 'Strict'
			));
		}
	}

	public static function UrlEncode(string $sV, bool $bEncode = false) : string
	{
		return $bEncode ? \urlencode($sV) : $sV;
	}

	public static function WebPath() : string
	{
		static $sAppPath;
		if (!$sAppPath) {
			$sAppPath = \preg_replace('#index\\.php.*$#D', '', $_SERVER['SCRIPT_NAME']);
//			$sAppPath = Api::Config()->Get('labs', 'app_default_path', '');
		}
		return $sAppPath;
	}

	public static function WebVersionPath() : string
	{
		return self::WebPath().'snappymail/v/'.APP_VERSION.'/';
	}

	public static function WebStaticPath() : string
	{
		return self::WebVersionPath().'static/';
	}

	public static function RemoveSuggestionDuplicates(array $aSuggestions) : array
	{
		$aResult = array();

		foreach ($aSuggestions as $aItem)
		{
			$sLine = \implode('~~', $aItem);
			if (!isset($aResult[$sLine]))
			{
				$aResult[$sLine] = $aItem;
			}
		}

		return array_values($aResult);
	}

	public static function CustomParseIniFile(string $sFileName, bool $bProcessSections = false) : array
	{
		return @\parse_ini_file($sFileName, !!$bProcessSections) ?: array();
//		return @\parse_ini_string(\file_get_contents($sFileName), $bProcessSections) ?: array();
	}
}
