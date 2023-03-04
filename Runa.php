<?php
namespace VirusAlex;

use pocketmine\entity\Entity;
use pocketmine\Player;
use \pocketmine\event\player\PlayerInteractEvent; 
use pocketmine\entity\Effect;
use \pocketmine\event\player\PlayerDropItemEvent; 
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\EntityEventPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\entity\Human;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\NamedTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\item\ItemIds;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\scheduler\CallbackTask;
use pocketmine\tile\Tile;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\event\TranslationContainer;
use pocketmine\level\Location;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\types\InventoryNetworkIds;
use pocketmine\network\mcpe\protocol\protocolInfo;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\network\mcpe\protocol\BlockEntityDataPacket;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\ContainerSetSlotPacket;
use pocketmine\network\mcpe\protocol\INVENTORY_ACTION_PACKET;
use pocketmine\network\mcpe\protocol\ContainerSetContentPacket;


class Runa extends PluginBase implements Listener{
	
	public $chest = array(); //Пока у игрока открыт сундук он находится в этом массиве
    public $updates = array(); //Сюда временно заносится игрок который открывает аукцион(для таймера)
	private static $offHandItems = [];

		public function onEnable(){
			if(!is_dir($this->getDataFolder())) @mkdir($this->getDataFolder());
				$this->getServer()->getPluginManager()->registerEvents($this, $this);
				$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask(array(
				$this,
				"Update"
				)), 2);
		}
		 public function onCommand(CommandSender $player, Command $cmd, $label, array $args){
				
			$nick = strtolower($player->getName());
		 
			if($cmd->getName() == "runa"){
					$this->openAuction($player,$nick); //Вызываем функцию открытия аукциона для игрока, 
					$this->chest[$nick] = true;  //Добавляем игрока в массив, тем самым подтверждая, что у него открыт сундук
				
				
			}
		 }
		public function openAuction($player,$nick){
			 //Первый сундук 
			    $pk = new UpdateBlockPacket; //Создаём пакет обновления блока
				$pk->x = (int)round($player->x);   
				$pk->y = (int)round($player->y) - (int)3;    //Указываем кординаты (на 3 блока ниже чем игрок)
				$pk->z = (int)round($player->z);
				$pk->blockId = 54;         //Айди блока (сундук)
				$pk->blockData = 5;        //Дамаг блока, в данном случае решает в какую сторону будет повёрнут сундук
				$player->dataPacket($pk);  //Отправляем пакет игроку
				
			//Второй сундук
				$pk = new UpdateBlockPacket;  //ТОЖЕ САМОЕ (Только на 1 блок дальше по кординате z)
				$pk->x = (int)round($player->x);
				$pk->y = (int)round($player->y) - (int)3;
				$pk->z = (int)round($player->z) + (int)1; 
				$pk->blockId = 54;
				$pk->blockData = 5;
				$player->dataPacket($pk); 
	
	
	         //создаём nbt тег для первого сундука
			 
				$nbt = new CompoundTag("", [
				new StringTag("id", Tile::CHEST),  //тип 
				new StringTag("CustomName", "§l§eРуна"),   // Имя которое будет присвоено сундуку
				new IntTag("x", (int)round($player->x)),      //Кординаты первого сундука
				new IntTag("y", (int)round($player->y) - (int)3),
				new IntTag("z", (int)round($player->z))
				]);
				
				
					$tile1 = Tile::createTile("Chest", $player->getLevel(), $nbt); //Создаём тайл типа "Сундук" в мире игрока с тегами $nbt
		
		
		     //создаём nbt тег для второго сундука
			 
					$nbt = new CompoundTag("", [        //Всё тоже самое
					new StringTag("id", Tile::CHEST),
					new StringTag("CustomName", "§l§eРуна"),
					new IntTag("x", (int)round($player->x)),
					new IntTag("y", (int)round($player->y) - (int)3),
					new IntTag("z", (int)round($player->z) + (int)1)
				]);
				
				
				    $tile2 = Tile::createTile("Chest", $player->getLevel(), $nbt);
		
		
		
					$tile1->pairWith($tile2);  //Соединяем два сундука в один большой
					$tile2->pairWith($tile1);  //Типо назначаем их парой друг друга :D хз как объяснить что такое тайл но по сути $tile = $chest
 

					
                    $this->updates[$nick] = 1; //Добавляю игрока в массив для таймера 
	  }


          public function Update(){ //Функция которая поможет нам отправлять пакеты с задержкой
		  foreach($this->updates as $nick => $value){ //Получаем всех игроков которых мы записали для таймера
			  
			  $player = $this->getServer()->getPlayer($nick); 
			  	 $x = (int)round($player->x);
				 $y = (int)round($player->y)-(int)3;   
				 $z = (int)round($player->z);
				    if($this->updates[$nick] == 1) $this->updates[$nick]++; else{
					if($this->updates[$nick] == 2) $this->updates[$nick]++;  //если равен 2 значит сундук открывается, если равен 10 значит сундук удаляется
					//Тут лучше вообще нечего не трогай я сам хз как это работает, я писал это ночью
					else{
						   if($this->updates[$nick] == 10 or $this->updates[$nick] == 11) return $this->updates[$nick]++;
						   if($this->updates[$nick] == 12){
							   
							   	$block = Server::getInstance()->getDefaultLevel()->getBlock(new Vector3($x, $y, $z));
		
									$pk = new UpdateBlockPacket;
									$pk->x = (int)round($player->x);
									$pk->y = (int)round($player->y)-(int)3;
									$pk->z = (int)round($player->z);
									$pk->blockId = $block->getId();
									$pk->blockData = 0;
									$player->dataPacket($pk);
									
									
									
									$block = Server::getInstance()->getDefaultLevel()->getBlock(new Vector3($x, $y, $z + 1));
								
									$pk = new UpdateBlockPacket;
									$pk->x = (int)round($player->x);
									$pk->y = (int)round($player->y)-(int)3;
									$pk->z = (int)round($player->z) + 1;
									$pk->blockId = $block->getId();
									$pk->blockData = 0;
									$player->dataPacket($pk);
							        unset($this->updates[$nick]);
							   return;
						   }
						   $pk = new ContainerOpenPacket; //Создаём пакет открывающий контейнер(инвентарь)
						   $pk->windowid = 10;  //ид окна указываем 10 - тобишь Сундук
						   $pk->type = InventoryNetworkIds::CONTAINER; //Тип контейнер
						   $pk->x = (int)round($player->x);         //Кординаты сундука который открываем
						   $pk->y = (int)round($player->y) - (int)3;
						   $pk->z = (int)round($player->z);
						   
						   $player->dataPacket($pk); //Отправляем пакет
						   
						   $this->setContent($player); //Устанавливаем контент
						   
						   unset($this->updates[$nick]); //Удаляем из массива ник игрока т.к аукцион уже открыт
					}
					}
		  }
			
		}	 
