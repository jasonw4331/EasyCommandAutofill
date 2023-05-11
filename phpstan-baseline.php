<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Method jasonwynn10\\\\EasyCommandAutofill\\\\Main\\:\\:generateAliasEnum\\(\\) has parameter \\$aliases with no value type specified in iterable type array\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Method jasonwynn10\\\\EasyCommandAutofill\\\\Main\\:\\:generateGenericCommandData\\(\\) has parameter \\$aliases with no value type specified in iterable type array\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Method jasonwynn10\\\\EasyCommandAutofill\\\\Main\\:\\:generatePocketMineDefaultCommandData\\(\\) has parameter \\$aliases with no value type specified in iterable type array\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$input of method pocketmine\\\\item\\\\StringToItemParser\\:\\:parse\\(\\) expects string, int\\|string given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$target of method pocketmine\\\\command\\\\Command\\:\\:testPermissionSilent\\(\\) expects pocketmine\\\\command\\\\CommandSender, pocketmine\\\\player\\\\Player\\|null given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$callback of function array_filter expects callable\\(int\\|string\\)\\: mixed, Closure\\(string\\)\\: bool given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$enumValues of class pocketmine\\\\network\\\\mcpe\\\\protocol\\\\types\\\\command\\\\CommandEnum constructor expects array\\<int, string\\>, array\\<int\\<0, max\\>, int\\|string\\> given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$enumValues of class pocketmine\\\\network\\\\mcpe\\\\protocol\\\\types\\\\command\\\\CommandEnum constructor expects array\\<int, string\\>, array\\<int\\|string\\> given\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$enumValues of class pocketmine\\\\network\\\\mcpe\\\\protocol\\\\types\\\\command\\\\CommandEnum constructor expects array\\<int, string\\>, array\\<string, string\\> given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$player of method jasonwynn10\\\\EasyCommandAutofill\\\\Main\\:\\:generatePlayerSpecificCommandData\\(\\) expects pocketmine\\\\player\\\\Player, pocketmine\\\\player\\\\Player\\|null given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Main.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
