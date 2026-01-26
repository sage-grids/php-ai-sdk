<?php

namespace SageGrids\PhpAiSdk\Http;

use Generator;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class StreamingResponse
{
    private bool $cancelled = false;

    /**
     * @param bool $strictParsing When true, throws on malformed SSE data instead of skipping
     */
    public function __construct(
        private StreamInterface $stream,
        private readonly bool $strictParsing = false
    ) {
    }

    /**
     * Cancel the stream processing.
     *
     * This will stop the events() generator on the next iteration
     * and close the underlying stream.
     */
    public function cancel(): void
    {
        $this->cancelled = true;

        if ($this->stream->isReadable()) {
            $this->stream->close();
        }
    }

    /**
     * Check if the stream has been cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    /**
     * @return Generator<SSEEvent>
     * @throws RuntimeException When strictParsing is enabled and malformed SSE data is encountered
     */
    public function events(): Generator
    {
        $buffer = '';

        try {
            while (!$this->cancelled && !$this->stream->eof()) {
                try {
                    $chunk = $this->stream->read(1024);
                } catch (RuntimeException $e) {
                    if ($this->cancelled) {
                        break;
                    }
                    throw $e;
                }

                if ($chunk === '') {
                    if ($this->stream->eof()) {
                        break;
                    }
                    continue;
                }

                $buffer .= $chunk;

                // Process complete messages separated by double newline
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    if ($this->cancelled) {
                        break 2;
                    }

                    $message = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    $event = $this->parseEvent($message);
                    if ($event !== null) {
                        yield $event;
                    }
                }
            }

            // Handle any remaining buffer (non-standard, but be lenient unless strict)
            if (!$this->cancelled && !empty($buffer)) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    if ($this->strictParsing) {
                        throw new RuntimeException(
                            'Malformed SSE: stream ended without proper termination (missing \\n\\n)'
                        );
                    }

                    $event = $this->parseEvent($buffer);
                    if ($event !== null) {
                        yield $event;
                    }
                }
            }
        } finally {
            if (!$this->cancelled && $this->stream->isReadable()) {
                $this->stream->close();
            }
        }
    }

    /**
     * Parse an SSE event block into an SSEEvent object.
     *
     * @throws RuntimeException When strictParsing is enabled and the block is malformed
     */
    private function parseEvent(string $block): ?SSEEvent
    {
        $lines = explode("\n", $block);
        $data = [];
        $event = null;
        $id = null;
        $retry = null;
        $hasValidField = false;

        foreach ($lines as $line) {
            // Don't trim leading space - SSE spec says single leading space after colon is removed
            if ($line === '') {
                continue;
            }

            // Comments start with colon
            if (str_starts_with($line, ':')) {
                continue;
            }

            $colonPos = strpos($line, ':');
            if ($colonPos === false) {
                // Field with no value (valid in SSE spec)
                $field = $line;
                $value = '';
            } else {
                $field = substr($line, 0, $colonPos);
                // Per SSE spec: if there's a space after the colon, remove it
                $value = substr($line, $colonPos + 1);
                if (str_starts_with($value, ' ')) {
                    $value = substr($value, 1);
                }
            }

            switch ($field) {
                case 'data':
                    $data[] = $value;
                    $hasValidField = true;
                    break;
                case 'event':
                    $event = $value;
                    $hasValidField = true;
                    break;
                case 'id':
                    // SSE spec: id field must not contain null
                    if (strpos($value, "\0") === false) {
                        $id = $value;
                        $hasValidField = true;
                    } elseif ($this->strictParsing) {
                        throw new RuntimeException('Malformed SSE: id field contains null character');
                    }
                    break;
                case 'retry':
                    if (ctype_digit($value)) {
                        $retry = (int) $value;
                        $hasValidField = true;
                    } elseif ($this->strictParsing && $value !== '') {
                        throw new RuntimeException('Malformed SSE: retry field must be an integer');
                    }
                    break;
                default:
                    // Unknown fields are ignored per SSE spec
                    break;
            }
        }

        if (!$hasValidField) {
            return null;
        }

        $joinedData = implode("\n", $data);

        // Attempt to decode JSON data if it looks like JSON
        $decodedData = $joinedData;
        if ($joinedData !== '' && ($joinedData[0] === '{' || $joinedData[0] === '[')) {
            $json = json_decode($joinedData, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $decodedData = $json;
            } elseif ($this->strictParsing) {
                throw new RuntimeException(
                    'Malformed SSE: JSON parse error - ' . json_last_error_msg()
                );
            }
        }

        return new SSEEvent($event, $decodedData, $id, $retry);
    }
}
