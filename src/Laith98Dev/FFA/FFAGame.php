<?php

namespace Laith98Dev\FFA;

/*  
 *  A plugin for PocketMine-MP.
 *  
 *	 _           _ _   _    ___   ___  _____             
 *	| |         (_) | | |  / _ \ / _ \|  __ \            
 *	| |     __ _ _| |_| |_| (_) | (_) | |  | | _____   __
 *	| |    / _` | | __| '_ \__, |> _ <| |  | |/ _ \ \ / /
 *	| |___| (_| | | |_| | | |/ /| (_) | |__| |  __/\ V / 
 *	|______\__,_|_|\__|_| |_/_/  \___/|_____/ \___| \_/  
 *	
 *	Copyright (C) 2021 Laith98Dev
 *  
 *	Youtube: Laith Youtuber
 *	Discord: Laith98Dev#0695
 *	Gihhub: Laith98Dev
 *	Email: help@laithdev.tk
 *	Donate: https://paypal.me/Laith113
 *
 *	This program is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 	
 */

use pocketmine\entity\Location;

use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\item\VanillaItems;

use pocketmine\player\Player;
use pocketmine\player\GameMode;

use pocketmine\math\Vector3;

use pocketmine\world\Position;

use pocketmine\utils\{Config, TextFormat as TF};

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;

class FFAGame 
{
	/** @var Main */
	private $plugin;
	
	/** @var array */
	private $data;
	
	/** @var string[] */
	private $players = [];
	
	/** @var string[] */
	private $scoreboards = [];
	
	/** @var int */
	private $scoreboardsLine = 0;
	
	private $scoreboardsLines = [
		0 => TF::BOLD . TF::DARK_BLUE . "FFA",
		1 => TF::BOLD . TF::LIGHT_BLUE . "F" . TF::DARK_BLUE . "FA",
		2 => TF::BOLD . TF::DARK_BLUE . "F" . TF::LIGHT_BLUE . "F" . TF::DARK_BLUE . "A",
		3 => TF::BOLD . TF::DARK_BLUE . "FF" . TF::LIGHT_BLUE . "A",
		4 => TF::BOLD . TF::LIGHT_BLUE . "FFA"
	];
	
	public $protect = [];
	
	public function __construct(Main $plugin, array $data){
		$this->plugin = $plugin;
		$this->UpdateData($data);
	}
	
	public function getPlugin(){
		return $this->plugin;
	}
	
	public function UpdateData(array $data){
		$this->data = $data;
	}
	
	public function getData(){
		return $this->data;
	}
	
	public function getName(){
		return $this->getData()["name"];
	}
	
	public function getWorld(){
		return $this->getData()["world"];
	}
	
	public function getLobby(){
		return $this->getData()["lobby"];
	}
	
	public function getRespawn(){
		return $this->getData()["respawn"];
	}
	
	public function getPlayers(){
		return $this->players;
	}
	
	public function isProtected(Player $player){
		return isset($this->protect[$player->getName()]);
	}
	
	public function inArena(Player $player){
		return isset($this->players[$player->getName()]) ? true : false;
	}
	
	public function new(Player $player, string $objectiveName, string $displayName): void{
		if(isset($this->scoreboards[$player->getName()])){
			$this->remove($player);
		}
		$pk = new SetDisplayObjectivePacket();
		$pk->displaySlot = "sidebar";
		$pk->objectiveName = $objectiveName;
		$pk->displayName = $displayName;
		$pk->criteriaName = "dummy";
		$pk->sortOrder = 0;
		$player->getNetworkSession()->sendDataPacket($pk);
		$this->scoreboards[$player->getName()] = $objectiveName;
	}

	public function remove(Player $player): void{
		$objectiveName = $this->getObjectiveName($player) ?? "ffa";
		$pk = new RemoveObjectivePacket();
		$pk->objectiveName = $objectiveName;
		$player->getNetworkSession()->sendDataPacket($pk);
		unset($this->scoreboards[$player->getName()]);
	}

