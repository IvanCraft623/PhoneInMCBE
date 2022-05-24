<?php

/**
 * @author Frago9876543210
 * @link   https://github.com/Frago9876543210/PhoneInMcpe
 */

declare(strict_types=1);

namespace Frago9876543210\PhoneInMcpe;


use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Human;
use pocketmine\entity\Skin;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;

class EntityInfo extends Human{

	protected $scale = 0.125;

	private int $width;

	private int $height;

	public function __construct(Location $location, Skin $skin, int $width, int $height){
		$this->width = $width;
		$this->height = $height;
		parent::__construct($location, $skin);
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		//NOTE: you can change size
		//0.125 * 4 = 0.5
		return new EntitySizeInfo(0.0, 0.125, 0.0);
	}

	public function canSaveWithChunk() : bool{
		return false;
	}

	public function getWidth() : int{
		return $this->width;
	}

	public function getHeight() : int{
		return $this->height;
	}

	public function attack(EntityDamageEvent $event): void {}

	protected function move(float $dx, float $dy, float $dz): void {}
}