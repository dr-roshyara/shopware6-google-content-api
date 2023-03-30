<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class DocumentTypeDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'pickware_document_type';
    }

    public function getCollectionClass(): string
    {
        return DocumentTypeCollection::class;
    }

    public function getEntityClass(): string
    {
        return DocumentTypeEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('technical_name', 'technicalName'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('description', 'description'))->addFlags(new Required()),
            (new OneToManyAssociationField(
                'documents',
                DocumentDefinition::class,
                'document_type_technical_name',
                'technical_name',
            ))->addFlags(new RestrictDelete()),
        ]);
    }
}