	public function setLine(Player $player, int $score, string $message): void{
		if(!isset($this->scoreboards[$player->getName()])){
			$this->plugin->getLogger()->error("Cannot set a score to a player with no scoreboard");
			return;
		}
		if($score > 15 || $score < 1){
			$this->plugin->getLogger()->error("Score must be between the value of 1-15. $score out of range");
			return;
		}
		$objectiveName = $this->getObjectiveName($player) ?? "ffa";
		$entry = new ScorePacketEntry();
		$entry->objectiveName = $objectiveName;
		$entry->type = $entry::TYPE_FAKE_PLAYER;
		$entry->customName = $message;
		$entry->score = $score;
		$entry->scoreboardId = $score;
		$pk = new SetScorePacket();
		$pk->type = $pk::TYPE_CHANGE;
		$pk->entries[] = $entry;
		$player->getNetworkSession()->sendDataPacket($pk);
	}

	public function getObjectiveName(Player $player): ?string{
		return isset($this->scoreboards[$player->getName()]) ? $this->scoreboards[$player->getName()] : null;
	}
	
	public function getLevel(?string $name = null){
		if($name == null){
			$this->plugin->getServer()->getWorldManager()->loadWorld($this->getWorld());
			return $this->plugin->getServer()->getWorldManager()->getWorldByName($this->getWorld());
		}
		return $this->plugin->getServer()->getWorldManager()->getWorldByName($name);
	}
	
	public function broadcast(string $message){
		foreach ($this->getPlayers() as $player){
			$player->sendMessage($message);
		}
	}
	
	public function joinPlayer(Player $player): bool{
		
		if(isset($this->players[$player->getName()]))
			return false;
		
		$lobby = $this->getLobby();
		
		if(!is_array($lobby) || count($lobby) == 0){
			if($player->hasPermission("ffa.command.admin"))
				$player->sendMessage(TF::RED . "§l§2»§r§c Please set lobby position!§e Usage: /ffa setlobby");
			return false;
		}
		
		if(!is_array($this->getRespawn()) || count($this->getRespawn()) == 0){
			if($player->hasPermission("ffa.command.admin"))
				$player->sendMessage(TF::RED . "§l§2»§r§c Please set respawn position!§e Usage: /ffa setrespawn");
			return false;
		}
		
		$x = floatval($lobby["PX"]);
		$y = floatval($lobby["PY"]);
		$z = floatval($lobby["PZ"]);
		$yaw = floatval($lobby["YAW"]);
		$pitch = floatval($lobby["PITCH"]);
		
		$player->teleport(new Position($x, $y, $z, $this->getLevel()), $yaw, $pitch);
		
		$player->setGamemode(GameMode::ADVENTURE());
		$player->setHealth(20);
		$player->getHungerManager()->setFood(20);
		
		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->getCraftingGrid()->clearAll();
		$player->getEffects()->clear();
		//$player->removeAllEffects();
		
		$player->getInventory()->setItem(0, ItemFactory::getInstance()->get(ItemIds::IRON_SWORD, 0, 1));
		$player->getInventory()->setItem(1, ItemFactory::getInstance()->get(ItemIds::GOLDEN_APPLE, 0, 5));
		$player->getInventory()->setItem(2, ItemFactory::getInstance()->get(ItemIds::BOW, 0, 1));
		$player->getInventory()->setItem(3, ItemFactory::getInstance()->get(ItemIds::ARROW, 0, 15));
		
		$player->getArmorInventory()->setHelmet(ItemFactory::getInstance()->get(ItemIds::IRON_HELMET));
		$player->getArmorInventory()->setChestplate(ItemFactory::getInstance()->get(ItemIds::IRON_CHESTPLATE));
		$player->getArmorInventory()->setLeggings(ItemFactory::getInstance()->get(ItemIds::IRON_LEGGINGS));
		$player->getArmorInventory()->setBoots(ItemFactory::getInstance()->get(ItemIds::IRON_BOOTS));
		
		$this->players[$player->getName()] = $player;
		
		$cfg = new Config($this->plugin->getDataFolder() . "config.yml", Config::YAML);
		if($cfg->get("join-and-respawn-protected") === true){
			$this->protect[$player->getName()] = 3;
			$player->sendMessage("§l§2»§r§c You died! §3You're now protected for 3 seconds, §buse it wisely!");
		}
		
		$this->broadcast($player->getName() . " §ajoined §1F§9F§1A§g!");
		return true;
	}
	
