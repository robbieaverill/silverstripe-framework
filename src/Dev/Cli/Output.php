<?php

namespace SilverStripe\Dev\Cli;

/**
 * Sends output to the CLI
 */
class Output implements OutputInterface
{
    /**
     * {@inheritDoc}
     */
    public function write($message, $newline = true)
    {
        if ($newline) {
            $message .= PHP_EOL;
        }
        fwrite(STDOUT, (string) $message);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function error($message, $newline = true)
    {
        if ($newline) {
            $message .= PHP_EOL;
        }
        fwrite(STDERR, (string) $message);
        return $this;
    }
}
