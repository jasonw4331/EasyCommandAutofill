<?php

declare(strict_types=1);

namespace jasonw4331\EasyCommandAutofill;

use muqsit\simplepackethandler\SimplePacketHandler;
use pocketmine\command\Command;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\event\EventPriority;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\ItemBlock;
use pocketmine\item\StringToItemParser;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandData;
use pocketmine\network\mcpe\protocol\types\command\CommandEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandEnumConstraint;
use pocketmine\network\mcpe\protocol\types\command\CommandOverload;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\network\mcpe\protocol\types\ParticleIds;
use pocketmine\network\mcpe\protocol\UpdateSoftEnumPacket;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;
use ReflectionClass;
use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function count;
use function explode;
use function in_array;
use function mb_strtolower;
use function preg_match_all;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;
use function ucfirst;
use const PREG_PATTERN_ORDER;

final class Main extends PluginBase{
	/** @var CommandData[] $manualOverrides */
	private array $manualOverrides = [];
	/** @var string[] $debugCommands */
	private array $debugCommands = [];
	/** @var CommandEnum[] $hardcodedEnums */
	private array $hardcodedEnums = [];
	/** @var CommandEnum[] $softEnums */
	private array $softEnums = [];
	/** @var CommandEnumConstraint[] $enumConstraints */
	private array $enumConstraints = [];

	public function onEnable() : void{
		SimplePacketHandler::createInterceptor($this, EventPriority::HIGH, false)->interceptOutgoing($this->handleCommandsPacket(...));

		if($this->getConfig()->get('Highlight Debugging Commands', false) !== false)
			$this->debugCommands = ['dumpmemory', 'gc', 'timings', 'status'];

		if($this->getConfig()->get('Generate Default Enums', true) !== false)
			$this->setDefaultEnumData();

		if($this->getConfig()->get('Generate PocketMine Command Autofill', true) !== false)
			$this->setDefaultCommandUsages();
	}

