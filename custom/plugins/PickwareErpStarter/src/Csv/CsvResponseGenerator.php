<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Csv;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class CsvResponseGenerator
{
    /**
     * @var Serializer
     */
    private $serializer;

    public function __construct()
    {
        $this->serializer = new Serializer(
            [new ObjectNormalizer()],
            [
                new CsvEncoder([
                    CsvEncoder::DELIMITER_KEY => ';',
                    CsvEncoder::ENCLOSURE_KEY => '"',
                ]),
            ],
        );
    }

    public function createCsvResponse(array $rows): Response
    {
        return new Response(
            $this->serializer->encode($rows, 'csv'),
            Response::HTTP_OK,
            ['Content-Type' => 'text/csv'],
        );
    }
}
