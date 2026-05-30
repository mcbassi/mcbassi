<?php
declare(strict_types=1);

namespace App\Integrations;

final class DropboxSyncService
{
    /**
     * @return array{ok:bool,message:string}
     */
    public function sync(): array
    {
        return [
            'ok' => true,
            'message' => 'Scaffold pronto. Implemente aqui a sincronização real do Dropbox.',
        ];
    }
}
