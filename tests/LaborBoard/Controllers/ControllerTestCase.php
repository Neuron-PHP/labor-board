<?php

namespace Tests\LaborBoard\Controllers;

use Neuron\Core\System\MemorySession;
use Neuron\Jobs\Queue\QueueManager;
use Neuron\LaborBoard\Config;
use Neuron\LaborBoard\Controllers\Base;
use Neuron\LaborBoard\Flash;
use Neuron\LaborBoard\Services\Board;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Views\ViewContext;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;
use PHPUnit\Framework\TestCase;

abstract class ControllerTestCase extends TestCase
{
	protected QueueManager $manager;
	protected Router $router;
	protected IMvcApplication $app;
	protected array $viewData = [];
	protected string $rendered = '<html>ok</html>';

	protected function setUp(): void
	{
		parent::setUp();
		Registry::getInstance()->reset();
		$_GET = [];

		$this->manager = new QueueManager( null, [ 'driver' => 'array', 'default' => 'default', 'retry_after' => 90 ] );
		$this->router = $this->createMock( Router::class );
		$this->router->method( 'generateUrl' )->willReturnCallback(
			fn( string $name, array $params = [] ) => '/admin/queue/' . $name . ( $params ? '?' . http_build_query( $params ) : '' )
		);

		$this->app = $this->createMock( IMvcApplication::class );
		$this->app->method( 'getRouter' )->willReturn( $this->router );
		$this->app->method( 'hasContainer' )->willReturn( false );
	}

	protected function tearDown(): void
	{
		$_GET = [];
		Registry::getInstance()->reset();
		parent::tearDown();
	}

	protected function board( ?Config $config = null ): Board
	{
		return new Board( $this->manager, $config ?? new Config(), null, null, null );
	}

	protected function request( array $route = [] ): Request
	{
		$request = new Request();
		$request->setRouteParameters( $route );

		return $request;
	}

	/**
	 * @template T of Base
	 * @param class-string<T> $class
	 * @return T
	 */
	protected function controller( string $class, ?Board $board = null ): Base
	{
		$controller = $this->getMockBuilder( $class )
			->setConstructorArgs( [ $this->app ] )
			->onlyMethods( [ 'view' ] )
			->getMock();

		$view = $this->getMockBuilder( ViewContext::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'title', 'status', 'with', 'withCurrentUser', 'withCsrfToken', 'render' ] )
			->getMock();

		$view->method( 'title' )->willReturn( $view );
		$view->method( 'status' )->willReturn( $view );
		$view->method( 'withCurrentUser' )->willReturn( $view );
		$view->method( 'withCsrfToken' )->willReturn( $view );
		$view->method( 'with' )->willReturnCallback( function( $data ) use ( $view ) {
			if( is_array( $data ) )
			{
				$this->viewData = $data + $this->viewData;
			}

			return $view;
		} );
		$view->method( 'render' )->willReturn( $this->rendered );

		$controller->method( 'view' )->willReturn( $view );
		$controller->setBoard( $board ?? $this->board() );
		$controller->setFlash( new Flash( new MemorySession() ) );

		return $controller;
	}
}
