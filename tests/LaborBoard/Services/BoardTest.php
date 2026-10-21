<?php

namespace Tests\LaborBoard\Services;

use Neuron\Jobs\Queue\JobState;
use Neuron\Jobs\Queue\QueueManager;
use Neuron\Jobs\Schedule\ScheduleFile;
use Neuron\LaborBoard\Config;
use Neuron\LaborBoard\Services\Board;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;
use Tests\LaborBoard\Fixtures\RecordingJob;
use Tests\LaborBoard\Fixtures\UniqueJob;

class BoardTest extends TestCase
{
	private QueueManager $_manager;
	private string $_base;

	protected function setUp(): void
	{
		parent::setUp();
		Registry::getInstance()->reset();
		RecordingJob::$runs = [];

		$this->_manager = new QueueManager( null, [ 'driver' => 'array', 'default' => 'default', 'retry_after' => 90 ] );
		$this->_base = sys_get_temp_dir() . '/labor_board_' . uniqid();
		mkdir( $this->_base . '/config', 0777, true );
	}

	protected function tearDown(): void
	{
		@unlink( $this->_base . '/config/schedule.yaml' );
		@rmdir( $this->_base . '/config' );
		@rmdir( $this->_base );
		Registry::getInstance()->reset();
		parent::tearDown();
	}

	private function board( ?Config $config = null, ?int $now = null, ?ScheduleFile $schedule = null ): Board
	{
		return new Board( $this->_manager, $config ?? new Config(), $schedule, null, null, null, $now );
	}

	private function writeSchedule( string $yaml ): ScheduleFile
	{
		file_put_contents( $this->_base . '/config/schedule.yaml', $yaml );

		return ScheduleFile::fromBasePath( $this->_base );
	}

	public function testUnavailableBoardExposesTheError(): void
	{
		$board = new Board( null, new Config(), null, null, null, 'store down' );

		$this->assertFalse( $board->isAvailable() );
		$this->assertSame( 'store down', $board->error() );
		$this->assertSame( [], $board->queues() );
		$this->assertSame( 0, $board->failedCount() );
	}

	public function testQueuesApplyThresholdsAndIncludeTheDefaultWhenEmpty(): void
	{
		$board = $this->board( new Config( warnPending: 2, alertPending: 5, warnOldestAge: 60 ) );

		$rows = $board->queues();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'default', $rows[0]['name'] );
		$this->assertSame( Board::LEVEL_OK, $rows[0]['level'] );

		$this->_manager->dispatch( new RecordingJob(), [ 'n' => 1 ], 'mail' );
		$this->_manager->dispatch( new RecordingJob(), [ 'n' => 2 ], 'mail' );

