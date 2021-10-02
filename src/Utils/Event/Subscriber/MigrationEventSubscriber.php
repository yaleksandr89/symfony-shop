<?php

declare(strict_types=1);

namespace App\Utils\Event\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

class MigrationEventSubscriber implements EventSubscriber
{
    /**
     * @return string[]
     */
    public function getSubscribedEvents(): array
    {
        return [
            'postGenerateSchema',
        ];
    }

    /**
     * @param GenerateSchemaEventArgs $args
     * @throws SchemaException
     */
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $schema = $args->getSchema();

        if (!$schema->hasNamespace('public')) {
            $schema->createNamespace('public');
        }
    }
}