	private function setDefaultEnumData() : void{
		$worldConstants = array_keys((new ReflectionClass(World::class))->getConstants());
		$levelEventConstants = array_keys((new ReflectionClass(LevelEvent::class))->getConstants());

		$this->addHardcodedEnum(new CommandEnum('Boolean', ['true', 'false'], false), false);

		$difficultyOptions = array_filter($worldConstants, fn(string $constant) => str_starts_with($constant, 'DIFFICULTY_'));
		$difficultyOptions = array_map(fn(string $difficultyString) => substr($difficultyString, strlen('DIFFICULTY_')), $difficultyOptions);
		$difficultyOptions = array_merge($difficultyOptions, array_map(fn(string $difficultyString) => $difficultyString[0], $difficultyOptions));
		$difficultyOptions = array_map(fn(string $difficultyString) => mb_strtolower($difficultyString), $difficultyOptions);
		$this->addHardcodedEnum(new CommandEnum('Difficulty', $difficultyOptions, false), false);

		$gamemodeOptions = array_keys(GameMode::getAll());
		$gamemodeOptions = array_merge($gamemodeOptions, array_map(fn(string $gameModeString) => $gameModeString[0], $gamemodeOptions));
		$gamemodeOptions = array_map(fn(string $gameModeString) => mb_strtolower($gameModeString), $gamemodeOptions);
		$this->addHardcodedEnum(new CommandEnum('GameMode', $gamemodeOptions, false), false); // TODO: change to translated strings

		$particleOptions = array_filter($levelEventConstants, fn(string $constant) => str_starts_with($constant, 'PARTICLE_'));
		$particleOptions = array_map(fn(string $particleString) => substr($particleString, strlen('PARTICLE_')), $particleOptions);
		$particleOptions = array_merge($particleOptions, array_keys((new ReflectionClass(ParticleIds::class))->getConstants()));
		$particleOptions = array_unique(array_map(fn(string $particleString) => mb_strtolower($particleString), $particleOptions));
		$this->addHardcodedEnum(new CommandEnum('Particle', $particleOptions, false), false);

		$soundOptions = array_filter($levelEventConstants, fn(string $constant) => str_starts_with($constant, 'SOUND_'));
		$soundOptions = array_map(fn(string $soundString) => substr($soundString, strlen('SOUND_')), $soundOptions);
		$soundOptions = array_map(fn(string $soundString) => mb_strtolower($soundString), $soundOptions);
		$this->addHardcodedEnum(new CommandEnum('Sound', $soundOptions, false), false);

		$timeSpecOptions = array_filter($worldConstants, fn(string $constant) => str_starts_with($constant, 'TIME_'));
		$timeSpecOptions = array_map(fn(string $timeSpecString) => substr($timeSpecString, strlen('TIME_')), $timeSpecOptions);
		$timeSpecOptions = array_map(fn(string $timeSpecString) => mb_strtolower($timeSpecString), $timeSpecOptions);
		$this->addHardcodedEnum(new CommandEnum('TimeSpec', $timeSpecOptions, false), false);

		/** @var string[] $effectOptions */
		$effectOptions = StringToEffectParser::getInstance()->getKnownAliases();
		$this->addSoftEnum(new CommandEnum('Effect', $effectOptions, true), false);
		$this->addSoftEnum(new CommandEnum('Effects', $effectOptions, true), false);
		/** @var string[] $enchantmentOptions */
		$enchantmentOptions = StringToEnchantmentParser::getInstance()->getKnownAliases();
		$this->addSoftEnum(new CommandEnum('Enchant', $enchantmentOptions, true), false);
		$this->addSoftEnum(new CommandEnum('Enchants', $enchantmentOptions, true), false);
		$this->addSoftEnum(new CommandEnum('Enchantment', $enchantmentOptions, true), false); // proper english word
		$this->addSoftEnum(new CommandEnum('Enchantments', $enchantmentOptions, true), false); // proper english word (plural)
		/** @var string[] $itemOptions */
		$itemOptions = StringToItemParser::getInstance()->getKnownAliases();
		$itemOptions = array_filter($itemOptions, fn(string $itemName) => str_starts_with($itemName, 'minecraft:'));
		$this->addSoftEnum(new CommandEnum('Item', $itemOptions, true), false);
		$this->addSoftEnum(new CommandEnum('Items', $itemOptions, true), false);

		$blocks = [];
		foreach($itemOptions as $alias){
			$item = StringToItemParser::getInstance()->parse($alias);
			if($item instanceof ItemBlock)
				$blocks[] = $alias;
		}
		$this->addSoftEnum(new CommandEnum('Block', $blocks, true), false);
	}