		$mail = array_values( array_filter( $this->board( new Config( warnPending: 2 ) )->queues(), fn( $q ) => $q['name'] === 'mail' ) )[0];
		$this->assertSame( 2, $mail['pending'] );
		$this->assertSame( Board::LEVEL_WARN, $mail['level'] );
		$this->assertSame( Board::LEVEL_ALERT, $board->level( 5, null ) );
		$this->assertSame( Board::LEVEL_WARN, $board->level( 0, 60 ) );
	}

	public function testJobListingDeleteAndRelease(): void
	{
		$now = time();
		$this->_manager->dispatch( new RecordingJob(), [ 'a' => 1 ], 'default', 0 );
		$delayedId = $this->_manager->dispatch( new RecordingJob(), [ 'a' => 2 ], 'default', 120 );

		$board = $this->board( new Config( pageSize: 10 ), $now );
		$pending = $board->jobs( 'default', JobState::Pending );
		$delayed = $board->jobs( 'default', JobState::Delayed );

		$this->assertSame( 1, $pending['total'] );
		$this->assertSame( 1, $delayed['total'] );
		$this->assertTrue( $pending['rows'][0]['can_delete'] );
		$this->assertFalse( $pending['rows'][0]['can_release'] );
		$this->assertTrue( $delayed['rows'][0]['can_release'] );
		$this->assertSame( $delayedId, $delayed['rows'][0]['id'] );
		$this->assertSame( 'RecordingJob', $pending['rows'][0]['short_class'] );
		$this->assertStringContainsString( '"a":1', $pending['rows'][0]['args_preview'] );

		$this->assertTrue( $board->releaseNow( $delayedId ) );
		$this->assertSame( 2, $this->board( now: $now )->jobs( 'default', JobState::Pending )['total'] );

		$id = $pending['rows'][0]['id'];
		$this->assertTrue( $board->deleteJob( $id ) );
		$this->assertNull( $board->job( $id ) );
	}

	public function testReservedJobIsStaleAfterThresholdAndCannotBeDeleted(): void
	{
		$this->_manager->dispatch( new RecordingJob(), [], 'default' );
		$reserved = $this->_manager->getDriver()->pop( 'default' );
		$this->assertNotNull( $reserved );

		$board = $this->board( new Config( staleAfter: 30 ), $reserved->getReservedAt() + 45 );
		$row = $board->job( $reserved->getId() );

		$this->assertSame( 'reserved', $row['state'] );
		$this->assertTrue( $row['stale'] );
		$this->assertFalse( $row['can_delete'] );
		$this->assertFalse( $board->deleteJob( $reserved->getId() ) );
	}

	public function testFailedRetryAndForget(): void
	{
		$this->_manager->dispatch( new RecordingJob(), [ 'x' => 1 ] );
		$job = $this->_manager->getDriver()->pop( 'default' );
		$this->_manager->getDriver()->failed( $job, new \RuntimeException( "boom\nstack" ) );

		$board = $this->board();
		$page = $board->failed();

		$this->assertSame( 1, $page['total'] );
		$this->assertStringContainsString( 'boom', $page['rows'][0]['exception_summary'] );
		$this->assertSame( [ 'default' ], $board->failedQueues() );

		$id = $page['rows'][0]['id'];
		$detail = $board->failedJob( $id );
		$this->assertStringContainsString( 'stack', $detail['exception'] );

		$this->assertTrue( $board->retry( $id ) );
		$this->assertSame( 0, $board->failedCount() );
		$this->assertSame( 1, $board->jobs( 'default', JobState::Pending )['total'] );
	}

	public function testRetryAllAndClearFailed(): void
	{
		$this->_manager->dispatch( new RecordingJob(), [] );
		$this->_manager->getDriver()->failed( $this->_manager->getDriver()->pop( 'default' ), new \RuntimeException( 'a' ) );
		$this->_manager->dispatch( new RecordingJob(), [] );
		$this->_manager->getDriver()->failed( $this->_manager->getDriver()->pop( 'default' ), new \RuntimeException( 'b' ) );

		$board = $this->board();

		$this->assertSame( 2, $board->retryAll() );
		$this->assertSame( 0, $board->failedCount() );
		$this->assertSame( 2, $board->jobs( 'default', JobState::Pending )['total'] );

		$this->_manager->getDriver()->failed( $this->_manager->getDriver()->pop( 'default' ), new \RuntimeException( 'c' ) );
		$this->assertSame( 1, $board->clearFailed() );
		$this->assertSame( 0, $board->failedCount() );
	}

	public function testDispatchScheduledAndUniqueDedup(): void
	{
		$schedule = $this->writeSchedule( <<<YAML
schedule:
  recording:
    class: Tests\\LaborBoard\\Fixtures\\RecordingJob
    cron: "* * * * *"
    args:
      n: 1
    queue: mail
  unique:
    class: Tests\\LaborBoard\\Fixtures\\UniqueJob
    cron: "0 * * * *"
YAML
		);

		$board = $this->board( schedule: $schedule );

		$this->assertTrue( $board->hasSchedule() );
		$this->assertCount( 2, $board->upcoming( 5 ) );

		$first = $board->dispatchScheduled( 'recording' );
		$this->assertTrue( $first['ok'] );
		$this->assertFalse( $first['deduplicated'] );
		$this->assertSame( 1, $board->jobs( 'mail', JobState::Pending )['total'] );

		$missing = $board->dispatchScheduled( 'nope' );
		$this->assertFalse( $missing['ok'] );

		$once = $board->dispatchScheduled( 'unique' );
		$again = $board->dispatchScheduled( 'unique' );
		$this->assertTrue( $once['ok'] );
		$this->assertTrue( $again['ok'] );
		$this->assertTrue( $again['deduplicated'] );
		$this->assertSame( $once['id'], $again['id'] );
	}

	public function testStatsAndHelpers(): void
	{
		$stats = $this->board()->stats();

		$this->assertTrue( $stats['available'] );
		$this->assertSame( 'array', $stats['driver'] );
		$this->assertTrue( $stats['inspectable'] );
		$this->assertSame( 90, $stats['retry_after'] );

		$this->assertSame( 'RecordingJob', Board::shortClass( RecordingJob::class ) );
		$this->assertSame( '', Board::preview( [] ) );
		$this->assertSame( 'boom', Board::firstLine( "boom\nstack" ) );
		$this->assertStringContainsString( "\n", Board::pretty( '{"a":1}' ) );
	}

	public function testClearQueueRemovesWaitingJobs(): void
	{
		$this->_manager->dispatch( new RecordingJob(), [], 'mail' );
		$this->_manager->dispatch( new UniqueJob(), [], 'mail' );

		$this->assertSame( 2, $this->board()->clearQueue( 'mail' ) );
		$this->assertSame( 0, $this->board()->jobs( 'mail', JobState::Pending )['total'] );
	}

	public function testPaging(): void
	{
		for( $i = 0; $i < 5; $i++ )
		{
			$this->_manager->dispatch( new RecordingJob(), [ 'i' => $i ] );
		}

		$page = $this->board( new Config( pageSize: 2 ) )->jobs( 'default', JobState::Pending, 2 );

		$this->assertSame( 5, $page['total'] );
		$this->assertSame( 2, $page['page'] );
		$this->assertSame( 3, $page['pages'] );
		$this->assertCount( 2, $page['rows'] );
	}
}
