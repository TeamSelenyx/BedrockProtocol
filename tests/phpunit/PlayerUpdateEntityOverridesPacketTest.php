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
use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\OverrideUpdateType;
use const INF;
use const NAN;

final class PlayerUpdateEntityOverridesPacketTest extends TestCase{

	public function testVariants() : void{
		$actorUniqueId = 0x0102030405;
		$propertyIndex = 0x010203;

		/**
		 * @var list<array{
		 *     PlayerUpdateEntityOverridesPacket,
		 *     OverrideUpdateType,
		 *     ?int,
		 *     ?float,
		 *     \Closure(ByteBufferWriter): void
		 * }> $cases
		 */
		$cases = [
			[
				PlayerUpdateEntityOverridesPacket::createClearOverrides(
					$actorUniqueId,
					$propertyIndex
				),
				OverrideUpdateType::CLEAR_OVERRIDES,
				null,
				null,
				static function(ByteBufferWriter $out) : void{},
			],
			[
				PlayerUpdateEntityOverridesPacket::createRemoveOverride(
					$actorUniqueId,
					$propertyIndex
				),
				OverrideUpdateType::REMOVE_OVERRIDE,
				null,
				null,
				static function(ByteBufferWriter $out) : void{},
			],
			[
				PlayerUpdateEntityOverridesPacket::createIntOverride(
					$actorUniqueId,
					$propertyIndex,
					-123456789
				),
				OverrideUpdateType::SET_INT_OVERRIDE,
				-123456789,
				null,
				static function(ByteBufferWriter $out) : void{
					LE::writeSignedInt($out, -123456789);
				},
			],
			[
				PlayerUpdateEntityOverridesPacket::createFloatOverride(
					$actorUniqueId,
					$propertyIndex,
					1.25
				),
				OverrideUpdateType::SET_FLOAT_OVERRIDE,
				null,
				1.25,
				static function(ByteBufferWriter $out) : void{
					LE::writeFloat($out, 1.25);
				},
			],
		];

		foreach(
			$cases as [
				$packet,
				$updateType,
				$intValue,
				$floatValue,
				$writePayload,
			]
		){
			$expected = new ByteBufferWriter();

			VarInt::writeUnsignedInt(
				$expected,
				ProtocolInfo::PLAYER_UPDATE_ENTITY_OVERRIDES_PACKET
			);
			CommonTypes::putActorUniqueId(
				$expected,
				$actorUniqueId
			);
			VarInt::writeUnsignedInt(
				$expected,
				$propertyIndex
			);
			LE::writeUnsignedInt(
				$expected,
				$updateType->value
			);
			$writePayload($expected);

			$actual = new ByteBufferWriter();
			$packet->encode($actual);

			self::assertSame(
				$expected->getData(),
				$actual->getData(),
				$updateType->name
			);

			$decoded = PacketPool::getInstance()->getPacket(
				$expected->getData()
			);

			self::assertInstanceOf(
				PlayerUpdateEntityOverridesPacket::class,
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
				$propertyIndex,
				$decoded->getPropertyIndex()
			);
			self::assertSame(
				$updateType,
				$decoded->getUpdateType()
			);
			self::assertSame(
				$intValue,
				$decoded->getIntOverrideValue()
			);
			self::assertSame(
				$floatValue,
				$decoded->getFloatOverrideValue()
			);

			$reencoded = new ByteBufferWriter();
			$decoded->encode($reencoded);

			self::assertSame(
				$expected->getData(),
				$reencoded->getData()
			);
		}
	}

	public function testUnknownUpdateTypeIsRejected() : void{
		$buffer = new ByteBufferWriter();

		VarInt::writeUnsignedInt(
			$buffer,
			ProtocolInfo::PLAYER_UPDATE_ENTITY_OVERRIDES_PACKET
		);
		CommonTypes::putActorUniqueId($buffer, 1);
		VarInt::writeUnsignedInt($buffer, 2);
		LE::writeUnsignedInt($buffer, 4);

		$decoded = PacketPool::getInstance()->getPacket(
			$buffer->getData()
		);

		self::assertInstanceOf(
			PlayerUpdateEntityOverridesPacket::class,
			$decoded
		);

		$this->expectException(PacketDecodeException::class);

		$decoded->decode(
			new ByteBufferReader($buffer->getData())
		);
	}

	public function testNonFiniteFloatFactoriesAreRejected() : void{
		foreach([INF, -INF, NAN] as $value){
			try{
				PlayerUpdateEntityOverridesPacket::createFloatOverride(
					1,
					2,
					$value
				);

				self::fail(
					"Non-finite float override was accepted"
				);
			}catch(InvalidArgumentException){
				self::addToAssertionCount(1);
			}
		}
	}

	public function testNonFiniteFloatPayloadIsRejected() : void{
		$buffer = new ByteBufferWriter();

		VarInt::writeUnsignedInt(
			$buffer,
			ProtocolInfo::PLAYER_UPDATE_ENTITY_OVERRIDES_PACKET
		);
		CommonTypes::putActorUniqueId($buffer, 1);
		VarInt::writeUnsignedInt($buffer, 2);
		LE::writeUnsignedInt(
			$buffer,
			OverrideUpdateType::SET_FLOAT_OVERRIDE->value
		);
		LE::writeFloat($buffer, INF);

		$decoded = PacketPool::getInstance()->getPacket(
			$buffer->getData()
		);

		self::assertInstanceOf(
			PlayerUpdateEntityOverridesPacket::class,
			$decoded
		);

		$this->expectException(PacketDecodeException::class);
		$this->expectExceptionMessage(
			"Float override value must be finite"
		);

		$decoded->decode(
			new ByteBufferReader($buffer->getData())
		);
	}
}
