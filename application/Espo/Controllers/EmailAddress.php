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

namespace Espo\Controllers;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\BadRequest;

use Espo\Tools\Email\AddressService as Service;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\RecordBase;

class EmailAddress extends RecordBase
{
    private const ADDRESS_MAX_SIZE = 50;

    /**
     * @return array<int,array<string,mixed>>
     * @throws Forbidden
     * @throws BadRequest
     */
    public function actionSearchInAddressBook(Request $request): array
    {
        if (!$this->acl->checkScope('Email')) {
            throw new Forbidden();
        }

        if (!$this->acl->checkScope('Email', 'create')) {
            throw new Forbidden();
        }

        $q = $request->getQueryParam('q');

        if ($q === null) {
            throw new BadRequest("No `q` parameter.");
        }

        $maxSize = intval($request->getQueryParam('maxSize'));

        if (!$maxSize || $maxSize > self::ADDRESS_MAX_SIZE) {
            $maxSize = (int) $this->config->get('recordsPerPage');
        }

        $onlyActual = $request->getQueryParam('onlyActual') === 'true';

        return $this->getEmailAddressService()->searchInAddressBook($q, $maxSize, $onlyActual);
    }

    private function getEmailAddressService(): Service
    {
        return $this->injectableFactory->create(Service::class);
    }
}
