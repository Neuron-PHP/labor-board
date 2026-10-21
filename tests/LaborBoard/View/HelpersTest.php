<?php

namespace Tests\LaborBoard\View;

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
	public function testDuration(): void
	{
		$this->assertSame( '—', labor_board_duration( null ) );
		$this->assertSame( '0s', labor_board_duration( 0 ) );
		$this->assertSame( '42s', labor_board_duration( 42 ) );
		$this->assertSame( '3m 10s', labor_board_duration( 190 ) );
		$this->assertSame( '2h 5m', labor_board_duration( 7500 ) );
		$this->assertSame( '3d 4h', labor_board_duration( 3 * 86400 + 4 * 3600 ) );
	}

	public function testTime(): void
	{
		$this->assertSame( '—', labor_board_time( null ) );
		$this->assertSame( date( 'Y-m-d H:i:s', 1_700_000_000 ), labor_board_time( 1_700_000_000 ) );
	}

	public function testCsrfFieldEscapesTheToken(): void
	{
		\Neuron\Patterns\Registry::getInstance()->set(
			\Neuron\Core\Registry\RegistryKeys::AUTH_CSRF_TOKEN,
			'"quoted"'
		);

		$html = csrf_field();

		$this->assertStringContainsString( 'name="csrf_token"', $html );
		$this->assertStringContainsString( '&quot;quoted&quot;', $html );

		\Neuron\Patterns\Registry::getInstance()->reset();
	}
}
