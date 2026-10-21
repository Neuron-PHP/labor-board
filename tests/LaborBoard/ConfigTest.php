<?php

namespace Tests\LaborBoard;

use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\Source\Memory;
use Neuron\LaborBoard\Config;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		Registry::getInstance()->reset();
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->reset();
		parent::tearDown();
	}

	public function testDefaultsWhenSettingsAreMissing(): void
	{
		$config = Config::fromSettings( null );

		$this->assertTrue( $config->enabled );
		$this->assertSame( 'admin', $config->authFilter );
		$this->assertSame( [ 'admin' ], $config->roles );
		$this->assertSame( Config::LAYOUT_AUTO, $config->layout );
		$this->assertSame( 50, $config->pageSize );
		$this->assertSame( 10, $config->refreshSeconds );
		$this->assertNull( $config->staleAfter );
		$this->assertTrue( $config->requiresRole() );
		$this->assertTrue( $config->allowsRole( 'admin' ) );
		$this->assertFalse( $config->allowsRole( 'editor' ) );
		$this->assertFalse( $config->allowsRole( null ) );
	}

	public function testExplicitNullTurnsAuthAndRoleOff(): void
	{
		$settings = new Memory();
		$settings->set( Config::SECTION, 'auth_filter', null );
		$settings->set( Config::SECTION, 'role', 'none' );

		$config = Config::fromSettings( $settings );

		$this->assertNull( $config->authFilter );
		$this->assertSame( [], $config->roles );
		$this->assertFalse( $config->requiresRole() );
		$this->assertTrue( $config->allowsRole( 'subscriber' ) );
		$this->assertTrue( $config->allowsRole( null ) );
	}

	public function testRoleAcceptsAListOrCommaString(): void
	{
		$list = new Memory();
		$list->set( Config::SECTION, 'role', [ 'admin', 'editor' ] );
		$this->assertSame( [ 'admin', 'editor' ], Config::fromSettings( $list )->roles );

		$csv = new Memory();
		$csv->set( Config::SECTION, 'role', 'admin, editor' );
		$this->assertSame( [ 'admin', 'editor' ], Config::fromSettings( $csv )->roles );
	}

	public function testEnabledFalseAndNumericBounds(): void
	{
		$settings = new Memory();
		$settings->set( Config::SECTION, 'enabled', 'off' );
		$settings->set( Config::SECTION, 'page_size', 0 );
		$settings->set( Config::SECTION, 'refresh_seconds', -3 );
		$settings->set( Config::SECTION, 'stale_after', 45 );
		$settings->set( Config::SECTION, 'layout', 'labor-board' );

		$config = Config::fromSettings( $settings );

		$this->assertFalse( $config->enabled );
		$this->assertSame( 1, $config->pageSize );
		$this->assertSame( 0, $config->refreshSeconds );
		$this->assertSame( 45, $config->staleAfter );
		$this->assertSame( 'labor-board', $config->layout );
	}

	public function testFromRegistryReadsApplicationSettings(): void
	{
		$settings = new Memory();
		$settings->set( Config::SECTION, 'auth_filter', 'auth' );
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $settings );

		$this->assertSame( 'auth', Config::fromRegistry()->authFilter );
	}
}
