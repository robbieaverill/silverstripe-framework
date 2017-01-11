<?php

namespace SilverStripe\Dev\Cli;

/**
 * OutputInterface classes will provide the ability to send output to the CLI via either stdout or stderr
 */
interface OutputInterface
{
    /**
     * Send a message to stdout
     *
     * @param  string $message
     * @param  bool   $newline
     * @return $this
     */
    public function write($messsage, $newline = true);

    /**
     * Send an error message to stderr
     *
     * @param  string $message
     * @param  bool   $newline
     * @return $this
     */
    public function error($messagem, $newline = true);
}
