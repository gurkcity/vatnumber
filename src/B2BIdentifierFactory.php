<?php

namespace Onlineshopmodule\PrestaShop\Module\Vatnumber;

use Address;
use Validate;
use Zone;

class B2BIdentifierFactory
{
    protected $id_zone_1 = 0;
    protected $id_zone_2 = 0;

    public function __construct(
        int $id_zone_1,
        int $id_zone_2
    ) {
        $this->id_zone_1 = $id_zone_1;
        $this->id_zone_2 = $id_zone_2;
    }

    public function getB2BIdentifierForAddress(Address $address): B2BIdentifier
    {
        if (!$this->id_zone_1 || !$this->id_zone_2) {
            throw new B2BIdentifierException('Zones are not set');
        }

        $zone_1 = new Zone($this->id_zone_1);
		$zone_2 = new Zone($this->id_zone_2);

        if (!Validate::isLoadedObject($zone_1) || !Validate::isLoadedObject($zone_2)) {
            throw new B2BIdentifierException('Zones are not set');
        }

        return new B2BIdentifier(
			$address,
			$zone_1,
			$zone_2
		);
    }
}
