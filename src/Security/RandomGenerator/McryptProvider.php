<?php

namespace SilverStripe\Security\RandomGenerator;

/**
 * Generates entropy using the PHP mcrypt library
 *
 * @deprecated 4.0.0 Use OpenSslProvider instead. Not compatible with PHP 7.1.
 * @package security
 */
class McryptProvider extends EntropyProvider
{
	/**
	 * {@inheritDoc}
	 *
	 * TODO Fails with "Could not gather sufficient random data" on IIS, temporarily disabled on windows
	 *
	 * @return string|void
	 */
	public function generate()
	{
		if (!$this->getIsWindows() && function_exists('mcrypt_create_iv')) {
			$e = mcrypt_create_iv(64, MCRYPT_DEV_URANDOM);
			if ($e !== false) {
				return $e;
			}
		}
	}
}
