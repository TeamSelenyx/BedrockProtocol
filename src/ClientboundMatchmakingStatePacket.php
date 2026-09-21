<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

final class ClientboundMatchmakingStatePacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::CLIENTBOUND_MATCHMAKING_STATE_PACKET;

	public const STATE_IDLE = 0;
	public const STATE_MATCHMAKING = 1;
	public const STATE_MATCH_FOUND = 2;

	private int $state;
	private string $destinationName;

	public static function create(int $state, string $destinationName) : self{
		$result = new self;
		$result->state = $state;
		$result->destinationName = $destinationName;
		return $result;
	}

	public function getState() : int{ return $this->state; }

	public function getDestinationName() : string{ return $this->destinationName; }

	protected function decodePayload(ByteBufferReader $in) : void{
		$this->state = Byte::readUnsigned($in);
		$this->destinationName = CommonTypes::getString($in);
	}

	protected function encodePayload(ByteBufferWriter $out) : void{
		Byte::writeUnsigned($out, $this->state);
		CommonTypes::putString($out, $this->destinationName);
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleClientboundMatchmakingState($this);
	}
}
