<?php
declare(strict_types=1);
namespace jasonwynn10\EasyCommandAutofill;

use pocketmine\command\Command;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\event\EventPriority;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\ItemBlock;
use pocketmine\item\StringToItemParser;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandData;
use pocketmine\network\mcpe\protocol\types\command\CommandEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandEnumConstraint;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\network\mcpe\protocol\types\ParticleIds;
use pocketmine\network\mcpe\protocol\UpdateSoftEnumPacket;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;

class Main extends PluginBase{
	/** @var CommandData[] $manualOverrides */
	protected $manualOverrides = [];
	/** @var string[] $debugCommands */
	protected $debugCommands = [];
	/** @var CommandEnum[] $hardcodedEnums */
	protected $hardcodedEnums = [];
	/** @var CommandEnum[] $softEnums */
	protected $softEnums = [];
	/** @var CommandEnumConstraint[] $enumConstraints */
	protected $enumConstraints = [];

	public function onEnable() : void {
		$this->getServer()->getPluginManager()->registerEvent(DataPacketSendEvent::class, \Closure::fromCallable([$this, "onDataPacketSend"]), EventPriority::HIGH, $this, false);

		if($this->getConfig()->get('Highlight Debugging Commands', false) !== false)
			$this->debugCommands = ['dumpmemory', 'gc', 'timings', 'status'];

		if($this->getConfig()->get('Generate Default Enums', true) !== false)
			$this->setDefaultEnumData();

		if($this->getConfig()->get('Generate PocketMine Command Autofill', true) !== false)
			$this->setDefaultCommandData();
	}

	private function setDefaultEnumData() : void {
		$worldConstants = array_keys((new \ReflectionClass(World::class))->getConstants());
		$levelEventConstants = array_keys((new \ReflectionClass(LevelEvent::class))->getConstants());

		$this->addHardcodedEnum(new CommandEnum('Boolean', ['true', 'false']), false);

		$difficultyOptions = array_filter($worldConstants, fn(string $constant) => str_starts_with($constant, 'DIFFICULTY_'));
		$difficultyOptions = array_map(fn(string $difficultyString) => substr($difficultyString, strlen('DIFFICULTY_')), $difficultyOptions);
		$difficultyOptions = array_merge($difficultyOptions, array_map(fn(string $difficultyString) => $difficultyString[0], $difficultyOptions));
		$difficultyOptions = array_map(fn(string $difficultyString) => strtolower($difficultyString), $difficultyOptions);
		$this->addHardcodedEnum(new CommandEnum('Difficulty', $difficultyOptions), false);

		$gamemodeOptions = array_map(fn(GameMode $gamemode) => $gamemode->name(), GameMode::getAll());
		$gamemodeOptions = array_merge($gamemodeOptions, array_map(fn(string $gameModeString) => $gameModeString[0], $gamemodeOptions));
		$gamemodeOptions = array_map(fn(string $gameModeString) => strtolower($gameModeString), $gamemodeOptions);
		$this->addHardcodedEnum(new CommandEnum('GameMode', $gamemodeOptions), false); // TODO: change to translated strings

		$particleOptions = array_filter($levelEventConstants, fn(string $constant) => str_starts_with($constant, 'PARTICLE_'));
		$particleOptions = array_map(fn(string $particleString) => substr($particleString, strlen('PARTICLE_')), $particleOptions);
		$particleOptions = array_merge($particleOptions, array_keys((new \ReflectionClass(ParticleIds::class))->getConstants()));
		$particleOptions = array_unique(array_map(fn(string $particleString) => strtolower($particleString), $particleOptions));
		$this->addHardcodedEnum(new CommandEnum('Particle', $particleOptions), false);

		$soundOptions = array_filter($levelEventConstants, fn(string $constant) => str_starts_with($constant, 'SOUND_'));
		$soundOptions = array_map(fn(string $soundString) => substr($soundString, strlen('SOUND_')), $soundOptions);
		$soundOptions = array_map(fn(string $soundString) => strtolower($soundString), $soundOptions);
		$this->addHardcodedEnum(new CommandEnum('Sound', $soundOptions), false);

		$timeSpecOptions = array_filter($worldConstants, fn(string $constant) => str_starts_with($constant, 'TIME_'));
		$timeSpecOptions = array_map(fn(string $timeSpecString) => substr($timeSpecString, strlen('TIME_')), $timeSpecOptions);
		$timeSpecOptions = array_map(fn(string $timeSpecString) => strtolower($timeSpecString), $timeSpecOptions);
		$this->addHardcodedEnum(new CommandEnum('TimeSpec', $timeSpecOptions), false);

		$this->addSoftEnum(new CommandEnum('Effect', StringToEffectParser::getInstance()->getKnownAliases()), false);
		$this->addSoftEnum(new CommandEnum('Enchant', StringToEnchantmentParser::getInstance()->getKnownAliases()), false);
		$this->addSoftEnum(new CommandEnum('Enchantment', StringToEnchantmentParser::getInstance()->getKnownAliases()), false); // proper english word
		$itemOptions = StringToItemParser::getInstance()->getKnownAliases();
		$itemOptions = array_filter($itemOptions, fn(string $itemName) => str_starts_with($itemName, 'minecraft:'));
		$this->addSoftEnum(new CommandEnum('Item', $itemOptions), false);

		$blocks = [];
		foreach($itemOptions as $alias) {
			$item = StringToItemParser::getInstance()->parse($alias);
			if($item instanceof ItemBlock)
				$blocks[] = $alias;
		}
		$this->addSoftEnum(new CommandEnum('Block', $blocks), false);
	}

