<?php
declare(strict_types=1);
namespace jasonwynn10\EasyCommandAutofill;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\network\mcpe\protocol\types\CommandData;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener {
	/** @var self $instance */
	private static $instance;
	/** @var CommandData[] $manualOverrides */
	protected $manualOverrides = [];

	/**
	 * @return self
	 */
	public static function getInstance() : self {
		return self::$instance;
	}

	public function onLoad() {
		self::$instance = $this;
	}

	public function onEnable() {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
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

	public function onPlayerCreate(PlayerCreationEvent $event) {
		$event->setPlayerClass(AutofillPlayer::class);
	}
}