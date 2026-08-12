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
use pocketmine\network\mcpe\protocol\types\sound\SoundDataFade;
use pocketmine\network\mcpe\protocol\types\sound\SoundDataPause;
use pocketmine\network\mcpe\protocol\types\sound\SoundDataResume;
use pocketmine\network\mcpe\protocol\types\sound\SoundDataSeekTo;
use pocketmine\network\mcpe\protocol\types\sound\SoundDataSetPitch;
use pocketmine\network\mcpe\protocol\types\sound\SoundDataSetVolume;
use pocketmine\network\mcpe\protocol\types\sound\SoundDataStop;
use pocketmine\network\mcpe\protocol\types\sound\SoundDataUpdate;

final class ClientboundUpdateSoundDataPacketTest extends TestCase{
	public function testVariants() : void{
		$serverSoundHandle = 0x0102030405060708;

		/**
		 * @var list<array{
		 *     SoundDataUpdate,
		 *     \Closure(ByteBufferWriter): void
		 * }> $cases
		 */
		$cases = [
			[
				new SoundDataStop(),
				static function(ByteBufferWriter $out) : void{},
			],
			[
				new SoundDataSetVolume(0.5),
				static function(ByteBufferWriter $out) : void{
					LE::writeFloat($out, 0.5);
				},
			],
			[
				new SoundDataSetPitch(1.25),
				static function(ByteBufferWriter $out) : void{
					LE::writeFloat($out, 1.25);
				},
			],
			[
				new SoundDataFade(2.5, 0.75),
				static function(ByteBufferWriter $out) : void{
					LE::writeFloat($out, 2.5);
					LE::writeFloat($out, 0.75);
				},
			],
			[
				new SoundDataSeekTo(3.25),
				static function(ByteBufferWriter $out) : void{
					LE::writeFloat($out, 3.25);
				},
			],
			[
				new SoundDataPause(),
				static function(ByteBufferWriter $out) : void{},
			],
			[
				new SoundDataResume(),
				static function(ByteBufferWriter $out) : void{},
			],
		];

		foreach($cases as [$update, $writeExpectedPayload]){
			$expected = new ByteBufferWriter();
			VarInt::writeUnsignedInt(
				$expected,
				ProtocolInfo::CLIENTBOUND_UPDATE_SOUND_DATA_PACKET
			);
			LE::writeUnsignedLong($expected, $serverSoundHandle);
			LE::writeUnsignedInt($expected, $update->getTypeId());
			$writeExpectedPayload($expected);

			$actual = new ByteBufferWriter();
			ClientboundUpdateSoundDataPacket::create(
				$serverSoundHandle,
				$update
			)->encode($actual);

			self::assertSame(
				$expected->getData(),
				$actual->getData(),
				$update::class
			);

			$decoded = PacketPool::getInstance()->getPacket(
				$expected->getData()
			);

			self::assertInstanceOf(
				ClientboundUpdateSoundDataPacket::class,
				$decoded
			);

			$decoded->decode(
				new ByteBufferReader($expected->getData())
			);

			self::assertSame(
				$serverSoundHandle,
				$decoded->getServerSoundHandle()
			);

			self::assertEquals(
				$update,
				$decoded->getUpdate()
			);

			$reencoded = new ByteBufferWriter();
			$decoded->encode($reencoded);

			self::assertSame(
				$expected->getData(),
				$reencoded->getData()
			);
		}
	}

	public function testUnknownVariantIsRejected() : void{
		$buffer = new ByteBufferWriter();

		VarInt::writeUnsignedInt(
			$buffer,
			ProtocolInfo::CLIENTBOUND_UPDATE_SOUND_DATA_PACKET
		);
		LE::writeUnsignedLong($buffer, 1);
		LE::writeUnsignedInt($buffer, 7);

		$decoded = PacketPool::getInstance()->getPacket(
			$buffer->getData()
		);

		self::assertInstanceOf(
			ClientboundUpdateSoundDataPacket::class,
			$decoded
		);

		$this->expectException(PacketDecodeException::class);
		$this->expectExceptionMessage(
			"Unknown sound data update type 7"
		);

		$decoded->decode(
			new ByteBufferReader($buffer->getData())
		);
	}
}