public function setContent($player){
			
				  $pk = new ContainerSetContentPacket; //Создаём пакет установки контента в сундук
					$pk->windowid = 10;
					$pk->targetEid = -1;
				
					for($i = 0; $i < 54; $i++){ //создаём цикл от 0 слота по 54 слот $i номер слота
						$customname = "§l§fБока";
						$itid = 102; $dmg = 0; //Стеклянная панель
						$arr = [10,11,12,14,15,16,19,20,21,23,24,25,28,29,30,32,33,34];
					     if(in_array($i, $arr)){
							 $itid = 0; 
						 }
						 
						$arr2 = array(10 => 331,11=>353,12 => 331,22=>236,19=>399,20=>388,21=>399,24=>420,28=>265,29=>318,30=>265);
						 if(isset($arr2[$i])){
							 $customname = null;
							 $itid = $arr2[$i]; 
							 if($itid == 420) $customname = "Руна";
						 if($itid == 236){ $customname = "Зеленый бетон"; $dmg = 5;}
						 }
						$item = Item::get($itid, $dmg, 1); 
						if($customname !== null) $item->setCustomName($customname);
						$pk->slots[$i] = $item;
						$customname = null;
						}
						$pk->slots[49] = Item::get(236, 14, 1)->setCustomName("§l§cВыйти");
						$player->dataPacket($pk);
						return;

		}
	  public function closeAuc($player){  //Функция закрытия аукциона тут мы подчищаем следы ахпахпхапха
			   $nick = strtolower($player->getName());
               $this->updates[$nick] = 10; //Добавляем ник игрока в таймер, если значение 10, значит таймер будет удалять сундуки
		         if(isset($this->chest[$nick])){
		          $pk = new ContainerClosePacket();
				  $pk->windowid = 10;           //Отправляем пакет закрытия сундука
				  unset($this->chest[$nick]);
				  $player->dataPacket($pk);
				
		 }
	
  
		  }
		  public function onTransaction(InventoryTransactionEvent $event){
			  $player = $event->getTransaction()->getPlayer();
			  $nick = strtolower($player->getName());          //Просто запрещаем любые перемещения во время аукциона
		  if(isset($this->chest[$nick]) or isset($this->updates[$nick])){
			  $event->setCancelled(true);
			 
		  }
			  
		  }
		  public function drop(PlayerDropItemEvent $event){
			  $player = $event->getPlayer();
			  $nick = strtolower($player->getName()); //Выбрасываем мусор гражданин? Пошёл нахуй пидр блять а ну быстро в обезъянник
			  if(isset($this->chest[$nick])) $event->setCancelled(true);
			  if(isset($this->updates[$nick])) $event->setCancelled(true);
			  
		  }	
		  	private static function processVisualOffHand(Player $player, Item $item){
		$packet = new MobEquipmentPacket();
		$packet->eid = $packet->entityRuntimeId = $player->getId();
		$packet->item = $item;
		$packet->inventorySlot = $packet->slot = 0;
		$packet->hotbarSlot = $packet->selectedSlot = $player->getInventory()->getHeldItemIndex();
		$packet->windowId = 119;
		Server::getInstance()->broadcastPacket($player->getViewers(), $packet);
	}
	
	
	 public function onEntityDamageByEntity(EntityDamageEvent $event){
		  $result = 0;
		  if ($event instanceof EntityDamageByEntityEvent) {
		    $entity = $event->getEntity();
			$nick = strtolower($entity->getName());
			$damager = $event->getDamager();
			if($damager instanceof Player){
			if($entity->getName() == $damager->getName()) return;
			
			//($entity instanceof Player){
	        $item = Runa::getItemInOffHand($damager);
			if($item->getId() == 420){
				  $damager->addEffect(Effect::getEffect(10)->setAmplifier(2)->setDuration(20));	
		//	}
					 }
				}
		  }
			}
	  public function PacketReceive(DataPacketReceiveEvent $event){ //Функция эвент получения пакетов сервером от клиента
		   $player = $event->getPlayer();
		   $nick = strtolower($player->getName());
	
		   if($event->getPacket() instanceof ContainerClosePacket){
			  if(isset($this->chest[$nick])){
			  $this->closeAuc($player);
			  unset($this->chest[$nick]);
			  }
		   }
		  if($event->getPacket() instanceof INVENTORY_ACTION_PACKET or $event->getPacket() instanceof ContainerSetSlotPacket){
			  $pk = $event->getPacket();
		 $nick = strtolower($player->getName());
		 
		 	 if(!isset($this->chest[$nick])) return false;
			 		 
			  $item = $pk->item;
			  
			  $id = $item->getId();
           
			   if($item->getCustomName() == "§l§cВыйти" or $item->getCustomName() == "§l§cОтменить"){ //Пишем что будет если игрока нажмёт на эти кнопки
			   
				   $this->closeAuc($player); //Вызываем функцию закрытия аукциона
				   
			   }
			  
					  }
				  }
		private static function setItemInOffHand(Player $player, Item $item){
			
		$item = clone $item;
		Runa::$offHandItems[$player->getName()] = $item;
		Runa::processVisualOffHand($player, $item);
	}

	private static function getItemInOffHand(Player $player) : Item{
		return Runa::$offHandItems[$player->getName()] ?? Item::get(ItemIds::AIR);
	}


	public function onQuit(PlayerQuitEvent $event){
		unset(Runa::$offHandItems[$event->getPlayer()->getName()]);
	}


	public function onJoin(PlayerLoginEvent $event){
		Runa::setItemInOffHand($event->getPlayer(), Item::get(ItemIds::AIR));
	}


	public function onRespawn(PlayerRespawnEvent $event){
		Runa::setItemInOffHand($event->getPlayer(), Item::get(ItemIds::AIR));
		$pk = new ContainerSetSlotPacket();
		$pk->windowid = 119;
		$pk->slot = 0;
		$pk->item = Item::get(ItemIds::AIR);
		$pk->hotbarSlot = 0;
		$pk->unknown = 0;
		$player = $event->getPlayer();
	    $nick = strtolower($player->getName());
		$event->getPlayer()->dataPacket($pk);
			$this->runa[$nick] = true;
	}

public function onInteract(PlayerInteractEvent $event){
	$player = $event->getPlayer();
	$nick = strtolower($player->getName());
	$item = $player->getInventory()->getItemInHand();
	if($item->getId() == 420){
	$player->getInventory()->setItemInHand(Item::get(0,0,0));
	Runa::setItemInOffHand($player,$item);
	    $pk = new ContainerSetSlotPacket();
		$pk->windowid = 119;
		$pk->slot = 0;
		$pk->item = $item;
		$pk->hotbarSlot = 0;
		$pk->unknown = 0;
		$player->dataPacket($pk);

	}
	
}
	public function onReceived(DataPacketReceiveEvent $event){
		$packet = $event->getPacket();

		if($packet instanceof MobEquipmentPacket){
			$item = $packet->item;
			$slot = $packet->slot;
			$windowId = $packet->windowId;
			if($item instanceof Item && $windowId === 119 && $slot === 0x00){
				$player = $event->getPlayer();
				if($item->getId() == 0){
					if(Runa::getItemInOffHand($player)->getId() == 420)$player->getInventory()->addItem(Runa::getItemInOffHand($player));
				}
				
				Runa::setItemInOffHand($player, $item);
			}
		}
	}
		
}
				


?>