<?php

namespace Neuron\LaborBoard\Filters;

use Neuron\Core\Exceptions\Forbidden;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\LaborBoard\Config;
use Neuron\Log\Log;
use Neuron\Patterns\Registry;
use Neuron\Routing\Filter;
use Neuron\Routing\RouteMap;
use Neuron\Routing\Router;

/**
 * Runs the host application's authentication filter, then a role gate.
 *
 * The package cannot name the CMS filter directly, so `labor_board.auth_filter`
 * names a filter on the router (default `admin`) and this filter runs it at
 * request time. If that filter is not registered the request is refused
 * with 403: installing the package must never expose an unauthenticated
 * admin surface. `auth_filter: null` is the only way to skip the inner
 * filter and is logged as a warning when the component registers.
 *
 * After the inner filter has run (and published AUTH_USER_ROLE), the role
 * must be one of `labor_board.role` unless that is null.
 *
 * @package Neuron\LaborBoard\Filters
 */
class DelegatingAuthFilter extends Filter
{
	public const NAME = 'labor-board.auth';

	private Router $_router;
	private Config $_config;
	private bool $_missingLogged = false;

	public function __construct( Router $router, Config $config )
	{
		$this->_router = $router;
		$this->_config = $config;

		parent::__construct(
			function( RouteMap $route ) { $this->check( $route ); },
			null
		);
	}

	/**
	 * @param RouteMap $route
	 * @return void
	 * @throws Forbidden
	 */
	protected function check( RouteMap $route ): void
	{
		$name = $this->_config->authFilter;

		if( $name !== null )
		{
			$inner = $this->resolve( $name );

			if( $inner === null )
			{
				throw new Forbidden(
					"Labor Board requires the '{$name}' filter, which is not registered.",
					$route->getPath(),
					'labor_board'
				);
			}

			// Whatever the inner filter throws (login redirect, 403) propagates.
			$inner->pre( $route );
		}

		$role = Registry::getInstance()->get( RegistryKeys::AUTH_USER_ROLE );
		$role = is_string( $role ) ? $role : null;

		if( !$this->_config->allowsRole( $role ) )
		{
			Log::warning( 'Labor Board refused role ' . ( $role ?? '(none)' ) . " on {$route->getPath()}" );

			throw new Forbidden(
				'The queue dashboard requires the ' . implode( ' or ', $this->_config->roles ) . ' role.',
				$route->getPath(),
				'labor_board'
			);
		}
	}

	/**
	 * @param string $name
	 * @return Filter|null
	 */
	private function resolve( string $name ): ?Filter
	{
		try
		{
			return $this->_router->getFilter( $name );
		}
		catch( \Throwable )
		{
			if( !$this->_missingLogged )
			{
				$this->_missingLogged = true;
				Log::error( "Labor Board: labor_board.auth_filter names '{$name}' but no such filter is registered; refusing all requests." );
			}

			return null;
		}
	}
}
