<?php

namespace Tests\LaborBoard\Filters;

use Neuron\Core\Exceptions\Forbidden;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\LaborBoard\Config;
use Neuron\LaborBoard\Filters\DelegatingAuthFilter;
use Neuron\Patterns\Registry;
use Neuron\Routing\Filter;
use Neuron\Routing\RouteMap;
use Neuron\Routing\Router;
use PHPUnit\Framework\TestCase;

class DelegatingAuthFilterTest extends TestCase
{
	private Router $_router;
	private RouteMap $_route;

	protected function setUp(): void
	{
		parent::setUp();
		Registry::getInstance()->reset();

		$this->_router = new Router();
		$this->_route = $this->createMock( RouteMap::class );
		$this->_route->method( 'getPath' )->willReturn( '/admin/queue' );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->reset();
		parent::tearDown();
	}

	private function filter( Config $config ): DelegatingAuthFilter
	{
		return new DelegatingAuthFilter( $this->_router, $config );
	}

	private function registerInner( ?callable $pre = null ): void
	{
		$this->_router->registerFilter( 'admin', new Filter(
			$pre ?? static function(): void {},
			null
		) );
	}

	public function testMissingInnerFilterFailsClosed(): void
	{
		$this->expectException( Forbidden::class );
		$this->expectExceptionMessage( "Labor Board requires the 'admin' filter" );

		$this->filter( new Config() )->pre( $this->_route );
	}

	public function testInnerFilterRunsThenRoleGatePasses(): void
	{
		$ran = false;
		$this->registerInner( function() use ( &$ran ): void {
			$ran = true;
			Registry::getInstance()->set( RegistryKeys::AUTH_USER_ROLE, 'admin' );
		} );

		$this->filter( new Config() )->pre( $this->_route );

		$this->assertTrue( $ran );
	}

	public function testWrongRoleIsForbiddenAfterInnerFilter(): void
	{
		$this->registerInner( function(): void {
			Registry::getInstance()->set( RegistryKeys::AUTH_USER_ROLE, 'editor' );
		} );

		$this->expectException( Forbidden::class );
		$this->expectExceptionMessage( 'admin' );

		$this->filter( new Config() )->pre( $this->_route );
	}

	public function testNullAuthFilterSkipsInnerAndStillChecksRole(): void
	{
		Registry::getInstance()->set( RegistryKeys::AUTH_USER_ROLE, 'admin' );

		$this->filter( new Config( authFilter: null ) )->pre( $this->_route );

		$this->assertTrue( true, 'no exception' );
	}

	public function testNullAuthFilterWithNoRoleRequiredIsOpen(): void
	{
		$this->filter( new Config( authFilter: null, roles: [] ) )->pre( $this->_route );

		$this->assertTrue( true, 'no exception' );
	}

	public function testInnerExceptionPropagates(): void
	{
		$this->registerInner( function(): void {
			throw new Forbidden( 'login first', '/admin/queue', 'admin' );
		} );

		$this->expectException( Forbidden::class );
		$this->expectExceptionMessage( 'login first' );

		$this->filter( new Config() )->pre( $this->_route );
	}
}
