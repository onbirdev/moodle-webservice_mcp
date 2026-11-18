<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace webservice_mcp\local;

use core\exception\moodle_exception;

/**
 * Immutable and validated MCP JSON-RPC 2.0 request.
 *
 * This class represents a fully validated JSON-RPC request following the
 * Model Context Protocol (MCP) specification. It enforces JSON-RPC 2.0
 * structure, validates required fields, and provides typed access to
 * request properties such as method name, parameters, protocol version,
 * and request ID.
 *
 * All instances are immutable and guaranteed to represent a valid
 * JSON-RPC 2.0 request after construction.
 *
 * @package     webservice_mcp
 * @author      MohammadReza PourMohammad <onbirdev@gmail.com>
 * @copyright   2025 MohammadReza PourMohammad
 * @link        https://onbir.dev
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request {
    /** @var string JSON-RPC protocol version. Always "2.0". */
    public string $jsonrpc;

    /** @var string The requested MCP method name. */
    public string $method;

    /** @var int|string|null Optional request ID. Present for calls expecting a response, null for notifications. */
    public mixed $id;

    /** @var array|null Method parameters as an array, or null if not provided. */
    public ?array $params;

    /**
     * Constructor.
     *
     * Builds a validated, immutable MCP request object from a decoded JSON array.
     *
     * @param array $data Decoded JSON representing the JSON-RPC request.
     * @throws moodle_exception If validation fails or required fields are missing.
     */
    public function __construct(array $data) {
        $this->validate($data);

        $this->jsonrpc = $data['jsonrpc'];
        $this->method = $data['method'];
        $this->id = $data['id'] ?? null;
        $this->params = $data['params'] ?? null;
    }

    /**
     * Validate JSON-RPC 2.0 request structure.
     *
     * Ensures the data contains the required `jsonrpc` field set to "2.0"
     * and a valid, non-empty method name.
     *
     * @param array $data Decoded JSON input.
     * @return void
     * @throws moodle_exception If the JSON-RPC structure is invalid.
     */
    protected function validate(array $data): void {
        if (($data['jsonrpc'] ?? null) !== '2.0') {
            throw new moodle_exception(
                'err_invalid_jsonrpc',
                'webservice_mcp',
                '',
                null,
                'JSON-RPC version must be 2.0'
            );
        }

        if (empty($data['method']) || !is_string($data['method'])) {
            throw new moodle_exception(
                'err_missing_method',
                'webservice_mcp',
                '',
                null,
                'Method field is required and must be a string'
            );
        }
    }

    /**
     * Create an MCP request object from raw HTTP input.
     *
     * Reads the raw request body from php://input, decodes the JSON,
     * validates the structure, and returns a fully constructed request object.
     *
     * @return self A validated request instance.
     * @throws moodle_exception If the body is empty, JSON decoding fails, or the request format is invalid.
     */
    public static function from_raw_input(): self {
        $raw = file_get_contents('php://input');

        if ($raw === false || $raw === '') {
            throw new moodle_exception(
                'err_empty_request',
                'webservice_mcp',
                '',
                null,
                'Request body is empty'
            );
        }

        return self::from_json($raw);
    }

    /**
     * Create an MCP request object from a raw JSON string.
     *
     * @param string $raw Raw JSON string.
     * @return self A validated request instance.
     * @throws moodle_exception If the string is empty, JSON decoding fails, or the request format is invalid.
     */
    public static function from_json(string $raw): self {
        if ($raw === '') {
            throw new moodle_exception(
                'err_empty_request',
                'webservice_mcp',
                '',
                null,
                'Request body is empty'
            );
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new moodle_exception(
                'err_invalid_json',
                'webservice_mcp',
                '',
                null,
                'JSON parsing error: ' . json_last_error_msg()
            );
        }

        if (!is_array($data)) {
            throw new moodle_exception(
                'err_invalid_json',
                'webservice_mcp',
                '',
                null,
                'JSON must be an object'
            );
        }

        return new self($data);
    }

    /**
     * Check if raw input body is empty.
     *
     * @return bool True if the request body is empty or unreadable.
     */
    public static function is_raw_input_empty(): bool {
        $raw = file_get_contents('php://input');
        return $raw === '' || $raw === false;
    }
}
