<?php

namespace Aschmelyun\Fleet;

use Symfony\Component\Process\Process;

class Fleet
{
    public static function process(string $command, bool $withBuffer = false): Process
    {
        $process = new Process(explode(' ', $command));

        if ($withBuffer) {
            $process->run(function ($type, $buffer) {
                echo $buffer;
            });

            return $process;
        }

        $process->run();

        return $process;
    }
}
