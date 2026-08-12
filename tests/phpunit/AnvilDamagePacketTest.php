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
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\BlockPosition;

class AnvilDamagePacketTest extends TestCase{

	public function testPayloadContainsOnlyBlockPosition() : void{
		$position = new BlockPosition(-12, 64, 37);

		$expected = new ByteBufferWriter();
		VarInt::writeUnsignedInt($expected, ProtocolInfo::ANVIL_DAMAGE_PACKET);
		CommonTypes::putBlockPosition($expected, $position);

		$actual = new ByteBufferWriter();
		AnvilDamagePacket::create($position)->encode($actual);

		self::assertSame($expected->getData(), $actual->getData());

		$decoded = PacketPool::getInstance()->getPacket($expected->getData());
		self::assertInstanceOf(AnvilDamagePacket::class, $decoded);

		$decoded->decode(new ByteBufferReader($expected->getData()));

		self::assertEquals($position, $decoded->getBlockPosition());
	}
}
