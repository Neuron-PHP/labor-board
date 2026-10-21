<?php

namespace Tests\LaborBoard;

use Neuron\Core\System\MemorySession;
use Neuron\LaborBoard\Flash;
use PHPUnit\Framework\TestCase;

class FlashTest extends TestCase
{
	public function testPullReturnsAndConsumesMessages(): void
	{
		$session = new MemorySession();
		$flash = new Flash( $session );

		$flash->success( 'queued' );
		$flash->error( 'nope' );

		( new \ReflectionMethod( $session, 'ageFlashData' ) )->invoke( $session );

		$first = $flash->pull();
		$this->assertSame( 'queued', $first['Success'] );
		$this->assertSame( 'nope', $first['Error'] );

		$second = $flash->pull();
		$this->assertNull( $second['Success'] );
		$this->assertNull( $second['Error'] );
	}
}
