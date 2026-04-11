<?php
declare(strict_types=1);

$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'employer_browseJobs.php' . ($query !== '' ? '?' . $query : '');

header('Location: ' . $target, true, 302);
exit;
