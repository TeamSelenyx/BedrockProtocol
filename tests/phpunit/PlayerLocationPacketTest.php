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

use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\PlayerLocationType;

final class PlayerLocationPacketTest extends TestCase{

	public function testVariants() : void{
		$actorUniqueId = 0x0102030405;
		$position = new Vector3(1.25, -2.5, 3.75);

		/**
		 * @var list<array{
		 *     PlayerLocationPacket,
		 *     PlayerLocationType,
		 *     ?Vector3
		 * }> $cases
		 */
		$cases = [
			[
				PlayerLocationPacket::createCoordinates(
					$actorUniqueId,
					$position
				),
				PlayerLocationType::PLAYER_LOCATION_COORDINATES,
				$position,
			],
			[
				PlayerLocationPacket::createHide(
					$actorUniqueId
				),
				PlayerLocationType::PLAYER_LOCATION_HIDE,
				null,
			],
		];

		foreach($cases as [$packet, $type, $expectedPosition]){
			$expected = new ByteBufferWriter();

			VarInt::writeUnsignedInt(
				$expected,
				ProtocolInfo::PLAYER_LOCATION_PACKET
			);
			CommonTypes::putActorUniqueId(
				$expected,
				$actorUniqueId
			);
			LE::writeUnsignedInt(
				$expected,
				$type->value
			);

			if($expectedPosition !== null){
				CommonTypes::putVector3(
					$expected,
					$expectedPosition
				);
			}

			$actual = new ByteBufferWriter();
			$packet->encode($actual);

			self::assertSame(
				$expected->getData(),
				$actual->getData(),
				$type->name
			);

			$decoded = PacketPool::getInstance()->getPacket(
				$expected->getData()
			);

			self::assertInstanceOf(
				PlayerLocationPacket::class,
				$decoded
			);

			$decoded->decode(
				new ByteBufferReader($expected->getData())
			);

			self::assertSame(
				$actorUniqueId,
				$decoded->getActorUniqueId()
			);
			self::assertSame(
				$type,
				$decoded->getType()
			);
			self::assertEquals(
				$expectedPosition,
				$decoded->getPosition()
			);

			$reencoded = new ByteBufferWriter();
			$decoded->encode($reencoded);

			self::assertSame(
				$expected->getData(),
				$reencoded->getData()
			);
		}
	}

	public function testUnknownLocationTypeIsRejected() : void{
		$buffer = new ByteBufferWriter();

		VarInt::writeUnsignedInt(
			$buffer,
			ProtocolInfo::PLAYER_LOCATION_PACKET
		);
		CommonTypes::putActorUniqueId($buffer, 1);
		LE::writeUnsignedInt($buffer, 2);

		$decoded = PacketPool::getInstance()->getPacket(
			$buffer->getData()
		);

		self::assertInstanceOf(
			PlayerLocationPacket::class,
			$decoded
		);

		$this->expectException(PacketDecodeException::class);

		$decoded->decode(
			new ByteBufferReader($buffer->getData())
		);
	}
}
