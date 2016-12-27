<?php

namespace SilverStripe\Security\RandomGenerator;

/**
 * Generates entropy using the Unix random generator
 *
 * @package security
 */
class UnixGeneratorProvider extends EntropyProvider
{
	/**
	 * Read from the unix random number generator
	 *
	 * {@inheritDoc}
	 *
	 * @return string|void
	 */
	public function generate()
	{
		if (!$this->getIsWindows()
			&& !ini_get('open_basedir')
			&& is_readable('/dev/urandom')
			&& ($h = fopen('/dev/urandom', 'rb'))
		) {
			$e = fread($h, 64);
			fclose($h);
			return $e;
		}
	}
}
