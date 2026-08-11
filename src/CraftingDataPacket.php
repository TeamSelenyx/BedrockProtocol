<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\recipe\MaterialReducerRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\MaterialReducerRecipeOutput;
use pocketmine\network\mcpe\protocol\types\recipe\MultiRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\PotionContainerChangeRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\PotionTypeRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeWithTypeId;
use pocketmine\network\mcpe\protocol\types\recipe\ShapedRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\ShapelessRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\SmithingTransformRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\SmithingTrimRecipe;
use function count;

class CraftingDataPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::CRAFTING_DATA_PACKET;

	public const ENTRY_SHAPELESS = 0;
	public const ENTRY_SHAPED = 1;
	public const ENTRY_MULTI = 4;
	public const ENTRY_USER_DATA_SHAPELESS = 5;
	public const ENTRY_SHAPELESS_CHEMISTRY = 6;
	public const ENTRY_SHAPED_CHEMISTRY = 7;
	public const ENTRY_SMITHING_TRANSFORM = 8;
	public const ENTRY_SMITHING_TRIM = 9;

	/** @var RecipeWithTypeId[] */
	public array $recipesWithTypeIds = [];
	/** @var PotionTypeRecipe[] */
	public array $potionTypeRecipes = [];
	/** @var PotionContainerChangeRecipe[] */
	public array $potionContainerRecipes = [];
	/** @var MaterialReducerRecipe[] */
	public array $materialReducerRecipes = [];
	public bool $cleanRecipes = false;

	/**
	 * @generate-create-func
	 * @param RecipeWithTypeId[]            $recipesWithTypeIds
	 * @param PotionTypeRecipe[]            $potionTypeRecipes
	 * @param PotionContainerChangeRecipe[] $potionContainerRecipes
	 * @param MaterialReducerRecipe[]       $materialReducerRecipes
	 */
	public static function create(array $recipesWithTypeIds, array $potionTypeRecipes, array $potionContainerRecipes, array $materialReducerRecipes, bool $cleanRecipes) : self{
		$result = new self;
		$result->recipesWithTypeIds = $recipesWithTypeIds;
		$result->potionTypeRecipes = $potionTypeRecipes;
		$result->potionContainerRecipes = $potionContainerRecipes;
		$result->materialReducerRecipes = $materialReducerRecipes;
		$result->cleanRecipes = $cleanRecipes;
		return $result;
	}

	protected function decodePayload(ByteBufferReader $in) : void{
		//recipes are grouped into one length-prefixed vector per type, in this exact order
		$this->getRecipes($in, self::ENTRY_SHAPED, ShapedRecipe::decode(...));
		$this->getRecipes($in, self::ENTRY_SHAPELESS, ShapelessRecipe::decode(...));
		$this->getRecipes($in, self::ENTRY_MULTI, MultiRecipe::decode(...));
		$this->getRecipes($in, self::ENTRY_USER_DATA_SHAPELESS, ShapelessRecipe::decode(...));
		$this->getRecipes($in, self::ENTRY_SHAPELESS_CHEMISTRY, ShapelessRecipe::decode(...));
		$this->getRecipes($in, self::ENTRY_SHAPED_CHEMISTRY, ShapedRecipe::decode(...));
		$this->getRecipes($in, self::ENTRY_SMITHING_TRANSFORM, SmithingTransformRecipe::decode(...));
		$this->getRecipes($in, self::ENTRY_SMITHING_TRIM, SmithingTrimRecipe::decode(...));

		for($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; ++$i){
			$inputId = VarInt::readSignedInt($in);
			$inputMeta = VarInt::readSignedInt($in);
			$ingredientId = VarInt::readSignedInt($in);
			$ingredientMeta = VarInt::readSignedInt($in);
			$outputId = VarInt::readSignedInt($in);
			$outputMeta = VarInt::readSignedInt($in);
			$this->potionTypeRecipes[] = new PotionTypeRecipe($inputId, $inputMeta, $ingredientId, $ingredientMeta, $outputId, $outputMeta);
		}
		for($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; ++$i){
			$input = VarInt::readSignedInt($in);
			$ingredient = VarInt::readSignedInt($in);
			$output = VarInt::readSignedInt($in);
			$this->potionContainerRecipes[] = new PotionContainerChangeRecipe($input, $ingredient, $output);
		}
		for($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; ++$i){
			$inputIdAndData = VarInt::readSignedInt($in);
			[$inputId, $inputMeta] = [$inputIdAndData >> 16, $inputIdAndData & 0x7fff];
			$outputs = [];
			for($j = 0, $outputCount = VarInt::readUnsignedInt($in); $j < $outputCount; ++$j){
				$outputItemId = VarInt::readSignedInt($in);
				$outputItemCount = VarInt::readSignedInt($in);
				$outputs[] = new MaterialReducerRecipeOutput($outputItemId, $outputItemCount);
			}
			$this->materialReducerRecipes[] = new MaterialReducerRecipe($inputId, $inputMeta, $outputs);
		}
		$this->cleanRecipes = CommonTypes::getBool($in);
	}

	/**
	 * @phpstan-param \Closure(int, ByteBufferReader) : RecipeWithTypeId $decoder
	 *
	 * @throws PacketDecodeException
	 * @throws DataDecodeException
	 */
	private function getRecipes(ByteBufferReader $in, int $typeId, \Closure $decoder) : void{
		for($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; ++$i){
			$this->recipesWithTypeIds[] = $decoder($typeId, $in);
		}
	}

	/**
	 * @param RecipeWithTypeId[] $recipes
	 */
	private static function putRecipes(ByteBufferWriter $out, array $recipes) : void{
		VarInt::writeUnsignedInt($out, count($recipes));
		foreach($recipes as $recipe){
			$recipe->encode($out);
		}
	}

	protected function encodePayload(ByteBufferWriter $out) : void{
		$byType = [];
		foreach($this->recipesWithTypeIds as $recipe){
			$typeId = $recipe->getTypeId();
			match($typeId){
				self::ENTRY_SHAPED, self::ENTRY_SHAPELESS, self::ENTRY_MULTI, self::ENTRY_USER_DATA_SHAPELESS,
				self::ENTRY_SHAPELESS_CHEMISTRY, self::ENTRY_SHAPED_CHEMISTRY, self::ENTRY_SMITHING_TRANSFORM,
				self::ENTRY_SMITHING_TRIM => $byType[$typeId][] = $recipe,
				default => throw new \InvalidArgumentException("Unhandled recipe type $typeId"),
			};
		}
		self::putRecipes($out, $byType[self::ENTRY_SHAPED] ?? []);
		self::putRecipes($out, $byType[self::ENTRY_SHAPELESS] ?? []);
		self::putRecipes($out, $byType[self::ENTRY_MULTI] ?? []);
		self::putRecipes($out, $byType[self::ENTRY_USER_DATA_SHAPELESS] ?? []);
		self::putRecipes($out, $byType[self::ENTRY_SHAPELESS_CHEMISTRY] ?? []);
		self::putRecipes($out, $byType[self::ENTRY_SHAPED_CHEMISTRY] ?? []);
		self::putRecipes($out, $byType[self::ENTRY_SMITHING_TRANSFORM] ?? []);
		self::putRecipes($out, $byType[self::ENTRY_SMITHING_TRIM] ?? []);
		VarInt::writeUnsignedInt($out, count($this->potionTypeRecipes));
		foreach($this->potionTypeRecipes as $recipe){
			VarInt::writeSignedInt($out, $recipe->getInputItemId());
			VarInt::writeSignedInt($out, $recipe->getInputItemMeta());
			VarInt::writeSignedInt($out, $recipe->getIngredientItemId());
			VarInt::writeSignedInt($out, $recipe->getIngredientItemMeta());
			VarInt::writeSignedInt($out, $recipe->getOutputItemId());
			VarInt::writeSignedInt($out, $recipe->getOutputItemMeta());
		}
		VarInt::writeUnsignedInt($out, count($this->potionContainerRecipes));
		foreach($this->potionContainerRecipes as $recipe){
			VarInt::writeSignedInt($out, $recipe->getInputItemId());
			VarInt::writeSignedInt($out, $recipe->getIngredientItemId());
			VarInt::writeSignedInt($out, $recipe->getOutputItemId());
		}
		VarInt::writeUnsignedInt($out, count($this->materialReducerRecipes));
		foreach($this->materialReducerRecipes as $recipe){
			VarInt::writeSignedInt($out, ($recipe->getInputItemId() << 16) | $recipe->getInputItemMeta());
			VarInt::writeUnsignedInt($out, count($recipe->getOutputs()));
			foreach($recipe->getOutputs() as $output){
				VarInt::writeSignedInt($out, $output->getItemId());
				VarInt::writeSignedInt($out, $output->getCount());
			}
		}
		CommonTypes::putBool($out, $this->cleanRecipes);
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleCraftingData($this);
	}
}
