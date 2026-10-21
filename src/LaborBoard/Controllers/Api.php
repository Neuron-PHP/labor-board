<?php

namespace Neuron\LaborBoard\Controllers;

use Neuron\LaborBoard\Filters\DelegatingAuthFilter;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * JSON for the auto-refreshing counters.
 *
 * @package Neuron\LaborBoard\Controllers
 */
#[RouteGroup( prefix: '/admin/queue', filters: [ DelegatingAuthFilter::NAME ] )]
class Api extends Base
{
	#[Get( '/api/stats', name: 'labor_board_api_stats' )]
	public function stats( Request $request ): string
	{
		return $this->renderJson( HttpResponseStatus::OK, $this->board()->stats() );
	}
}
