<?php
declare(strict_types=1);
namespace jasonwynn10\EasyCommandAutofill;

use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\CommandData;
use pocketmine\network\mcpe\protocol\types\CommandEnum;
use pocketmine\network\mcpe\protocol\types\CommandParameter;
use pocketmine\Player;
use pocketmine\Server;

class AutofillPlayer extends Player {
	public function sendCommandData() {
		$pk = new AvailableCommandsPacket();
		foreach($this->server->getCommandMap()->getCommands() as $name => $command) {
			if(isset($pk->commandData[$command->getName()]) or $command->getName() === "help")
				continue;
			if(in_array($command->getName(), array_keys(Main::getInstance()->getManualOverrides()))) {
				$pk->commandData[$command->getName()] = Main::getInstance()->getManualOverrides()[$name];
				continue;
			}
			$usage = $command->getUsage();
			//var_dump($this->server->getLanguage()->translateString($usage));
			if(empty($usage) or $usage[0] === '%') {
				$data = new CommandData();
				//TODO: commands containing uppercase letters in the name crash 1.9.0 client
				$data->commandName = strtolower($command->getName());
				$data->commandDescription = $this->server->getLanguage()->translateString($command->getDescription());
				$data->flags = (int)in_array($command->getName(), Main::getInstance()->getDebugCommands());
				$data->permission = 0;

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
			$commandString = explode(" ", $usage)[0];
			preg_match_all('/(\s[<\[])((?:[a-zA-Z0-9]+))((\s*:\s*)(string|int|x y z|float|mixed|target|message|text|json|command|boolean))?([>\]])/ius', $usage, $matches, PREG_PATTERN_ORDER, strlen($commandString));
			//var_dump($usage, $matches);
			$argumentCount = count($matches[0])-1;
			if($argumentCount < 0 and $command->testPermissionSilent($this)) {
				$data = new CommandData();
				//TODO: commands containing uppercase letters in the name crash 1.9.0 client
				$data->commandName = strtolower($command->getName());
				$data->commandDescription = $this->server->getLanguage()->translateString($command->getDescription());
				$data->flags = (int)in_array($command->getName(), Main::getInstance()->getDebugCommands());
				$data->permission = 0;

				$parameter = new CommandParameter();
				$parameter->paramName = "args";
				$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_RAWTEXT;
				$parameter->isOptional = true;
				$data->overloads[0][0] = $parameter;

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
			$data = new CommandData();
			//TODO: commands containing uppercase letters in the name crash 1.9.0 client
			$data->commandName = strtolower($command->getName());
			$data->commandDescription = Server::getInstance()->getLanguage()->translateString($command->getDescription());
			$data->flags = (int)in_array($command->getName(), Main::getInstance()->getDebugCommands()); // make command autofill blue if debug
			$data->permission = (int)$command->testPermissionSilent($this); // hide commands players do not have permission to use
			for($argNumber = 0; $argNumber <= $argumentCount; ++$argNumber) {
				$paramName = strtolower($matches[2][$argNumber]);
				$fieldType = strtolower($matches[4][$argNumber]);
				switch($fieldType) {
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
				if(!empty($matches[1][$argNumber])) {
					$optional = $matches[1][$argNumber] === '[';
				}else{
					$optional = false;
				}
				$parameter = new CommandParameter();
				$parameter->paramName = $paramName;
				$parameter->paramType = $paramType;
				$parameter->isOptional = $optional;
				$data->overloads[0][$argNumber] = $parameter;
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
		$this->dataPacket($pk);
	}
}