<?php

namespace Tests\LaborBoard\Fixtures;

use Neuron\Jobs\IJob;

/**
 * A job that records its runs. Not unique, so dispatching twice queues twice.
 */
class RecordingJob implements IJob
{
	/** @var list<array> */
	public static array $runs = [];

	public function getName(): string
	{
		return 'recording';
	}

	public function run( array $argv = [] ): mixed
	{
		self::$runs[] = $argv;

		return true;
	}
}
