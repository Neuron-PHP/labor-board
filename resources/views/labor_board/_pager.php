<?php
/**
 * Pagination for a Board page result.
 *
 * Expects: $Paging (array with page, pages, total, page_size), $PageUrl (callable int => string)
 */
$h = static fn( ?string $s ): string => htmlspecialchars( (string)$s );

if( ( $Paging['pages'] ?? 1 ) > 1 ):
	$page = (int)$Paging['page'];
	$pages = (int)$Paging['pages'];
	$from = max( 1, $page - 3 );
	$to = min( $pages, $page + 3 );
?>
<nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Pages">
	<span class="text-muted small"><?= (int)$Paging['total'] ?> total, <?= (int)$Paging['page_size'] ?> per page</span>
	<ul class="pagination pagination-sm mb-0">
		<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $h( $PageUrl( $page - 1 ) ) ?>">&laquo;</a></li>
		<?php for( $i = $from; $i <= $to; $i++ ): ?>
			<li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $h( $PageUrl( $i ) ) ?>"><?= $i ?></a></li>
		<?php endfor; ?>
		<li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $h( $PageUrl( $page + 1 ) ) ?>">&raquo;</a></li>
	</ul>
</nav>
<?php endif; ?>
