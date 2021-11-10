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
	protected Main $plugin;

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
	 * @priority HIGHEST
	 */
	public function onDataPacketSend(DataPacketSendEvent $event) : void {
		$packets = $event->getPackets();
		foreach($packets as $pk) {
			if(!$pk instanceof AvailableCommandsPacket)
				return;
			foreach($event->getTargets() as $networkSession) {
				$pk->commandData = [];
				foreach($this->plugin->getServer()->getCommandMap()->getCommands() as $command) {
					if(isset($pk->commandData[$command->getName()]) or $command->getName() === "help" or !$command->testPermissionSilent($networkSession->getPlayer()))
						continue;
					if(in_array($command->getName(), array_keys($this->plugin->getManualOverrides()))) {
						$data = $this->plugin->getManualOverrides()[$command->getName()];
						$data->flags = $data->flags ?? 0;
						$data->permission = $command->testPermissionSilent($networkSession->getPlayer()) ? 0 : 1;
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
					if($usage === '' or $usage[0] === '%') {
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
						$data = new CommandData(strtolower($command->getName()), $this->plugin->getServer()->getLanguage()->translateString($command->getDescription()), (int)in_array($command->getName(), $this->plugin->getDebugCommands()), $command->testPermissionSilent($networkSession->getPlayer()) ? 0 : 1, $aliasEnum, $overloads);
						$pk->commandData[$command->getName()] = $data;
						continue;
					}
					$usages = explode(" OR ", $usage); // split command trees
					$data = new CommandData(strtolower($command->getName()), Server::getInstance()->getLanguage()->translateString($command->getDescription()), (int)in_array($command->getName(), $this->plugin->getDebugCommands()), $command->testPermissionSilent($networkSession->getPlayer()) ? 0 : 1, null, []);
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
							$data = new CommandData(strtolower($command->getName()), $this->plugin->getServer()->getLanguage()->translateString($command->getDescription()), (int)in_array($command->getName(), $this->plugin->getDebugCommands()), $command->testPermissionSilent($networkSession->getPlayer()) ? 0 : 1, $aliasEnum, []);
							$pk->commandData[$command->getName()] = $data;
							continue;
						}
						for($argNumber = 0; $argNumber <= $argumentCount; ++$argNumber) {
							if(!isset($matches[1][$argNumber]) or $matches[1][$argNumber] === " ") {
								$data->overloads[$tree][$argNumber] = CommandParameter::enum(strtolower($matches[2][$argNumber]), $enum = new CommandEnum(strtolower($matches[2][$argNumber]), [strtolower($matches[2][$argNumber])]), 1, false);
								$pk->hardcodedEnums[] = $enum;
								continue;
							}
							$optional = str_contains($matches[1][$argNumber], '[');
							$paramName = strtolower($matches[2][$argNumber]);
							if(str_contains($paramName, "|") and str_contains($paramName, "/")) {
								if(!isset($matches[3][$argNumber]) and $this->plugin->getConfig()->get("Parse-with-Parameter-Names", true) === true) {
									if(str_contains($paramName, "player") or str_contains($paramName, "target")) {
										$paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_TARGET;
									}elseif(str_contains($paramName, "count")) {
										$paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_INT;
									}else{
										$paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_RAWTEXT;
									}
								}else{
									$paramType = match (strtolower($matches[3][$argNumber])) {
										"string" => AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_STRING,
										"int" => AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_INT,
										"x y z" => AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_POSITION,
										"float" => AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_FLOAT,
										"player", "target" => AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_TARGET,
										"message" => AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_MESSAGE,
										"json" => AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_JSON,
										"command" => AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_COMMAND,
										"boolean", "bool", "mixed" => AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_VALUE,
										"postfix" => AvailableCommandsPacket::ARG_FLAG_POSTFIX,
										default => AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_RAWTEXT,
									};
								}
								$data->overloads[$tree][$argNumber] = CommandParameter::standard($paramName, $paramType, 0, $optional);
							}elseif(str_contains($paramName, "|")){
								++$enumCount;
								$enumValues = explode("|", $paramName);
								$data->overloads[$tree][$argNumber] = CommandParameter::enum($paramName, $enum = new CommandEnum($data->getName()." Enum#".$enumCount, $enumValues), 1, $optional);
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