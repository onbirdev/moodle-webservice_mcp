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
            $info = external_api::external_function_info($function->functionname);

            // Skip if function info is unavailable or deprecated.
            if (empty($info) || !empty($info->deprecated)) {
                continue;
            }

            $inputschema = self::ensure_object_schema(self::build_schema($info->parameters_desc));
            $outputschema = self::build_schema($info->returns_desc);

            $tools[] = [
                'name' => $info->name,
                'description' => $info->description ?? '',
                'inputSchema' => $inputschema,
                'outputSchema' => self::normalise_schema([
                    'type' => 'object',
                    'properties' => [
                        'result' => $outputschema,
                    ],
                ]),
            ];
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
            return self::normalise_schema(['type' => 'object', 'properties' => []]);
        }

        $schema = self::generate_schema($desc);

        // Required is only meaningful when this schema is a property of a parent object.
        unset($schema['_required']);

        return self::normalise_schema($schema);
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

        // Required controls whether a property may be absent; it does not make the value nullable.
        if ($param->required === VALUE_REQUIRED) {
            $schema['_required'] = true;
        }

        // Nullability is independent from required/optional and uses JSON Schema's null type name.
        if ($param->allownull === NULL_ALLOWED) {
            $schema['type'] = [$type, 'null'];
        }

        // Handle external_value (simple types).
        if ($param instanceof external_value) {
            if (!empty($param->desc)) {
                $schema['description'] = $param->desc;
            }

            return $schema;
        }

        // Handle external_single_structure (object).
        if ($param instanceof external_single_structure) {
            $schema['properties'] = [];
            $requiredfields = [];

            foreach ($param->keys as $key => $subparam) {
                $subschema = self::generate_schema($subparam);

                // Collect required fields at this level.
                if (!empty($subschema['_required'])) {
                    $requiredfields[] = (string) $key;
                }

                // Remove internal marker before adding to schema.
                unset($subschema['_required']);

                $schema['properties'][$key] = $subschema;
            }

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
     * @return string JSON Schema type (string, number, integer, boolean, object, array).
     */
    protected static function get_schema_type(external_description $param): string {
        if ($param instanceof external_value) {
            switch ($param->type) {
                case PARAM_INT:
                case PARAM_SEQUENCE:
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

    /**
     * Ensure an MCP tool input schema is an object schema.
     *
     * Moodle function parameter descriptions are external function parameters (an object),
     * but this final guard keeps the provider contract valid if a third-party function returns
     * an incomplete description.
     *
     * @param array $schema The generated schema.
     * @return array A top-level object schema.
     */
    protected static function ensure_object_schema(array $schema): array {
        $types = (array) ($schema['type'] ?? []);
        if (!in_array('object', $types, true)) {
            $schema = [
                'type' => 'object',
                'properties' => [],
            ];
        } else {
            // Tool arguments themselves cannot be null, even when a malformed description says otherwise.
            $schema['type'] = 'object';
            if (!array_key_exists('properties', $schema)) {
                $schema['properties'] = [];
            }
        }

        return self::normalise_schema($schema);
    }

    /**
     * Normalise a generated schema to the provider-compatible JSON Schema 2020-12 subset.
     *
     * This is intentionally applied after schema generation as a defence-in-depth guard for
     * third-party Moodle functions and future schema keywords.
     *
     * @param array $schema The schema to normalise.
     * @return array The normalised schema.
     */
    protected static function normalise_schema(array $schema): array {
        if (array_key_exists('type', $schema)) {
            $schema['type'] = self::normalise_schema_type($schema['type']);
        }

        if (array_key_exists('description', $schema) && !is_string($schema['description'])) {
            if (is_scalar($schema['description']) || $schema['description'] instanceof \Stringable) {
                $schema['description'] = (string) $schema['description'];
            } else {
                unset($schema['description']);
            }
        }

        if (array_key_exists('properties', $schema)) {
            $properties = is_object($schema['properties'])
                ? get_object_vars($schema['properties'])
                : $schema['properties'];
            $normalisedproperties = [];

            if (is_array($properties)) {
                foreach ($properties as $name => $propertyschema) {
                    if (is_array($propertyschema)) {
                        $normalisedproperties[(string) $name] = self::normalise_schema($propertyschema);
                    }
                }
            }

            // Empty PHP arrays encode as [], while JSON Schema requires an object here.
            if (empty($normalisedproperties)) {
                $schema['properties'] = (object) [];
            } else {
                $schema['properties'] = array_is_list($normalisedproperties)
                    ? (object) $normalisedproperties
                    : $normalisedproperties;
            }
        }

        $types = (array) ($schema['type'] ?? []);
        if (in_array('object', $types, true) && !array_key_exists('properties', $schema)) {
            $schema['properties'] = (object) [];
        }

        if (array_key_exists('items', $schema)) {
            $schema['items'] = is_array($schema['items'])
                ? self::normalise_schema($schema['items'])
                : ['type' => 'string'];
        } elseif (in_array('array', $types, true)) {
            $schema['items'] = ['type' => 'string'];
        }

        if (array_key_exists('required', $schema)) {
            if (!is_array($schema['required'])) {
                unset($schema['required']);
            } else {
                $required = [];
                foreach ($schema['required'] as $name) {
                    if (is_string($name) && !in_array($name, $required, true)) {
                        $required[] = $name;
                    }
                }

                if (empty($required)) {
                    unset($schema['required']);
                } else {
                    $schema['required'] = $required;
                }
            }
        }

        foreach (['minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'multipleOf'] as $keyword) {
            if (!array_key_exists($keyword, $schema)) {
                continue;
            }

            $value = $schema[$keyword];
            if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
                unset($schema[$keyword]);
                continue;
            }

            if ($keyword === 'multipleOf' && $value <= 0) {
                unset($schema[$keyword]);
            }
        }

        $countkeywords = [
            'minLength',
            'maxLength',
            'minItems',
            'maxItems',
            'minProperties',
            'maxProperties',
            'minContains',
            'maxContains',
        ];
        foreach ($countkeywords as $keyword) {
            if (array_key_exists($keyword, $schema)
                    && (!is_int($schema[$keyword]) || $schema[$keyword] < 0)) {
                unset($schema[$keyword]);
            }
        }

        // Internal generation marker; never expose it as a provider schema keyword.
        unset($schema['_required']);

        return $schema;
    }

    /**
     * Normalise a JSON Schema type or union of types.
     *
     * @param mixed $type A generated JSON Schema type value.
     * @return string|array A valid JSON Schema primitive type or unique union of types.
     */
    protected static function normalise_schema_type(mixed $type): string|array {
        if (!is_array($type)) {
            return self::normalise_schema_type_name($type);
        }

        $types = [];
        foreach ($type as $typename) {
            $normalisedtype = self::normalise_schema_type_name($typename);
            if (!in_array($normalisedtype, $types, true)) {
                $types[] = $normalisedtype;
            }
        }

        if (empty($types)) {
            return 'string';
        }

        return count($types) === 1 ? reset($types) : $types;
    }

    /**
     * Convert aliases and malformed type names to a JSON Schema primitive type name.
     *
     * @param mixed $type A generated type name.
     * @return string A valid JSON Schema primitive type name.
     */
    protected static function normalise_schema_type_name(mixed $type): string {
        if ($type === null || (is_string($type) && strtolower($type) === 'none')) {
            return 'null';
        }

        if (!is_string($type)) {
            return 'string';
        }

        switch (strtolower($type)) {
            case 'string':
            case 'number':
            case 'integer':
            case 'boolean':
            case 'object':
            case 'array':
            case 'null':
                return strtolower($type);
            case 'int':
            case 'param_int':
            case 'param_integer':
            case 'param_sequence':
                return 'integer';
            case 'float':
            case 'double':
            case 'param_float':
            case 'param_number':
            case 'param_localisedfloat':
                return 'number';
            case 'bool':
            case 'param_bool':
                return 'boolean';
            case 'dict':
                return 'object';
            case 'list':
                return 'array';
            default:
                // Moodle text types, language aliases such as "str", "any", and unknowns are strings.
                return 'string';
        }
    }
}
