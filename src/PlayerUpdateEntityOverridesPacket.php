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

use InvalidArgumentException;
use LogicException;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\OverrideUpdateType;
use function is_finite;

class PlayerUpdateEntityOverridesPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::PLAYER_UPDATE_ENTITY_OVERRIDES_PACKET;

	private int $actorUniqueId;
	private int $propertyIndex;
	private OverrideUpdateType $updateType;
	private ?int $intOverrideValue = null;
	private ?float $floatOverrideValue = null;

	/**
	 * @generate-create-func
	 */
	private static function create(
		int $actorUniqueId,
		int $propertyIndex,
		OverrideUpdateType $updateType,
		?int $intOverrideValue,
		?float $floatOverrideValue
	) : self{
		$result = new self;
		$result->actorUniqueId = $actorUniqueId;
		$result->propertyIndex = $propertyIndex;
		$result->updateType = $updateType;
		$result->intOverrideValue = $intOverrideValue;
		$result->floatOverrideValue = $floatOverrideValue;
		return $result;
	}

	public static function createIntOverride(
		int $actorUniqueId,
		int $propertyIndex,
		int $value
	) : self{
		return self::create(
			$actorUniqueId,
			$propertyIndex,
			OverrideUpdateType::SET_INT_OVERRIDE,
			$value,
			null
		);
	}

	public static function createFloatOverride(
		int $actorUniqueId,
		int $propertyIndex,
		float $value
	) : self{
		if(!is_finite($value)){
			throw new InvalidArgumentException(
				"Float override value must be finite"
			);
		}

		return self::create(
			$actorUniqueId,
			$propertyIndex,
			OverrideUpdateType::SET_FLOAT_OVERRIDE,
			null,
			$value
		);
	}

	public static function createClearOverrides(
		int $actorUniqueId,
		int $propertyIndex
	) : self{
		return self::create(
			$actorUniqueId,
			$propertyIndex,
			OverrideUpdateType::CLEAR_OVERRIDES,
			null,
			null
		);
	}

	public static function createRemoveOverride(
		int $actorUniqueId,
		int $propertyIndex
	) : self{
		return self::create(
			$actorUniqueId,
			$propertyIndex,
			OverrideUpdateType::REMOVE_OVERRIDE,
			null,
			null
		);
	}

	public function getActorUniqueId() : int{
		return $this->actorUniqueId;
	}

	public function getPropertyIndex() : int{
		return $this->propertyIndex;
	}

	public function getUpdateType() : OverrideUpdateType{
		return $this->updateType;
	}

	public function getIntOverrideValue() : ?int{
		return $this->intOverrideValue;
	}

	public function getFloatOverrideValue() : ?float{
		return $this->floatOverrideValue;
	}

	protected function decodePayload(ByteBufferReader $in) : void{
		$this->actorUniqueId = CommonTypes::getActorUniqueId($in);
		$this->propertyIndex = VarInt::readUnsignedInt($in);
		$this->updateType = OverrideUpdateType::fromPacket(
			LE::readUnsignedInt($in)
		);

		$this->intOverrideValue = null;
		$this->floatOverrideValue = null;

		if($this->updateType === OverrideUpdateType::SET_INT_OVERRIDE){
			$this->intOverrideValue = LE::readSignedInt($in);
		}elseif(
			$this->updateType ===
			OverrideUpdateType::SET_FLOAT_OVERRIDE
		){
			$value = LE::readFloat($in);

			if(!is_finite($value)){
				throw new PacketDecodeException(
					"Float override value must be finite"
				);
			}

			$this->floatOverrideValue = $value;
		}
	}

	protected function encodePayload(ByteBufferWriter $out) : void{
		CommonTypes::putActorUniqueId($out, $this->actorUniqueId);
		VarInt::writeUnsignedInt($out, $this->propertyIndex);
		LE::writeUnsignedInt($out, $this->updateType->value);

		if($this->updateType === OverrideUpdateType::SET_INT_OVERRIDE){
			if($this->intOverrideValue === null){
				throw new LogicException(
					"PlayerUpdateEntityOverridesPacket with type " .
					"SET_INT_OVERRIDE requires intOverrideValue"
				);
			}

			LE::writeSignedInt($out, $this->intOverrideValue);
		}elseif(
			$this->updateType ===
			OverrideUpdateType::SET_FLOAT_OVERRIDE
		){
			if($this->floatOverrideValue === null){
				throw new LogicException(
					"PlayerUpdateEntityOverridesPacket with type " .
					"SET_FLOAT_OVERRIDE requires floatOverrideValue"
				);
			}

			if(!is_finite($this->floatOverrideValue)){
				throw new LogicException(
					"Float override value must be finite"
				);
			}

			LE::writeFloat($out, $this->floatOverrideValue);
		}
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handlePlayerUpdateEntityOverrides($this);
	}
}