	private function setDefaultCommandUsages() : void{
		$map = $this->getServer()->getCommandMap();
		$language = $this->getServer()->getLanguage();

		$commandUsages = [
			'ban' => '/ban <player: target> [reason: message]',
			'ban-ip' => '/ban-ip <player: target> [reason: message] OR /ban-ip <address: string> [reason: message]',
			'banlist' => '/banlist <ips|players>',
			'clear' => '/clear [player: target] [itemName: Item] [maxCount: int]',
			'defaultgamemode' => '/defaultgamemode <gameMode: GameMode> OR /defaultgamemode <gameMode: int>',
			'deop' => '/deop <player: target>',
			'difficulty' => '/difficulty <difficulty: Difficulty> OR /difficulty <difficulty: int>',
			'dumpmemory' => '/dumpmemory',
			'effect' => '/effect <player: target> <effect: Effect> [duration: int] [amplifier: int] [hideParticles: Boolean] OR /effect <player: target> clear',
			'enchant' => '/enchant <player: target> <enchantmentId: int> [level: int] OR /enchant <player: target> <enchantmentName: Enchant> [level: int]',
			'gamemode' => '/gamemode <gameMode: GameMode> [player: target] OR /gamemode <gameMode: int> [player: target]',
			'gc' => '/gc',
			'give' => '/give <player: target> <item: Item> [amount: int] [data: json]',
			'kick' => '/kick <player: target> [reason: message]',
			'kill' => '/kill <player: target>',
			'list' => '/list',
			'me' => '/me <message: message>',
			'op' => '/op <player: target>',
			'pardon' => '/pardon <player: target>',
			'pardon-ip' => '/pardon-ip <player: target> OR /pardon-ip <address: string>',
			'particle' => '/particle <particle: Particle> <position: x y z> <relative: x y z> [count: int] [data: int]',
			'plugins' => '/plugins',
			'save-all' => '/save-all',
			'save-off' => '/save-off',
			'save-on' => '/save-on',
			'say' => '/say <message: message>',
			'seed' => '/seed',
			'setworldspawn' => '/setworldspawn [position: x y z]',
			'spawnpoint' => '/spawnpoint [player: target] [position: x y z]',
			'status' => '/status',
			'stop' => '/stop',
			'tell' => '/tell <player: target> <message: message>',
			'time' => '/time add <amount: int> OR /time query OR /time set <amount: int> OR /time set <time: TimeSpec> OR /time start OR /time stop',
			'timings' => '/timings on OR /timings off OR /timings paste OR /timings reset OR /timings report',
			'title' => '/title <player: target> <title: string> [subtitle: string] [time: int] OR /title <player: target> clear',
			'tp' => '/tp <player: target> [position: x y z] [yaw: float] [pitch: float] OR /tp <player: target> <destination: target>',
			'transferserver' => '/transferserver <address: string> [port: int]',
			'version' => '/version [plugin: string]',
			'whitelist' => '/whitelist add [player: target] OR /whitelist remove [player: target] OR /whitelist <on|off|list|reload>',
		];

		foreach($commandUsages as $commandName => $usage){
			$command = $map->getCommand('pocketmine:' . $commandName);
			if(!$command instanceof Command)
				continue;
			$name = $command->getName();
			$aliases = $command->getAliases();
			$description = $command->getDescription();
			$description = $description instanceof Translatable ? $language->translate($description) : $description;
			$this->addManualOverride('pocketmine:' . $commandName, $this->generateGenericCommandData($name, $aliases, $description, $usage));
		}
	}

	public function handleCommandsPacket(AvailableCommandsPacket $pk, NetworkSession $networkSession) : bool{
		$player = $networkSession->getPlayer();
		$pk->commandData = [];
		foreach($this->getServer()->getCommandMap()->getCommands() as $command){
			if(isset($pk->commandData[$command->getName()]) || $command->getName() === "help" || !$command->testPermissionSilent($player))
				continue;

			$pk->commandData[$command->getName()] = $this->generatePlayerSpecificCommandData($command, $networkSession->getPlayer());
		}
		$pk->softEnums = $this->getSoftEnums();
		$pk->hardcodedEnums = $this->getHardcodedEnums();
		$pk->enumConstraints = $this->getEnumConstraints();
		return true;
	}

	public function generatePlayerSpecificCommandData(Command $command, Player $player) : CommandData{
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
		foreach($filteredData as $data){
			$data->description = $description;
			$data->permission = (int) !$hasPermission;
			if(!$data->aliases instanceof CommandEnum){
				$data->aliases = $this->generateAliasEnum($name, $aliases);
			}
			//$player->sendMessage($name.' is a manual override');
			return $data; // yes I know this in a loop, ill deal with this logic later
		}

		return $this->generateGenericCommandData($name, $aliases, $description, $usage, $hasPermission);
	}

