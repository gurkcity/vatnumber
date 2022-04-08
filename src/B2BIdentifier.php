<?php

/**
 * 1. Fall: Rechnung inkl. Mwst
 * B2C Kunde in DE und EU
 * B2B Kunde ohne Vat in DE
 *
 * 2. Fall: Produkte Netto, Summe zzgl. MwSt
 * B2B Kunde ohne VAT in EU
 * B2B Kunde mit VAT aus Welt
 *
 * 3. Fall Nettorechung
 * Kunde aus Welt
 * B2B mit Vat aus EU
 */


namespace Onlineshopmodule\PrestaShop\Module\Vatnumber;

use Address;
use Country;
use Validate;
use Zone;

class B2BIdentifier
{
    const TYPE_WITH_TAX = 0;
    const TYPE_TAX_IN_TOTAL = 1;
    const TYPE_TAX_EXCL = 2;

    private $address = null;
    private $zone_1 = null;
    private $zone_2 = null;

    public function __construct(
        Address $address,
        Zone $zone_1,
        Zone $zone_2
    ) {
        $this->address = $address;
        $this->zone_1 = $zone_1;
        $this->zone_2 = $zone_2;
    }

    public function getB2BType(): int
    {
        if (
            $this->isInZone3()
            || $this->isCompanyWithVatInZone2()
        ) {
            return self::TYPE_TAX_EXCL;
        } elseif (
            $this->isCompanyWithVatInZone1()
            || $this->isCompanyWithoutVatInZone2()
            || $this->isCompanyWithVatInZone3()
        ) {
            return self::TYPE_TAX_IN_TOTAL;
        }

        return self::TYPE_WITH_TAX;
    }

    private function isInZone1(): bool
    {
        $country = $this->getAddressCountry();

        if (!Validate::isLoadedObject($country)) {
            return false;
        }

        return (int)$country->id_zone === (int)$this->zone_1->id;
    }

    private function isInZone2(): bool
    {
        $country = $this->getAddressCountry();

        if (!Validate::isLoadedObject($country)) {
            return false;
        }

        return (int)$country->id_zone === (int)$this->zone_2->id;
    }

    private function isInZone3(): bool
    {
        return !$this->isInZone1() && !$this->isInZone2();
    }

    private function isCompanyWithVatInZone1(): bool
    {
        return $this->isCompany() && $this->hasVat() && $this->isInZone1();
    }

    private function isCompanyWithVatInZone2(): bool
    {
        return $this->isCompany() && $this->hasVat() && $this->isInZone2();
    }

    private function isCompanyWithoutVatInZone2(): bool
    {
        return $this->isCompany() && !$this->hasVat() && $this->isInZone2();
    }

    private function isCompanyWithVatInZone3(): bool
    {
        return $this->isCompany() && $this->hasVat() && $this->isInZone3();
    }

    private function isCompany(): bool
    {
        return '' !== $this->address->company;
    }

    private function isVatValid(string $vat): bool
    {
        return '' !== $vat;
    }

    private function hasVat(): bool
    {
        return '' !== $this->address->vat_number;
    }

    private function getAddressCountry(): Country
    {
        return new Country((int)$this->address->id_country);
    }
}
