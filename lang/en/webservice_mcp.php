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

/**
 * English language strings for the MCP web service plugin.
 *
 * @package     webservice_mcp
 * @author      MohammadReza PourMohammad <onbirdev@gmail.com>
 * @copyright   2025 MohammadReza PourMohammad
 * @link        https://onbir.dev
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['err_empty_request'] = 'Request body is empty';
$string['err_invalid_json'] = 'Invalid JSON';
$string['err_invalid_jsonrpc'] = 'Invalid JSON-RPC version';
$string['err_missing_method'] = 'Missing method';
$string['err_missing_tool_name'] = 'Missing tool name';
$string['instructions'] = 'Server instructions';
$string['instructions_desc'] = 'Guidance for AI agents, sent as <code>instructions</code> in the MCP <code>initialize</code> '
    . 'response. Clients pass it to the model, for example in the system prompt. Use it to explain how the functions '
    . 'of your services work together and which conventions apply on this site. Leave empty to send no instructions.';
$string['mcp:use'] = 'Use MCP web service';
$string['pluginname'] = 'Model Context Protocol';
$string['privacy:metadata'] =
    'The MCP web service plugin does not store any personal data. It provides a protocol for accessing existing Moodle web service functions.';