	/**
	 * @phpstan-param string[] $aliases
	 */
	public function generateGenericCommandData(string $name, array $aliases, string $description, string $usage, bool $hasPermission = false) : CommandData{
		$hasPermission = (int) !$hasPermission;

		if($usage === '' || $usage[0] === '%'){
			//$player->sendMessage($name.' is a generated default');
			$data = $this->generatePocketMineDefaultCommandData($name, $aliases, $description);
			$data->permission = $hasPermission;
			return $data;
		}

		$usages = explode(" OR ", $usage); // split command trees
		$overloads = [];
		$enumCount = 0;
		for($tree = 0; $tree < count($usages); ++$tree){
			$usage = $usages[$tree];
			$treeOverloads = [];
			$commandString = explode(" ", $usage)[0];
			preg_match_all('/\h*([<\[])?\h*([\w|]+)\h*:?\h*([\w\h]+)?\h*[>\]]?\h*/iu', $usage, $matches, PREG_PATTERN_ORDER, strlen($commandString)); // https://regex101.com/r/1REoJG/22
			$argumentCount = count($matches[0]) - 1;
			for($argNumber = 0; $argumentCount >= 0 && $argNumber <= $argumentCount; ++$argNumber){
				if($matches[1][$argNumber] === '' || $matches[3][$argNumber] === ''){
					$paramName = mb_strtolower($matches[2][$argNumber]);
					$softEnums = $this->getSoftEnums();
					if(isset($softEnums[$paramName])){
						$enum = $softEnums[$paramName];
					}else{
						$this->addSoftEnum($enum = new CommandEnum($paramName, [$paramName], true), false);
					}
					$treeOverloads[$argNumber] = CommandParameter::enum($paramName, $enum, CommandParameter::FLAG_FORCE_COLLAPSE_ENUM, false); // collapse and assume required because no $optional identifier exists in usage message
					continue;
				}
				$optional = str_contains($matches[1][$argNumber], '[');
				$paramName = mb_strtolower($matches[2][$argNumber]);
				$paramType = mb_strtolower($matches[3][$argNumber] ?? '');
				if(in_array($paramType, array_keys(array_merge($this->softEnums, $this->hardcodedEnums)), true)){
					$enum = $this->getSoftEnums()[$paramType] ?? $this->getHardcodedEnums()[$paramType];
					$treeOverloads[$argNumber] = CommandParameter::enum($paramName, $enum, 0, $optional); // do not collapse because there is an $optional identifier in usage message
				}elseif(str_contains($paramName, "|")){
					$enumValues = explode("|", $paramName);
					$this->addSoftEnum($enum = new CommandEnum($name . " Enum#" . ++$enumCount, $enumValues, true), false);
					$treeOverloads[$argNumber] = CommandParameter::enum($paramName, $enum, CommandParameter::FLAG_FORCE_COLLAPSE_ENUM, $optional);
				}elseif(str_contains($paramName, "/")){
					$enumValues = explode("/", $paramName);
					$this->addSoftEnum($enum = new CommandEnum($name . " Enum#" . ++$enumCount, $enumValues, true), false);
					$treeOverloads[$argNumber] = CommandParameter::enum($paramName, $enum, CommandParameter::FLAG_FORCE_COLLAPSE_ENUM, $optional);
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
					$treeOverloads[$argNumber] = CommandParameter::standard($paramName, $paramType, 0, $optional);
				}
			}
			$overloads[$tree] = new CommandOverload(false, $treeOverloads);
		}
		//$player->sendMessage($name.' is a fully generated command');
		return new CommandData(
			mb_strtolower($name),
			$description,
			(int) ($this->getConfig()->get('Highlight Debugging Commands', false) !== false && in_array($name, $this->debugCommands, true)),
			$hasPermission,
			$this->generateAliasEnum($name, $aliases),
			$overloads,
			[]
		);
	}

	/**
	 * @phpstan-param string[] $aliases
	 */
	public function generateAliasEnum(string $name, array $aliases) : ?CommandEnum{
		if(count($aliases) > 0){
			if(!in_array($name, $aliases, true)){
				//work around a client bug which makes the original name not show when aliases are used
				$aliases[] = $name;
			}
			return new CommandEnum(ucfirst($name) . "Aliases", $aliases, false);
		}
		return null;
	}

	/**
	 * @phpstan-param string[] $aliases
	 */
	private function generatePocketMineDefaultCommandData(string $name, array $aliases, string $description) : CommandData{
		return new CommandData(
			mb_strtolower($name),
			$description,
			0,
			1,
			$this->generateAliasEnum($name, $aliases),
			[
				new CommandOverload(false, [
					CommandParameter::standard("args", AvailableCommandsPacket::ARG_TYPE_RAWTEXT, 0, true)
				])
			],
			[]
		);
	}

