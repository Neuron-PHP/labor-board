<?php
/**
 * Section tabs shared by every queue page.
 *
 * Expects: $Active ('overview'|'failed'|'queue'), $FailedCount (optional), $Queue (optional)
 *
 * @var string $Active
 */
$h = static fn( ?string $s ): string => htmlspecialchars( (string)$s );
$failedCount = $FailedCount ?? null;
?>
<ul class="nav nav-tabs mb-4">
	<li class="nav-item">
		<a class="nav-link <?= $Active === 'overview' ? 'active' : '' ?>" href="<?= $h( route_path( 'labor_board' ) ) ?>">
			<i class="bi bi-stack me-1"></i>Overview
		</a>
	</li>
	<?php if( !empty( $Queue ) ): ?>
	<li class="nav-item">
		<a class="nav-link <?= $Active === 'queue' ? 'active' : '' ?>" href="<?= $h( route_path( 'labor_board_queue', [ 'queue' => $Queue ] ) ) ?>">
			<i class="bi bi-list-task me-1"></i><?= $h( $Queue ) ?>
		</a>
	</li>
	<?php endif; ?>
	<li class="nav-item">
		<a class="nav-link <?= $Active === 'failed' ? 'active' : '' ?>" href="<?= $h( route_path( 'labor_board_failed' ) ) ?>">
			<i class="bi bi-exclamation-triangle me-1"></i>Failed
			<?php if( $failedCount !== null && $failedCount > 0 ): ?>
				<span class="badge bg-danger ms-1" data-lb-failed-count><?= (int)$failedCount ?></span>
			<?php endif; ?>
		</a>
	</li>
</ul>
