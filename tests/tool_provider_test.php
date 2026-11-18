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

use context_system;
use core_external\external_api;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use externallib_advanced_testcase;
use ReflectionClass;
use stdClass;
use webservice_mcp\local\tool_provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for MCP tool provider class.
 *
 * @package     webservice_mcp
 * @author      MohammadReza PourMohammad <onbirdev@gmail.com>
 * @copyright   2025 MohammadReza PourMohammad
 * @link        https://onbir.dev
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_mcp\local\tool_provider
 */
final class tool_provider_test extends externallib_advanced_testcase {
    /**
     * Test schema generation for nullable value.
     */
    public function test_generate_schema_nullable_value(): void {
        $this->resetAfterTest(true);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('generate_schema');
        $method->setAccessible(true);

        // Test when NULL is allowed.
        $nullableparam = new external_value(
            PARAM_TEXT,
            'Nullable field',
            VALUE_OPTIONAL,
            null,
            NULL_ALLOWED
        );

        $nullableschema = $method->invoke(null, $nullableparam);

        $this->assertIsArray($nullableschema['type']);
        $this->assertEquals(['string', 'null'], $nullableschema['type']);
        $this->assertEquals('Nullable field', $nullableschema['description']);

        // Test when NULL is NOT allowed.
        $notnullparam = new external_value(
            PARAM_TEXT,
            'Non-nullable field',
            VALUE_OPTIONAL,
            null,
            NULL_NOT_ALLOWED
        );

        $notnullschema = $method->invoke(null, $notnullparam);

        $this->assertIsString($notnullschema['type']);
        $this->assertEquals('string', $notnullschema['type']);
    }

