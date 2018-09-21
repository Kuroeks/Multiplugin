<?php

namespace Multiplugin;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\item\Item;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener{
	
    private $cached_bow;
    
    public function onLoad(){
                    $this->getLogger()->info("§bLoading...");
          }
          public function onEnable(){
     $this->saveResource("config.yml");
     $this->getServer()->getPluginManager()->registerEvents($this, $this);
     $this->getServer()->getPluginManager()->registerEvents($this,$this);
     $this->getLogger()->info("§aPlugin Enabled");
     $this->getServer()->getPluginManager()->registerEvents($this, $this);
          }
          public function onDisable(): void {
                    $this->getLogger()->info("§cPlugin Disabled");
          }
          
          public function getTeleportationBow(int $count = 1) : Item
    {
        if (isset($this->cached_bow)) {
            return clone $this->cached_bow;
        }

        $item = Item::get(Item::BOW, 0, $count);
        $item->setCustomName(TextFormat::RESET . TextFormat::colorize($this->getConfig()->get("bow-name")));
        $item->setLore(array_map(
            function (string $value) : string {
                return TextFormat::RESET . TextFormat::colorize($value);
            },
            $this->getConfig()->get("bow-lore")
        ));

        $nbt = $item->getNamedTag();
        $nbt->setByte("TeleportBow", 1);
        $item->setNamedTag($nbt);

        return $this->cached_bow = $item;
    }
	public function onEntityShootBow(EntityShootBowEvent $event) : void
    {
        if ($event->getBow()->getNamedTagEntry("TeleportBow") instanceof ByteTag) {
            $this->projectiles[$event->getProjectile()->getId()] = true;
        }
    }
          public function onJoin(PlayerJoinEvent $event){
   $player = $event->getPlayer();
   $name = $player->getName();
   $event->setJoinMessage("§6[§a+§6]§b $name");
    }
          public function onQuit(PlayerQuitEvent $event){
   $player = $event->getPlayer();
   $name = $player->getName();
   $event->setQuitMessage("§6[§c-§6]§b $name");
          }
    
    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event) : void
    {
        if ($event instanceof EntityDamageByChildEntityEvent) {
            $projectile = $event->getChild();
        } elseif ($event->getCause() === EntityDamageEvent::CAUSE_PROJECTILE) {
            $projectile = $event->getDamager();
        } else {
            return;
        }

        if (isset($this->projectiles[$projecile->getId()])) {
            $projectile->flagForDespawn();
            $event->setCancelled();
        }
    }

    public function onEntityDespawn(EntityDespawnEvent $event) : void
    {
        unset($this->projectiles[$event->getEntity()->getId()]);
    }

    public function onProjectileHit(ProjectileHitEvent $event) : void
    {
        $projectile = $event->getEntity();
        if (isset($this->projectiles[$projectile->getId()])) {
            $owner = $projectile->getOwningEntity();

            if ($owner !== null && $owner->isAlive() && !$owner->isClosed()) {
                $level = $projectile->getLevel();
                $level->broadcastLevelEvent($owner, LevelEventPacket::EVENT_PARTICLE_ENDERMAN_TELEPORT);
                $level->addSound(new EndermanTeleportSound($owner));
                $owner->teleport($event->getRayTraceResult()->getHitVector());
                $level->addSound(new EndermanTeleportSound($owner));
            }
        }
	}
	 public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool{
		switch(strtolower($cmd->getName())){
			case "tpbow":{
            $inventory = $sender->getInventory();
            $inventory->addItem($this->getTeleportationBow());
            $inventory->addItem(Item::get(Item::ARROW));
			}
     return true;
		}
	}
}