<?php
declare(strict_types=1);
namespace jasonwynn10\EasyCommandAutofill;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\CommandData;
use pocketmine\network\mcpe\protocol\types\CommandEnum;
use pocketmine\network\mcpe\protocol\types\CommandEnumConstraint;
use pocketmine\network\mcpe\protocol\types\CommandParameter;
use pocketmine\network\mcpe\protocol\UpdateSoftEnumPacket;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;

class Main extends PluginBase implements Listener {
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

	public function onEnable() {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if($this->getConfig()->get("Highlight-Debug", true))
			$this->debugCommands = ["dumpmemory", "gc", "timings", "status"];
	}

	/**
	 * @param string $commandName
	 * @param CommandData $data
	 *
	 * @return self
	 */
	public function addManualOverride(string $commandName, CommandData $data) : self {
		$this->manualOverrides[$commandName] = $data;
		foreach($this->getServer()->getOnlinePlayers() as $player) {
			$player->sendDataPacket(new AvailableCommandsPacket());
		}
		return $this;
	}

	/**
	 * @return CommandData[]
	 */
	public function getManualOverrides() : array {
		return $this->manualOverrides;
	}

	/**
	 * @param string $commandName
	 *
	 * @return self
	 */
	public function addDebugCommand(string $commandName) : self {
		$this->debugCommands[] = $commandName;
		foreach($this->getServer()->getOnlinePlayers() as $player) {
			$player->sendDataPacket(new AvailableCommandsPacket());
		}
		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getDebugCommands() : array {
		return $this->debugCommands;
	}

	/**
	 * @param CommandEnum $enum
	 *
	 * @return self
	 */
	public function addHardcodedEnum(CommandEnum $enum) : self {
		foreach($this->softEnums as $softEnum)
			if($enum->enumName === $softEnum->enumName)
				throw new \InvalidArgumentException("Hardcoded enum is already in soft enum list.");
		$this->hardcodedEnums[] = $enum;
		foreach($this->getServer()->getOnlinePlayers() as $player) {
			$player->sendDataPacket(new AvailableCommandsPacket());
		}
		return $this;
	}

	/**
	 * @return CommandEnum[]
	 */
	public function getHardcodedEnums() : array {
		return $this->hardcodedEnums;
	}

	/**
	 * @param CommandEnum $enum
	 *
	 * @return self
	 */
	public function addSoftEnum(CommandEnum $enum) : self {
		foreach($this->hardcodedEnums as $hardcodedEnum)
			if($enum->enumName === $hardcodedEnum->enumName)
				throw new \InvalidArgumentException("Soft enum is already in hardcoded enum list.");
		$this->softEnums[] = $enum;
		$pk = new UpdateSoftEnumPacket();
		$pk->enumName = $enum->enumName;
		$pk->values = $enum->enumValues;
		$pk->type = UpdateSoftEnumPacket::TYPE_ADD;
		$this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk);
		return $this;
	}

	/**
	 * @return CommandEnum[]
	 */
	public function getSoftEnums() : array {
		return $this->softEnums;
	}

