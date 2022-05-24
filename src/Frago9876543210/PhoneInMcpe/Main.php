<?php

/**
 * @author Frago9876543210
 * @link   https://github.com/Frago9876543210/PhoneInMcpe
 */

declare(strict_types=1);

namespace Frago9876543210\PhoneInMcpe;


use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\entity\Location;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\PlayerSkinPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\plugin\PluginBase;
use Ramsey\Uuid\Uuid;

class Main extends PluginBase implements Listener{


	public string $model = '{"geometry.flat":{"bones":[{"name":"body","pivot":[0,0,0],"cubes":[{"origin":[0,0,0],"size":[64,64,1],"uv":[0,0]}]}]}}';

	public int $width = 7; //1920 / 64 = 30; 30 / 2 = 15; 15 / 2 = 7.5; 7

	public int $height = 4; //1080 / 64 = 16.875; 16 / 2 = 8; 8 / 2 = 4

	/** @var  EntityInfo[] $entities */
	public array $entities = [];
	/** @var EntityInfo[] $lastEntity */
	public array $lastEntity = [];

	public function onEnable() : void{
		if(!extension_loaded("gd")){
			$this->getLogger()->error("[-] Turn on gd lib in php.ini or recompile php!");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		$this->width = (int) $this->getConfig()->get("width", $this->width);
		$this->width = (int) $this->getConfig()->get("height", $this->height);

		$path = $this->getDataFolder();
		if(file_exists($path . "tmp")){
			$this->removeDir($path . "tmp");
		}
		@mkdir($path . "tmp", 0666, true);

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		echo shell_exec('adb devices -l'); //for init phone & start adb server

		//If you have a productive PC, then you can use it:
		//$this->getServer()->getScheduler()->scheduleRepeatingTask(new UpdateImageTask($this), 1);
		//but this is better for small tests:
		$this->getScheduler()->scheduleRepeatingTask(new UpdateImageTask($this), 5);
	}

	public function chat(PlayerChatEvent $e) : void{
		$p = $e->getPlayer();
		$m = $e->getMessage();

		$args = explode(" ", $m);
		$word = array_shift($args);

		if($word === "start"){
			unset($this->entities);
			$e->cancel();
			$location = $p->getLocation();
			$coordinates = $location->asVector3();
			$pitch = deg2rad($location->pitch);
			$yaw = deg2rad($location->yaw);
			$direction = new Vector3(-sin($yaw) * cos($pitch), -sin($pitch), cos($yaw) * cos($pitch));
			for($x = 1; $x < $this->width + 1; $x++){
				for($y = 1; $y < $this->height + 1; $y++){
					//todo: check this
					$entity = new EntityInfo(
						new Location($coordinates->x + $direction->x + ($x * 0.5), $coordinates->y + ($y * 0.5), $coordinates->z + $direction->z, $location->getWorld(), 0.0, 0.0),
						new Skin("Skin", str_repeat('Z', 16384), "", "geometry.flat", $this->model),
						$x * 4 * 64,
						$y * 4 * 64
					);
					$entity->spawnToAll();
					$this->entities[] = $entity;
				}
			}
		}elseif($word === "touch"){
			$e->cancel();
			if(isset($args[0]) && isset($args[1])){
				$this->touch(intval($args[0]), intval($args[1]));
			}
		}elseif($word === "shell"){
			$e->cancel();
			shell_exec("adb shell " . implode(" ", $args));
		}elseif($word === "stop"){
			$e->cancel();
			foreach($this->entities as $entityInfo){
				$entityInfo->flagForDespawn();
			}
			$this->entities = [];
		}
	}

	public function handleInteractPacket(DataPacketReceiveEvent $e) : void{
		//InventoryTransactionPacket works very rarely
		//InteractPacket better
		$packet = $e->getPacket();
		if($packet instanceof InteractPacket){
			$eid = $packet->targetActorRuntimeId;
			foreach($this->entities as $entityInfo){
				if($entityInfo->getId() == $eid){
					$this->lastEntity[$e->getOrigin()->getPlayer()->getId()] = $entityInfo;
				}
			}
		}
	}

	public function onPress(PlayerInteractEvent $e){
		if($e->getAction() == $e::RIGHT_CLICK_BLOCK && $e->getItem()->equals(VanillaItems::STICK())){
			$id = $e->getPlayer()->getId();
			if(isset($this->lastEntity[$id])){
				$this->touch($this->lastEntity[$id]->getHeight(), $this->lastEntity[$id]->getWidth());
			}
		}
	}

	/**
	 * Converts an image to bytes
	 * @param string $filename path to png
	 * @return string bytes for skin
	 */
	public function getTextureFromFile(string $filename) : string{
		$im = imagecreatefrompng($filename);
		list($width, $height) = getimagesize($filename);
		$bytes = "";
		for($y = 0; $y < $height; $y++){
			for($x = 0; $x < $width; $x++){
				$argb = imagecolorat($im, $x, $y);
				$a = ((~((int) ($argb >> 24))) << 1) & 0xff;
				$r = ($argb >> 16) & 0xff;
				$g = ($argb >> 8) & 0xff;
				$b = $argb & 0xff;
				$bytes .= chr($r) . chr($g) . chr($b) . chr($a);
			}
		}
		imagedestroy($im);
		return $bytes;
	}

	/**
	 * Recursive crop image
	 * @param string $image  path to png
	 * @param string $output path to output png
	 */
	public function cropRecursive(string $image, string $output) : void{
		$size = getimagesize($image);
		$im = imagecreatefrompng($image);
		$newIm = imagecreatetruecolor(64, 64);
		$i = 0;
		for($xc = 0; $xc < $size[0]; $xc = $xc + 64){
			for($y = $size[1] - 64; $y >= 0; $y = $y - 64){
				imagecopy($newIm, $im, 0, 0, $xc, $y, 64, 64);
				imagepng($newIm, $output . $i++ . '.png');
			}
		}
		imagedestroy($newIm);
		imagedestroy($im);
	}

	/**
	 * Resizes the picture
	 * @param string $filename path to png
	 * @param string $output   path to output png
	 * @param int    $new_width
	 * @param int    $new_height
	 */
	public function resize(string $filename, string $output, int $new_width, int $new_height) : void{
		list($width, $height) = getimagesize($filename);
		$image_p = imagecreatetruecolor($new_width, $new_height);
		$image = imagecreatefrompng($filename);
		imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
		imagepng($image_p, $output);
		imagedestroy($image);
		imagedestroy($image_p);
	}

	/**
	 * Remove directory
	 * @param string $dir
	 */
	private function removeDir(string $dir) : void{
		if($objects = glob($dir . "/*")){
			foreach($objects as $obj){
				is_dir($obj) ? $this->removeDir($obj) : unlink($obj);
			}
		}
		rmdir($dir);
	}

	/**
	 * @param int $x
	 * @param int $y
	 */
	public function touch(int $x, int $y) : void{
		shell_exec("adb shell input tap $x $y");
	}

	public function onDisable() : void{
		@unlink($this->getServer()->getDataPath() . "s.png");
		@unlink($this->getServer()->getDataPath() . "s1.png");
	}
}
