<?php
declare(strict_types=1);
namespace jasonwynn10\EasyCommandAutofill;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandData;
use pocketmine\network\mcpe\protocol\types\command\CommandEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;

class EventListener implements Listener{
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
						$data->permission = $command->testPermissionSilent($networkSession->getPlayer()) ? 0 : 1;
						if(!$data->aliases instanceof CommandEnum) {
							$data->aliases = $this->generateAliasEnum($command);
						}
						$pk->commandData[$command->getName()] = $data;
						continue;
					}
					$usage = $command->getUsage() instanceof Translatable ? $this->plugin->getServer()->getLanguage()->translate($command->getUsage()) : $command->getUsage();
					if($usage === '' or $usage[0] === '%') {
						$pk->commandData[$command->getName()] = $this->generatePocketMineDefaultCommandData($command, $networkSession->getPlayer());
						continue;
					}
					$usages = explode(" OR ", $usage); // split command trees
					$overloads = [];
					$enumCount = 0;
					for($tree = 0; $tree < count($usages); ++$tree) {
						$usage = $usages[$tree];
						$overloads[$tree] = [];
						$commandString = explode(" ", $usage)[0];
						preg_match_all('/(\s?[<\[]?\s*)([a-zA-Z0-9|\/]+)\s*:?\s*(string|int|x y z|float|mixed|target|message|text|json|command|boolean|bool|player)?\s*[>\]]?\s?/iu', $usage, $matches, PREG_PATTERN_ORDER, strlen($commandString));
						$argumentCount = count($matches[0])-1;
						for($argNumber = 0; $argumentCount >= 0 and $argNumber <= $argumentCount; ++$argNumber){
							if(!isset($matches[1][$argNumber]) or $matches[1][$argNumber] === " "){
								$overloads[$tree][$argNumber] = CommandParameter::enum(strtolower($matches[2][$argNumber]), $enum = new CommandEnum(strtolower($matches[2][$argNumber]), [strtolower($matches[2][$argNumber])]), CommandParameter::FLAG_FORCE_COLLAPSE_ENUM, false);
								$pk->hardcodedEnums[] = $enum;
								continue;
							}
							$optional = str_contains($matches[1][$argNumber], '[');
							$paramName = strtolower($matches[2][$argNumber]);
							if(!str_contains($paramName, "|") and !str_contains($paramName, "/")){
								if(!isset($matches[3][$argNumber]) and $this->plugin->getConfig()->get("Parse-with-Parameter-Names", true) === true){
									if(str_contains($paramName, "player") or str_contains($paramName, "target")){
										$paramType = AvailableCommandsPacket::ARG_TYPE_TARGET;
									}elseif(str_contains($paramName, "count")){
										$paramType = AvailableCommandsPacket::ARG_TYPE_INT;
									}elseif(str_contains($paramName, "block")){
										$paramType = AvailableCommandsPacket::ARG_TYPE_INT; // TODO: change to block names enum
									}else{
										$paramType = AvailableCommandsPacket::ARG_TYPE_RAWTEXT;
									}
								}else{
									$paramType = match (strtolower($matches[3][$argNumber])) {
										"string" => AvailableCommandsPacket::ARG_TYPE_STRING,
										"int" => AvailableCommandsPacket::ARG_TYPE_INT,
										"x y z" => AvailableCommandsPacket::ARG_TYPE_POSITION,
										"float" => AvailableCommandsPacket::ARG_TYPE_FLOAT,
										"player", "target" => AvailableCommandsPacket::ARG_TYPE_TARGET,
										"message" => AvailableCommandsPacket::ARG_TYPE_MESSAGE,
										"json" => AvailableCommandsPacket::ARG_TYPE_JSON,
										"command" => AvailableCommandsPacket::ARG_TYPE_COMMAND,
										"boolean", "bool", "mixed" => AvailableCommandsPacket::ARG_TYPE_VALUE,
										default => AvailableCommandsPacket::ARG_TYPE_RAWTEXT,
									};
								}
								$overloads[$tree][$argNumber] = CommandParameter::standard($paramName, $paramType, 0, $optional);
							}elseif(str_contains($paramName, "|")){
								++$enumCount;
								$enumValues = explode("|", $paramName);
								$overloads[$tree][$argNumber] = CommandParameter::enum($paramName, $enum = new CommandEnum($command->getName() . " Enum#" . $enumCount, $enumValues), CommandParameter::FLAG_FORCE_COLLAPSE_ENUM, $optional);
								$pk->softEnums[] = $enum;
							}elseif(str_contains($paramName, "/")){
								++$enumCount;
								$enumValues = explode("/", $paramName);
								$overloads[$tree][$argNumber] = CommandParameter::enum($paramName, $enum = new CommandEnum($command->getName() . " Enum#" . $enumCount, $enumValues), CommandParameter::FLAG_FORCE_COLLAPSE_ENUM, $optional);
								$pk->softEnums[] = $enum;
							}
						}
					}
					$softEnums = $this->plugin->getSoftEnums();
					foreach($pk->softEnums as $softEnum){
						foreach($softEnums as $key => $enum){
							if($enum->getName() === $softEnum->getName())
								unset($softEnums[$key]);
						}
					}
					$pk->softEnums = array_merge($pk->softEnums, $softEnums);

					$enums = $this->plugin->getHardcodedEnums();
					foreach($pk->hardcodedEnums as $hardcodedEnum){
						foreach($enums as $key => $enum){
							if($enum->getName() === $hardcodedEnum->getName())
								unset($enums[$key]);
						}
					}
					$pk->hardcodedEnums = array_merge($pk->hardcodedEnums, $enums);

					$enumConstraints = $this->plugin->getEnumConstraints();
					foreach($pk->enumConstraints as $constrainedEnum){
						foreach($enumConstraints as $key => $enum){
							if($enum->getEnum()->getName() === $constrainedEnum->getEnum()->getName())
								unset($enumConstraints[$key]);
						}
					}
					$pk->enumConstraints = array_merge($pk->enumConstraints, $enumConstraints);

					$description = $command->getDescription();
					$pk->commandData[$command->getName()] = new CommandData(strtolower($command->getName()), $description instanceof Translatable ? $this->plugin->getServer()->getLanguage()->translate($description) : $description, (int) in_array($command->getName(), $this->plugin->getDebugCommands()), $command->testPermissionSilent($networkSession->getPlayer()) ? 0 : 1, $this->generateAliasEnum($command), $overloads);
				}
			}
		}
	}

	private function generateAliasEnum(Command $command) : ?CommandEnum{
		$return = null;
		$aliases = $command->getAliases();
		if(count($aliases) > 0){
			if(!in_array($command->getName(), $aliases, true)){
				//work around a client bug which makes the original name not show when aliases are used
				$aliases[] = $command->getName();
			}
			$return = new CommandEnum(ucfirst($command->getName()) . "Aliases", $aliases);
		}
		return $return;
	}

	private function generatePocketMineDefaultCommandData(Command $command, CommandSender $commandSender) : CommandData{
		$description = $command->getDescription();
		return new CommandData(strtolower($command->getName()), $description instanceof Translatable ? $commandSender->getLanguage()->translate($description) : $description, 0, $command->testPermissionSilent($commandSender) ? 0 : 1, $this->generateAliasEnum($command), [[CommandParameter::standard("args", AvailableCommandsPacket::ARG_TYPE_RAWTEXT, 0, true)]]);
	}
}