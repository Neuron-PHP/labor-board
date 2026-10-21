<?php
/**
 * @var \Neuron\LaborBoard\Services\Board $Board
 * @var array $Job
 */
$h = static fn( ?string $s ): string => htmlspecialchars( (string)$s );
?>
<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-danger"></i><?= $h( $Job['short_class'] ?: '(unknown)' ) ?></h2>
		<div class="d-flex gap-2">
			<form method="post" action="<?= $h( route_path( 'labor_board_failed_retry', [ 'id' => $Job['id'] ] ) ) ?>">
				<?= csrf_field() ?>
				<button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>Retry</button>
			</form>
			<form method="post" action="<?= $h( route_path( 'labor_board_failed_forget', [ 'id' => $Job['id'] ] ) ) ?>"
				onsubmit="return confirm('Forget this failed job?');">
				<?= csrf_field() ?>
				<input type="hidden" name="_method" value="DELETE">
				<button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Forget</button>
			</form>
		</div>
	</div>

	<?php $Active = 'failed'; $FailedCount = $Board->failedCount(); include __DIR__ . '/../_tabs.php'; ?>

	<div class="row g-3 mb-3">
		<div class="col-lg-5">
			<div class="card h-100">
				<div class="card-header">Job</div>
				<div class="card-body p-0">
					<table class="table table-sm mb-0">
						<tbody>
							<tr><th class="text-muted fw-normal" style="width: 9rem">ID</th><td class="font-monospace small"><?= $h( $Job['id'] ) ?></td></tr>
							<tr><th class="text-muted fw-normal">Class</th><td><code><?= $h( $Job['class'] ) ?></code></td></tr>
							<tr><th class="text-muted fw-normal">Queue</th><td><span class="badge bg-info text-dark"><?= $h( $Job['queue'] ) ?></span></td></tr>
							<tr><th class="text-muted fw-normal">Failed</th><td><?= $h( labor_board_time( $Job['failed_at'] ) ) ?> <span class="text-muted small">(<?= $h( labor_board_duration( $Job['failed_ago'] ) ) ?> ago)</span></td></tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<div class="col-lg-7">
			<div class="card h-100">
				<div class="card-header">Payload</div>
				<div class="card-body">
					<pre class="mb-0 small"><code><?= $h( $Job['payload'] ) ?></code></pre>
				</div>
			</div>
		</div>
	</div>

	<div class="card border-danger">
		<div class="card-header text-danger">Exception</div>
		<div class="card-body">
			<pre class="mb-0 small" style="white-space: pre-wrap"><code><?= $h( $Job['exception'] ) ?></code></pre>
		</div>
	</div>
</div>
