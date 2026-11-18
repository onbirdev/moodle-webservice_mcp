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

use core\event\webservice_function_called;
use core\exception\invalid_parameter_exception;
use core\exception\moodle_exception;
use core\exception\required_capability_exception;
use core_external\external_api;
use dml_missing_record_exception;
use stdClass;
use Throwable;
use webservice_access_exception;
use webservice_base_server;

/**
 * MCP (Model Context Protocol) web service server implementation.
 *
 * This server handles JSON-RPC 2.0 requests following the MCP specification.
 * It supports MCP-specific methods like initializem notifications/initialized, ping, tools/list, and tools/call,
 * as well as direct function invocation.
 *
 * @package     webservice_mcp
 * @author      MohammadReza PourMohammad <onbirdev@gmail.com>
 * @copyright   2025 MohammadReza PourMohammad
 * @link        https://onbir.dev
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class server extends webservice_base_server {
    /** @var string Protocol version supported by this server. */
    private const PROTOCOL_VERSION = '2025-03-26';

    /** @var string Server name. */
    private const SERVER_NAME = 'Moodle MCP Server';

    /** @var string Server version. */
    private const SERVER_VERSION = '1.0.0';

    /** @var string HTTP request method. */
    protected string $httpmethod;

    /** @var request|null Parsed MCP request object. */
    protected ?request $mcprequest = null;

    /** @var bool Whether parse_request() has already run for this request lifecycle. */
    protected bool $requestparsed = false;

    /** @var int Last HTTP status code set by the server. */
    protected int $httpstatus = 200;

    /**
     * Constructor.
     *
     * @param int $authmethod Authentication method (e.g., WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN).
     */
    public function __construct(int $authmethod) {
        parent::__construct($authmethod);
        $this->wsname = 'mcp';
    }

    /**
     * Main server execution method.
     *
     * Handles the complete request lifecycle: parsing, authentication,
     * execution, and response generation.
     *
     * @return void
     */
    public function run(): void {
        $this->set_headers();
        $this->httpmethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Handle CORS preflight before authentication (browsers never send credentials on OPTIONS).
        if ($this->httpmethod === 'OPTIONS') {
            $this->set_http_status(200);
            return;
        }

        // Allocate sufficient memory for complex operations.
        raise_memory_limit(MEMORY_EXTRA);

        // Set extended timeout for long-running operations.
        external_api::set_timeout();

        // Configure exception handler.
        set_exception_handler([$this, 'exception_handler']);

        // Parse incoming request.
        $this->parse_request();

        // Handle MCP-specific methods that don't require standard flow.
        if (empty($this->functionname)) {
            // Authenticate for MCP methods.
            $this->authenticate_user();

            $this->handle_mcp_method();

            $this->session_cleanup();

            die;
        }

        // Use parent flow for standard function calls.
        parent::run();
    }

    /**
     * Parse and validate incoming request, set token, method, parameters.
     *
     * @return void
     */
    protected function parse_request(): void {
        if ($this->requestparsed) {
            return;
        }
        $this->requestparsed = true;

        parent::set_web_service_call_settings();

        $this->token = $this->extract_token();
        $this->httpmethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($this->httpmethod === 'POST') {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $this->mcprequest = request::from_json($raw);

                // Handle MCP tool invocation.
                if ($this->is_tool_call()) {
                    $this->extract_tool_call();
                }
            }
        }
    }

    /**
     * Determine whether the incoming MCP request represents a tools/call invocation.
     *
     * @return bool True if the request is a tools/call request, false otherwise.
     */
    private function is_tool_call(): bool {
        return !empty($this->mcprequest->method) && $this->mcprequest->method === 'tools/call';
    }

    /**
     * Extract the function name and parameters from an MCP tools/call request.
     *
     * This method populates:
     *   - $this->functionname
     *   - $this->parameters
     *
     * @return void
     * @throws moodle_exception If the tool name is missing.
     */
    public function extract_tool_call(): void {
        if (empty($this->mcprequest->params) || empty($this->mcprequest->params['name'])) {
            throw new moodle_exception('err_missing_tool_name', 'webservice_mcp');
        }

        // Extract function and arguments.
        $this->functionname = $this->mcprequest->params['name'];
        $this->parameters = $this->mcprequest->params['arguments'] ?? [];
    }

    /**
     * Extract Bearer token from Authorization header or fallback to wstoken GET param.
     *
     * @return string|null
     */
    protected function extract_token(): ?string {
        // Try robust header extraction (case-insensitive).
        $auth = null;

        if (function_exists('getallheaders')) {
            $headers = array_change_key_case(getallheaders(), CASE_LOWER);
            $auth = $headers['authorization'] ?? null;
        }

        // Fallback to $_SERVER keys (common in CGI/FPM).
        if ($auth === null) {
            $keys = [
                'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'Authorization',
            ];
            foreach ($keys as $key) {
                if (!empty($_SERVER[$key])) {
                    $auth = $_SERVER[$key];
                    break;
                }
            }
        }

        if (!empty($auth) && preg_match('/Bearer\s+(\S+)/i', $auth, $matches)) {
            return $matches[1];
        }

        // Fallback to GET parameter (validated).
        return optional_param('wstoken', null, PARAM_ALPHANUMEXT);
    }

    /**
     * Handle MCP-specific endpoints: CORS preflight, server info (GET),
     * initialize, notifications/initialized, ping and tools/list (POST).
     *
     * @return void
     */
    protected function handle_mcp_method(): void {
        // Handle GET for basic server info (not JSON-RPC wrapped).
        if ($this->httpmethod === 'GET') {
            $this->log_mcp_call('server_info');
            $this->send_server_info();
            return;
        }

        if (!($this->mcprequest instanceof request) || !isset($this->mcprequest->method)) {
            // Bad request.
            $this->set_http_status(400);
            echo $this->safe_json_encode([
                'jsonrpc' => $this->mcprequest->jsonrpc ?? '2.0',
                'error' => ['code' => -32600, 'message' => 'Invalid Request'],
                'id' => $this->mcprequest->id ?? null,
            ]);
            return;
        }

        switch ($this->mcprequest->method) {
            case 'initialize':
                $this->log_mcp_call('initialize');
                $this->send_initialize_response();
                break;

            case 'notifications/initialized':
                $this->log_mcp_call('notifications/initialized');
                $this->handle_initialized_notification();
                break;

            case 'ping':
                $this->log_mcp_call('ping');
                $this->send_ping_response();
                break;

            case 'tools/list':
                $this->log_mcp_call('tools/list');
                $this->send_tools_list_response();
                break;

            default:
                // If method unexpectedly reached here, return method not found.
                $payload = [
                    'jsonrpc' => $this->mcprequest->jsonrpc ?? '2.0',
                    'error' => ['code' => -32601, 'message' => 'Method not found'],
                    'id' => $this->mcprequest->id ?? null,
                ];
                echo $this->safe_json_encode($payload);
        }
    }

    /**
     * Trigger a Moodle web service event for MCP protocol methods so discovery
     * and session requests are recorded in the standard audit logs.
     *
     * @param string $method The MCP method name.
     * @return void
     */
    protected function log_mcp_call(string $method): void {
        $event = webservice_function_called::create([
            'other' => [
                'function' => 'mcp:' . $method,
            ],
        ]);
        $event->trigger();
    }

    /**
     * Output server info for GET requests.
     *
     * @return void
     */
    protected function send_server_info(): void {
        $response = [
            'name' => self::SERVER_NAME,
            'version' => self::SERVER_VERSION,
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => [
                'tools' => ['listChanged' => true],
            ],
        ];

        echo $this->safe_json_encode($response);
    }

    /**
     * Send MCP initialize JSON-RPC response.
     *
     * @return void
     */
    protected function send_initialize_response(): void {
        $result = [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => [
                'tools' => ['listChanged' => true],
            ],
            'serverInfo' => [
                'name' => self::SERVER_NAME,
                'version' => self::SERVER_VERSION,
            ],
            'instructions' => 'Moodle MCP server initialized successfully',
        ];

        $payload = [
            'jsonrpc' => $this->mcprequest->jsonrpc,
            'id' => $this->mcprequest->id,
            'result' => $result,
        ];

        echo $this->safe_json_encode($payload);
    }

    /**
     * Handle MCP notifications/initialized client notification.
     *
     * According to MCP specification, notifications do not expect a JSON-RPC response body.
     *
     * @return void
     */
    protected function handle_initialized_notification(): void {
        $this->set_http_status(204);
    }

    /**
     * Sends a JSON-RPC ping response.
     *
     * @return void
     */
    protected function send_ping_response(): void {
        echo $this->safe_json_encode([
            'jsonrpc' => '2.0',
            'result' => new stdClass(),
            'id' => $this->mcprequest->id,
        ]);
    }

    /**
     * Send tools list as MCP JSON-RPC response.
     *
     * @return void
     */
    protected function send_tools_list_response(): void {
        if (!empty($this->restricted_serviceid) && !empty($this->userid) && $this->restricted_context !== null) {
            $tools = tool_provider::get_tools_for_service(
                (int) $this->restricted_serviceid,
                (int) $this->userid,
                $this->restricted_context
            );
        } else {
            $tools = tool_provider::get_tools($this->token);
        }

        $payload = [
            'jsonrpc' => $this->mcprequest->jsonrpc,
            'id' => $this->mcprequest->id,
            'result' => [
                'tools' => $tools,
            ],
        ];

        echo $this->safe_json_encode($payload);
    }

    /**
     * Send a successful response for standard function calls.
     *
     * This method validates return values using external_api and wraps
     * the result in MCP tools/call format when appropriate.
     *
     * @return void
     */
    protected function send_response(): void {
        $validatedvalues = null;
        $exception = null;

        try {
            if ($this->function->returns_desc !== null) {
                $validatedvalues = external_api::clean_returnvalue(
                    $this->function->returns_desc,
                    $this->returns
                );
            } else {
                $validatedvalues = $this->returns;
            }
        } catch (Throwable $ex) {
            $exception = $ex;
        }

        if ($exception !== null) {
            $response = $this->generate_error($exception);
            echo $this->safe_json_encode($response);
            return;
        }

        // Fix arrays to be objects for tools/call format.
        $validatedvalues = [
            'result' => $validatedvalues,
        ];

        $content = [
            'type' => 'text',
            'text' => json_encode($validatedvalues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $result = [
            'content' => [$content],
            'structuredContent' => $validatedvalues,
        ];

        $payload = [
            'jsonrpc' => $this->mcprequest->jsonrpc ?? '2.0',
            'id' => $this->mcprequest->id ?? null,
            'result' => $result,
        ];

        echo $this->safe_json_encode($payload);
    }

    /**
     * Sends an error response, optionally logging exception details for debugging.
     *
     * @param Throwable|null $ex The exception to log and include in the error response, or null if no exception is provided.
     * @return void
     */
    protected function send_error($ex = null): void {
        if ($ex !== null && debugging('', DEBUG_MINIMAL)) {
            $this->log_exception_for_debug($ex);
        }

        echo $this->safe_json_encode($this->generate_error($ex));
    }

    /**
     * Generates a standardized error response for handling exceptions in the JSON-RPC protocol.
     *
     * @param Throwable|moodle_exception|null $ex The exception to process. If null, a default internal error is returned.
     * @return array The formatted error response containing error code, message, and additional data.
     */
    protected function generate_error($ex): array {
        if ($ex === null) {
            return [
                'jsonrpc' => $this->mcprequest->jsonrpc ?? '2.0',
                'error' => ['code' => -32603, 'message' => 'Internal error'],
                'id' => $this->mcprequest->id ?? null,
            ];
        }

        $errordata = [
            'exception' => get_class($ex),
            'message' => $ex->getMessage(),
            'code' => $ex->getCode(),
        ];

        if (isset($ex->errorcode)) {
            $errordata['errorcode'] = $ex->errorcode;
        }

        if (debugging() && isset($ex->debuginfo)) {
            $errordata['debuginfo'] = $ex->debuginfo;
        }

        return [
            'jsonrpc' => $this->mcprequest->jsonrpc ?? '2.0',
            'error' => [
                'code' => $this->get_jsonrpc_error_code($ex),
                'message' => $ex->getMessage(),
                'data' => $errordata,
            ],
            'id' => $this->mcprequest->id ?? null,
        ];
    }

    /**
     * Map an exception to a standard JSON-RPC 2.0 error code.
     *
     * @param Throwable $ex The thrown exception or error.
     * @return int JSON-RPC 2.0 error code.
     */
    protected function get_jsonrpc_error_code(Throwable $ex): int {
        if ($ex instanceof invalid_parameter_exception) {
            return -32602;
        }

        if (
            $ex instanceof dml_missing_record_exception
            && (($ex->tablename ?? '') === 'external_functions' || ($this->is_tool_call() && $this->function === null))
        ) {
            return -32601;
        }

        if ($ex instanceof webservice_access_exception || $ex instanceof required_capability_exception) {
            return -32600;
        }

        if ($ex instanceof moodle_exception) {
            $errorcode = $ex->errorcode ?? '';
            if ($errorcode === 'err_invalid_json') {
                return -32700;
            }
            if ($errorcode === 'err_missing_tool_name') {
                return -32602;
            }
            if (
                in_array($errorcode, [
                    'err_empty_request',
                    'err_invalid_jsonrpc',
                    'err_missing_method',
                    'invalidtoken',
                    'wsaccessuserdeleted',
                    'wsaccessuserunconfirmed',
                    'wsaccessusersuspended',
                    'wsaccessuserexpired',
                    'wsaccessusernologin',
                ], true)
            ) {
                return -32600;
            }
        }

        return -32603;
    }

    /**
     * Set HTTP status code if response headers have not yet been sent.
     *
     * @param int $code HTTP status code.
     * @return void
     */
    protected function set_http_status(int $code): void {
        $this->httpstatus = $code;
        if (!headers_sent()) {
            http_response_code($code);
        }
    }

    /**
     * Set JSON/CORS and caching headers.
     *
     * @return void
     */
    protected function set_headers(): void {
        if (headers_sent()) {
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, must-revalidate, max-age=0');
        header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . ' GMT');
        header('Pragma: no-cache');

        // CORS - allow any origin by default (adjust for production use).
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    }

    /**
     * Safely encode data to JSON and handle errors.
     *
     * @param mixed $data
     * @return string JSON encoded string
     */
    protected function safe_json_encode(mixed $data): string {
        // Use JSON_THROW_ON_ERROR if available.
        if (defined('JSON_THROW_ON_ERROR')) {
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            // Avoid leaking internal structures; return minimal error JSON-RPC.
            $fallback = [
                'jsonrpc' => $this->mcprequest->jsonrpc ?? '2.0',
                'error' => ['code' => -32603, 'message' => 'Internal JSON encoding error'],
                'id' => $this->mcprequest->id ?? null,
            ];
            return json_encode($fallback);
        }

        return $encoded;
    }

    /**
     * Log rich exception information when debugging is enabled.
     *
     * @param Throwable $ex
     * @return void
     */
    protected function log_exception_for_debug(Throwable $ex): void {
        $info = get_exception_info($ex);
        $message = 'MCP exception handler: ' . $info->message .
            ' Debug: ' . ($info->debuginfo ?? '') . "\n" .
            format_backtrace($info->backtrace ?? [], true);
        debugging($message);
    }
}
