<?php
declare(strict_types=1);
namespace jasonwynn10\EasyCommandAutofill;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandData;
use pocketmine\network\mcpe\protocol\types\command\CommandEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\Server;

class EventListener implements Listener {
	/** @var Main $plugin */
	protected $plugin;

	/**
	 * EventListener constructor.
	 *
	 * @param Main $plugin
	 */
	public function __construct(Main $plugin) {
		$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
		$this->plugin = $plugin;
	}

	/**
	 * @param DataPacketSendEvent $event
	 * @priority HIGH
	 */
	public function onDataPacketSend(DataPacketSendEvent $event) {
		$packets = $event->getPackets();
		foreach($packets as $pk) {
			if(!$pk instanceof AvailableCommandsPacket)
				return;
			foreach($event->getTargets() as $networkSession) {
				$pk->commandData = [];
				foreach($this->plugin->getServer()->getCommandMap()->getCommands() as $name => $command) {
					if(isset($pk->commandData[$command->getName()]) or $command->getName() === "help" or !$command->testPermissionSilent($networkSession->getPlayer()))
						continue;
					if(in_array($command->getName(), array_keys($this->plugin->getManualOverrides()))) {
						$data = $this->plugin->getManualOverrides()[$command->getName()];
						$data->flags = $data->flags ?? 0;
						$data->permission = (int)!$command->testPermissionSilent($networkSession->getPlayer());
						if(!$data->aliases instanceof CommandEnum) {
							$aliases = $command->getAliases();
							if(count($aliases) > 0){
								if(!in_array($data->getName(), $aliases, true)){
									//work around a client bug which makes the original name not show when aliases are used
									$aliases[] = $data->getName();
								}
								$data->aliases = new CommandEnum(ucfirst($command->getName()) . "Aliases", $aliases);
							}
						}
						$pk->commandData[$command->getName()] = $data;
						continue;
					}
					$usage = $this->plugin->getServer()->getLanguage()->translateString($command->getUsage());
					if(empty($usage) or $usage[0] === '%') {
						$aliasEnum = null;
						$aliases = $command->getAliases();
						if(count($aliases) > 0){
							if(!in_array(strtolower($command->getName()), $aliases, true)) {
								//work around a client bug which makes the original name not show when aliases are used
								$aliases[] = strtolower($command->getName());
							}
							$aliasEnum = new CommandEnum(ucfirst($command->getName()) . "Aliases", $aliases);
						}
						$overloads = [];
						$overloads[0][0] = CommandParameter::standard("args", AvailableCommandsPacket::ARG_TYPE_RAWTEXT, 0, true);
						$data = new CommandData(strtolower($command->getName()), $this->plugin->getServer()->getLanguage()->translateString($command->getDescription()), (int)in_array($command->getName(), $this->plugin->getDebugCommands()), (int)!$command->testPermissionSilent($networkSession->getPlayer()), $aliasEnum, $overloads);
						$pk->commandData[$command->getName()] = $data;
						continue;
					}
					$usages = explode(" OR ", $usage); // split command trees
					$data = new CommandData(strtolower($command->getName()), Server::getInstance()->getLanguage()->translateString($command->getDescription()), (int)in_array($command->getName(), $this->plugin->getDebugCommands()), (int)!$command->testPermissionSilent($networkSession->getPlayer()), null, []);
					$enumCount = 0;
					for($tree = 0; $tree < count($usages); ++$tree) {
						$usage = $usages[$tree];
						$commandString = explode(" ", $usage)[0];
						preg_match_all('/(\s?[<\[]?\s*)([a-zA-Z0-9|\/]+)(?:\s*:?\s*)(string|int|x y z|float|mixed|target|message|text|json|command|boolean|bool|player)?(?:\s*[>\]]?\s?)/iu', $usage, $matches, PREG_PATTERN_ORDER, strlen($commandString));
						$argumentCount = count($matches[0])-1;
						if($argumentCount < 0) {
							$aliasEnum = null;
							$aliases = $command->getAliases();
							if(count($aliases) > 0){
								if(!in_array(strtolower($command->getName()), $aliases, true)){
									//work around a client bug which makes the original name not show when aliases are used
									$aliases[] = strtolower($command->getName());
								}
								$aliasEnum = new CommandEnum(ucfirst($command->getName()) . "Aliases", $aliases);
							}
							$data = new CommandData(strtolower($command->getName()), $this->plugin->getServer()->getLanguage()->translateString($command->getDescription()), (int)in_array($command->getName(), $this->plugin->getDebugCommands()), (int)!$command->testPermissionSilent($networkSession->getPlayer()), $aliasEnum, []);
							$pk->commandData[$command->getName()] = $data;
							continue;
						}
						for($argNumber = 0; $argNumber <= $argumentCount; ++$argNumber) {
							if(empty($matches[1][$argNumber]) or $matches[1][$argNumber] === " ") {
								$data->overloads[$tree][$argNumber] = CommandParameter::enum(strtolower($matches[2][$argNumber]), $enum = new CommandEnum(strtolower($matches[2][$argNumber]), [strtolower($matches[2][$argNumber])]), 1, false);
								$pk->hardcodedEnums[] = $enum;
								continue;
							}
							$optional = strpos($matches[1][$argNumber], '[') !== false;
							$paramName = strtolower($matches[2][$argNumber]);
							if(stripos($paramName, "|") === false and stripos($paramName, "/") === false) {
								if(empty($matches[3][$argNumber]) and $this->plugin->getConfig()->get("Parse-with-Parameter-Names", true)) {
									if(stripos($paramName, "player") !== false or stripos($paramName, "target") !== false) {
										$paramType = AvailableCommandsPacket::ARG_TYPE_TARGET;
									}elseif(stripos($paramName, "count") !== false) {
										$paramType = AvailableCommandsPacket::ARG_TYPE_INT;
									}else{
										$paramType = AvailableCommandsPacket::ARG_TYPE_RAWTEXT;
									}
								}else{
									switch(strtolower($matches[3][$argNumber])) {
										case "string":
											$paramType = AvailableCommandsPacket::ARG_TYPE_STRING;
										break;
										case "int":
											$paramType = AvailableCommandsPacket::ARG_TYPE_INT;
										break;
										case "x y z":
											$paramType = AvailableCommandsPacket::ARG_TYPE_POSITION;
										break;
										case "float":
											$paramType = AvailableCommandsPacket::ARG_TYPE_FLOAT;
										break;
										case "player":
										case "target":
											$paramType = AvailableCommandsPacket::ARG_TYPE_TARGET;
										break;
										case "message":
											$paramType = AvailableCommandsPacket::ARG_TYPE_MESSAGE;
										break;
										case "json":
											$paramType = AvailableCommandsPacket::ARG_TYPE_JSON;
										break;
										case "command":
											$paramType = AvailableCommandsPacket::ARG_TYPE_COMMAND;
										break;
										case "boolean":
										case "bool":
										case "mixed":
											$paramType = AvailableCommandsPacket::ARG_TYPE_VALUE;
										break;
										default:
										case "text":
											$paramType = AvailableCommandsPacket::ARG_TYPE_RAWTEXT;
										break;
									}
								}
								$data->overloads[$tree][$argNumber] = CommandParameter::standard($paramName, $paramType, 0, $optional);
							}elseif(stripos($paramName, "|") !== false){
								++$enumCount;
								$enumValues = explode("|", $paramName);
								$data->overloads[$tree][$argNumber] = CommandParameter::enum($paramName, $enum = $enum = new CommandEnum($data->getName()." Enum#".$enumCount, $enumValues), 1, $optional);
								$pk->softEnums[] = $enum;
							}else{
								++$enumCount;
								$enumValues = explode("/", $paramName);
								$data->overloads[$tree][$argNumber] = CommandParameter::enum($paramName, $enum = new CommandEnum($data->getName()." Enum#".$enumCount, $enumValues), 1, $optional);
								$pk->softEnums[] = $enum;
							}
						}
						$aliases = $command->getAliases();
						if(count($aliases) > 0){
							if(!in_array($data->getName(), $aliases, true)){
								//work around a client bug which makes the original name not show when aliases are used
								$aliases[] = $data->getName();
							}
							$data->aliases = new CommandEnum(ucfirst($command->getName()) . "Aliases", $aliases);
						}
						$softEnums = $this->plugin->getSoftEnums();
						foreach($pk->softEnums as $softEnum) {
							foreach($softEnums as $key => $enum) {
								if($enum->getName() === $softEnum->getName())
									unset($softEnums[$key]);
							}
						}
						$pk->softEnums = $softEnums;
						$enums = $this->plugin->getHardcodedEnums();
						foreach($pk->hardcodedEnums as $hardcodedEnum) {
							foreach($enums as $key => $enum) {
								if($enum->getName() === $hardcodedEnum->getName())
									unset($enums[$key]);
							}
						}
						$pk->hardcodedEnums = $enums;
						$enumConstraints = $this->plugin->getEnumConstraints();
						foreach($pk->enumConstraints as $constrainedEnum) {
							foreach($enumConstraints as $key => $enum) {
								if($enum->getEnum()->getName() === $constrainedEnum->getEnum()->getName())
									unset($enumConstraints[$key]);
							}
						}
						$pk->enumConstraints = $enumConstraints;
						$pk->commandData[$command->getName()] = $data;
					}
				}
			}
		}
	}
}