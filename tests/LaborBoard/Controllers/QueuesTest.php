<?php

namespace Tests\LaborBoard\Controllers;

use Neuron\Core\Exceptions\NotFound;
use Neuron\Jobs\Queue\JobState;
use Neuron\Jobs\Queue\QueueManager;
use Neuron\LaborBoard\Config;
use Neuron\LaborBoard\Controllers\Queues;
use Neuron\LaborBoard\Services\Board;
use Tests\LaborBoard\Fixtures\RecordingJob;

class QueuesTest extends ControllerTestCase
{
	public function testIndexListsAQueue(): void
	{
		$id = $this->manager->dispatch( new RecordingJob(), [ 'n' => 1 ], 'mail' );

		$this->controller( Queues::class )->index( $this->request( [ 'queue' => 'mail' ] ) );

		$this->assertSame( 'mail', $this->viewData['Queue'] );
		$this->assertSame( JobState::Pending, $this->viewData['State'] );
		$this->assertSame( 1, $this->viewData['Jobs']['total'] );
		$this->assertSame( $id, $this->viewData['Jobs']['rows'][0]['id'] );
	}

	public function testShowThrowsWhenTheJobIsMissing(): void
	{
		$this->expectException( NotFound::class );

		$this->controller( Queues::class )->show( $this->request( [ 'queue' => 'mail', 'id' => 'nope' ] ) );
	}

	public function testReleaseAndDeleteRedirect(): void
	{
		$id = $this->manager->dispatch( new RecordingJob(), [], 'mail', 60 );
		$controller = $this->controller( Queues::class );

		$controller->release( $this->request( [ 'queue' => 'mail', 'id' => $id ] ) );
		$this->assertNotNull( $controller->redirectedTo() );

		$pendingId = $this->manager->dispatch( new RecordingJob(), [], 'mail' );
		$controller->destroy( $this->request( [ 'queue' => 'mail', 'id' => $pendingId ] ) );
		$this->assertNull( $this->board()->job( $pendingId ) );
	}

	public function testClearRemovesTheQueue(): void
	{
		$this->manager->dispatch( new RecordingJob(), [], 'mail' );

		$this->controller( Queues::class )->clear( $this->request( [ 'queue' => 'mail' ] ) );

		$this->assertSame( 0, $this->board()->jobs( 'mail', JobState::Pending )['total'] );
	}

	public function testSyncDriverRedirectsHome(): void
	{
		$sync = new QueueManager( null, [ 'driver' => 'sync' ] );
		$board = new Board( $sync, new Config() );
		$controller = $this->controller( Queues::class, $board );

		$this->assertSame( '', $controller->index( $this->request( [ 'queue' => 'default' ] ) ) );
		$this->assertNotNull( $controller->redirectedTo() );
	}
}
