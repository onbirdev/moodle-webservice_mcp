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

use core_external\external_api;
use core_external\external_description;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use stdClass;
use Throwable;

/**
 * Tool provider for MCP protocol.
 *
 * This class provides methods to discover and describe available Moodle
 * external functions as MCP tools. It generates JSON Schema representations
 * of function parameters and return values, making them discoverable to
 * MCP clients.
 *
 * @package     webservice_mcp
 * @author      MohammadReza PourMohammad <onbirdev@gmail.com>
 * @copyright   2025 MohammadReza PourMohammad
 * @link        https://onbir.dev
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_provider {
    /**
     * Retrieve a list of available tools for a given token.
     *
     * This method queries the database for external functions available
     * to the service associated with the provided token, and converts
     * each function's metadata into MCP tool format with JSON Schema
     * descriptions.
     *
     * @param string $token The external service token.
     * @return array Array of tool definitions.
     */
    public static function get_tools(string $token): array {
        global $DB;

        $tokenrecord = $DB->get_record('external_tokens', ['token' => $token], '*', MUST_EXIST);

        $tools = [];

        // Query functions available for this service.
        $functions = $DB->get_records(
            'external_services_functions',
            ['externalserviceid' => $tokenrecord->externalserviceid],
            'functionname'
        );

        foreach ($functions as $function) {
            try {
                $info = external_api::external_function_info($function->functionname);
            } catch (Throwable $e) {
                // Skip functions that fail to load (e.g. missing plugin class).
                continue;
            }

            // Skip if function info is unavailable or deprecated.
            if (empty($info) || !empty($info->deprecated)) {
                continue;
            }

            $inputschema = self::build_schema($info->parameters_desc);

            $tool = [
                'name' => $info->name,
                'description' => $info->description ?? '',
                'inputSchema' => $inputschema,
            ];

            if ($info->returns_desc !== null) {
                $tool['outputSchema'] = [
                    'type' => 'object',
                    'properties' => [
                        'result' => self::build_schema($info->returns_desc),
                    ],
                ];
            }

            $tools[] = $tool;
        }

        return $tools;
    }

    /**
     * Build a JSON Schema from an external description object.
     *
     * @param external_description|null $desc The external description.
     * @return array JSON Schema representation.
     */
    protected static function build_schema(?external_description $desc): array {
        if ($desc === null) {
            return ['type' => 'object', 'properties' => new stdClass()];
        }

        $schema = self::generate_schema($desc);
        unset($schema['_required']);

        return $schema;
    }

    /**
     * Generate JSON Schema representation of a parameter description.
     *
     * Converts Moodle external API parameter descriptions into JSON Schema
     * format compatible with MCP tool definitions.
     *
     * @param external_description $param The parameter description.
     * @return array JSON Schema representation.
     */
    protected static function generate_schema(external_description $param): array {
        $type = self::get_schema_type($param);
        $schema = ['type' => $type];

        // Mark as internally required for parent structure processing.
        if ($param->required === VALUE_REQUIRED) {
            $schema['_required'] = true;
        }

        // Handling for nullable values and structures in JSON Schema.
        if ($param->allownull == NULL_ALLOWED) {
            $schema['type'] = [$type, 'null'];
        }

        // Handle external_value (simple types).
        if ($param instanceof external_value) {
            if (!empty($param->desc)) {
                $schema['description'] = $param->desc;
            }

            if ($param->required === VALUE_DEFAULT && $param->default !== null) {
                $schema['default'] = $param->default;
            }

            return $schema;
        }

        // Handle external_single_structure (object).
        if ($param instanceof external_single_structure) {
            $properties = [];
            $requiredfields = [];

            foreach ($param->keys as $key => $subparam) {
                $subschema = self::generate_schema($subparam);

                // Collect required fields at this level.
                if (!empty($subschema['_required'])) {
                    $requiredfields[] = (string) $key;
                }

                // Remove internal marker before adding to schema.
                unset($subschema['_required']);

                $properties[$key] = $subschema;
            }

            $schema['properties'] = (empty($properties) || array_is_list($properties))
                ? (object) $properties
                : $properties;

            if (!empty($requiredfields)) {
                $schema['required'] = $requiredfields;
            }

            return $schema;
        }

        // Handle external_multiple_structure (array).
        if ($param instanceof external_multiple_structure) {
            $itemschema = self::generate_schema($param->content);

            // Remove required marker from array items (not applicable).
            unset($itemschema['_required']);

            $schema['items'] = $itemschema;
            return $schema;
        }

        return $schema;
    }

    /**
     * Convert Moodle parameter type to JSON Schema type.
     *
     * @param external_description $param The parameter description.
     * @return string JSON Schema type (string, integer, number, boolean, object, array).
     */
    protected static function get_schema_type(external_description $param): string {
        if ($param instanceof external_value) {
            switch ($param->type) {
                case PARAM_INT:
                    return 'integer';
                case PARAM_FLOAT:
                case PARAM_LOCALISEDFLOAT:
                    return 'number';
                case PARAM_BOOL:
                    return 'boolean';
                default:
                    return 'string';
            }
        }

        if ($param instanceof external_single_structure) {
            return 'object';
        }

        if ($param instanceof external_multiple_structure) {
            return 'array';
        }

        return 'object';
    }
}