	public function addManualOverride(string $commandName, CommandData $data, bool $sendPacket = true) : self{
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
	public function getManualOverrides() : array{
		return $this->manualOverrides;
	}

	public function addDebugCommand(string $commandName, bool $sendPacket = true) : self{
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
	public function getDebugCommands() : array{
		return $this->debugCommands;
	}

	public function addHardcodedEnum(CommandEnum $enum, bool $sendPacket = true) : self{
		foreach($this->softEnums as $softEnum)
			if($enum->getName() === $softEnum->getName())
				throw new \InvalidArgumentException("Hardcoded enum is already in soft enum list.");
		$this->hardcodedEnums[mb_strtolower($enum->getName())] = $enum;
		if(!$sendPacket)
			return $this;
		foreach($this->getServer()->getOnlinePlayers() as $player)
			$player->getNetworkSession()->sendDataPacket(new AvailableCommandsPacket());
		return $this;
	}

	/**
	 * @return CommandEnum[]
	 */
	public function getHardcodedEnums() : array{
		return $this->hardcodedEnums;
	}

	public function addSoftEnum(CommandEnum $enum, bool $sendPacket = true) : self{
		foreach(array_merge($this->softEnums, $this->hardcodedEnums) as $enum2)
			if($enum->getName() === $enum2->getName())
				throw new \InvalidArgumentException("Enum is already in an enum list.");
		$this->softEnums[mb_strtolower($enum->getName())] = $enum;
		if(!$sendPacket)
			return $this;
		$pk = UpdateSoftEnumPacket::create($enum->getName(), $enum->getValues(), UpdateSoftEnumPacket::TYPE_ADD);
		foreach($this->getServer()->getOnlinePlayers() as $player)
			$player->getNetworkSession()->sendDataPacket($pk, false);
		return $this;
	}

	public function updateSoftEnum(CommandEnum $enum, bool $sendPacket = true) : self{
		if(!in_array($enum->getName(), array_keys($this->softEnums), true))
			throw new \InvalidArgumentException("Enum is not in soft enum list.");
		$this->softEnums[mb_strtolower($enum->getName())] = $enum;
		if(!$sendPacket)
			return $this;
		$pk = UpdateSoftEnumPacket::create($enum->getName(), $enum->getValues(), UpdateSoftEnumPacket::TYPE_SET);
		foreach($this->getServer()->getOnlinePlayers() as $player)
			$player->getNetworkSession()->sendDataPacket($pk, false);
		return $this;
	}

	public function removeSoftEnum(CommandEnum $enum, bool $sendPacket = true) : self{
		unset($this->softEnums[mb_strtolower($enum->getName())]);
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
	public function getSoftEnums() : array{
		return $this->softEnums;
	}

	public function addEnumConstraint(CommandEnumConstraint $enumConstraint) : self{
		foreach($this->hardcodedEnums as $hardcodedEnum)
			if($enumConstraint->getEnum()->getName() === $hardcodedEnum->getName()){
				$this->enumConstraints[] = $enumConstraint;
				foreach($this->getServer()->getOnlinePlayers() as $player){
					$player->getNetworkSession()->sendDataPacket(new AvailableCommandsPacket());
				}
				return $this;
			}
		foreach($this->softEnums as $softEnum)
			if($enumConstraint->getEnum()->getName() === $softEnum->getName()){
				$this->enumConstraints[] = $enumConstraint;
				foreach($this->getServer()->getOnlinePlayers() as $player){
					$player->getNetworkSession()->sendDataPacket(new AvailableCommandsPacket());
				}
				return $this;
			}
		throw new \InvalidArgumentException("Enum name does not exist in any Enum list");
	}

	/**
	 * @return CommandEnumConstraint[]
	 */
	public function getEnumConstraints() : array{
		return $this->enumConstraints;
	}
}
