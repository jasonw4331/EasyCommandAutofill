<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$target of method pocketmine\\\\command\\\\Command\\:\\:testPermissionSilent\\(\\) expects pocketmine\\\\command\\\\CommandSender, pocketmine\\\\player\\\\Player\\|null given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$player of method jasonw4331\\\\EasyCommandAutofill\\\\Main\\:\\:generatePlayerSpecificCommandData\\(\\) expects pocketmine\\\\player\\\\Player, pocketmine\\\\player\\\\Player\\|null given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Main.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
