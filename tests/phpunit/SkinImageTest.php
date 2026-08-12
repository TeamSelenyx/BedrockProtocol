<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;

class SkinImageTest extends TestCase{

	public function testFromLegacy256x256() : void{
		$data = str_repeat("\x00", 256 * 256 * 4);
		$image = SkinImage::fromLegacy($data);

		self::assertSame(256, $image->getWidth());
		self::assertSame(256, $image->getHeight());
		self::assertSame($data, $image->getData());
	}

	public function testFromLegacy128x128() : void{
		$data = str_repeat("\x00", 128 * 128 * 4);
		$image = SkinImage::fromLegacy($data);

		self::assertSame(128, $image->getWidth());
		self::assertSame(128, $image->getHeight());
		self::assertSame($data, $image->getData());
	}
}