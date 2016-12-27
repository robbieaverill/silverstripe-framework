<?php

namespace SilverStripe\Security\RandomGenerator;

/**
 * Generates a random string (weak)
 *
 * @package security
 */
class RandomProvider extends EntropyProvider
{
	/**
	 * Generate a random string (weak)
	 *
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function generate()
	{
		return uniqid(mt_rand(), true);
	}
}
