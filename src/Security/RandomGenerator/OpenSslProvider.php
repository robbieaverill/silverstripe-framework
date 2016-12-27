<?php

namespace SilverStripe\Security\RandomGenerator;

/**
 * Generates entropy using the openssl PHP library
 *
 * @package security
 */
class OpenSslProvider extends EntropyProvider
{
	/**
	 * Use OpenSSL methods - may slow down execution by a few ms. Only returns
	 * if a strong algorithm was used.
	 *
	 * {@inheritDoc}
	 *
	 * @return string|void
	 */
	public function generate()
	{
		if (function_exists('openssl_random_pseudo_bytes')) {
			$e = openssl_random_pseudo_bytes(64, $strong);
			if ($strong) {
				return $e;
			}
		}
	}
}
