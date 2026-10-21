<?php

namespace Tests\LaborBoard;

use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\Source\Memory;
use Neuron\LaborBoard\Config;
use Neuron\LaborBoard\Filters\DelegatingAuthFilter;
use Neuron\LaborBoard\Provider;
use Neuron\Mvc\IMvcApplication;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;
use PHPUnit\Framework\TestCase;

class ProviderTest extends TestCase
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

	private function settings( array $values ): void
	{
		$memory = new Memory();

		foreach( $values as $name => $value )
		{
			$memory->set( Config::SECTION, $name, $value );
		}

		Registry::getInstance()->set( RegistryKeys::SETTINGS, $memory );
	}

	public function testEnabledProviderDeclaresPathsViewsAndNavigation(): void
	{
		$paths = Provider::controllerPaths();
		$this->assertNotEmpty( $paths );
		$this->assertDirectoryExists( $paths[0]['path'] );
		$this->assertSame( 'Neuron\\LaborBoard\\Controllers', $paths[0]['namespace'] );
		$this->assertDirectoryExists( Provider::viewPaths()[0] );

		$nav = Provider::navigation();
		$this->assertSame( 'system', $nav[0]['group'] );
		$this->assertSame( 'Queue', $nav[0]['label'] );
		$this->assertSame( Provider::ROUTE_OVERVIEW, $nav[0]['route'] );
	}

	public function testDisabledProviderDeclaresNothing(): void
	{
		$this->settings( [ 'enabled' => false ] );

		$this->assertSame( [], Provider::controllerPaths() );
		$this->assertSame( [], Provider::viewPaths() );
		$this->assertSame( [], Provider::navigation() );
	}

	public function testRegisterAttachesTheDelegatingFilter(): void
	{
		$router = new Router();
		$app = $this->createMock( IMvcApplication::class );
		$app->method( 'getRouter' )->willReturn( $router );

		Provider::register( $app );

		$this->assertSame( DelegatingAuthFilter::class, get_class( $router->getFilter( DelegatingAuthFilter::NAME ) ) );
	}

	public function testDisabledRegisterIsANoOp(): void
	{
		$this->settings( [ 'enabled' => false ] );

		$router = new Router();
		$app = $this->createMock( IMvcApplication::class );
		$app->expects( $this->never() )->method( 'getRouter' );

		Provider::register( $app );

		try
		{
			$router->getFilter( DelegatingAuthFilter::NAME );
			$this->fail( 'disabled provider must not register the filter' );
		}
		catch( \Exception )
		{
			$this->assertTrue( true );
		}
	}
}