	/**
	 * @param CommandEnum $enum
	 *
	 * @return self
	 */
	public function updateSoftEnum(CommandEnum $enum) : self {
		$pk = new UpdateSoftEnumPacket();
		$pk->enumName = $enum->enumName;
		$pk->values = $enum->enumValues;
		$pk->type = UpdateSoftEnumPacket::TYPE_SET;
		$this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk);
		return $this;
	}

	/**
	 * @param CommandEnum $enum
	 *
	 * @return self
	 */
	public function removeSoftEnum(CommandEnum $enum) : self {
		$pk = new UpdateSoftEnumPacket();
		$pk->enumName = $enum->enumName;
		$pk->values = $enum->enumValues;
		$pk->type = UpdateSoftEnumPacket::TYPE_REMOVE;
		$this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk);
		return $this;
	}

	/**
	 * @param CommandEnumConstraint $enumConstraint
	 *
	 * @return Main
	 */
	public function addEnumConstraint(CommandEnumConstraint $enumConstraint) : self {
		foreach($this->hardcodedEnums as $hardcodedEnum)
			if($enumConstraint->getEnum()->enumName === $hardcodedEnum->enumName) {
				$this->enumConstraints[] = $enumConstraint;
				foreach($this->getServer()->getOnlinePlayers() as $player) {
					$player->sendDataPacket(new AvailableCommandsPacket());
				}
				return $this;
			}
		throw new \InvalidArgumentException("Soft enum is already in hardcoded enum list.");
	}

	/**
	 * @return CommandEnumConstraint[]
	 */
	public function getEnumConstraints() : array {
		return $this->enumConstraints;
	}

	/**
	 * @param DataPacketSendEvent $event
	 * @priority HIGHEST
	 */
	public function onDataPacketSend(DataPacketSendEvent $event) {
		$pk = $event->getPacket();
		if(!$pk instanceof AvailableCommandsPacket)
			return;
		$pk->commandData = [];
		foreach($this->plugin->getServer()->getCommandMap()->getCommands() as $name => $command) {
			if(isset($pk->commandData[$command->getName()]) or $command->getName() === "help")
				continue;
			if(in_array($command->getName(), array_keys($this->getManualOverrides()))) {
				$data = $this->getManualOverrides()[$command->getName()];
				$data->commandName = $data->commandName ?? $command->getName();
				$data->commandDescription = $data->commandDescription ?? $command->getDescription();
				$data->flags = $data->flags ?? 0;
				$data->permission = (int)$command->testPermissionSilent($event->getPlayer());
				$pk->commandData[$command->getName()] = $data;
				continue;
			}
			$usage = $this->plugin->getServer()->getLanguage()->translateString($command->getUsage());
			if(empty($usage) or $usage[0] === '%') {
				$data = new CommandData();
				$data->commandName = strtolower($command->getName()); //TODO: commands containing uppercase letters in the name crash 1.9.0 client
				$data->commandDescription = $this->plugin->getServer()->getLanguage()->translateString($command->getDescription());
				$data->flags = (int)in_array($command->getName(), $this->getDebugCommands());
				$data->permission = (int)$command->testPermissionSilent($event->getPlayer());

				$parameter = new CommandParameter();
				$parameter->paramName = "args";
				$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_RAWTEXT;
				$parameter->isOptional = true;
				$data->overloads[0][0] = $parameter;

				$aliases = $command->getAliases();
				if(count($aliases) > 0){
					if(!in_array($data->commandName, $aliases, true)) {
						//work around a client bug which makes the original name not show when aliases are used
						$aliases[] = $data->commandName;
					}
					$data->aliases = new CommandEnum();
					$data->aliases->enumName = ucfirst($command->getName()) . "Aliases";
					$data->aliases->enumValues = $aliases;
				}
				$pk->commandData[$command->getName()] = $data;
				continue;
			}
			$usages = explode(" OR ", $usage); // split command trees
			$data = new CommandData();
			$data->commandName = strtolower($command->getName()); //TODO: commands containing uppercase letters in the name crash 1.9.0 client
			$data->commandDescription = Server::getInstance()->getLanguage()->translateString($command->getDescription());
			$data->flags = (int)in_array($command->getName(), $this->plugin->getDebugCommands()); // make command autofill blue if debug
			$data->permission = (int)$command->testPermissionSilent($event->getPlayer()); // hide commands players do not have permission to use
			$enumCount = 0;
			for($tree = 0; $tree < count($usages); ++$tree) {
				$usage = $usages[$tree];
				$commandString = explode(" ", $usage)[0];
				preg_match_all('/(\s?[<\[]?\s*)([a-zA-Z0-9|]+)(?:\s*:?\s*)(string|int|x y z|float|mixed|target|message|text|json|command|boolean|bool)?(?:\s*[>\]]?\s?)/iu', $usage, $matches, PREG_PATTERN_ORDER, strlen($commandString));
				$argumentCount = count($matches[0])-1;
				if($argumentCount < 0) {
					$data = new CommandData();
					$data->commandName = strtolower($command->getName()); //TODO: commands containing uppercase letters in the name crash 1.9.0 client
					$data->commandDescription = $this->plugin->getServer()->getLanguage()->translateString($command->getDescription());
					$data->flags = (int)in_array($command->getName(), $this->plugin->getDebugCommands());
					$data->permission = (int)$command->testPermissionSilent($event->getPlayer());

					$aliases = $command->getAliases();
					if(count($aliases) > 0){
						if(!in_array($data->commandName, $aliases, true)){
							//work around a client bug which makes the original name not show when aliases are used
							$aliases[] = $data->commandName;
						}
						$data->aliases = new CommandEnum();
						$data->aliases->enumName = ucfirst($command->getName()) . "Aliases";
						$data->aliases->enumValues = $aliases;
					}
					$pk->commandData[$command->getName()] = $data;
					continue;
				}
				for($argNumber = 0; $argNumber <= $argumentCount; ++$argNumber) {
					if(empty($matches[1][$argNumber])) {
						$parameter = new CommandParameter();
						$parameter->paramName = strtolower($matches[2][$argNumber]);
						$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_ENUM | AvailableCommandsPacket::ARG_FLAG_VALID | $enumCount++;
						$enum = new CommandEnum();
						$enum->enumName = strtolower($matches[2][$argNumber]);
						$enum->enumValues = [strtolower($matches[2][$argNumber])];
						$parameter->enum = $enum;
						$parameter->flags = 1;
						$parameter->isOptional = false;
						$data->overloads[$tree][$argNumber] = $parameter;
						$pk->hardcodedEnums[] = $enum;
						continue;
					}
					$optional = $matches[1][$argNumber] === '[';
					$paramName = strtolower($matches[2][$argNumber]);
					if(stripos($paramName, "|") === false) {
						if(empty($matches[3][$argNumber]) and $this->plugin->getConfig()->get("Parse-with-Parameter-Names", true)) {
							if(stripos($paramName, "player") !== false or stripos($paramName, "target") !== false) {
								$paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_TARGET;
							}elseif(stripos($paramName, "count") !== false) {
								$paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_INT;
							}else{
								$paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_RAWTEXT;
							}
						}else{
							switch(strtolower($matches[3][$argNumber])) {
								case "string":
									$paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_STRING;
								break;
								case "int":
									$paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_INT;
								break;
								case "x y z":
									$paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_POSITION;
								break;
								case "float":
									$paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_FLOAT;
								break;
								case "target":
									$paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_TARGET;
								break;
								case "message":
									$paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_MESSAGE;
								break;
								case "json":
									$paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_JSON;
								break;
								case "command":
									$paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_COMMAND;
								break;
								case "boolean":
								case "mixed":
									$paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_VALUE;
								break;
								default:
								case "text":
									$paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_RAWTEXT;
								break;
							}
						}
						$parameter = new CommandParameter();
						$parameter->paramName = $paramName;
						$parameter->paramType = $paramType;
						$parameter->isOptional = $optional;
						$data->overloads[$tree][$argNumber] = $parameter;
					}else{
						++$enumCount;
						$enumValues = explode("|", $paramName);
						$parameter = new CommandParameter();
						$parameter->paramName = $paramName;
						$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_ENUM | AvailableCommandsPacket::ARG_FLAG_VALID | $enumCount;
						$enum = new CommandEnum();
						$enum->enumName = $data->commandName." Enum#".$enumCount; // TODO: change to readable name
						$enum->enumValues = $enumValues;
						$parameter->enum = $enum;
						$parameter->flags = 1;
						$parameter->isOptional = $optional;
						$data->overloads[$tree][$argNumber] = $parameter;
						$pk->softEnums[] = $enum;
					}
				}
				$aliases = $command->getAliases();
				if(count($aliases) > 0){
					if(!in_array($data->commandName, $aliases, true)){
						//work around a client bug which makes the original name not show when aliases are used
						$aliases[] = $data->commandName;
					}
					$data->aliases = new CommandEnum();
					$data->aliases->enumName = ucfirst($command->getName()) . "Aliases";
					$data->aliases->enumValues = $aliases;
				}
				$softEnums = $this->plugin->getSoftEnums();
				foreach($pk->softEnums as $softEnum) {
					foreach($softEnums as $key => $enum) {
						if($enum->enumName === $softEnum->enumName)
							unset($softEnums[$key]);
					}
				}
				$pk->softEnums = $softEnums;
				$enums = $this->plugin->getHardcodedEnums();
				foreach($pk->hardcodedEnums as $hardcodedEnum) {
					foreach($enums as $key => $enum) {
						if($enum->enumName === $hardcodedEnum->enumName)
							unset($enums[$key]);
					}
				}
				$pk->hardcodedEnums = $enums;
				$enumConstraints = $this->plugin->getEnumConstraints();
				foreach($pk->enumConstraints as $constrainedEnum) {
					foreach($enumConstraints as $key => $enum) {
						if($enum->getEnum()->enumName === $constrainedEnum->enumName)
							unset($enumConstraints[$key]);
					}
				}
				$pk->enumConstraints = $enumConstraints;
				$pk->commandData[$command->getName()] = $data;
			}
		}
	}
}