	private function setDefaultCommandData() : void {
		$map = $this->getServer()->getCommandMap();
		$language = $this->getServer()->getLanguage();

		$commandName = 'pocketmine:ban';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/ban <player: target> [reason: string]'));

		$commandName = 'pocketmine:ban-ip';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/ban-ip <player: target> [reason: string] OR /ban-ip <address: string> [reason: string]'));

		$commandName = 'pocketmine:banlist';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/ban <player: target> [reason: string]'));

		$commandName = 'pocketmine:clear';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/clear [player: target]'));

		$commandName = 'pocketmine:defaultgamemode';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/defaultgamemode <gameMode: GameMode> OR /defaultgamemode <gameMode: int>'));

		$commandName = 'pocketmine:deop';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/deop <player: target>'));

		$commandName = 'pocketmine:difficulty';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/difficulty <difficulty: Difficulty> OR /difficulty <difficulty: int>'));

		$commandName = 'pocketmine:dumpmemory';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/dumpmemory'));

		$commandName = 'pocketmine:effect';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/effect <player: target> <effect: Effect> [duration: int] [amplifier: int] [hideParticles: Boolean]'));

		$commandName = 'pocketmine:enchant';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/enchant <player: target> <enchantmentId: int> [level: int] OR /enchant <player: target> <enchantmentName: Enchant> [level: int]'));

		$commandName = 'pocketmine:gamemode';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/gamemode <gameMode: GameMode> [player: target] OR /gamemode <gameMode: int> [player: target]'));

		$commandName = 'pocketmine:gc';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/gc'));

		$commandName = 'pocketmine:give';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/give <player: target> <item: Item> [amount: int] [data: json]'));

		$commandName = 'pocketmine:kick';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/kick <player: target> [reason: string]'));

		$commandName = 'pocketmine:kill';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/kill <player: target>'));

		$commandName = 'pocketmine:list';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/list'));

		$commandName = 'pocketmine:me';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/me <message: string>'));

		$commandName = 'pocketmine:op';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/op <player: target>'));

		$commandName = 'pocketmine:pardon';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/pardon <player: target>'));

		$commandName = 'pocketmine:pardon-ip';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/pardon-ip <player: target> OR /pardon-ip <address: string>'));

		$commandName = 'pocketmine:particle';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/particle <player: target> <particle: string> <position: x y z> <relative: x y z> [count: int] [data: int]'));

		$commandName = 'pocketmine:plugins';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/plugins'));

		$commandName = 'pocketmine:save-all';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/save-all'));

		$commandName = 'pocketmine:save-off';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/save-off'));

		$commandName = 'pocketmine:save-on';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/save-on'));

		$commandName = 'pocketmine:say';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/say <message: string>'));

		$commandName = 'pocketmine:seed';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/seed'));

		$commandName = 'pocketmine:setworldspawn';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/setworldspawn [position: x y z]'));

		$commandName = 'pocketmine:spawnpoint';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/spawnpoint [player: target] [position: x y z]'));

		$commandName = 'pocketmine:status';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/status'));

		$commandName = 'pocketmine:stop';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/stop'));

		$commandName = 'pocketmine:tell';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/tell <player: target> <message: string>'));

		$commandName = 'pocketmine:time';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/time add <amount: int> OR /time set <amount: int> OR /time set <time: TimeSpec> OR /time <start|stop|query>')); // TODO separate trees

		$commandName = 'pocketmine:timings';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/timings <on|off|paste|reset|report>')); // TODO separate trees

		$commandName = 'pocketmine:title';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/title <player: target> <title: string> [subtitle: string] [time: int]'));

		$commandName = 'pocketmine:tp';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/tp <player: target> [position: x y z]'));

		$commandName = 'pocketmine:transferserver';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/transferserver <address: string> [port: int]'));

		$commandName = 'pocketmine:version';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/version [plugin: string]'));

		$commandName = 'pocketmine:whitelist';
		$command = $map->getCommand($commandName);
		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$this->addManualOverride($commandName, $this->generateGenericCommandData($name, $aliases, $description, '/whitelist <add|remove|on|off|list|reload> [player: target]'));
	}

