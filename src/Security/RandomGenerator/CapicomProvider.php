<?php

namespace SilverStripe\Security\RandomGenerator;

/**
 * Generates entropy using the Windows Capicom lib
 *
 * @package security
 */
class CapicomProvider extends EntropyProvider
{
	/**
	 * Try to read from the windows RNG
	 *
	 * {@inheritDoc}
	 *
	 * @return string|void
	 */
	public function generate()
	{
		if ($this->getIsWindows() && class_exists('COM')) {
			try {
				$comObj = new \COM('CAPICOM.Utilities.1');

				if (is_callable(array($comObj,'GetRandom'))) {
					return base64_decode($comObj->GetRandom(64, 0));
				}
			} catch (Exception $ex) {
				// no-op
			}
		}
	}
}
