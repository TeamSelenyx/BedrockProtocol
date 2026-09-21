<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;

final class ServerboundMatchmakingCancelPacket extends DataPacket implements ServerboundPacket{
	public const NETWORK_ID = ProtocolInfo::SERVERBOUND_MATCHMAKING_CANCEL_PACKET;

	public static function create() : self{ return new self; }

	protected function decodePayload(ByteBufferReader $in) : void{}

	protected function encodePayload(ByteBufferWriter $out) : void{}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleServerboundMatchmakingCancel($this);
	}
}
