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

namespace pocketmine\network\mcpe\protocol\types\sound;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pocketmine\network\mcpe\protocol\PacketDecodeException;

abstract class SoundDataUpdate{
	public const TYPE_STOP = 0;
	public const TYPE_SET_VOLUME = 1;
	public const TYPE_SET_PITCH = 2;
	public const TYPE_FADE = 3;
	public const TYPE_SEEK_TO = 4;
	public const TYPE_PAUSE = 5;
	public const TYPE_RESUME = 6;

	final public static function read(ByteBufferReader $in) : self{
		$typeId = LE::readUnsignedInt($in);

		return match($typeId){
			self::TYPE_STOP => new SoundDataStop(),
			self::TYPE_SET_VOLUME => new SoundDataSetVolume(LE::readFloat($in)),
			self::TYPE_SET_PITCH => new SoundDataSetPitch(LE::readFloat($in)),
			self::TYPE_FADE => new SoundDataFade(
				LE::readFloat($in),
				LE::readFloat($in)
			),
			self::TYPE_SEEK_TO => new SoundDataSeekTo(LE::readFloat($in)),
			self::TYPE_PAUSE => new SoundDataPause(),
			self::TYPE_RESUME => new SoundDataResume(),
			default => throw new PacketDecodeException("Unknown sound data update type $typeId"),
		};
	}

	abstract public function getTypeId() : int;

	abstract public function write(ByteBufferWriter $out) : void;
}
