<?php
/**
 * @var \Neuron\LaborBoard\Services\Board $Board
 * @var string|null $QueueFilter
 * @var list<string> $FailedQueues
 * @var array $Failed rows,total,page,pages,page_size
 */
$h = static fn( ?string $s ): string => htmlspecialchars( (string)$s );
$listUrl = static function( int $page = 1 ) use ( $QueueFilter ): string
{
	$query = array_filter( [ 'queue' => $QueueFilter, 'page' => $page > 1 ? $page : null ], fn( $v ) => $v !== null );

	return route_path( 'labor_board_failed' ) . ( $query ? '?' . http_build_query( $query ) : '' );
};
?>
<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Failed Jobs</h2>
		<?php if( $Failed['total'] > 0 ): ?>
		<div class="d-flex gap-2">
			<form method="post" action="<?= $h( route_path( 'labor_board_failed_retry_all' ) ) ?>"
				onsubmit="return confirm('Queue every failed job again?');">
				<?= csrf_field() ?>
				<button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>Retry all</button>
			</form>
			<form method="post" action="<?= $h( route_path( 'labor_board_failed_clear' ) ) ?>"
				onsubmit="return confirm('Forget every failed job? This cannot be undone.');">
				<?= csrf_field() ?>
				<input type="hidden" name="_method" value="DELETE">
				<button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Clear all</button>
			</form>
		</div>
		<?php endif; ?>
	</div>

	<?php $Active = 'failed'; $FailedCount = $Failed['total']; include __DIR__ . '/../_tabs.php'; ?>

	<?php if( count( $FailedQueues ) > 1 ): ?>
	<form method="get" action="<?= $h( route_path( 'labor_board_failed' ) ) ?>" class="row g-2 align-items-center mb-3">
		<div class="col-auto"><label for="lb-queue-filter" class="col-form-label">Queue</label></div>
		<div class="col-auto">
			<select id="lb-queue-filter" name="queue" class="form-select form-select-sm" onchange="this.form.submit()">
				<option value="">All</option>
				<?php foreach( $FailedQueues as $q ): ?>
					<option value="<?= $h( $q ) ?>" <?= $q === $QueueFilter ? 'selected' : '' ?>><?= $h( $q ) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</form>
	<?php endif; ?>

	<div class="card">
		<div class="card-body p-0">
			<?php if( $Failed['rows'] === [] ): ?>
				<div class="p-3 text-muted">
					<i class="bi bi-check-circle me-1"></i>
					No failed jobs<?= $QueueFilter ? ' in <code>' . $h( $QueueFilter ) . '</code>' : '' ?>.
				</div>
			<?php else: ?>
				<table class="table table-striped align-middle mb-0">
					<thead>
						<tr>
							<th>Job</th>
							<th>Queue</th>
							<th>Error</th>
							<th>Failed</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach( $Failed['rows'] as $job ): ?>
						<tr>
							<td>
								<a href="<?= $h( route_path( 'labor_board_failed_show', [ 'id' => $job['id'] ] ) ) ?>" title="<?= $h( $job['class'] ) ?>"><?= $h( $job['short_class'] ?: '(unknown)' ) ?></a>
								<?php if( $job['args_preview'] !== '' ): ?><div><code class="small"><?= $h( $job['args_preview'] ) ?></code></div><?php endif; ?>
							</td>
							<td><span class="badge bg-info text-dark"><?= $h( $job['queue'] ) ?></span></td>
							<td class="small text-danger" style="max-width: 32rem"><div class="text-truncate" title="<?= $h( $job['exception_summary'] ) ?>"><?= $h( $job['exception_summary'] ) ?></div></td>
							<td class="small text-nowrap" title="<?= $h( labor_board_time( $job['failed_at'] ) ) ?>"><?= $h( labor_board_duration( $job['failed_ago'] ) ) ?> ago</td>
							<td class="text-end text-nowrap">
								<form method="post" action="<?= $h( route_path( 'labor_board_failed_retry', [ 'id' => $job['id'] ] ) ) ?>" class="d-inline">
									<?= csrf_field() ?>
									<button type="submit" class="btn btn-sm btn-outline-primary" title="Retry"><i class="bi bi-arrow-repeat"></i></button>
								</form>
								<form method="post" action="<?= $h( route_path( 'labor_board_failed_forget', [ 'id' => $job['id'] ] ) ) ?>" class="d-inline"
									onsubmit="return confirm('Forget this failed job?');">
									<?= csrf_field() ?>
									<input type="hidden" name="_method" value="DELETE">
									<button type="submit" class="btn btn-sm btn-outline-danger" title="Forget"><i class="bi bi-trash"></i></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<?php $Paging = $Failed; $PageUrl = $listUrl; include __DIR__ . '/../_pager.php'; ?>
</div>