	public function onDataPacketSend(DataPacketSendEvent $event) : void {
		$packets = $event->getPackets();
		foreach($packets as $pk) {
			if(!$pk instanceof AvailableCommandsPacket)
				return;
			foreach($event->getTargets() as $networkSession) {
				$player = $networkSession->getPlayer();
				$pk->commandData = [];
				foreach($this->getServer()->getCommandMap()->getCommands() as $command) {
					if(isset($pk->commandData[$command->getName()]) or $command->getName() === "help" or !$command->testPermissionSilent($player))
						continue;

					$pk->commandData[$command->getName()] = $this->generatePlayerSpecificCommandData($command, $networkSession->getPlayer());
				}
			}
			$pk->softEnums = $this->getSoftEnums();
			$pk->hardcodedEnums = $this->getHardcodedEnums();
			$pk->enumConstraints = $this->getEnumConstraints();
		}
	}

	public function generatePlayerSpecificCommandData(Command $command, Player $player) : CommandData {
		$language = $player->getLanguage();

		$name = $command->getName();
		$aliases = $command->getAliases();
		$description = $command->getDescription();
		$description = $description instanceof Translatable ? $language->translate($description) : $description;
		$usage = $command->getUsage();
		$usage = $usage instanceof Translatable ? $language->translate($usage) : $usage;
		$hasPermission = $command->testPermissionSilent($player);

		$filteredData = array_filter(
			$this->getManualOverrides(),
			fn(CommandData $data) => $name === $data->name
		);
		foreach($filteredData as $data) {
			$data->description = $description;
			$data->permission = (int)!$hasPermission;
			if(!$data->aliases instanceof CommandEnum) {
				$data->aliases = $this->generateAliasEnum($name, $aliases);
			}
			//$player->sendMessage($name.' is a manual override');
			return $data; // yes I know this in a loop, ill deal with this logic later
		}

		return $this->generateGenericCommandData($name, $aliases, $description, $usage, $hasPermission);
	}

