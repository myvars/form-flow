<?php

declare(strict_types=1);

namespace MyVars\FormFlow\View;

enum FormOperation: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Filter = 'filter';
    case Action = 'action';
    case Index = 'index';

    public function past(): string
    {
        return match ($this) {
            self::Create => 'created',
            self::Update => 'updated',
            self::Delete => 'deleted',
            self::Filter => 'filtered',
            self::Action => 'actioned',
            self::Index => 'indexed',
        };
    }
}
