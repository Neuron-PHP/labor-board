<?php

namespace Neuron\LaborBoard;

use Neuron\Core\System\ISession;
use Neuron\Core\System\RealSession;

/**
 * One-request success/error messages, carried across a redirect.
 *
 * Keys are package-specific so a host application's own flash messages
 * are never consumed or overwritten by the queue pages.
 *
 * @package Neuron\LaborBoard
 */
class Flash
{
	public const SUCCESS = 'labor_board.success';
	public const ERROR = 'labor_board.error';

	private ISession $_session;

	public function __construct( ?ISession $session = null )
	{
		$this->_session = $session ?? new RealSession();
	}

	public function success( string $message ): void
	{
		$this->_session->flash( self::SUCCESS, $message );
	}

	public function error( string $message ): void
	{
		$this->_session->flash( self::ERROR, $message );
	}

	/**
	 * Pull both messages for the current request.
	 *
	 * @return array{Success:?string,Error:?string}
	 */
	public function pull(): array
	{
		return [
			'Success' => $this->_session->getFlash( self::SUCCESS ),
			'Error' => $this->_session->getFlash( self::ERROR ),
		];
	}
}
