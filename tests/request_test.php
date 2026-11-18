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

namespace webservice_mcp;

use advanced_testcase;
use moodle_exception;
use webservice_mcp\local\request;

/**
 * Tests for MCP request class.
 *
 * @package     webservice_mcp
 * @author      MohammadReza PourMohammad <onbirdev@gmail.com>
 * @copyright   2025 MohammadReza PourMohammad
 * @link        https://onbir.dev
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_mcp\local\request
 */
final class request_test extends advanced_testcase {
    /**
     * Test valid JSON-RPC 2.0 request construction.
     */
    public function test_valid_request_construction(): void {
        $this->resetAfterTest(true);

        $data = [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
            'params' => [],
        ];

        $request = new request($data);

        $this->assertEquals('2.0', $request->jsonrpc);
        $this->assertEquals('tools/list', $request->method);
        $this->assertEquals(1, $request->id);
        $this->assertEquals([], $request->params);
    }

    /**
     * Test request construction without optional id field.
     */
    public function test_request_without_id(): void {
        $this->resetAfterTest(true);

        $data = [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
        ];

        $request = new request($data);

        $this->assertEquals('2.0', $request->jsonrpc);
        $this->assertEquals('initialize', $request->method);
        $this->assertNull($request->id);
        $this->assertNull($request->params);
    }

    /**
     * Test request construction without optional params field.
     */
    public function test_request_without_params(): void {
        $this->resetAfterTest(true);

        $data = [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ];

        $request = new request($data);

        $this->assertEquals('2.0', $request->jsonrpc);
        $this->assertEquals('tools/list', $request->method);
        $this->assertEquals(1, $request->id);
        $this->assertNull($request->params);
    }

    /**
     * Test request with string id.
     */
    public function test_request_with_string_id(): void {
        $this->resetAfterTest(true);

        $data = [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 'request-123',
            'params' => ['name' => 'test_tool'],
        ];

        $request = new request($data);

        $this->assertEquals('request-123', $request->id);
    }

    /**
     * Test request validation fails with missing jsonrpc field.
     */
    public function test_invalid_request_missing_jsonrpc(): void {
        $this->resetAfterTest(true);

        $data = [
            'method' => 'tools/list',
            'id' => 1,
        ];

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('JSON-RPC version must be 2.0');
        new request($data);
    }

    /**
     * Test request validation fails with wrong jsonrpc version.
     */
    public function test_invalid_request_wrong_jsonrpc_version(): void {
        $this->resetAfterTest(true);

        $data = [
            'jsonrpc' => '1.0',
            'method' => 'tools/list',
            'id' => 1,
        ];

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('JSON-RPC version must be 2.0');
        new request($data);
    }

    /**
     * Test request validation fails with missing method field.
     */
    public function test_invalid_request_missing_method(): void {
        $this->resetAfterTest(true);

        $data = [
            'jsonrpc' => '2.0',
            'id' => 1,
        ];

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Method field is required and must be a string');
        new request($data);
    }

    /**
     * Test request validation fails with empty method field.
     */
    public function test_invalid_request_empty_method(): void {
        $this->resetAfterTest(true);

        $data = [
            'jsonrpc' => '2.0',
            'method' => '',
            'id' => 1,
        ];

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Method field is required and must be a string');
        new request($data);
    }

    /**
     * Test request validation fails with non-string method field.
     */
    public function test_invalid_request_non_string_method(): void {
        $this->resetAfterTest(true);

        $data = [
            'jsonrpc' => '2.0',
            'method' => 123,
            'id' => 1,
        ];

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Method field is required and must be a string');
        new request($data);
    }

    /**
     * Test request with complex params structure.
     */
    public function test_request_with_complex_params(): void {
        $this->resetAfterTest(true);

        $data = [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 1,
            'params' => [
                'name' => 'test_tool',
                'arguments' => [
                    'param1' => 'value1',
                    'param2' => 123,
                    'param3' => ['nested' => 'array'],
                ],
            ],
        ];

        $request = new request($data);

        $this->assertEquals('tools/call', $request->method);
        $this->assertIsArray($request->params);
        $this->assertEquals('test_tool', $request->params['name']);
        $this->assertIsArray($request->params['arguments']);
        $this->assertEquals('value1', $request->params['arguments']['param1']);
    }

    /**
     * Test request::from_json with valid JSON payload.
     */
    public function test_from_json_valid(): void {
        $this->resetAfterTest(true);

        $request = request::from_json('{"jsonrpc":"2.0","method":"ping","id":9}');

        $this->assertSame('2.0', $request->jsonrpc);
        $this->assertSame('ping', $request->method);
        $this->assertSame(9, $request->id);
    }

    /**
     * Test request::from_json rejects empty input.
     */
    public function test_from_json_empty(): void {
        $this->resetAfterTest(true);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Request body is empty');
        request::from_json('');
    }

    /**
     * Test request::from_json rejects malformed JSON.
     */
    public function test_from_json_invalid(): void {
        $this->resetAfterTest(true);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('JSON parsing error');
        request::from_json('{invalid json');
    }
}