	public function quitPlayer(Player $player): bool{
		if(!isset($this->players[$player->getName()]))
			return false;
		
		unset($this->players[$player->getName()]);
		
		$this->remove($player);
		
		$player->teleport($this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->getCraftingGrid()->clearAll();
		$player->getEffects()->clear();
		$player->setGamemode($this->plugin->getServer()->getGamemode());
		//$player->setGamemode(GameMode::SURVIVAL());
		$player->setHealth(20);
		$player->getHungerManager()->setFood(20);
		
		$this->broadcast($player->getName() . " §cjoined §1F§9F§1A§g!");
		return true;
	}
	
	public function killPlayer(Player $player): void{
		$message = null;
		$event = $player->getLastDamageCause();
		
		if($event == null)
			return;
		
		if(!is_int($event->getCause()))
			return;
		
		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->getCraftingGrid()->clearAll();
		$player->getEffects()->clear();
		
		$player->setGamemode(GameMode::ADVENTURE());
		$player->setHealth(20);
		$player->getHungerManager()->setFood(20);
		$this->plugin->addDeath($player);
		$cfg = new Config($this->plugin->getDataFolder() . "config.yml", Config::YAML);
		switch ($event->getCause()){
			case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
				$damager = $event instanceof EntityDamageByEntityEvent ? $event->getDamager() : null;
				if($damager !== null && $damager instanceof Player){
					$message = str_replace(["{PLAYER}", "{KILLER}", "&"], [$player->getName(), $damager->getName(), TF::ESCAPE], $cfg->get("death-attack-message"));
					$this->plugin->addKill($damager);
					
					$damager->sendPopup(TF::YELLOW . "+1 Kill");
					$damager->setHealth(20);
					$damager->getHungerManager()->setFood(20);
					
					$damager->getInventory()->clearAll();
					$damager->getArmorInventory()->clearAll();
					$damager->getCraftingGrid()->clearAll();
					$damager->getEffects()->clear();
					
					$damager->getInventory()->setItem(0, ItemFactory::getInstance()->get(ItemIds::IRON_SWORD, 0, 1));
					$damager->getInventory()->setItem(1, ItemFactory::getInstance()->get(ItemIds::GOLDEN_APPLE, 0, 5));
					$damager->getInventory()->setItem(2, ItemFactory::getInstance()->get(ItemIds::BOW, 0, 1));
					$damager->getInventory()->setItem(3, ItemFactory::getInstance()->get(ItemIds::ARROW, 0, 15));
					
					$damager->getArmorInventory()->setHelmet(ItemFactory::getInstance()->get(ItemIds::IRON_HELMET));
					$damager->getArmorInventory()->setChestplate(ItemFactory::getInstance()->get(ItemIds::IRON_CHESTPLATE));
					$damager->getArmorInventory()->setLeggings(ItemFactory::getInstance()->get(ItemIds::IRON_LEGGINGS));
					$damager->getArmorInventory()->setBoots(ItemFactory::getInstance()->get(ItemIds::IRON_BOOTS));
				}
			break;
			
			case EntityDamageEvent::CAUSE_VOID:
				$message = str_replace(["{PLAYER}", "&"], [$player->getName(), TF::ESCAPE], $cfg->get("death-void-message"));
			break;
		}
		
		if($message !== null)
			$this->broadcast($message);
		
		if($cfg->get("death-respawn-inMap") === true){
			$this->respawn($player);
		} else {
			$this->quitPlayer($player);
		}
	}
	
	public function respawn(Player $player){
		$player->setGamemode(GameMode::ADVENTURE());
		$player->setHealth(20);
		$player->getHungerManager()->setFood(20);
		
		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->getCraftingGrid()->clearAll();
		$player->getEffects()->clear();
		
		$player->getInventory()->setItem(0, ItemFactory::getInstance()->get(ItemIds::IRON_SWORD, 0, 1));
		$player->getInventory()->setItem(1, ItemFactory::getInstance()->get(ItemIds::GOLDEN_APPLE, 0, 5));
		$player->getInventory()->setItem(2, ItemFactory::getInstance()->get(ItemIds::BOW, 0, 1));
		$player->getInventory()->setItem(3, ItemFactory::getInstance()->get(ItemIds::ARROW, 0, 15));
		
		$player->getArmorInventory()->setHelmet(ItemFactory::getInstance()->get(ItemIds::IRON_HELMET));
		$player->getArmorInventory()->setChestplate(ItemFactory::getInstance()->get(ItemIds::IRON_CHESTPLATE));
		$player->getArmorInventory()->setLeggings(ItemFactory::getInstance()->get(ItemIds::IRON_LEGGINGS));
		$player->getArmorInventory()->setBoots(ItemFactory::getInstance()->get(ItemIds::IRON_BOOTS));
		
		$respawn = $this->getRespawn();
		$x = floatval($respawn["PX"]);
		$y = floatval($respawn["PY"]);
		$z = floatval($respawn["PZ"]);
		$yaw = floatval($respawn["YAW"]);
		$pitch = floatval($respawn["PITCH"]);
		
		$player->teleport(new Position($x, $y, $z, $this->getLevel()), $yaw, $pitch);
		
		$cfg = new Config($this->plugin->getDataFolder() . "config.yml", Config::YAML);
		if($cfg->get("join-and-respawn-protected") === true){
			$this->protect[$player->getName()] = 3;
			$player->sendMessage("You're now protected 3 seconds");
		}
		
		$player->sendTitle(TF::YELLOW . TF::BOLD . "Respawned");
	}
	
	public function tick(){
		foreach ($this->getPlayers() as $player){
			$cfg = new Config($this->plugin->getDataFolder() . "config.yml", Config::YAML);
			$this->new($player, "ffa", $this->scoreboardsLines[$this->scoreboardsLine]);
			$this->setLine($player, 1, "=================  ");
			$this->setLine($player, 2, "§5Blossom §l§d»§r Hub");
			$this->setLine($player, 3, "      ");
			$this->setLine($player, 4, "§ePlaying: " . TF::WHITE . count($this->getPlayers()) . "  ");
			$this->setLine($player, 4, "§eMap: " . TF::WHITE . $this->getName() . "  ");
			$this->setLine($player, 5, "   ");
			$this->setLine($player, 6, "§aKills: " . TF::WHITE . $this->plugin->getKills($player) . " ");
			$this->setLine($player, 7, "§cDeaths: " . TF::WHITE . $this->plugin->getDeaths($player) . " ");
			$this->setLine($player, 8, "=================    ");
			$this->setLine($player, 9, " " . $cfg->get("scoreboardIp", "play.example.net") . " ");
		}
		
		if($this->scoreboardsLine == (count($this->scoreboardsLines) - 1)){
			$this->scoreboardsLine = 0;
		} else {
			++$this->scoreboardsLine;
		}
		
		foreach ($this->protect as $name => $time){
			//var_dump("Player: " . $name . " Time: " . $time . "\n");
			if($time == 0){
				unset($this->protect[$name]);
			} else {
				$this->protect[$name]--;
			}
		}
	}
}
