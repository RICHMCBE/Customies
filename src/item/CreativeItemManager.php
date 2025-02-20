<?php
declare(strict_types=1);

namespace customiesdevs\customies\item;

use pocketmine\inventory\CreativeCategory;
use pocketmine\inventory\CreativeGroup;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\Item;
use pocketmine\lang\Translatable;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\SingletonTrait;

final class CreativeItemManager{
	use SingletonTrait;

	// pm uses spl object_id, so we need to cache all existing groups to use them
	// https://github.com/pmmp/PocketMine-MP/blob/77be5f8e25cb0a9f0fb7fcd79183ff63e65d4e05/src/network/mcpe/cache/CreativeInventoryCache.php#L82
	private array $groups = [];

	public function __construct(){
		CreativeInventory::getInstance()->getContentChangedCallbacks()->add(function() : void{
			//clear group cache?
		});
	}

	private function loadGroups() : void{
		if($this->groups !== []){
			return;
		}
		foreach(CreativeInventory::getInstance()->getAllEntries() as $entry){
			$group = $entry->getGroup();
			if($group !== null){
				$this->groups[$group->getName()->getText()] = $group;
			}
		}
	}

	public function addBlockItem(Item $item, CreativeInventoryInfo $creativeInfo) : void{
		$this->loadGroups();
		if($creativeInfo->getCategory() === CreativeInventoryInfo::CATEGORY_ALL || $creativeInfo->getCategory() === CreativeInventoryInfo::CATEGORY_COMMANDS){
			return;
		}

		$group = $this->groups[$creativeInfo->getGroup()] ?? ($creativeInfo->getGroup() !== "" && $creativeInfo->getGroup() !== CreativeInventoryInfo::NONE ? new CreativeGroup(
			new Translatable($creativeInfo->getGroup()),
			$item
		) : null);
		if($group !== null){
			$this->groups[$group->getName()->getText()] = $group;
		}

		$category = match ($creativeInfo->getCategory()) { //wait, can we add existing groups in different categories here?
			CreativeInventoryInfo::CATEGORY_CONSTRUCTION => CreativeCategory::CONSTRUCTION,
			CreativeInventoryInfo::CATEGORY_ITEMS => CreativeCategory::ITEMS,
			CreativeInventoryInfo::CATEGORY_NATURE => CreativeCategory::NATURE,
			CreativeInventoryInfo::CATEGORY_EQUIPMENT => CreativeCategory::EQUIPMENT,
			default => throw new AssumptionFailedError("Unknown category")
		};

		CreativeInventory::getInstance()->add($item, $category, $group);
	}

	public function addItem(Item $item, ?CreativeCategory $category = null) : void{
		$this->loadGroups();
		if($item instanceof ItemComponents){
			$item_properties = $item->getComponents()->getCompoundTag("components")?->getCompoundTag("item_properties");
			if($item_properties !== null && $item_properties->count() !== 0){
				if($category === null){
					$categoryTag = $item_properties->getTag("creative_category");
					if($categoryTag instanceof IntTag){
						$categoryVal = $categoryTag->getValue();
						if($categoryVal !== 0){
							$category = CreativeCategory::cases()[$categoryVal - 1];
						}
					}
				}
				if($category !== null){
					$group = null;
					$groupTag = $item_properties->getTag("creative_group");
					if($groupTag instanceof StringTag){
						$groupVal = $groupTag->getValue();
						if($groupVal !== ""){
							/** @noinspection PhpParamsInspection */
							$group = $this->groups[$groupVal] ?? new CreativeGroup(new Translatable($groupVal), $item);
							$this->groups[$group->getName()->getText()] = $group;
						}
					}
					/** @noinspection PhpParamsInspection */
					CreativeInventory::getInstance()->add($item, $category, $group);
				}
			}
		}
	}
}