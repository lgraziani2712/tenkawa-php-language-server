<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle;

class WorkspaceEditClientCapabilities
{
    /**
     * The client supports versioned document changes in `WorkspaceEdit`s.
     *
     * @var bool|null
     */
    public $documentChanges;
}