	public function generateGenericCommandData(string $name, array $aliases, string $description, string $usage, bool $hasPermission = false) : CommandData {
		$hasPermission = (int)!$hasPermission;

		if($usage === '' or $usage[0] === '%') {
			//$player->sendMessage($name.' is a generated default');
			$data = $this->generatePocketMineDefaultCommandData($name, $aliases, $description);
			$data->permission = $hasPermission;
			return $data;
		}

		$usages = explode(" OR ", $usage); // split command trees
		$overloads = [];
		$enumCount = 0;
		for($tree = 0; $tree < count($usages); ++$tree) {
			$usage = $usages[$tree];
			$overloads[$tree] = [];
			$commandString = explode(" ", $usage)[0];
			preg_match_all('/\h*([<\[])?\h*([\w|]+)\h*:?\h*([\w\h]+)?\h*[>\]]?\h*/iu', $usage, $matches, PREG_PATTERN_ORDER, strlen($commandString)); // https://regex101.com/r/1REoJG/22
			$argumentCount = count($matches[0])-1;
			for($argNumber = 0; $argumentCount >= 0 and $argNumber <= $argumentCount; ++$argNumber) {
				if($matches[1][$argNumber] === '' or $matches[3][$argNumber] === '') {
					$paramName = strtolower($matches[2][$argNumber]);
					$softEnums = $this->getSoftEnums();
					if(isset($softEnums[$paramName])) {
						$enum = $softEnums[$paramName];
					}else{
						$this->addSoftEnum($enum = new CommandEnum($paramName, [$paramName]), false);
					}
					$overloads[$tree][$argNumber] = CommandParameter::enum($paramName, $enum, CommandParameter::FLAG_FORCE_COLLAPSE_ENUM, false); // collapse and assume required because no $optional identifier exists in usage message
					continue;
				}
				$optional = str_contains($matches[1][$argNumber], '[');
				$paramName = strtolower($matches[2][$argNumber]);
				$paramType = strtolower($matches[3][$argNumber] ?? '');
				if(in_array($paramType, array_keys(array_merge($this->softEnums, $this->hardcodedEnums)), true)) {
					$enum = $this->getSoftEnums()[$paramType] ?? $this->getHardcodedEnums()[$paramType];
					$overloads[$tree][$argNumber] = CommandParameter::enum($paramName, $enum, 0, $optional); // do not collapse because there is an $optional identifier in usage message
				}elseif(str_contains($paramName, "|")) {
					$enumValues = explode("|", $paramName);
					$this->addSoftEnum($enum = new CommandEnum($name . " Enum#" . ++$enumCount, $enumValues), false);
					$overloads[$tree][$argNumber] = CommandParameter::enum($paramName, $enum, CommandParameter::FLAG_FORCE_COLLAPSE_ENUM, $optional);
				}elseif(str_contains($paramName, "/")) {
					$enumValues = explode("/", $paramName);
					$this->addSoftEnum($enum = new CommandEnum($name . " Enum#" . ++$enumCount, $enumValues), false);
					$overloads[$tree][$argNumber] = CommandParameter::enum($paramName, $enum, CommandParameter::FLAG_FORCE_COLLAPSE_ENUM, $optional);
				}else{
					$paramType = match ($paramType) { // ordered by constant value
						'int' => AvailableCommandsPacket::ARG_TYPE_INT,
						'float' => AvailableCommandsPacket::ARG_TYPE_FLOAT,
						'mixed' => AvailableCommandsPacket::ARG_TYPE_VALUE,
						'player', 'target' => AvailableCommandsPacket::ARG_TYPE_TARGET,
						'string' => AvailableCommandsPacket::ARG_TYPE_STRING,
						'x y z' => AvailableCommandsPacket::ARG_TYPE_POSITION,
						'message' => AvailableCommandsPacket::ARG_TYPE_MESSAGE,
						default => AvailableCommandsPacket::ARG_TYPE_RAWTEXT,
						'json' => AvailableCommandsPacket::ARG_TYPE_JSON,
						'command' => AvailableCommandsPacket::ARG_TYPE_COMMAND,
					};
					$overloads[$tree][$argNumber] = CommandParameter::standard($paramName, $paramType, 0, $optional);
				}
			}
		}
		//$player->sendMessage($name.' is a fully generated command');
		return new CommandData(strtolower($name), $description, (int) ($this->getConfig()->get('Highlight Debugging Commands', false) !== false and in_array($name, $this->debugCommands, true)), $hasPermission, $this->generateAliasEnum($name, $aliases), $overloads);
	}

	public function generateAliasEnum(string $name, array $aliases) : ?CommandEnum {
		if(count($aliases) > 0) {
			if(!in_array($name, $aliases, true)) {
				//work around a client bug which makes the original name not show when aliases are used
				$aliases[] = $name;
			}
			return new CommandEnum(ucfirst($name) . "Aliases", $aliases);
		}
		return null;
	}

	private function generatePocketMineDefaultCommandData(string $name, array $aliases, string $description) : CommandData {
		return new CommandData(
			strtolower($name),
			$description,
			0,
			1,
			$this->generateAliasEnum($name, $aliases),
			[
				[
					CommandParameter::standard("args", AvailableCommandsPacket::ARG_TYPE_RAWTEXT, 0, true)
				]
			]
		);
	}

	public function addManualOverride(string $commandName, CommandData $data, bool $sendPacket = true) : self {
		$this->manualOverrides[$commandName] = $data;
		if(!$sendPacket)
			return $this;
		foreach($this->getServer()->getOnlinePlayers() as $player)
			$player->getNetworkSession()->sendDataPacket(new AvailableCommandsPacket());
		return $this;
	}

