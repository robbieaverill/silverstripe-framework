<?php

namespace SilverStripe\Security;

use Exception;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;

/**
 * Generates entropy values based on strongest available methods
 * (mcrypt_create_iv(), openssl_random_pseudo_bytes(), /dev/urandom, COM.CAPICOM.Utilities.1, mt_rand()).
 * Chosen method depends on operating system and PHP version.
 *
 * @author Ingo Schommer
 */
class RandomGenerator
{
	/**
	 * Note: Returned values are not guaranteed to be crypto-safe,
	 * depending on the used retrieval method.
	 *
	 * @return string Returns a random series of bytes
	 */
	public function generateEntropy()
	{
		foreach ($this->getProviders() as $providerClass) {
			/** @var RandomGenerator\EntropyProvider $provider */
			$provider = Injector::inst()->get($providerClass);
			if ($result = $provider->generate()) {
				return $result;
			}
		}

		throw new Exception('No entropy providers are correctly configured for this OS.');
	}

	/**
	 * Gets a list of configured entropy providers in the preferred sort order
	 *
	 * @return array
	 */
	public function getProviders()
	{
		$configuration = Config::inst()->get(__CLASS__, 'entropy_providers');
		if (!$configuration) {
			return [];
		}

		usort($configuration, function ($a, $b) {
			return $a['sort_order'] > $b['sort_order'];
		});

		return array_column($configuration, 'class');
	}

	/**
	 * Generates a random token that can be used for session IDs, CSRF tokens etc., based on
	 * hash algorithms.
	 *
	 * If you are using it as a password equivalent (e.g. autologin token) do NOT store it
	 * in the database as a plain text but encrypt it with Member::encryptWithUserSettings.
	 *
	 * @param String $algorithm Any identifier listed in hash_algos() (Default: whirlpool)
	 *
	 * @return String Returned length will depend on the used $algorithm
	 */
	public function randomToken($algorithm = 'whirlpool')
	{
		return hash($algorithm, $this->generateEntropy());
	}
}
