<?php
declare(strict_types=1);

namespace App\Infra;

final class ProcessRunner
{
    /**
     * @return array{code:int,output:string}
     */
    public function run(string $command): array
    {
        $output = [];
        $code = 0;
        exec($command . ' 2>&1', $output, $code);

        return [
            'code' => $code,
            'output' => implode("\n", $output),
        ];
    }
}
