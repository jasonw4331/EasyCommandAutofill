<?php
declare(strict_types=1);
namespace jasonwynn10\EasyCommandAutofill;

use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandData;
use pocketmine\network\mcpe\protocol\types\command\CommandEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandEnumConstraint;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\network\mcpe\protocol\UpdateSoftEnumPacket;
use pocketmine\plugin\PluginBase;

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
		new EventListener($this);
		if($this->getConfig()->get("Highlight-Debug", true))
			$this->debugCommands = ["dumpmemory", "gc", "timings", "status"];
		$map = $this->getServer()->getCommandMap();

		$command = $map->getCommand("pocketmine:difficulty");
		$description = $command->getDescription() instanceof Translatable ? $command->getDescription()->getText() : $command->getDescription();
		$this->addManualOverride("pocketmine:difficulty",
			new CommandData(
				$command->getName(),
				$description,
				0,
				1,
				null,
				[
					[
						CommandParameter::standard("new difficulty", AvailableCommandsPacket::ARG_TYPE_RAWTEXT, 0, false)
					],
				]
			)
		);

		$command = $map->getCommand("pocketmine:give");
		$description = $command->getDescription() instanceof Translatable ? $command->getDescription()->getText() : $command->getDescription();
		$this->addManualOverride("pocketmine:give",
			new CommandData(
				$command->getName(),
				$description,
				0, // no flags
				1, // default no permission
				null,
				[
					[
						CommandParameter::standard("player", AvailableCommandsPacket::ARG_TYPE_TARGET, 0, false),
						CommandParameter::standard("item", AvailableCommandsPacket::ARG_TYPE_RAWTEXT, 0, false),
						CommandParameter::standard("amount", AvailableCommandsPacket::ARG_TYPE_INT, 0, true),
						CommandParameter::standard("tags", AvailableCommandsPacket::ARG_TYPE_JSON, 0, true)
					]
				]
			)
		);

		$command = $map->getCommand("pocketmine:setworldspawn");
		$description = $command->getDescription() instanceof Translatable ? $command->getDescription()->getText() : $command->getDescription();
		$this->addManualOverride("pocketmine:setworldspawn",
			new CommandData(
				$command->getName(),
				$description,
				0, // no flags
				1, // default no permission
				null,
				[
					[
						CommandParameter::standard("position", AvailableCommandsPacket::ARG_TYPE_POSITION, 0, false)
					]
				]
			)
		);

		$command = $map->getCommand("pocketmine:setworldspawn");
		$description = $command->getDescription() instanceof Translatable ? $command->getDescription()->getText() : $command->getDescription();
		$this->addManualOverride("pocketmine:setworldspawn",
			new CommandData(
				$command->getName(),
				$description,
				0, // no flags
				1, // default no permission
				null,
				[
					[
						CommandParameter::standard("position", AvailableCommandsPacket::ARG_TYPE_POSITION, 0, true)
					]
				]
			)
		);

		$command = $map->getCommand("pocketmine:spawnpoint");
		$description = $command->getDescription() instanceof Translatable ? $command->getDescription()->getText() : $command->getDescription();
		$this->addManualOverride("pocketmine:spawnpoint",
			new CommandData(
				$command->getName(),
				$description,
				0, // no flags
				1, // default no permission
				null,
				[
					[
						CommandParameter::standard("player", AvailableCommandsPacket::ARG_TYPE_TARGET, 0, true),
						CommandParameter::standard("position", AvailableCommandsPacket::ARG_TYPE_POSITION, 0, true)
					]
				]
			)
		);

		$command = $map->getCommand("pocketmine:teleport");
		$description = $command->getDescription() instanceof Translatable ? $command->getDescription()->getText() : $command->getDescription();
		$this->addManualOverride("pocketmine:teleport",
			new CommandData(
				$command->getName(),
				$description,
				0, // no flags
				1, // default no permission
				null,
				[
					[
						CommandParameter::standard("victim", AvailableCommandsPacket::ARG_TYPE_TARGET, 0, false),
						CommandParameter::standard("destination", AvailableCommandsPacket::ARG_TYPE_TARGET, 0, true),
					],
					[
						CommandParameter::standard("victim", AvailableCommandsPacket::ARG_TYPE_TARGET, 0, false),
						CommandParameter::standard("destination", AvailableCommandsPacket::ARG_TYPE_POSITION, 0, true),
						CommandParameter::standard("x-rot", AvailableCommandsPacket::ARG_TYPE_FLOAT, 0, true),
						CommandParameter::standard("y-rot", AvailableCommandsPacket::ARG_TYPE_FLOAT, 0, true),
					]
				]
			)
		);

		$command = $map->getCommand("pocketmine:title");
		$description = $command->getDescription() instanceof Translatable ? $command->getDescription()->getText() : $command->getDescription();
		$this->addManualOverride("pocketmine:title",
			new CommandData(
				$command->getName(),
				$description,
				0, // no flags
				1, // default no permission
				null,
				[
					[
						CommandParameter::standard("player", AvailableCommandsPacket::ARG_TYPE_TARGET, 0, false),
						CommandParameter::enum("title Enum #0", new CommandEnum('title Enum #0', ['clear']), 0, false),
					],
					[
						CommandParameter::standard("player", AvailableCommandsPacket::ARG_TYPE_TARGET, 0, false),
						CommandParameter::enum("title Enum #1", new CommandEnum('title Enum #1', ['reset']), 0, false),
					],
					[
						CommandParameter::standard("player", AvailableCommandsPacket::ARG_TYPE_TARGET, 0, false),
						CommandParameter::enum("title Enum #2", new CommandEnum('title Enum #2', ['title', 'subtitle', 'actionbar']), 0, false),
						CommandParameter::standard("titleText", AvailableCommandsPacket::ARG_TYPE_MESSAGE, 0, false),
					],
					[
						CommandParameter::standard("player", AvailableCommandsPacket::ARG_TYPE_TARGET, 0, false),
						CommandParameter::enum("title Enum #3", new CommandEnum('title Enum #3', ['times']), 0, false),
						CommandParameter::standard("fadeIn", AvailableCommandsPacket::ARG_TYPE_INT, 0, false),
						CommandParameter::standard("titleText", AvailableCommandsPacket::ARG_TYPE_INT, 0, false),
						CommandParameter::standard("stay", AvailableCommandsPacket::ARG_TYPE_INT, 0, false),
						CommandParameter::standard("fadeOut", AvailableCommandsPacket::ARG_TYPE_INT, 0, false),
					]
				]
			)
		);

		$command = $map->getCommand("pocketmine:version");
		$description = $command->getDescription() instanceof Translatable ? $command->getDescription()->getText() : $command->getDescription();
		$this->addManualOverride("pocketmine:version",
			new CommandData(
				$command->getName(),
				$description,
				0, // no flags
				1, // default no permission
				null,
				[
					[
						CommandParameter::standard("plugin name", AvailableCommandsPacket::ARG_TYPE_RAWTEXT, 0, true),
					],
				]
			)
		);
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
			$player->getNetworkSession()->sendDataPacket(new AvailableCommandsPacket());
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
			$player->getNetworkSession()->sendDataPacket(new AvailableCommandsPacket());
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
			if($enum->getName() === $softEnum->getName())
				throw new \InvalidArgumentException("Hardcoded enum is already in soft enum list.");
		$this->hardcodedEnums[] = $enum;
		foreach($this->getServer()->getOnlinePlayers() as $player) {
			$player->getNetworkSession()->sendDataPacket(new AvailableCommandsPacket());
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
			if($enum->getName() === $hardcodedEnum->getName())
				throw new \InvalidArgumentException("Soft enum is already in hardcoded enum list.");
		$this->softEnums[] = $enum;
		$pk = new UpdateSoftEnumPacket();
		$pk->enumName = $enum->getName();
		$pk->values = $enum->getValues();
		$pk->type = UpdateSoftEnumPacket::TYPE_ADD;
		foreach($this->getServer()->getOnlinePlayers() as $player) {
			$player->getNetworkSession()->sendDataPacket($pk);
		}
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
		$pk->enumName = $enum->getName();
		$pk->values = $enum->getValues();
		$pk->type = UpdateSoftEnumPacket::TYPE_SET;
		foreach($this->getServer()->getOnlinePlayers() as $player) {
			$player->getNetworkSession()->sendDataPacket($pk);
		}
		return $this;
	}

	/**
	 * @param CommandEnum $enum
	 *
	 * @return self
	 */
	public function removeSoftEnum(CommandEnum $enum) : self {
		$pk = new UpdateSoftEnumPacket();
		$pk->enumName = $enum->getName();
		$pk->values = $enum->getValues();
		$pk->type = UpdateSoftEnumPacket::TYPE_REMOVE;
		foreach($this->getServer()->getOnlinePlayers() as $player) {
			$player->getNetworkSession()->sendDataPacket($pk);
		}
		return $this;
	}

	/**
	 * @param CommandEnumConstraint $enumConstraint
	 *
	 * @return Main
	 */
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