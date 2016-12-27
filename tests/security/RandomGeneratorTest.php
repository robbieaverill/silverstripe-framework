<?php

use SilverStripe\Core\Config\Config;
use SilverStripe\Security\RandomGenerator;
use SilverStripe\Dev\SapphireTest;

/**
 * @coversDefaultClass \SilverStripe\Security\RandomGenerator
 * @package framework
 * @subpackage tests
 * @author Ingo Schommer
 */
class RandomGeneratorTest extends SapphireTest
{
	/**
	 * @var RandomGenerator
	 */
	protected $generator;

	/**
	 * {@inheritDoc}
	 */
	public function setUp()
	{
		parent::setUp();
		$this->generator = new RandomGenerator;
	}

	/**
	 * @covers ::generateEntropy
	 * @covers ::getProviders
	 */
	public function testGenerateEntropy()
	{
		$this->assertNotNull($this->generator->generateEntropy());
		$this->assertNotEquals($this->generator->generateEntropy(), $this->generator->generateEntropy());
	}

	/**
	 * @covers ::generateEntropy
	 * @expectedException Exception
	 * @expectedExceptionMessage No entropy providers are correctly configured for this OS.
	 */
	public function testGenerateEntropyThrowsExceptionWithNoProviders()
	{
		Config::inst()->update(RandomGenerator::class, 'entropy_providers', null);
		$this->generator->generateEntropy();
	}

	/**
	 * Ensure that providers are returned in preferred order according to their "sort_order"
	 *
	 * @covers ::getProviders
	 */
	public function testReturnProvidersInPreferredOrder()
	{
		Config::inst()->update(RandomGenerator::class, 'entropy_providers', null);
		Config::inst()->update(RandomGenerator::class, 'entropy_providers', [
			['class' => 'FooBar', 'sort_order' => 20],
			['class' => 'BarBaz', 'sort_order' => 5],
			['class' => 'BazFoo', 'sort_order' => 10]
		]);

		$result = $this->generator->getProviders();
		$this->assertSame(['BarBaz', 'BazFoo', 'FooBar'], $result);
	}

	/**
	 * @covers ::randomToken
	 */
	public function testGenerateHash()
	{
		$this->assertNotNull($this->generator->randomToken());
		$this->assertNotEquals($this->generator->randomToken(), $this->generator->randomToken());
	}

	/**
	 * @covers ::randomToken
	 */
	public function testGenerateHashWithAlgorithm()
	{
		$this->assertNotNull($this->generator->randomToken('md5'));
		$this->assertNotEquals($this->generator->randomToken(), $this->generator->randomToken('md5'));
	}
}
