<?php

namespace Neuron\LaborBoard\Controllers;

use Neuron\Core\NString;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\LaborBoard\Config;
use Neuron\LaborBoard\Flash;
use Neuron\LaborBoard\Provider;
use Neuron\LaborBoard\Services\Board;
use Neuron\Mvc\Controllers\Base as MvcController;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Mvc\Views\ViewLocator;
use Neuron\Patterns\Registry;

/**
 * Shared plumbing for the queue pages: the Board, flash messages, layout
 * selection and redirects.
 *
 * Views live under resources/views/labor_board/<controller>/ regardless of
 * where the host keeps its own, so getControllerViewPath() is pinned.
 *
 * @package Neuron\LaborBoard\Controllers
 */
abstract class Base extends MvcController
{
	protected Config $_config;
	private ?Board $_board = null;
	private ?Flash $_flash = null;
	private ?string $_redirectedTo = null;

	public function __construct( IMvcApplication $app )
	{
		parent::__construct( $app );
		$this->_config = Config::fromRegistry();
	}

	// -- collaborators ----------------------------------------------------

	public function setBoard( Board $board ): static
	{
		$this->_board = $board;
		return $this;
	}

	public function setFlash( Flash $flash ): static
	{
		$this->_flash = $flash;
		return $this;
	}

	protected function board(): Board
	{
		if( $this->_board === null )
		{
			$app = $this->getApplication();
			$basePath = Registry::getInstance()->get( RegistryKeys::BASE_PATH );

			$this->_board = Board::fromRegistry(
				$this->_config,
				is_string( $basePath ) ? $basePath : null,
				$app->hasContainer() ? $app->getContainer() : null
			);
		}

		return $this->_board;
	}

	protected function flash(): Flash
	{
		return $this->_flash ??= new Flash();
	}

	// -- rendering --------------------------------------------------------

	/**
	 * labor_board/<snake_case controller>
	 */
	protected function getControllerViewPath(): string
	{
		$short = new \ReflectionClass( static::class )->getShortName();

		return 'labor_board/' . new NString( $short )->toSnakeCase();
	}

	/**
	 * Render a page in the resolved layout with the common variables.
	 *
	 * @param string $page
	 * @param array $data
	 * @param string $title
	 * @param HttpResponseStatus $status
	 * @return string
	 */
	protected function page( string $page, array $data, string $title, HttpResponseStatus $status = HttpResponseStatus::OK ): string
	{
		$common = [
			'Board' => $this->board(),
			'Config' => $this->_config,
			'Layout' => $this->layout(),
			'OverviewUrl' => $this->urlFor( Provider::ROUTE_OVERVIEW ) ?? '/admin/queue',
			'StatsUrl' => $this->urlFor( 'labor_board_api_stats' ),
		];

		return $this->view()
			->title( $title )
			->status( $status )
			->with( $common + $this->flash()->pull() + $data )
			->withCurrentUser()
			->withCsrfToken()
			->render( $page, $this->layout() );
	}

	/**
	 * Layout name: `admin` when the host has one and config says auto.
	 */
	protected function layout(): string
	{
		if( $this->_config->layout !== Config::LAYOUT_AUTO )
		{
			return $this->_config->layout;
		}

		$locator = new ViewLocator();

		return $locator->locate( 'layouts/' . Config::LAYOUT_ADMIN . '.php' ) !== null
			? Config::LAYOUT_ADMIN
			: Config::LAYOUT_FALLBACK;
	}

	// -- redirects --------------------------------------------------------

	/**
	 * Flash a message and send a Location header. Returns the empty body;
	 * does not exit, so the dispatcher and tests finish normally.
	 */
	protected function redirect( string $route, array $parameters = [], ?string $success = null, ?string $error = null ): string
	{
		if( $success !== null )
		{
			$this->flash()->success( $success );
		}

		if( $error !== null )
		{
			$this->flash()->error( $error );
		}

		$url = $this->urlFor( $route, $parameters ) ?? '/admin/queue';
		$this->_redirectedTo = $url;

		if( !headers_sent() )
		{
			header( 'Location: ' . $url, true, 303 );
		}

		return '';
	}

	/**
	 * Where the last redirect() pointed, for tests.
	 */
	public function redirectedTo(): ?string
	{
		return $this->_redirectedTo;
	}

	/**
	 * Pages that need a real queue store send sync-driver installs home.
	 */
	protected function requireInspection(): ?string
	{
		$board = $this->board();

		if( !$board->isAvailable() )
		{
			return $this->redirect( Provider::ROUTE_OVERVIEW, [], null, 'The queue is unavailable: ' . $board->error() );
		}

		if( $board->isSync() )
		{
			return $this->redirect( Provider::ROUTE_OVERVIEW, [], null, 'The sync driver runs jobs immediately; there is nothing to list.' );
		}

		return null;
	}
}
