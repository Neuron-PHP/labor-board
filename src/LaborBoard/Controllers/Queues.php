<?php

namespace Neuron\LaborBoard\Controllers;

use Neuron\Core\Exceptions\NotFound;
use Neuron\Jobs\Queue\JobState;
use Neuron\LaborBoard\Filters\DelegatingAuthFilter;
use Neuron\Mvc\Requests\Request;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Jobs waiting in one queue, with the per-job actions.
 *
 * @package Neuron\LaborBoard\Controllers
 */
#[RouteGroup( prefix: '/admin/queue', filters: [ DelegatingAuthFilter::NAME ] )]
class Queues extends Base
{
	#[Get( '/q/:queue', name: 'labor_board_queue' )]
	public function index( Request $request ): string
	{
		if( ( $redirect = $this->requireInspection() ) !== null )
		{
			return $redirect;
		}

		$queue = (string)$request->getRouteParameter( 'queue' );
		$state = JobState::tryFromInput( (string)$request->get( 'state', 'pending' ) ) ?? JobState::Pending;
		$page = max( 1, (int)$request->get( 'page', 1 ) );

		$board = $this->board();

		return $this->page( 'index', [
			'Queue' => $queue,
			'State' => $state,
			'States' => JobState::cases(),
			'Counts' => $board->stateCounts( $queue ),
			'Jobs' => $board->jobs( $queue, $state, $page ),
			'Inspectable' => $board->supportsInspection(),
		], "Queue: {$queue}" );
	}

	#[Get( '/q/:queue/:id', name: 'labor_board_job' )]
	public function show( Request $request ): string
	{
		if( ( $redirect = $this->requireInspection() ) !== null )
		{
			return $redirect;
		}

		$queue = (string)$request->getRouteParameter( 'queue' );
		$id = (string)$request->getRouteParameter( 'id' );
		$job = $this->board()->job( $id );

		if( $job === null || $job['queue'] !== $queue )
		{
			throw new NotFound( "Job {$id} is not in queue '{$queue}'." );
		}

		return $this->page( 'show', [
			'Queue' => $queue,
			'Job' => $job,
		], "Job {$job['short_class']}" );
	}

	#[Post( '/q/:queue/:id/release', name: 'labor_board_job_release', filters: [ 'csrf' ] )]
	public function release( Request $request ): string
	{
		$queue = (string)$request->getRouteParameter( 'queue' );
		$id = (string)$request->getRouteParameter( 'id' );

		return $this->board()->releaseNow( $id )
			? $this->redirect( 'labor_board_queue', [ 'queue' => $queue ], "Job {$id} is now available to run." )
			: $this->redirect( 'labor_board_queue', [ 'queue' => $queue ], null, "Job {$id} could not be released; it may have already run." );
	}

	#[Delete( '/q/:queue/:id', name: 'labor_board_job_delete', filters: [ 'csrf' ] )]
	public function destroy( Request $request ): string
	{
		$queue = (string)$request->getRouteParameter( 'queue' );
		$id = (string)$request->getRouteParameter( 'id' );

		return $this->board()->deleteJob( $id )
			? $this->redirect( 'labor_board_queue', [ 'queue' => $queue ], "Job {$id} deleted." )
			: $this->redirect( 'labor_board_queue', [ 'queue' => $queue ], null, "Job {$id} was not deleted; reserved jobs cannot be removed while a worker holds them." );
	}

	#[Delete( '/q/:queue', name: 'labor_board_queue_clear', filters: [ 'csrf' ] )]
	public function clear( Request $request ): string
	{
		$queue = (string)$request->getRouteParameter( 'queue' );
		$removed = $this->board()->clearQueue( $queue );

		return $this->redirect( 'labor_board_queue', [ 'queue' => $queue ], "Removed {$removed} job(s) from '{$queue}'." );
	}
}
