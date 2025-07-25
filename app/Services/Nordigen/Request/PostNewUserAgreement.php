<?php

/*
 * PostNewRequisitionRequest.php
 * Copyright (c) 2022 https://github.com/krehl
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\Nordigen\Request;

use App\Services\Nordigen\Response\NewUserAgreementResponse;
use App\Services\Shared\Response\Response;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Class PostNewUserAgreement
 */
class PostNewUserAgreement extends Request
{
    private string $accessValidForDays;
    private string $bank;
    private string $maxHistoricalDays;

    public function __construct(string $url, string $token)
    {
        $this->setParameters([]);
        $this->setBase($url);
        $this->setToken($token);
        $this->setUrl('api/v2/agreements/enduser/');
        $this->maxHistoricalDays  = '';
        $this->accessValidForDays = '';
        $this->bank               = '';
    }

    public function get(): Response
    {
        // Implement get() method.
    }

    /**
     * @throws GuzzleException
     */
    public function post(): Response
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $array
                = [
                    'institution_id'        => $this->bank,
                    'max_historical_days'   => $this->maxHistoricalDays,
                    'access_valid_for_days' => $this->accessValidForDays,
                ];

        $result = $this->authenticatedJsonPost($array);
        Log::debug('Returned from POST: ', $result);

        return new NewUserAgreementResponse($result);
    }

    public function put(): Response
    {
        // Implement put() method.
    }

    public function setAccessValidForDays(string $accessValidForDays): void
    {
        $this->accessValidForDays = $accessValidForDays;
    }

    public function setBank(string $bank): void
    {
        $this->bank = $bank;
    }

    public function setMaxHistoricalDays(string $maxHistoricalDays): void
    {
        $this->maxHistoricalDays = $maxHistoricalDays;
    }
}
