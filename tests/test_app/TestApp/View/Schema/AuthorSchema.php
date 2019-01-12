<?php
namespace TestApp\View\Schema;

use JsonApi\View\Schema\EntitySchema;

class AuthorSchema extends EntitySchema
{
    public function getId($entity): ?string
    {
        return $entity->get('id');
    }

    public function getAttributes($entity, array $fieldKeysFilter = null): ?array
    {
        return [
            'title' => $entity->title,
            'body' => $entity->body,
            'published' => $entity->published
        ];
    }

    public function getRelationships($entity, bool $isPrimary, array $includeRelationships): ?array
    {
        return [
            'articles' => [
                self::DATA => $entity->articles
            ]
        ];
    }
}
