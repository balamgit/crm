<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2022 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\ORM\Query\Part\Where;

use Espo\ORM\Query\Part\WhereItem;

class OrGroupBuilder
{
    /**
     * @var array<mixed,mixed>
     */
    private $raw = [];

    public function build(): OrGroup
    {
        return OrGroup::fromRaw($this->raw);
    }

    public function add(WhereItem $item): self
    {
        $key = $item->getRawKey();
        $value = $item->getRawValue();

        if ($item instanceof AndGroup) {
            $this->raw = self::normalizeRaw($this->raw);

            $this->raw[] = $value;

            return $this;
        }

        if (count($this->raw) === 0) {
            $this->raw[$key] = $value;

            return $this;
        }

        $this->raw = self::normalizeRaw($this->raw);

        $this->raw[] = [$key => $value];

        return $this;
    }

    /**
     * Merge with another OrGroup.
     */
    public function merge(OrGroup $orGroup): self
    {
        $this->raw = array_merge(
            self::normalizeRaw($this->raw),
            self::normalizeRaw($orGroup->getRawValue())
        );

        return $this;
    }

    /**
     * @param array<mixed,mixed> $raw
     * @return array<mixed,mixed>
     */
    private static function normalizeRaw(array $raw): array
    {
        if (count($raw) === 1 && array_keys($raw)[0] !== 0) {
            return [$raw];
        }

        return $raw;
    }
}
