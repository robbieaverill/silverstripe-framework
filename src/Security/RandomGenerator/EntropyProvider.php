<?php

namespace SilverStripe\Security\RandomGenerator;

/**
 * Generates entropy values based on strongest available methods
 * (mcrypt_create_iv(), openssl_random_pseudo_bytes(), /dev/urandom, COM.CAPICOM.Utilities.1, mt_rand()).
 * Chosen method depends on operating system and PHP version.
 *
 * @package security
 */
abstract class EntropyProvider
{
	/**
	 * Returns some pseudo-random bytes as a string for use as random entropy.
	 *
	 * If nothing is applicable or can be done then nothing is returned.
	 *
	 * @return string|void
	 */
	abstract public function generate();

	/**
	 * Returns whether the current operating system is Windows
	 *
	 * @return bool
	 */
	public function getIsWindows()
	{
		return preg_match('/WIN/', PHP_OS);
	}
}