    /**
     * Test schema generation for simple string value.
     */
    public function test_generate_schema_string(): void {
        $this->resetAfterTest(true);

        $param = new external_value(PARAM_TEXT, 'Test parameter', VALUE_REQUIRED, null, false);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('generate_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, $param);

        $this->assertEquals('string', $schema['type']);
        $this->assertEquals('Test parameter', $schema['description']);
        $this->assertTrue($schema['_required']);
    }

    /**
     * Test schema generation for integer value.
     */
    public function test_generate_schema_integer(): void {
        $this->resetAfterTest(true);

        $param = new external_value(PARAM_INT, 'Test integer', VALUE_OPTIONAL, null, false);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('generate_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, $param);

        $this->assertEquals('integer', $schema['type']);
        $this->assertEquals('Test integer', $schema['description']);
        $this->assertArrayNotHasKey('_required', $schema);
    }

    /**
     * Test schema generation for float value.
     */
    public function test_generate_schema_float(): void {
        $this->resetAfterTest(true);

        $param = new external_value(PARAM_FLOAT, 'Test float', VALUE_OPTIONAL, null, false);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('generate_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, $param);

        $this->assertEquals('number', $schema['type']);
    }

    /**
     * Test schema generation for boolean value.
     */
    public function test_generate_schema_boolean(): void {
        $this->resetAfterTest(true);

        $param = new external_value(PARAM_BOOL, 'Test boolean', VALUE_OPTIONAL, null, false);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('generate_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, $param);

        $this->assertEquals('boolean', $schema['type']);
    }

    /**
     * Test schema generation for single structure (object).
     */
    public function test_generate_schema_single_structure(): void {
        $this->resetAfterTest(true);

        $param = new external_single_structure([
            'name' => new external_value(PARAM_TEXT, 'Name field', VALUE_REQUIRED, null, false),
            'age' => new external_value(PARAM_INT, 'Age field', VALUE_OPTIONAL, null, false),
            'active' => new external_value(PARAM_BOOL, 'Active status', VALUE_REQUIRED, null, false),
        ]);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('generate_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, $param);

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('age', $schema['properties']);
        $this->assertArrayHasKey('active', $schema['properties']);

        $this->assertEquals('string', $schema['properties']['name']['type']);
        $this->assertEquals('integer', $schema['properties']['age']['type']);
        $this->assertEquals('boolean', $schema['properties']['active']['type']);

        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('name', $schema['required']);
        $this->assertContains('active', $schema['required']);
        $this->assertNotContains('age', $schema['required']);
    }

    /**
     * Test schema generation for multiple structure (array).
     */
    public function test_generate_schema_multiple_structure(): void {
        $this->resetAfterTest(true);

        $param = new external_multiple_structure(
            new external_value(PARAM_TEXT, 'Item description', VALUE_OPTIONAL, null, false)
        );

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('generate_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, $param);

        $this->assertEquals('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        $this->assertEquals('string', $schema['items']['type']);
    }

    /**
     * Test schema generation for nested structures.
     */
    public function test_generate_schema_nested_structure(): void {
        $this->resetAfterTest(true);

        $param = new external_single_structure([
            'user' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'User ID', VALUE_REQUIRED),
                'name' => new external_value(PARAM_TEXT, 'User name', VALUE_REQUIRED),
            ]),
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Course ID'),
                    'title' => new external_value(PARAM_TEXT, 'Course title'),
                ])
            ),
        ]);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('generate_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, $param);

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('user', $schema['properties']);
        $this->assertArrayHasKey('courses', $schema['properties']);

        // Test nested user object.
        $userschema = $schema['properties']['user'];
        $this->assertEquals('object', $userschema['type']);
        $this->assertArrayHasKey('id', $userschema['properties']);
        $this->assertArrayHasKey('name', $userschema['properties']);
        $this->assertEquals(['id', 'name'], $userschema['required']);

        // Test nested courses array.
        $coursesschema = $schema['properties']['courses'];
        $this->assertEquals('array', $coursesschema['type']);
        $this->assertEquals('object', $coursesschema['items']['type']);
        $this->assertArrayHasKey('id', $coursesschema['items']['properties']);
        $this->assertArrayHasKey('title', $coursesschema['items']['properties']);
        $this->assertEquals(['user', 'courses'], $schema['required']);
    }

    /**
     * Test schema type conversion.
     */
    public function test_get_schema_type(): void {
        $this->resetAfterTest(true);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('get_schema_type');
        $method->setAccessible(true);

        $this->assertEquals('string', $method->invoke(null, new external_value(PARAM_TEXT)));
        $this->assertEquals('integer', $method->invoke(null, new external_value(PARAM_INT)));
        $this->assertEquals('string', $method->invoke(null, new external_value(PARAM_SEQUENCE)));
        $this->assertEquals('number', $method->invoke(null, new external_value(PARAM_FLOAT)));
        $this->assertEquals('number', $method->invoke(null, new external_value(PARAM_LOCALISEDFLOAT)));
        $this->assertEquals('boolean', $method->invoke(null, new external_value(PARAM_BOOL)));
        $this->assertEquals('object', $method->invoke(null, new external_single_structure([])));
        $this->assertEquals('array', $method->invoke(null, new external_multiple_structure(
            new external_value(PARAM_TEXT)
        )));
    }

    /**
     * Test build_schema with null description.
     */
    public function test_build_schema_null(): void {
        $this->resetAfterTest(true);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('build_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, null);

        $this->assertEquals('object', $schema['type']);
        $this->assertInstanceOf(stdClass::class, $schema['properties']);
        $this->assertEquals('{"type":"object","properties":{}}', json_encode($schema));
    }

    /**
     * Test schema generation for empty single structure (object with no properties).
     */
    public function test_generate_schema_empty_single_structure(): void {
        $this->resetAfterTest(true);

        $param = new external_single_structure([]);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('build_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, $param);

        $this->assertEquals('object', $schema['type']);
        $this->assertInstanceOf(stdClass::class, $schema['properties']);
        $this->assertEquals('{"type":"object","properties":{}}', json_encode($schema));
    }

    /**
     * Test get_tools retrieves available functions.
     */
    public function test_get_tools(): void {
        global $DB, $USER;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create a test service.
        $service = new stdClass();
        $service->name = 'Test MCP Service';
        $service->enabled = 1;
        $service->restrictedusers = 0;
        $service->component = null;
        $service->timecreated = time();
        $service->timemodified = time();
        $service->shortname = 'test_mcp_service';
        $service->downloadfiles = 0;
        $service->uploadfiles = 0;
        $serviceid = $DB->insert_record('external_services', $service);

        // Add a function to the service (using an existing core function).
        $function = new stdClass();
        $function->externalserviceid = $serviceid;
        $function->functionname = 'core_webservice_get_site_info';
        $DB->insert_record('external_services_functions', $function);

        // Create a token for the service.
        $token = new stdClass();
        $token->token = bin2hex(random_bytes(32));
        $token->userid = $USER->id;
        $token->tokentype = EXTERNAL_TOKEN_PERMANENT;
        $token->contextid = context_system::instance()->id;
        $token->creatorid = $USER->id;
        $token->timecreated = time();
        $token->externalserviceid = $serviceid;
        $DB->insert_record('external_tokens', $token);

        // Test get_tools.
        $tools = tool_provider::get_tools($token->token);

        $this->assertIsArray($tools);
        $this->assertNotEmpty($tools);

        $tool = $tools[0];
        $this->assertArrayHasKey('name', $tool);
        $this->assertArrayHasKey('description', $tool);
        $this->assertArrayHasKey('inputSchema', $tool);
        $this->assertArrayHasKey('outputSchema', $tool);

        $this->assertEquals('core_webservice_get_site_info', $tool['name']);
        $this->assertIsArray($tool['inputSchema']);
        $this->assertIsArray($tool['outputSchema']);
    }

    /**
     * Test get_tools omits outputSchema for void-returning external functions.
     */
    public function test_get_tools_void_return(): void {
        global $DB, $USER;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create a test service.
        $service = new stdClass();
        $service->name = 'Test MCP Void Service';
        $service->enabled = 1;
        $service->restrictedusers = 0;
        $service->component = null;
        $service->timecreated = time();
        $service->timemodified = time();
        $service->shortname = 'test_mcp_void_service';
        $service->downloadfiles = 0;
        $service->uploadfiles = 0;
        $serviceid = $DB->insert_record('external_services', $service);

        // Add a void-returning function (mod_assign_save_grades returns null).
        $function = new stdClass();
        $function->externalserviceid = $serviceid;
        $function->functionname = 'mod_assign_save_grades';
        $DB->insert_record('external_services_functions', $function);

        // Create a token for the service.
        $token = new stdClass();
        $token->token = bin2hex(random_bytes(32));
        $token->userid = $USER->id;
        $token->tokentype = EXTERNAL_TOKEN_PERMANENT;
        $token->contextid = context_system::instance()->id;
        $token->creatorid = $USER->id;
        $token->timecreated = time();
        $token->externalserviceid = $serviceid;
        $DB->insert_record('external_tokens', $token);

        // Test get_tools.
        $tools = tool_provider::get_tools($token->token);

        $this->assertCount(1, $tools);

        $tool = $tools[0];
        $this->assertEquals('mod_assign_save_grades', $tool['name']);
        $this->assertArrayHasKey('inputSchema', $tool);
        $this->assertArrayNotHasKey('outputSchema', $tool);

        $encoded = json_encode($tool, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('"properties":[]', $encoded);
    }

    /**
     * Test structure nullability and requiredness are represented independently.
     */
    public function test_generate_schema_nullable_structures(): void {
        $this->resetAfterTest(true);

        $param = new external_single_structure([
            'optionalobject' => new external_single_structure(
                [],
                'Optional nullable object',
                VALUE_OPTIONAL,
                null,
                NULL_ALLOWED
            ),
            'requiredarray' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Item', VALUE_REQUIRED, null, NULL_NOT_ALLOWED),
                'Required nullable array',
                VALUE_REQUIRED,
                null,
                NULL_ALLOWED
            ),
        ]);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('build_schema');
        $method->setAccessible(true);
        $schema = $method->invoke(null, $param);

        $this->assertSame(['object', 'null'], $schema['properties']['optionalobject']['type']);
        $this->assertSame(['array', 'null'], $schema['properties']['requiredarray']['type']);
        $this->assertSame(['requiredarray'], $schema['required']);
        $this->assertEquals(new stdClass(), $schema['properties']['optionalobject']['properties']);
        $this->assertSame('string', $schema['properties']['requiredarray']['items']['type']);
    }

    /**
     * Test every installed external function produces draft 2020-12 compliant schemas.
     */
    public function test_all_exposed_tool_schemas_are_draft_2020_12(): void {
        global $DB, $USER;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $functionrecords = $DB->get_records('external_functions', null, 'name', 'name');
        $this->assertNotEmpty($functionrecords);

        $service = new stdClass();
        $service->name = 'MCP schema validation service';
        $service->enabled = 1;
        $service->restrictedusers = 0;
        $service->component = null;
        $service->timecreated = time();
        $service->timemodified = time();
        $service->shortname = 'mcp_schema_' . bin2hex(random_bytes(8));
        $service->downloadfiles = 0;
        $service->uploadfiles = 0;
        $serviceid = $DB->insert_record('external_services', $service);

        foreach (array_keys($functionrecords) as $functionname) {
            $function = new stdClass();
            $function->externalserviceid = $serviceid;
            $function->functionname = $functionname;
            $DB->insert_record('external_services_functions', $function);
        }

        $token = new stdClass();
        $token->token = bin2hex(random_bytes(32));
        $token->userid = $USER->id;
        $token->tokentype = EXTERNAL_TOKEN_PERMANENT;
        $token->contextid = context_system::instance()->id;
        $token->creatorid = $USER->id;
        $token->timecreated = time();
        $token->externalserviceid = $serviceid;
        $DB->insert_record('external_tokens', $token);

        $tools = tool_provider::get_tools($token->token);
        $this->assertNotEmpty($tools);

        foreach ($tools as $tool) {
            $this->assertSame('object', $tool['inputSchema']['type'], $tool['name']);
            $this->assertArrayHasKey('properties', $tool['inputSchema'], $tool['name']);

            $this->assert_valid_tool_schema($tool['inputSchema'], $tool['name'] . ' inputSchema');
            if (array_key_exists('outputSchema', $tool)) {
                $this->assert_valid_tool_schema($tool['outputSchema'], $tool['name'] . ' outputSchema');
            } else {
                $info = external_api::external_function_info($tool['name']);
                $this->assertNull($info->returns_desc, $tool['name'] . ' omitted outputSchema unexpectedly');
            }
        }
    }

    /**
     * Test get_tools returns empty when the service is disabled.
     */
    public function test_get_tools_disabled_service(): void {
        global $DB, $USER;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $service = (object) [
            'name' => 'Disabled MCP Service',
            'enabled' => 0,
            'restrictedusers' => 0,
            'component' => null,
            'timecreated' => time(),
            'timemodified' => time(),
            'shortname' => 'disabled_mcp_service',
            'downloadfiles' => 0,
            'uploadfiles' => 0,
        ];
        $serviceid = $DB->insert_record('external_services', $service);

        $DB->insert_record('external_services_functions', (object) [
            'externalserviceid' => $serviceid,
            'functionname' => 'core_webservice_get_site_info',
        ]);

        $token = (object) [
            'token' => bin2hex(random_bytes(32)),
            'userid' => $USER->id,
            'tokentype' => EXTERNAL_TOKEN_PERMANENT,
            'contextid' => context_system::instance()->id,
            'creatorid' => $USER->id,
            'timecreated' => time(),
            'externalserviceid' => $serviceid,
        ];
        $DB->insert_record('external_tokens', $token);

        $this->assertSame([], tool_provider::get_tools($token->token));
    }

    /**
     * Test get_tools enforces restrictedusers, validuntil, and iprestriction.
     */
    public function test_get_tools_restricted_users(): void {
        global $DB;
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();

        $service = (object) [
            'name' => 'Restricted MCP Service',
            'enabled' => 1,
            'restrictedusers' => 1,
            'component' => null,
            'timecreated' => time(),
            'timemodified' => time(),
            'shortname' => 'restricted_mcp_service',
            'downloadfiles' => 0,
            'uploadfiles' => 0,
        ];
        $serviceid = $DB->insert_record('external_services', $service);

        $DB->insert_record('external_services_functions', (object) [
            'externalserviceid' => $serviceid,
            'functionname' => 'core_webservice_get_site_info',
        ]);

        $token = (object) [
            'token' => bin2hex(random_bytes(32)),
            'userid' => $user->id,
            'tokentype' => EXTERNAL_TOKEN_PERMANENT,
            'contextid' => context_system::instance()->id,
            'creatorid' => $user->id,
            'timecreated' => time(),
            'externalserviceid' => $serviceid,
        ];
        $DB->insert_record('external_tokens', $token);

        // User is not listed in external_services_users yet.
        $this->assertSame([], tool_provider::get_tools($token->token));

        // Add user with expired validuntil.
        $serviceuserid = $DB->insert_record('external_services_users', (object) [
            'externalserviceid' => $serviceid,
            'userid' => $user->id,
            'validuntil' => time() - 3600,
            'timecreated' => time(),
        ]);
        $this->assertSame([], tool_provider::get_tools($token->token));

        // Update validuntil to future, but set a non-matching IP restriction.
        $DB->update_record('external_services_users', (object) [
            'id' => $serviceuserid,
            'validuntil' => time() + 3600,
            'iprestriction' => '192.0.2.1/32',
        ]);
        $this->assertSame([], tool_provider::get_tools($token->token));

        // Remove IP restriction; user is now authorized.
        $DB->update_record('external_services_users', (object) [
            'id' => $serviceuserid,
            'iprestriction' => null,
        ]);
        $tools = tool_provider::get_tools($token->token);
        $this->assertCount(1, $tools);
        $this->assertSame('core_webservice_get_site_info', $tools[0]['name']);
    }

    /**
     * Test get_tools enforces service requiredcapability in the token's context.
     */
    public function test_get_tools_required_capability(): void {
        global $DB;
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $sysctx = context_system::instance();

        $service = (object) [
            'name' => 'Capability MCP Service',
            'enabled' => 1,
            'restrictedusers' => 0,
            'requiredcapability' => 'moodle/site:config',
            'component' => null,
            'timecreated' => time(),
            'timemodified' => time(),
            'shortname' => 'cap_mcp_service',
            'downloadfiles' => 0,
            'uploadfiles' => 0,
        ];
        $serviceid = $DB->insert_record('external_services', $service);

        $DB->insert_record('external_services_functions', (object) [
            'externalserviceid' => $serviceid,
            'functionname' => 'core_webservice_get_site_info',
        ]);

        $token = (object) [
            'token' => bin2hex(random_bytes(32)),
            'userid' => $user->id,
            'tokentype' => EXTERNAL_TOKEN_PERMANENT,
            'contextid' => $sysctx->id,
            'creatorid' => $user->id,
            'timecreated' => time(),
            'externalserviceid' => $serviceid,
        ];
        $DB->insert_record('external_tokens', $token);

        // User lacks moodle/site:config.
        $this->assertSame([], tool_provider::get_tools($token->token));

        // Grant moodle/site:config at system context.
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/site:config', CAP_ALLOW, $roleid, $sysctx->id);
        $this->getDataGenerator()->role_assign($roleid, $user->id, $sysctx->id);

        $tools = tool_provider::get_tools($token->token);
        $this->assertCount(1, $tools);
        $this->assertSame('core_webservice_get_site_info', $tools[0]['name']);
    }

    /**
     * Test get_tools respects token-level validuntil and iprestriction.
     */
    public function test_get_tools_token_restrictions(): void {
        global $DB, $USER;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $service = (object) [
            'name' => 'Token Restricted MCP Service',
            'enabled' => 1,
            'restrictedusers' => 0,
            'component' => null,
            'timecreated' => time(),
            'timemodified' => time(),
            'shortname' => 'token_restricted_mcp_service',
            'downloadfiles' => 0,
            'uploadfiles' => 0,
        ];
        $serviceid = $DB->insert_record('external_services', $service);

        $DB->insert_record('external_services_functions', (object) [
            'externalserviceid' => $serviceid,
            'functionname' => 'core_webservice_get_site_info',
        ]);

        $token = (object) [
            'token' => bin2hex(random_bytes(32)),
            'userid' => $USER->id,
            'tokentype' => EXTERNAL_TOKEN_PERMANENT,
            'contextid' => context_system::instance()->id,
            'creatorid' => $USER->id,
            'timecreated' => time(),
            'validuntil' => time() - 60,
            'externalserviceid' => $serviceid,
        ];
        $tokenid = $DB->insert_record('external_tokens', $token);

        $this->assertSame([], tool_provider::get_tools($token->token));

        $DB->update_record('external_tokens', (object) [
            'id' => $tokenid,
            'validuntil' => time() + 3600,
            'iprestriction' => '192.0.2.1/32',
        ]);
        $this->assertSame([], tool_provider::get_tools($token->token));
    }

    /**
     * Assert that a tool schema, as serialised for MCP clients, is valid JSON Schema draft 2020-12.
     *
     * @param array $schema The schema produced by the tool provider.
     * @param string $path Location of the schema, used in failure messages.
     */
    private function assert_valid_tool_schema(array $schema, string $path): void {
        $json = json_encode($schema, JSON_THROW_ON_ERROR);
        $this->assert_valid_schema_node(json_decode($json, false, 512, JSON_THROW_ON_ERROR), $path);
    }

    /**
     * Recursively validate a decoded schema node against draft 2020-12 rules.
     *
     * @param mixed $node The decoded schema node.
     * @param string $path Location of the node, used in failure messages.
     */
    private function assert_valid_schema_node(mixed $node, string $path): void {
        $this->assertInstanceOf(stdClass::class, $node, "{$path} must be a JSON object");

        $keywords = ['type', 'description', 'default', 'properties', 'required', 'items'];
        foreach (array_keys(get_object_vars($node)) as $keyword) {
            $this->assertContains($keyword, $keywords, "{$path} has an unexpected keyword");
        }

        if (property_exists($node, 'type')) {
            $types = is_array($node->type) ? $node->type : [$node->type];
            $this->assertNotEmpty($types, "{$path}.type must not be empty");
            foreach ($types as $type) {
                $this->assertContains(
                    $type,
                    ['null', 'boolean', 'object', 'array', 'number', 'string', 'integer'],
                    "{$path}.type must only contain primitive type names"
                );
            }
            $this->assertSame(array_values(array_unique($types)), $types, "{$path}.type must be unique");
        }

        if (property_exists($node, 'description')) {
            $this->assertIsString($node->description, "{$path}.description");
        }

        if (property_exists($node, 'properties')) {
            $this->assertInstanceOf(stdClass::class, $node->properties, "{$path}.properties must be a JSON object");
            foreach (get_object_vars($node->properties) as $name => $propertyschema) {
                $this->assert_valid_schema_node($propertyschema, "{$path}.properties.{$name}");
            }
        }

        if (property_exists($node, 'required')) {
            $this->assertIsArray($node->required, "{$path}.required must be a JSON array");
            $this->assertContainsOnly('string', $node->required, true, "{$path}.required");
            $this->assertSame(array_values(array_unique($node->required)), $node->required, "{$path}.required must be unique");
        }

        if (property_exists($node, 'items')) {
            $this->assert_valid_schema_node($node->items, "{$path}.items");
        }
    }
}