	/**
	 * @return CommandData[]
	 */
	public function getManualOverrides() : array {
		return $this->manualOverrides;
	}

	public function addDebugCommand(string $commandName, bool $sendPacket = true) : self {
		$this->debugCommands[] = $commandName;
		if(!$sendPacket)
			return $this;
		foreach($this->getServer()->getOnlinePlayers() as $player)
			$player->getNetworkSession()->sendDataPacket(new AvailableCommandsPacket());
		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getDebugCommands() : array {
		return $this->debugCommands;
	}

	public function addHardcodedEnum(CommandEnum $enum, bool $sendPacket = true) : self {
		foreach($this->softEnums as $softEnum)
			if($enum->getName() === $softEnum->getName())
				throw new \InvalidArgumentException("Hardcoded enum is already in soft enum list.");
		$this->hardcodedEnums[strtolower($enum->getName())] = $enum;
		if(!$sendPacket)
			return $this;
		foreach($this->getServer()->getOnlinePlayers() as $player)
			$player->getNetworkSession()->sendDataPacket(new AvailableCommandsPacket());
		return $this;
	}

	/**
	 * @return CommandEnum[]
	 */
	public function getHardcodedEnums() : array {
		return $this->hardcodedEnums;
	}

	public function addSoftEnum(CommandEnum $enum, bool $sendPacket = true) : self {
		foreach(array_merge($this->softEnums, $this->hardcodedEnums) as $enum2)
			if($enum->getName() === $enum2->getName())
				throw new \InvalidArgumentException("Enum is already in an enum list.");
		$this->softEnums[strtolower($enum->getName())] = $enum;
		if(!$sendPacket)
			return $this;
		$pk = UpdateSoftEnumPacket::create($enum->getName(), $enum->getValues(), UpdateSoftEnumPacket::TYPE_ADD);
		foreach($this->getServer()->getOnlinePlayers() as $player)
			$player->getNetworkSession()->sendDataPacket($pk, false);
		return $this;
	}

	public function updateSoftEnum(CommandEnum $enum, bool $sendPacket = true) : self {
		if(!in_array($enum->getName(), array_keys($this->softEnums), true))
			throw new \InvalidArgumentException("Enum is not in soft enum list.");
		$this->softEnums[strtolower($enum->getName())] = $enum;
		if(!$sendPacket)
			return $this;
		$pk = UpdateSoftEnumPacket::create($enum->getName(), $enum->getValues(), UpdateSoftEnumPacket::TYPE_SET);
		foreach($this->getServer()->getOnlinePlayers() as $player)
			$player->getNetworkSession()->sendDataPacket($pk, false);
		return $this;
	}

	public function removeSoftEnum(CommandEnum $enum, bool $sendPacket = true) : self {
		unset($this->softEnums[strtolower($enum->getName())]);
		if(!$sendPacket)
			return $this;
		$pk = UpdateSoftEnumPacket::create($enum->getName(), $enum->getValues(), UpdateSoftEnumPacket::TYPE_REMOVE);
		foreach($this->getServer()->getOnlinePlayers() as $player)
			$player->getNetworkSession()->sendDataPacket($pk, false);
		return $this;
	}

	/**
	 * @return CommandEnum[]
	 */
	public function getSoftEnums() : array {
		return $this->softEnums;
	}

	public function addEnumConstraint(CommandEnumConstraint $enumConstraint) : self {
		foreach($this->hardcodedEnums as $hardcodedEnum)
			if($enumConstraint->getEnum()->getName() === $hardcodedEnum->getName()) {
				$this->enumConstraints[] = $enumConstraint;
				foreach($this->getServer()->getOnlinePlayers() as $player) {
					$player->getNetworkSession()->sendDataPacket(new AvailableCommandsPacket());
				}
				return $this;
			}
		foreach($this->softEnums as $softEnum)
			if($enumConstraint->getEnum()->getName() === $softEnum->getName()) {
				$this->enumConstraints[] = $enumConstraint;
				foreach($this->getServer()->getOnlinePlayers() as $player) {
					$player->getNetworkSession()->sendDataPacket(new AvailableCommandsPacket());
				}
				return $this;
			}
		throw new \InvalidArgumentException("Enum name does not exist in any Enum list");
	}

	/**
	 * @return CommandEnumConstraint[]
	 */
	public function getEnumConstraints() : array {
		return $this->enumConstraints;
	}
}