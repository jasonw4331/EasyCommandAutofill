<?php
declare(strict_types=1);
namespace jasonwynn10\EasyCommandAutofill;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\CommandData;
use pocketmine\network\mcpe\protocol\types\CommandEnum;
use pocketmine\network\mcpe\protocol\types\CommandParameter;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;

class Main extends PluginBase implements Listener {
	/** @var CommandData[] $manualOverrides */
	protected $manualOverrides = [];
	/** @var string[] $debugCommands */
	protected $debugCommands = [];

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
		return $this;
	}

	/**
	 * @return CommandData[]
	 */
	public function getManualOverrides() : array {
		return $this->manualOverrides;
	}

	/**
	 * @param string $debugCommands
	 *
	 * @return self
	 */
	public function addDebugCommand(string $debugCommands) : self {
		$this->debugCommands[] = $debugCommands;
		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getDebugCommands() : array {
		return $this->debugCommands;
	}

	/**
	 * @param DataPacketSendEvent $event
	 */
	public function onDataPacketSend(DataPacketSendEvent $event) {
		$pk = $event->getPacket();
		if(!$pk instanceof AvailableCommandsPacket)
			return;
		$pk->commandData = [];
		foreach($this->getServer()->getCommandMap()->getCommands() as $name => $command) {
			if(isset($pk->commandData[$command->getName()]) or $command->getName() === "help")
				continue;
			if(in_array($command->getName(), array_keys($this->getManualOverrides()))) {
				$pk->commandData[$command->getName()] = $this->getManualOverrides()[$name];
				continue;
			}
			$usage = $this->getServer()->getLanguage()->translateString($command->getUsage());
			if(empty($usage) or $usage[0] === '%') {
				$data = new CommandData();
				$data->commandName = strtolower($command->getName()); //TODO: commands containing uppercase letters in the name crash 1.9.0 client
				$data->commandDescription = $this->getServer()->getLanguage()->translateString($command->getDescription());
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
			$data->flags = (int)in_array($command->getName(), $this->getDebugCommands()); // make command autofill blue if debug
			$data->permission = (int)$command->testPermissionSilent($event->getPlayer()); // hide commands players do not have permission to use
			$enumCount = 0;
			for($tree = 0; $tree < count($usages); ++$tree) {
				$usage = $usages[$tree];
				$commandString = explode(" ", $usage)[0];
				preg_match_all('/(\s?[<\[]?\s*)([a-zA-Z0-9|]+)(?:\s*:?\s*)(string|int|x y z|float|mixed|target|message|text|json|command|boolean)?(?:\s*[>\]]?\s?)/iu', $usage, $matches, PREG_PATTERN_ORDER, strlen($commandString));
				$argumentCount = count($matches[0])-1;
				if($argumentCount < 0) {
					$data = new CommandData();
					$data->commandName = strtolower($command->getName()); //TODO: commands containing uppercase letters in the name crash 1.9.0 client
					$data->commandDescription = $this->getServer()->getLanguage()->translateString($command->getDescription());
					$data->flags = (int)in_array($command->getName(), $this->getDebugCommands());
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
					$optional = empty($matches[1][$argNumber]) ? false : ($matches[1][$argNumber] === '[');
					$paramName = strtolower($matches[2][$argNumber]);
					if(stripos($paramName, "|") === false) {
						if(empty($matches[3][$argNumber]) and $this->getConfig()->get("Parse-with-Parameter-Names", true)) {
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
				$pk->commandData[$command->getName()] = $data;
			}
		}
	}
}