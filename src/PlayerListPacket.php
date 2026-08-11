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

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\color\Color;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use function count;

class PlayerListPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::PLAYER_LIST_PACKET;

	public const TYPE_REMOVE = 0;
	public const TYPE_ADD = 1;

	private const ACTION_ADD = 0;
	private const ACTION_REMOVE = 1;

	public int $type;
	/** @var PlayerListEntry[] */
	public array $entries = [];

	/**
	 * @generate-create-func
	 * @param PlayerListEntry[] $entries
	 */
	private static function create(int $type, array $entries) : self{
		$result = new self;
		$result->type = $type;
		$result->entries = $entries;
		return $result;
	}

	/**
	 * @param PlayerListEntry[] $entries
	 */
	public static function add(array $entries) : self{
		return self::create(self::TYPE_ADD, $entries);
	}

	/**
	 * @param PlayerListEntry[] $entries
	 */
	public static function remove(array $entries) : self{
		return self::create(self::TYPE_REMOVE, $entries);
	}

	protected function decodePayload(ByteBufferReader $in) : void{
		$count = VarInt::readUnsignedInt($in);
		for($i = 0; $i < $count; ++$i){
			$entry = new PlayerListEntry();
			$type = VarInt::readUnsignedInt($in);
			if($i === 0){
				$this->type = $type;
			}elseif($type !== $this->type){
				throw new PacketDecodeException("Mixed entry types in the same PlayerListPacket are not supported");
			}
			Byte::readUnsigned($in);

			if($this->type === self::TYPE_ADD){
				$entry->uuid = CommonTypes::getUUID($in);
				$entry->actorUniqueId = CommonTypes::getActorUniqueId($in);
				$entry->username = CommonTypes::getString($in);
				$entry->xboxUserId = CommonTypes::getString($in);
				$entry->platformChatId = CommonTypes::getString($in);
				$entry->buildPlatform = LE::readSignedInt($in);
				$entry->skinData = CommonTypes::getSkin($in);
				$entry->isTeacher = CommonTypes::getBool($in);
				$entry->isHost = CommonTypes::getBool($in);
				$entry->isSubClient = CommonTypes::getBool($in);
				$entry->color = Color::fromARGB(LE::readUnsignedInt($in));
			}else{
				$entry->uuid = CommonTypes::getUUID($in);
			}

			$this->entries[$i] = $entry;
		}
	}

	protected function encodePayload(ByteBufferWriter $out) : void{
		VarInt::writeUnsignedInt($out, count($this->entries));
		foreach($this->entries as $entry){
			VarInt::writeUnsignedInt($out, $this->type);
			Byte::writeUnsigned($out, $this->type === self::TYPE_ADD ? self::ACTION_ADD : self::ACTION_REMOVE);
			if($this->type === self::TYPE_ADD){
				CommonTypes::putUUID($out, $entry->uuid);
				CommonTypes::putActorUniqueId($out, $entry->actorUniqueId);
				CommonTypes::putString($out, $entry->username);
				CommonTypes::putString($out, $entry->xboxUserId);
				CommonTypes::putString($out, $entry->platformChatId);
				LE::writeSignedInt($out, $entry->buildPlatform);
				CommonTypes::putSkin($out, $entry->skinData);
				CommonTypes::putBool($out, $entry->isTeacher);
				CommonTypes::putBool($out, $entry->isHost);
				CommonTypes::putBool($out, $entry->isSubClient);
				LE::writeUnsignedInt($out, ($entry->color ?? new Color(255, 255, 255))->toARGB());
			}else{
				CommonTypes::putUUID($out, $entry->uuid);
			}
		}
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handlePlayerList($this);
	}
}
